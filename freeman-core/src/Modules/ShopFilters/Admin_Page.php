<?php
/**
 * Shop Filters admin tool — a "Reindex now" batch runner rendered on the
 * Freeman → Shop Filters page. Mirrors the VariableStockFix bulk-audit pattern:
 * a get-total call followed by offset-paged batch calls driven by inline JS, so
 * a full rebuild of a medium catalogue never blocks a single request.
 *
 * Only wired when the indexer feature flag is on (Module::boot()).
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

use Freeman\Core\Core\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Admin reindex tool.
 */
final class Admin_Page {

	const NONCE          = 'freeman_core_shop_filters_reindex';
	const AJAX_GET_TOTAL = 'freeman_core_shop_filters_get_total';
	const AJAX_RUN_BATCH = 'freeman_core_shop_filters_reindex_batch';
	const BATCH_SIZE     = 50;

	/**
	 * Indexer.
	 *
	 * @var Indexer
	 */
	private $indexer;

	/**
	 * Constructor.
	 *
	 * @param Indexer $indexer Indexer.
	 */
	public function __construct( Indexer $indexer ) {
		$this->indexer = $indexer;
	}

	/**
	 * Register the page renderer + AJAX handlers.
	 */
	public function boot() {
		add_action( 'freeman_core/module_page/shop_filters', array( $this, 'render' ) );
		add_action( 'wp_ajax_' . self::AJAX_GET_TOTAL, array( $this, 'ajax_get_total' ) );
		add_action( 'wp_ajax_' . self::AJAX_RUN_BATCH, array( $this, 'ajax_run_batch' ) );
	}

	/**
	 * AJAX: report the product total + current index stats.
	 */
	public function ajax_get_total() {
		Security::verify_ajax_nonce( self::NONCE, '_ajax_nonce' );
		Security::require_cap_ajax( 'manage_woocommerce' );

		wp_send_json_success(
			array(
				'total'            => $this->indexer->count_products(),
				'indexed_products' => $this->indexer->repository()->count_indexed_products(),
				'rows'             => $this->indexer->repository()->count_rows(),
			)
		);
	}

	/**
	 * AJAX: reindex one batch.
	 */
	public function ajax_run_batch() {
		Security::verify_ajax_nonce( self::NONCE, '_ajax_nonce' );
		Security::require_cap_ajax( 'manage_woocommerce' );

		$offset    = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$processed = $this->indexer->reindex_batch( $offset, self::BATCH_SIZE );

		wp_send_json_success( array( 'processed' => $processed ) );
	}

	/**
	 * Render the tool on the module settings page.
	 */
	public function render() {
		$nonce = wp_create_nonce( self::NONCE );
		?>
		<h2><?php esc_html_e( 'Search index', 'freeman-core' ); ?></h2>
		<p><?php esc_html_e( 'Shop Filters keeps a lightweight index of every product\'s attributes, categories and in-stock state, refreshed automatically as products change. Use this only to rebuild it from scratch (e.g. after a bulk import that bypassed the normal save hooks).', 'freeman-core' ); ?></p>
		<p>
			<button id="freeman-sf-start" class="button button-primary"><?php esc_html_e( 'Reindex all products', 'freeman-core' ); ?></button>
			<button id="freeman-sf-stop"  class="button" disabled><?php esc_html_e( 'Stop', 'freeman-core' ); ?></button>
		</p>
		<div id="freeman-sf-progress" style="margin-top:20px;display:none;">
			<p id="freeman-sf-status"></p>
			<div style="background:#ddd;width:100%;height:20px;border-radius:3px;overflow:hidden;">
				<div id="freeman-sf-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.3s;"></div>
			</div>
			<p id="freeman-sf-counts" style="margin-top:10px;font-family:monospace;"></p>
		</div>
		<script>
		(function () {
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
			var actions = {
				getTotal: <?php echo wp_json_encode( self::AJAX_GET_TOTAL ); ?>,
				runBatch: <?php echo wp_json_encode( self::AJAX_RUN_BATCH ); ?>
			};
			var i18n = {
				counting: <?php echo wp_json_encode( __( 'Counting products…', 'freeman-core' ) ); ?>,
				indexing: <?php echo wp_json_encode( __( 'Indexing from', 'freeman-core' ) ); ?>,
				done:     <?php echo wp_json_encode( __( 'Reindex complete.', 'freeman-core' ) ); ?>,
				stopped:  <?php echo wp_json_encode( __( 'Stopped.', 'freeman-core' ) ); ?>,
				indexed:  <?php echo wp_json_encode( __( 'indexed', 'freeman-core' ) ); ?>
			};
			var startBtn = document.getElementById('freeman-sf-start');
			var stopBtn  = document.getElementById('freeman-sf-stop');
			var progress = document.getElementById('freeman-sf-progress');
			var statusEl = document.getElementById('freeman-sf-status');
			var bar      = document.getElementById('freeman-sf-bar');
			var counts   = document.getElementById('freeman-sf-counts');
			var total = 0, processed = 0, offset = 0, stopped = false;

			function ajax(action, data) {
				var body = new URLSearchParams(Object.assign({ action: action, _ajax_nonce: nonce }, data || {}));
				return fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
			}

			startBtn.addEventListener('click', async function () {
				stopped = false; processed = 0; offset = 0;
				progress.style.display = 'block';
				startBtn.disabled = true; stopBtn.disabled = false;
				statusEl.textContent = i18n.counting;
				bar.style.width = '0%';

				var totalResp = await ajax(actions.getTotal);
				if (!totalResp || !totalResp.success) {
					statusEl.textContent = 'Error'; startBtn.disabled = false; stopBtn.disabled = true; return;
				}
				total = totalResp.data.total;
				counts.textContent = '0 / ' + total + ' ' + i18n.indexed;

				while (!stopped) {
					statusEl.textContent = i18n.indexing + ' ' + offset + '…';
					var resp = await ajax(actions.runBatch, { offset: offset });
					if (!resp || !resp.success) { statusEl.textContent = 'Error'; break; }
					processed += resp.data.processed;
					offset    += resp.data.processed;
					var pct = total ? Math.min(100, Math.round((processed / total) * 100)) : 100;
					bar.style.width = pct + '%';
					counts.textContent = processed + ' / ' + total + ' ' + i18n.indexed;
					if (resp.data.processed === 0) break;
				}
				statusEl.textContent = stopped ? i18n.stopped : i18n.done;
				startBtn.disabled = false; stopBtn.disabled = true;
			});
			stopBtn.addEventListener('click', function () { stopped = true; });
		})();
		</script>
		<?php
	}
}
