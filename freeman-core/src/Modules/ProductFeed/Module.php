<?php
/**
 * Product Feed module.
 *
 * Generates a gzipped XML product feed (products + variations, stock, pricing,
 * attributes). Updates hourly AND instantly on stock/price changes with a
 * 30-second debounce. Exposes a clean rewrite rule at /product-feed.
 *
 * In 1.4.0 this class was trimmed to a thin coordinator: generation lives
 * in `Generator.php`, HTTP serving lives in `Server.php`. Third-party
 * callers of `generate_feed() / feed_file() / feed_url()` keep working via
 * the BC proxies at the bottom.
 *
 * Ported from wc-product-feed v1.3.0.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ProductFeed;

use Freeman\Core\Core\Module_Base;
use Freeman\Core\Core\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Module.
 */
final class Module extends Module_Base {

	const HOURLY_CRON = 'freeman_core_product_feed_hourly';
	const ASYNC_CRON  = 'freeman_core_product_feed_async';
	const NONCE       = 'freeman_core_product_feed_generate_now';

	/**
	 * Deprecated hook names — fired alongside the canonical ones for one
	 * release cycle (1.9.x). Removal target: 2.0.0.
	 */
	const DEPRECATED_HOURLY_CRON = 'freeman_productfeed_hourly';
	const DEPRECATED_ASYNC_CRON  = 'freeman_productfeed_async';
	const DEPRECATED_NONCE       = 'freeman_productfeed_generate_now';

	/* --- BC shims for the old Module-level constants ---------------- */

	/** @deprecated 1.4.0 Use Generator::BATCH. Kept for third-party callers. */
	const BATCH = Generator::BATCH;

	/** @deprecated 1.4.0 Use Server::REWRITE_SLUG. */
	const REWRITE_SLUG = Server::REWRITE_SLUG;

	/** @deprecated 1.4.0 Use Server::QUERY_VAR. */
	const QUERY_VAR = Server::QUERY_VAR;

	/** @deprecated 1.4.0 Use Generator::OPT_LAST_GEN. */
	const OPT_LAST_GEN = Generator::OPT_LAST_GEN;

	/**
	 * Generator (feed writer + path resolution).
	 *
	 * @var Generator|null
	 */
	private $generator = null;

	/**
	 * HTTP server (rewrite + serve_feed).
	 *
	 * @var Server|null
	 */
	private $server = null;

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'product_feed';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Product Feed', 'freeman-core' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Gzipped XML feed of every product (with variations, stock, pricing, attributes). Rebuilt hourly and within 30 seconds of any stock or price change. Exposed at /product-feed.', 'freeman-core' );
	}

	/**
	 * Settings schema.
	 *
	 * @return array
	 */
	public function settings_schema() {
		return array(
			'instant_update'  => array(
				'label'          => __( 'Instant updates', 'freeman-core' ),
				'type'           => 'checkbox',
				'checkbox_label' => __( 'Rebuild within ~30 seconds of any stock or price change', 'freeman-core' ),
				'default'        => 1,
			),
			'hourly_fallback' => array(
				'label'          => __( 'Hourly fallback', 'freeman-core' ),
				'type'           => 'checkbox',
				'checkbox_label' => __( 'Also run a full regeneration every hour (safety net)', 'freeman-core' ),
				'default'        => 1,
			),
		);
	}

	/**
	 * Lazy collaborator accessor for the generator.
	 *
	 * @return Generator
	 */
	public function generator() {
		if ( null === $this->generator ) {
			$this->generator = new Generator();
		}
		return $this->generator;
	}

	/**
	 * Lazy collaborator accessor for the HTTP server.
	 *
	 * @return Server
	 */
	public function server() {
		if ( null === $this->server ) {
			$this->server = new Server( $this->generator() );
		}
		return $this->server;
	}

	/**
	 * Activate — ensure output dir, cron, kick off first build.
	 */
	public function on_activate() {
		$this->generator()->ensure_feed_dir();
		if ( ! wp_next_scheduled( self::HOURLY_CRON ) ) {
			wp_schedule_event( time(), 'hourly', self::HOURLY_CRON );
		}
		wp_schedule_single_event( time() + 5, self::ASYNC_CRON );
	}

	/**
	 * Deactivate — clear crons.
	 */
	public function on_deactivate() {
		wp_clear_scheduled_hook( self::HOURLY_CRON );
		wp_clear_scheduled_hook( self::ASYNC_CRON );
		wp_clear_scheduled_hook( self::DEPRECATED_HOURLY_CRON );
		wp_clear_scheduled_hook( self::DEPRECATED_ASYNC_CRON );
	}

	/**
	 * Boot hooks.
	 */
	public function boot() {
		add_action( self::HOURLY_CRON, array( $this, 'cron_hourly' ) );
		add_action( self::ASYNC_CRON, array( $this, 'generate_feed' ) );

		// Deprecated cron hook aliases — kept for installs whose persisted
		// `cron` option still references the pre-1.9.0 names. Removed in 2.0.0.
		add_action( self::DEPRECATED_HOURLY_CRON, array( $this, 'cron_hourly' ) );
		add_action( self::DEPRECATED_ASYNC_CRON, array( $this, 'generate_feed' ) );

		add_action( 'plugins_loaded', array( $this, 'register_realtime_hooks' ), 20 );

		add_action( 'init', array( $this->server(), 'register_rewrite' ) );
		add_filter( 'query_vars', array( $this->server(), 'register_query_var' ) );
		add_action( 'template_redirect', array( $this->server(), 'serve_feed' ) );

		add_action( 'admin_post_' . self::NONCE, array( $this, 'handle_generate_now' ) );
		// Deprecated admin-post action — kept in case the form is cached client-side.
		add_action( 'admin_post_' . self::DEPRECATED_NONCE, array( $this, 'handle_generate_now' ) );

		add_action( 'freeman_core/module_page/' . $this->id(), array( $this, 'render_status_panel' ) );
	}

	/* -----------------------------------------------------------------
	 * Realtime triggers
	 * ----------------------------------------------------------------- */

	/**
	 * Register hooks that debounce-rebuild the feed on catalog changes.
	 */
	public function register_realtime_hooks() {
		if ( ! (int) $this->get_option( 'instant_update', 1 ) ) {
			return;
		}
		$hooks = array(
			'woocommerce_product_set_stock',
			'woocommerce_variation_set_stock',
			'woocommerce_product_set_stock_status',
			'woocommerce_update_product',
			'woocommerce_new_product',
			'woocommerce_order_status_completed',
			'woocommerce_order_status_cancelled',
			'woocommerce_order_status_refunded',
		);
		foreach ( $hooks as $hook ) {
			add_action( $hook, array( $this, 'schedule_debounced' ) );
		}
	}

	/**
	 * Schedule a debounced async regenerate.
	 */
	public function schedule_debounced() {
		$next = wp_next_scheduled( self::ASYNC_CRON );
		if ( $next && ( $next - time() ) < 60 ) {
			return;
		}
		wp_clear_scheduled_hook( self::ASYNC_CRON );
		wp_schedule_single_event( time() + 30, self::ASYNC_CRON );
	}

	/**
	 * Hourly cron entrypoint — respects the fallback toggle.
	 */
	public function cron_hourly() {
		if ( (int) $this->get_option( 'hourly_fallback', 1 ) ) {
			$this->generate_feed();
		}
	}

	/* -----------------------------------------------------------------
	 * BC proxies (old call sites still work after the 1.4.0 split)
	 * ----------------------------------------------------------------- */

	/** @return void */
	public function generate_feed() {
		$this->generator()->generate();
	}

	/** @return string */
	public function feed_dir() {
		return $this->generator()->feed_dir();
	}

	/** @return string */
	public function feed_url() {
		return $this->generator()->feed_url();
	}

	/** @return string */
	public function feed_file() {
		return $this->generator()->feed_file();
	}

	/** @return string */
	public function lock_file() {
		return $this->generator()->lock_file();
	}

	/* -----------------------------------------------------------------
	 * Admin UI
	 * ----------------------------------------------------------------- */

	/**
	 * Handle the "Generate Now" POST.
	 */
	public function handle_generate_now() {
		Security::verify_nonce( self::NONCE );
		Security::require_cap( 'manage_woocommerce' );
		$this->generator()->generate();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'freeman-' . $this->id(),
					'generated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the status panel on the module page.
	 */
	public function render_status_panel() {
		$last_gen     = get_option( Generator::OPT_LAST_GEN, '' );
		$feed_file    = $this->generator()->feed_file();
		$feed_exists  = file_exists( $feed_file );
		$feed_size    = $feed_exists ? size_format( filesize( $feed_file ) ) : '—';
		$next_cron    = wp_next_scheduled( self::HOURLY_CRON );
		$next_instant = wp_next_scheduled( self::ASYNC_CRON );
		$feed_url     = $this->server()->public_url();
		$generated    = isset( $_GET['generated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<h2><?php esc_html_e( 'Feed status', 'freeman-core' ); ?></h2>
		<?php if ( $generated ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Feed generated successfully.', 'freeman-core' ); ?></p></div>
		<?php endif; ?>
		<table class="widefat striped" style="max-width:680px;">
			<tr>
				<td><strong><?php esc_html_e( 'Feed URL', 'freeman-core' ); ?></strong></td>
				<td>
					<?php if ( $feed_exists ) : ?>
						<a href="<?php echo esc_url( $feed_url ); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html( $feed_url ); ?></a>
					<?php else : ?>
						<em style="color:#999"><?php esc_html_e( 'Not generated yet', 'freeman-core' ); ?></em>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Last generated', 'freeman-core' ); ?></strong></td>
				<td><?php echo $last_gen ? esc_html( get_date_from_gmt( $last_gen, 'Y-m-d H:i:s' ) ) : '<em>' . esc_html__( 'Never', 'freeman-core' ) . '</em>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'File size (gzipped)', 'freeman-core' ); ?></strong></td>
				<td><?php echo esc_html( $feed_size ); ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Next hourly run', 'freeman-core' ); ?></strong></td>
				<td><?php echo $next_cron ? esc_html( human_time_diff( $next_cron ) . ' from now' ) : '<em>' . esc_html__( 'Not scheduled', 'freeman-core' ) . '</em>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Instant rebuild queued', 'freeman-core' ); ?></strong></td>
				<td><?php echo $next_instant ? esc_html( 'In ' . human_time_diff( $next_instant ) ) : esc_html__( 'No', 'freeman-core' ); ?></td>
			</tr>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;">
			<?php wp_nonce_field( self::NONCE ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::NONCE ); ?>">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate now', 'freeman-core' ); ?></button>
		</form>
		<?php
	}
}
