<?php
/**
 * Settings export / import / backups section. Included by tools.php.
 *
 * @package FreemanCore
 */

defined( 'ABSPATH' ) || exit;

$st              = new \Freeman\Core\Core\Settings_Tools();
$import_enabled  = \Freeman\Core\Core\Feature_Flags::is_enabled( 'tools', 'settings_import' );
$backups         = $st->list_backups();
$uid             = get_current_user_id();
$last_result     = get_transient( 'freeman_core_settings_import_result_' . $uid );
if ( $last_result ) {
	delete_transient( 'freeman_core_settings_import_result_' . $uid );
}
?>
<hr style="margin:32px 0;"/>

<h2><?php esc_html_e( 'Settings export / import', 'freeman-core' ); ?></h2>

<?php if ( is_array( $last_result ) ) : ?>
	<?php if ( ! empty( $last_result['ok'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: 1: count written, 2: count total */
					esc_html__( 'Imported %1$d of %2$d options.', 'freeman-core' ),
					(int) $last_result['written'],
					(int) $last_result['total']
				);
				if ( ! empty( $last_result['backup_at'] ) ) {
					echo ' ';
					printf(
						/* translators: %s: ISO timestamp */
						esc_html__( 'Pre-import backup created at %s.', 'freeman-core' ),
						esc_html( $last_result['backup_at'] )
					);
				}
				?>
			</p>
		</div>
	<?php else : ?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php
				if ( 'write_failed' === ( $last_result['reason'] ?? '' ) ) {
					printf(
						/* translators: 1: failing key, 2: written count, 3: total */
						esc_html__( 'Import halted at "%1$s" after writing %2$d of %3$d options. Use the auto-backup below to roll back.', 'freeman-core' ),
						esc_html( (string) $last_result['failed_at'] ),
						(int) $last_result['written'],
						(int) $last_result['total']
					);
				} else {
					printf(
						/* translators: %s: validation reason */
						esc_html__( 'Import rejected: %s. No options were written and no backup was created.', 'freeman-core' ),
						esc_html( (string) ( $last_result['reason'] ?? 'unknown' ) )
					);
				}
				?>
			</p>
		</div>
	<?php endif; ?>
<?php endif; ?>

<h3><?php esc_html_e( 'Export', 'freeman-core' ); ?></h3>
<p><?php esc_html_e( 'Download every freeman_core_* and freeman_digital_* option as a JSON envelope. Runtime state (logs, boot diagnostics, the backup store) is excluded.', 'freeman-core' ); ?></p>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( \Freeman\Core\Core\Settings_Tools::NONCE_EXPORT ); ?>
	<input type="hidden" name="action" value="freeman_settings_export"/>
	<button type="submit" class="button button-primary"><?php esc_html_e( 'Download settings JSON', 'freeman-core' ); ?></button>
</form>

<h3 style="margin-top:24px;"><?php esc_html_e( 'Import', 'freeman-core' ); ?></h3>
<?php if ( ! $import_enabled ) : ?>
	<p>
		<em><?php esc_html_e( 'Settings import is disabled. Enable with:', 'freeman-core' ); ?></em>
		<code>wp option update freeman_core_tools_settings_import_enabled 1</code>
	</p>
<?php else : ?>
	<p><?php esc_html_e( 'Upload a previously-exported JSON envelope. Only version 1 envelopes are accepted. A backup of current state is created automatically before any write.', 'freeman-core' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( \Freeman\Core\Core\Settings_Tools::NONCE_IMPORT ); ?>
		<input type="hidden" name="action" value="freeman_settings_import"/>
		<input type="file" name="envelope" accept="application/json" required/>
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Import settings JSON', 'freeman-core' ); ?></button>
	</form>
<?php endif; ?>

<h3 style="margin-top:24px;"><?php esc_html_e( 'Backups', 'freeman-core' ); ?></h3>
<p><?php esc_html_e( 'Auto-backups created before each import. The five most recent are kept. A restore itself creates a backup, so any restore is also undoable.', 'freeman-core' ); ?></p>
<?php if ( empty( $backups ) ) : ?>
	<p><em><?php esc_html_e( 'No backups yet.', 'freeman-core' ); ?></em></p>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Timestamp (UTC)', 'freeman-core' ); ?></th>
				<th><?php esc_html_e( 'Source', 'freeman-core' ); ?></th>
				<th><?php esc_html_e( 'From site', 'freeman-core' ); ?></th>
				<th><?php esc_html_e( 'Options', 'freeman-core' ); ?></th>
				<th><?php esc_html_e( 'Restore', 'freeman-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $backups as $i => $b ) : ?>
				<tr>
					<td><code><?php echo esc_html( $b['exported_at'] ?? '' ); ?></code></td>
					<td><?php echo esc_html( $b['source'] ?? 'auto' ); ?></td>
					<td><code><?php echo esc_html( $b['site_url'] ?? '' ); ?></code></td>
					<td><?php echo (int) ( isset( $b['options'] ) && is_array( $b['options'] ) ? count( $b['options'] ) : 0 ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Restore this backup? Current settings will be backed up first.', 'freeman-core' ) ); ?>');">
							<?php wp_nonce_field( \Freeman\Core\Core\Settings_Tools::NONCE_RESTORE ); ?>
							<input type="hidden" name="action" value="freeman_settings_restore"/>
							<input type="hidden" name="backup_index" value="<?php echo (int) $i; ?>"/>
							<button type="submit" class="button"><?php esc_html_e( 'Restore', 'freeman-core' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
