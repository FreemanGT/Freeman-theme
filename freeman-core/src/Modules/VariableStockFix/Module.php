<?php
/**
 * Variable Stock Fix module.
 *
 * When every visible variation of a variable product is out of stock, this
 * module unchecks the parent's "Manage stock" box and clears the parent stock
 * quantity so WooCommerce's native "Hide out of stock items" setting hides
 * the product from the shop page.
 *
 * Ported from woo-variable-stock-fix v1.2.1.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\VariableStockFix;

use Freeman\Core\Core\Module_Base;
use Freeman\Core\Core\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Module.
 */
final class Module extends Module_Base {

	const CRON_HOOK     = 'freeman_core_variable_stock_fix_daily_audit';
	const DEBOUNCE_HOOK = 'freeman_core_variable_stock_fix_debounced_check';
	const DEBOUNCE_OPT  = 'freeman_core_variable_stock_fix_debounce_queue';
	const NONCE         = 'freeman_core_variable_stock_fix_nonce';
	const BATCH_SIZE    = 50;

	const AJAX_GET_TOTAL = 'freeman_core_variable_stock_fix_get_total';
	const AJAX_RUN_BATCH = 'freeman_core_variable_stock_fix_run_batch';

	/**
	 * Deprecated names — fired/registered alongside the canonical ones for
	 * one release cycle (1.9.x). Removal target: 2.0.0.
	 */
	const DEPRECATED_CRON_HOOK     = 'freeman_vpsf_daily_audit';
	const DEPRECATED_DEBOUNCE_HOOK = 'freeman_vpsf_debounced_check';
	const DEPRECATED_DEBOUNCE_OPT  = 'freeman_vpsf_debounce_queue';
	const DEPRECATED_AJAX_GET_TOTAL = 'freeman_vpsf_get_total';
	const DEPRECATED_AJAX_RUN_BATCH = 'freeman_vpsf_run_batch';

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'variable_stock_fix';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Variable Stock Fix', 'freeman-core' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'When all visible variations of a variable product are out of stock, this module unchecks the parent\'s "Manage stock" box so Woo\'s native "Hide out of stock items" hides the product from the shop.', 'freeman-core' );
	}

	/**
	 * Settings schema — exposes the daily audit toggle and bulk-tool entry.
	 *
	 * @return array
	 */
	public function settings_schema() {
		return array(
			'run_daily_audit' => array(
				'label'          => __( 'Daily audit', 'freeman-core' ),
				'type'           => 'checkbox',
				'checkbox_label' => __( 'Run a daily audit of recently modified variable products', 'freeman-core' ),
				'description'    => __( 'Safety-net: once per day the module scans products modified in the last 48h and fixes any that match.', 'freeman-core' ),
				'default'        => 1,
			),
		);
	}

	/**
	 * Boot.
	 */
	public function boot() {
		add_action( 'woocommerce_loaded', array( $this, 'init_wc_hooks' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_daily_audit' ) );
		add_action( self::DEBOUNCE_HOOK, array( $this, 'run_debounced_parent_checks' ) );

		// Deprecated cron hook aliases — kept for installs whose persisted
		// `cron` option still references the pre-1.9.0 names. Removed in 2.0.0.
		add_action( self::DEPRECATED_CRON_HOOK, array( $this, 'run_daily_audit' ) );
		add_action( self::DEPRECATED_DEBOUNCE_HOOK, array( $this, 'run_debounced_parent_checks' ) );

		add_action( 'wp_ajax_' . self::AJAX_GET_TOTAL, array( $this, 'ajax_get_total' ) );
		add_action( 'wp_ajax_' . self::AJAX_RUN_BATCH, array( $this, 'ajax_run_batch' ) );

		// Deprecated AJAX action aliases — kept in case a stale admin tab is
		// still open with the old action names baked into its inline JS.
		add_action( 'wp_ajax_' . self::DEPRECATED_AJAX_GET_TOTAL, array( $this, 'ajax_get_total' ) );
		add_action( 'wp_ajax_' . self::DEPRECATED_AJAX_RUN_BATCH, array( $this, 'ajax_run_batch' ) );

		add_action( 'freeman_core/module_page/' . $this->id(), array( $this, 'render_bulk_audit_ui' ) );
	}

	/**
	 * On activation — schedule cron.
	 */
	public function on_activate() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * On deactivation — clear cron.
	 */
	public function on_deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::DEBOUNCE_HOOK );
		wp_clear_scheduled_hook( self::DEPRECATED_CRON_HOOK );
		wp_clear_scheduled_hook( self::DEPRECATED_DEBOUNCE_HOOK );
		delete_option( self::DEBOUNCE_OPT );
		delete_option( self::DEPRECATED_DEBOUNCE_OPT );
	}

	/**
	 * Register WooCommerce product-lifecycle hooks.
	 */
	public function init_wc_hooks() {
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'on_product_save' ), 20, 1 );
		add_action( 'woocommerce_rest_insert_product_object', array( $this, 'on_product_save_rest' ), 20, 3 );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'on_variation_change' ), 20, 1 );
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'on_variation_change' ), 20, 1 );
		add_action( 'woocommerce_update_product_variation', array( $this, 'on_variation_id_change' ), 20, 1 );
	}

	/**
	 * On classic admin save — modify the in-memory product; WC persists.
	 *
	 * @param \WC_Product $product Product.
	 */
	public function on_product_save( $product ) {
		if ( ! $product instanceof \WC_Product || 'variable' !== $product->get_type() ) {
			return;
		}
		if ( $product->get_manage_stock() && $this->all_variations_out_of_stock( $product ) ) {
			$product->set_manage_stock( false );
			$product->set_stock_quantity( null );
		}
	}

	/**
	 * REST save — product already persisted, run full check.
	 *
	 * @param \WC_Product $product  Product.
	 * @param mixed       $request  Request.
	 * @param bool        $creating Whether this is a create.
	 */
	public function on_product_save_rest( $product, $request, $creating ) {
		unset( $request, $creating );
		if ( $product instanceof \WC_Product && 'variable' === $product->get_type() ) {
			$this->maybe_uncheck_manage_stock( $product );
		}
	}

	/**
	 * Variation stock/status change.
	 *
	 * @param \WC_Product_Variation|int $variation Variation.
	 */
	public function on_variation_change( $variation ) {
		if ( ! $variation instanceof \WC_Product_Variation ) {
			$variation = wc_get_product( $variation );
		}
		if ( $variation instanceof \WC_Product_Variation ) {
			$this->check_parent( $variation->get_parent_id() );
		}
	}

	/**
	 * Variation updated by id.
	 *
	 * @param int $variation_id Variation id.
	 */
	public function on_variation_id_change( $variation_id ) {
		$v = wc_get_product( $variation_id );
		if ( $v instanceof \WC_Product_Variation ) {
			$this->check_parent( $v->get_parent_id() );
		}
	}

	/**
	 * Queue a parent for fixing. Debounced via a single-event cron ~30s out
	 * so a bulk import touching hundreds of variations doesn't fire hundreds
	 * of maybe_uncheck_manage_stock() calls inline — each parent gets
	 * checked at most once, after the storm settles.
	 *
	 * @param int $parent_id Parent id.
	 */
	private function check_parent( $parent_id ) {
		if ( ! $parent_id ) {
			return;
		}
		$parent_id = (int) $parent_id;
		$queue     = (array) get_option( self::DEBOUNCE_OPT, array() );
		$queue[ $parent_id ] = time();
		update_option( self::DEBOUNCE_OPT, $queue, false );

		if ( ! wp_next_scheduled( self::DEBOUNCE_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::DEBOUNCE_HOOK );
		}
	}

	/**
	 * Drain the debounce queue — called by the scheduled single event.
	 */
	public function run_debounced_parent_checks() {
		$queue = get_option( self::DEBOUNCE_OPT, array() );
		if ( empty( $queue ) || ! is_array( $queue ) ) {
			delete_option( self::DEBOUNCE_OPT );
			return;
		}
		delete_option( self::DEBOUNCE_OPT );
		foreach ( array_keys( $queue ) as $parent_id ) {
			$parent = wc_get_product( (int) $parent_id );
			if ( $parent && 'variable' === $parent->get_type() ) {
				$this->maybe_uncheck_manage_stock( $parent );
			}
		}
	}

	/**
	 * Every visible variation out of stock?
	 *
	 * @param \WC_Product $product Product.
	 * @return bool
	 */
	private function all_variations_out_of_stock( $product ) {
		$children = $product->get_children();
		if ( empty( $children ) ) {
			return false;
		}
		$had_visible = false;
		foreach ( $children as $vid ) {
			$variation = wc_get_product( $vid );
			if ( ! $variation || ! $variation->variation_is_visible() ) {
				continue;
			}
			$had_visible = true;
			if ( $variation->is_in_stock() ) {
				return false;
			}
		}
		return $had_visible;
	}

	/**
	 * If conditions are met, uncheck manage_stock and save.
	 *
	 * @param \WC_Product|int $product Product.
	 * @return array{changed:bool,reason:string}
	 */
	public function maybe_uncheck_manage_stock( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}
		if ( ! $product instanceof \WC_Product || 'variable' !== $product->get_type() ) {
			return array(
				'changed' => false,
				'reason'  => 'not variable',
			);
		}
		if ( ! $product->get_manage_stock() ) {
			return array(
				'changed' => false,
				'reason'  => 'parent manage_stock already off',
			);
		}
		if ( ! $this->all_variations_out_of_stock( $product ) ) {
			return array(
				'changed' => false,
				'reason'  => 'at least one variation still in stock',
			);
		}
		$product->set_manage_stock( false );
		$product->set_stock_quantity( null );
		$product->save();

		if ( method_exists( '\WC_Product_Variable', 'sync_stock_status' ) ) {
			\WC_Product_Variable::sync_stock_status( $product->get_id() );
		} elseif ( method_exists( '\WC_Product_Variable', 'sync' ) ) {
			\WC_Product_Variable::sync( $product->get_id() );
		}

		return array(
			'changed' => true,
			'reason'  => 'unchecked manage_stock',
		);
	}

	/**
	 * AJAX: total variable products.
	 */
	public function ajax_get_total() {
		Security::verify_ajax_nonce( self::NONCE, '_ajax_nonce' );
		Security::require_cap_ajax( 'manage_woocommerce' );

		$q = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => 'variable',
					),
				),
				'no_found_rows'  => false,
			)
		);
		wp_send_json_success( array( 'total' => (int) $q->found_posts ) );
	}

	/**
	 * AJAX: run a batch.
	 */
	public function ajax_run_batch() {
		Security::verify_ajax_nonce( self::NONCE, '_ajax_nonce' );
		Security::require_cap_ajax( 'manage_woocommerce' );

		$offset  = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$dry_run = ! empty( $_POST['dry_run'] );
		$result  = $this->process_batch( $offset, self::BATCH_SIZE, $dry_run );
		wp_send_json_success( $result );
	}

	/**
	 * Process one batch.
	 *
	 * @param int  $offset     Offset.
	 * @param int  $batch_size Size.
	 * @param bool $dry_run    Dry run.
	 * @return array{processed:int,matched:int,fixed:int,log:string}
	 */
	public function process_batch( $offset, $batch_size, $dry_run = false ) {
		$q = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'fields'         => 'ids',
				'posts_per_page' => $batch_size,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => 'variable',
					),
				),
				'no_found_rows'  => true,
			)
		);

		$processed = 0;
		$matched   = 0;
		$fixed     = 0;
		$logs      = array();

		foreach ( $q->posts as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			++$processed;

			if ( ! $product->get_manage_stock() || ! $this->all_variations_out_of_stock( $product ) ) {
				continue;
			}
			++$matched;

			$label = '#' . $pid . ' ' . wp_strip_all_tags( $product->get_name() );

			if ( $dry_run ) {
				/* translators: %s: product label */
				$logs[] = '[dry-run] ' . sprintf( esc_html__( 'Would fix %s', 'freeman-core' ), $label );
			} else {
				$res = $this->maybe_uncheck_manage_stock( $product );
				if ( ! empty( $res['changed'] ) ) {
					++$fixed;
					/* translators: %s: product label */
					$logs[] = sprintf( esc_html__( 'Fixed %s', 'freeman-core' ), $label );
				}
			}
		}

		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		}

		return array(
			'processed' => $processed,
			'matched'   => $matched,
			'fixed'     => $fixed,
			'log'       => implode( "\n", $logs ),
		);
	}

	/**
	 * Daily audit — scans recently-modified variable products in chunks of
	 * BATCH_SIZE so one cron tick never runs long enough to time out.
	 * Self-chains via wp_schedule_single_event until there are no more hits,
	 * then the regular daily cron re-arms naturally on the next day.
	 *
	 * @param int $offset Paging offset; passed by the chained cron event.
	 */
	public function run_daily_audit( $offset = 0 ) {
		if ( ! (int) $this->get_option( 'run_daily_audit', 1 ) ) {
			return;
		}
		if ( ! class_exists( '\WooCommerce' ) ) {
			return;
		}
		$offset = max( 0, (int) $offset );
		$q      = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'fields'         => 'ids',
				'posts_per_page' => self::BATCH_SIZE,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'date_query'     => array(
					array(
						'column' => 'post_modified_gmt',
						'after'  => '48 hours ago',
					),
				),
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => 'variable',
					),
				),
				'no_found_rows'  => true,
			)
		);
		if ( empty( $q->posts ) ) {
			return;
		}
		foreach ( $q->posts as $pid ) {
			$this->maybe_uncheck_manage_stock( $pid );
		}

		// If we filled the batch, chain a follow-up tick so huge stores
		// still make progress without blowing max_execution_time.
		if ( count( $q->posts ) === self::BATCH_SIZE ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON_HOOK, array( $offset + self::BATCH_SIZE ) );
		}
	}

	/**
	 * Render the bulk audit UI on the module settings page.
	 */
	public function render_bulk_audit_ui() {
		$nonce = wp_create_nonce( self::NONCE );
		?>
		<h2><?php esc_html_e( 'Bulk audit', 'freeman-core' ); ?></h2>
		<p><?php esc_html_e( 'Scans every variable product. For each one where every visible variation is out of stock AND the parent still has "Manage stock" checked, the module unchecks that box and clears the parent stock quantity.', 'freeman-core' ); ?></p>
		<p>
			<label style="margin-right:15px;">
				<input type="checkbox" id="freeman-vpsf-dryrun" checked>
				<?php esc_html_e( 'Dry run (report only, no changes)', 'freeman-core' ); ?>
			</label>
			<button id="freeman-vpsf-start" class="button button-primary"><?php esc_html_e( 'Start scan', 'freeman-core' ); ?></button>
			<button id="freeman-vpsf-stop"  class="button" disabled><?php esc_html_e( 'Stop', 'freeman-core' ); ?></button>
		</p>
		<div id="freeman-vpsf-progress" style="margin-top:20px;display:none;">
			<p id="freeman-vpsf-status"></p>
			<div style="background:#ddd;width:100%;height:20px;border-radius:3px;overflow:hidden;">
				<div id="freeman-vpsf-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.3s;"></div>
			</div>
			<p id="freeman-vpsf-counts" style="margin-top:10px;font-family:monospace;"></p>
			<pre id="freeman-vpsf-log" style="background:#fff;border:1px solid #ccd0d4;padding:10px;max-height:300px;overflow:auto;font-size:12px;"></pre>
		</div>
		<script>
		(function () {
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var actions = {
				getTotal: <?php echo wp_json_encode( self::AJAX_GET_TOTAL ); ?>,
				runBatch: <?php echo wp_json_encode( self::AJAX_RUN_BATCH ); ?>
			};
			var i18n = {
				counting:    <?php echo wp_json_encode( __( 'Counting variable products…', 'freeman-core' ) ); ?>,
				scanning:    <?php echo wp_json_encode( __( 'Scanning batch starting at', 'freeman-core' ) ); ?>,
				fixing:      <?php echo wp_json_encode( __( 'Fixing batch starting at', 'freeman-core' ) ); ?>,
				doneDry:     <?php echo wp_json_encode( __( 'Scan complete. No changes were made (dry run).', 'freeman-core' ) ); ?>,
				doneLive:    <?php echo wp_json_encode( __( 'Done.', 'freeman-core' ) ); ?>,
				stopped:     <?php echo wp_json_encode( __( 'Stopped.', 'freeman-core' ) ); ?>,
				confirmLive: <?php echo wp_json_encode( __( 'Dry-run is OFF. This will actually modify products. Continue?', 'freeman-core' ) ); ?>,
				scanned:     <?php echo wp_json_encode( __( 'scanned', 'freeman-core' ) ); ?>,
				matched:     <?php echo wp_json_encode( __( 'match', 'freeman-core' ) ); ?>,
				fixed:       <?php echo wp_json_encode( __( 'fixed', 'freeman-core' ) ); ?>
			};
			var startBtn = document.getElementById('freeman-vpsf-start');
			var stopBtn  = document.getElementById('freeman-vpsf-stop');
			var dryEl    = document.getElementById('freeman-vpsf-dryrun');
			var progress = document.getElementById('freeman-vpsf-progress');
			var statusEl = document.getElementById('freeman-vpsf-status');
			var bar      = document.getElementById('freeman-vpsf-bar');
			var counts   = document.getElementById('freeman-vpsf-counts');
			var log      = document.getElementById('freeman-vpsf-log');
			var total = 0, processed = 0, matched = 0, fixed = 0, offset = 0, stopped = false;

			function ajax(action, data) {
				var body = new URLSearchParams(Object.assign({ action: action, _ajax_nonce: nonce }, data || {}));
				return fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
			}

			startBtn.addEventListener('click', async function () {
				var dryRun = dryEl.checked ? 1 : 0;
				if (!dryRun && !window.confirm(i18n.confirmLive)) return;
				stopped = false; processed = 0; matched = 0; fixed = 0; offset = 0;
				progress.style.display = 'block';
				startBtn.disabled = true; stopBtn.disabled = false;
				log.textContent = '';
				statusEl.textContent = i18n.counting;
				bar.style.width = '0%';

				var totalResp = await ajax(actions.getTotal);
				if (!totalResp || !totalResp.success) {
					statusEl.textContent = 'Error'; startBtn.disabled = false; stopBtn.disabled = true; return;
				}
				total = totalResp.data.total;
				counts.textContent = '0 / ' + total + ' ' + i18n.scanned;

				while (!stopped) {
					statusEl.textContent = (dryRun ? i18n.scanning : i18n.fixing) + ' ' + offset + '…';
					var resp = await ajax(actions.runBatch, { offset: offset, dry_run: dryRun });
					if (!resp || !resp.success) { log.textContent += 'Error\n'; break; }
					processed += resp.data.processed;
					matched   += resp.data.matched;
					fixed     += resp.data.fixed;
					offset    += resp.data.processed;
					if (resp.data.log) log.textContent += resp.data.log + '\n';
					var pct = total ? Math.min(100, Math.round((processed / total) * 100)) : 100;
					bar.style.width = pct + '%';
					counts.textContent = processed + ' / ' + total + ' ' + i18n.scanned + ', ' +
						matched + ' ' + i18n.matched + ', ' + fixed + ' ' + i18n.fixed;
					if (resp.data.processed === 0) break;
				}
				statusEl.textContent = stopped ? i18n.stopped : (dryRun ? i18n.doneDry : i18n.doneLive);
				startBtn.disabled = false; stopBtn.disabled = true;
			});
			stopBtn.addEventListener('click', function () { stopped = true; });
		})();
		</script>
		<?php
	}
}
