<?php
/**
 * View template for Settings tab.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template view file loaded within controller method.

if ( ! Capabilities::can_manage_settings() ) {
	wp_die( esc_html__( 'You do not have permission to access these settings.', 'advanced-email-log' ), 403 );
}

$ael_logging_enabled    = '1' === (string) get_option( 'ael_logging_enabled', '1' );
$ael_live_updates       = '1' === (string) get_option( 'ael_live_updates_enabled', '1' );
$ael_log_body           = '1' === (string) get_option( 'ael_log_email_body', '1' );
$ael_dashboard_widget   = '1' === (string) get_option( 'ael_dashboard_widget_enabled', '1' );
$ael_delete_uninstall   = '1' === (string) get_option( 'ael_delete_data_on_uninstall', '0' );
$ael_rows_per_page      = (int) get_option( 'ael_rows_per_page', 20 );
$ael_allowed_roles      = Capabilities::get_allowed_roles();
$ael_allow_role_operations = Capabilities::are_role_operations_allowed();

// Auto delete logs settings.
$ael_auto_delete_enabled       = '1' === (string) get_option( 'ael_auto_delete_enabled', '0' );
$ael_auto_delete_value         = max( 1, (int) get_option( 'ael_auto_delete_value', 30 ) );
$ael_auto_delete_unit          = (string) get_option( 'ael_auto_delete_unit', 'days' );
$ael_auto_delete_include_trash = '1' === (string) get_option( 'ael_auto_delete_include_trash', '1' );

// Get all registered roles for role assignment checkboxes.
$ael_all_roles  = wp_roles();
$ael_roles_list = $ael_all_roles ? $ael_all_roles->roles : array();
?>
<div class="ael-settings-container">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=advanced-email-log&tab=settings' ) ); ?>">
		<?php wp_nonce_field( 'ael_save_settings_action', 'ael_settings_nonce' ); ?>
		<input type="hidden" name="ael_save_settings" value="1" />

		<!-- Section 1: General Settings -->
		<div class="ael-settings-section">
			<div class="ael-section-header">
				<h3><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'General Settings', 'advanced-email-log' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Configure primary logging behavior, pagination, and real-time updates.', 'advanced-email-log' ); ?></p>
			</div>
			<div class="ael-section-body">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Email Logging', 'advanced-email-log' ); ?></th>
							<td>
								<label for="ael_logging_enabled">
									<input type="checkbox" name="ael_logging_enabled" id="ael_logging_enabled" value="1" <?php checked( $ael_logging_enabled, true ); ?> />
									<strong><?php esc_html_e( 'Enable email logging', 'advanced-email-log' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'When enabled, all outgoing emails dispatched via wp_mail() will be recorded.', 'advanced-email-log' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Log Email Body', 'advanced-email-log' ); ?></th>
							<td>
								<label for="ael_log_email_body">
									<input type="checkbox" name="ael_log_email_body" id="ael_log_email_body" value="1" <?php checked( $ael_log_body, true ); ?> />
									<strong><?php esc_html_e( 'Store email message body', 'advanced-email-log' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Stores the full HTML and text message content. If unchecked, subject and metadata are logged without saving the body text.', 'advanced-email-log' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Live Updates (Polling)', 'advanced-email-log' ); ?></th>
							<td>
								<label for="ael_live_updates_enabled">
									<input type="checkbox" name="ael_live_updates_enabled" id="ael_live_updates_enabled" value="1" <?php checked( $ael_live_updates, true ); ?> />
									<strong><?php esc_html_e( 'Enable background live polling in Email Logs tab', 'advanced-email-log' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Periodically checks for newly logged emails while you view the Email Logs screen. Automatically pauses when your browser tab is inactive.', 'advanced-email-log' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ael_rows_per_page"><?php esc_html_e( 'Logs Per Page', 'advanced-email-log' ); ?></label>
							</th>
							<td>
								<input type="number" name="ael_rows_per_page" id="ael_rows_per_page" value="<?php echo esc_attr( $ael_rows_per_page ); ?>" min="5" max="500" step="5" class="small-text" />
								<span class="description"><?php esc_html_e( 'Number of rows shown per page in the Email Logs table (default: 20).', 'advanced-email-log' ); ?></span>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Date & Time Display', 'advanced-email-log' ); ?></th>
							<td>
								<p>
									<code><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></code>
									<span class="description">
										(<?php
										/* translators: %s: timezone string */
										printf( esc_html__( 'Configured via Settings &rarr; General (%s timezone).', 'advanced-email-log' ), esc_html( wp_timezone_string() ) );
										?>)
									</span>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Section 2: User Roles Access Control -->
		<div class="ael-settings-section">
			<div class="ael-section-header">
				<h3><span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'User Roles Access Control', 'advanced-email-log' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Control which WordPress user roles can access and interact with Advanced Email Log.', 'advanced-email-log' ); ?></p>
			</div>
			<div class="ael-section-body">
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Allowed Roles', 'advanced-email-log' ); ?></legend>
					<div class="ael-roles-grid">
						<?php
						foreach ( $ael_roles_list as $ael_role_key => $ael_role_info ) :
							$ael_is_admin = ( 'administrator' === $ael_role_key );
							$ael_checked  = $ael_is_admin || in_array( $ael_role_key, $ael_allowed_roles, true );
							?>
							<label class="ael-role-checkbox-item">
								<input type="checkbox" name="ael_allowed_roles[]" value="<?php echo esc_attr( $ael_role_key ); ?>"
									<?php checked( $ael_checked, true ); ?>
									<?php disabled( $ael_is_admin, true ); ?> />
								<span><?php echo esc_html( translate_user_role( $ael_role_info['name'] ) ); ?></span>
								<?php if ( $ael_is_admin ) : ?>
									<em style="color:#646970; font-size:12px;">(<?php esc_html_e( 'Always Granted', 'advanced-email-log' ); ?>)</em>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</div>

					<div class="ael-role-operations-box" style="margin-top:16px; padding-top:14px; border-top:1px solid #dcdcde;">
						<label for="ael_allow_role_operations" style="font-weight:600; display:flex; align-items:center; gap:8px;">
							<input type="checkbox" name="ael_allow_role_operations" id="ael_allow_role_operations" value="1" <?php checked( $ael_allow_role_operations, true ); ?> />
							<span><?php esc_html_e( 'Allow checked roles to perform operations in plugin', 'advanced-email-log' ); ?></span>
						</label>
						<p class="description" style="margin-left:26px; margin-top:4px;">
							<?php esc_html_e( 'When unchecked (default & recommended), non-administrator allowed roles have strictly Read-Only access (can view logs and analytics, but cannot delete, trash, restore, clear logs, import CSV, send test emails, or modify settings). Check this box only if you want allowed roles to perform all management and write operations.', 'advanced-email-log' ); ?>
						</p>
					</div>
				</fieldset>
			</div>
		</div>

		<!-- Section 3: Auto Delete Logs (Retention Policy) -->
		<div class="ael-settings-section">
			<div class="ael-section-header">
				<h3><span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Auto Delete Logs (Retention Policy)', 'advanced-email-log' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Automatically purge email logs older than a specified time threshold to keep your database lean and performant.', 'advanced-email-log' ); ?></p>
			</div>
			<div class="ael-section-body">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto Delete Logs', 'advanced-email-log' ); ?></th>
							<td>
								<label for="ael_auto_delete_enabled">
									<input type="checkbox" name="ael_auto_delete_enabled" id="ael_auto_delete_enabled" value="1" <?php checked( $ael_auto_delete_enabled, true ); ?> />
									<strong><?php esc_html_e( 'Enable automatic deletion of old email logs', 'advanced-email-log' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'When enabled, a background cron task runs periodically to remove logs that exceed your specified retention threshold.', 'advanced-email-log' ); ?>
								</p>
							</td>
						</tr>

						<tr id="ael-retention-threshold-row">
							<th scope="row">
								<label for="ael_auto_delete_value"><?php esc_html_e( 'Delete Logs Older Than', 'advanced-email-log' ); ?></label>
							</th>
							<td>
								<div class="ael-retention-input-group" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
									<input type="number" name="ael_auto_delete_value" id="ael_auto_delete_value" value="<?php echo esc_attr( $ael_auto_delete_value ); ?>" min="1" step="1" class="small-text" />
									<select name="ael_auto_delete_unit" id="ael_auto_delete_unit">
										<option value="seconds" <?php selected( $ael_auto_delete_unit, 'seconds' ); ?>><?php esc_html_e( 'Seconds', 'advanced-email-log' ); ?></option>
										<option value="minutes" <?php selected( $ael_auto_delete_unit, 'minutes' ); ?>><?php esc_html_e( 'Minutes', 'advanced-email-log' ); ?></option>
										<option value="hours" <?php selected( $ael_auto_delete_unit, 'hours' ); ?>><?php esc_html_e( 'Hours', 'advanced-email-log' ); ?></option>
										<option value="days" <?php selected( $ael_auto_delete_unit, 'days' ); ?>><?php esc_html_e( 'Days', 'advanced-email-log' ); ?></option>
										<option value="months" <?php selected( $ael_auto_delete_unit, 'months' ); ?>><?php esc_html_e( 'Months', 'advanced-email-log' ); ?></option>
										<option value="years" <?php selected( $ael_auto_delete_unit, 'years' ); ?>><?php esc_html_e( 'Years', 'advanced-email-log' ); ?></option>
									</select>
									<button type="button" class="button button-secondary" id="ael-run-cleanup-btn" title="<?php esc_attr_e( 'Immediately trigger auto-delete cleanup for logs older than this threshold', 'advanced-email-log' ); ?>">
										<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
										<?php esc_html_e( 'Run Cleanup Now', 'advanced-email-log' ); ?>
									</button>
									<span class="spinner" id="ael-cleanup-spinner" style="float:none; margin: 0 6px;"></span>
								</div>
								<p class="description" style="margin-top: 6px;">
									<?php esc_html_e( 'Specify any custom interval (e.g. 30 days, 24 hours, 6 months). Email logs older than this interval will be permanently deleted.', 'advanced-email-log' ); ?>
								</p>
								<div id="ael-cleanup-notice" style="display:none; margin-top: 8px;"></div>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Include Trash Logs', 'advanced-email-log' ); ?></th>
							<td>
								<label for="ael_auto_delete_include_trash">
									<input type="checkbox" name="ael_auto_delete_include_trash" id="ael_auto_delete_include_trash" value="1" <?php checked( $ael_auto_delete_include_trash, true ); ?> />
									<span><?php esc_html_e( 'Also delete trashed logs older than the retention threshold', 'advanced-email-log' ); ?></span>
								</label>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Section 4: Privacy & Data Protection -->
		<div class="ael-settings-section">
			<div class="ael-section-header">
				<h3><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Privacy & Data Protection', 'advanced-email-log' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Comply with GDPR, CCPA, and data retention standards.', 'advanced-email-log' ); ?></p>
			</div>
			<div class="ael-section-body">
				<div class="ael-privacy-alert">
					<strong><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Personal Data Warning:', 'advanced-email-log' ); ?></strong>
					<p>
						<?php esc_html_e( 'Email logs may record personally identifiable information (PII), such as recipient names, email addresses, order details, and temporary security tokens. Advanced Email Log integrates directly with WordPress Core Privacy Tools (Tools &rarr; Export Personal Data and Tools &rarr; Erase Personal Data). Advanced Email Log never transmits your email records to any third-party service.', 'advanced-email-log' ); ?>
					</p>
				</div>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Uninstall Clean-up', 'advanced-email-log' ); ?></th>
							<td>
								<label for="ael_delete_data_on_uninstall">
									<input type="checkbox" name="ael_delete_data_on_uninstall" id="ael_delete_data_on_uninstall" value="1" <?php checked( $ael_delete_uninstall, true ); ?> />
									<strong style="color: #b32d2e;"><?php esc_html_e( 'Remove all plugin data upon uninstallation', 'advanced-email-log' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Default is unchecked (preserving logs). When checked, deleting the plugin through the WordPress Plugins screen will drop the ael_email_logs database table and remove all plugin options.', 'advanced-email-log' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Section 5: Performance & Dashboard -->
		<div class="ael-settings-section">
			<div class="ael-section-header">
				<h3><span class="dashicons dashicons-performance"></span> <?php esc_html_e( 'Performance & Dashboard', 'advanced-email-log' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Control dashboard integration without degrading site performance.', 'advanced-email-log' ); ?></p>
			</div>
			<div class="ael-section-body">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Dashboard Activity Widget', 'advanced-email-log' ); ?></th>
							<td>
								<label for="ael_dashboard_widget_enabled">
									<input type="checkbox" name="ael_dashboard_widget_enabled" id="ael_dashboard_widget_enabled" value="1" <?php checked( $ael_dashboard_widget, true ); ?> />
									<strong><?php esc_html_e( 'Display email activity summary on the main WordPress Dashboard', 'advanced-email-log' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Shows daily counts (sent today, accepted, failed) and the 5 most recent email events.', 'advanced-email-log' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="ael-save-bar">
			<?php submit_button( __( 'Save Changes', 'advanced-email-log' ), 'primary button-hero', 'submit', false ); ?>
		</div>
	</form>
</div>
