<?php
/**
 * Freeman → Feature Flags page view.
 *
 * Lists every flag from Feature_Flags::registry() as a checkbox grouped by
 * module. Saving posts to admin-post.php (handled by Settings_Hub::
 * handle_save_feature_flags). Flags whose effective state is forced by a
 * `freeman_core/feature_flag/...` code filter render disabled — the DB
 * option can't override a filter, so the UI says so rather than lying.
 *
 * @package FreemanCore
 */

defined( 'ABSPATH' ) || exit;

use Freeman\Core\Core\Feature_Flags;
use Freeman\Core\Core\Settings_Hub;

$fr_flag_groups = array();
foreach ( Feature_Flags::registry() as $fr_flag ) {
	$fr_flag_groups[ $fr_flag['module'] ][] = $fr_flag;
}

$fr_just_saved = ! empty( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own post-save redirect; no state change.
?>
<div class="wrap freeman-wrap">
	<h1><?php esc_html_e( 'Feature Flags', 'freeman-core' ); ?></h1>

	<?php if ( $fr_just_saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Feature flags saved.', 'freeman-core' ); ?></p></div>
	<?php endif; ?>

	<p class="description" style="max-width:48em;">
		<?php esc_html_e( 'These are roll-out switches for features that ship turned off. Most are meant to be flipped once per site and left alone — flip one only if you know what it gates. Each entry below explains its effect; the matching WP-CLI command is shown for reference.', 'freeman-core' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;">
		<?php wp_nonce_field( 'freeman_save_feature_flags' ); ?>
		<input type="hidden" name="action" value="freeman_save_feature_flags"/>

		<?php foreach ( $fr_flag_groups as $fr_module => $fr_flags ) : ?>
			<h2><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $fr_module ) ) ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<?php foreach ( $fr_flags as $fr_flag ) : ?>
					<?php
					$fr_option  = Feature_Flags::option_name( $fr_flag['module'], $fr_flag['feature'] );
					$fr_enabled = Feature_Flags::is_enabled( $fr_flag['module'], $fr_flag['feature'] );
					$fr_forced  = Feature_Flags::is_forced_by_filter( $fr_flag['module'], $fr_flag['feature'] );
					?>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $fr_option ); ?>"><?php echo esc_html( $fr_flag['label'] ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" id="<?php echo esc_attr( $fr_option ); ?>" name="flags[<?php echo esc_attr( $fr_option ); ?>]" value="1" <?php checked( $fr_enabled ); ?> <?php disabled( $fr_forced ); ?>/>
								<?php echo esc_html( $fr_enabled ? __( 'Enabled', 'freeman-core' ) : __( 'Disabled', 'freeman-core' ) ); ?>
							</label>
							<p class="description" style="max-width:48em;"><?php echo esc_html( $fr_flag['description'] ); ?></p>
							<p class="description">
								<code>wp option update <?php echo esc_html( $fr_option ); ?> <?php echo esc_html( $fr_enabled ? '0' : '1' ); ?></code>
								&nbsp;·&nbsp;<?php /* translators: %s: version string */ printf( esc_html__( 'since %s', 'freeman-core' ), esc_html( $fr_flag['since'] ) ); ?>
								<?php if ( ! empty( $fr_flag['shared'] ) ) : ?>
									&nbsp;·&nbsp;<em><?php esc_html_e( 'shared switch — one toggle, several sub-features', 'freeman-core' ); ?></em>
								<?php endif; ?>
							</p>
							<?php if ( $fr_forced ) : ?>
								<p class="description" style="color:#996800;">
									<?php esc_html_e( 'A code-level filter is forcing this flag — the checkbox is disabled because the database option would be ignored.', 'freeman-core' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody></table>
		<?php endforeach; ?>

		<?php submit_button( __( 'Save feature flags', 'freeman-core' ) ); ?>
	</form>

	<hr style="margin:32px 0;"/>
	<p class="description" style="max-width:48em;">
		<?php
		echo wp_kses(
			/* translators: %s: filter hook name, wrapped in <code> */
			sprintf( __( 'For staging / dev you can also force a flag from code without touching the database: add a filter on %s in a mu-plugin.', 'freeman-core' ), '<code>freeman_core/feature_flag/{module}/{feature}</code>' ),
			array( 'code' => array() )
		);
		?>
	</p>
</div>
