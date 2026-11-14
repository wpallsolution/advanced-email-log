<?php
/**
 * View template for Email Logs tab.
 *
 * @package WPAllSolution\AdvancedEmailLog
 * @var \WPAllSolution\AdvancedEmailLog\Logs_Table $table Logs table instance.
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template view file loaded within controller method.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only table filter inputs populated from GET parameters.

$live_polling_enabled = '1' === (string) get_option( 'ael_live_updates_enabled', '1' );
?>
<div class="ael-logs-container">
	<div class="ael-toolbar-bar">
		<div class="ael-toolbar-left">
			<button type="button" class="button ael-manual-refresh-btn" id="ael-manual-refresh-btn">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh', 'advanced-email-log' ); ?>
			</button>

			<?php if ( $live_polling_enabled ) : ?>
				<span class="ael-live-status-indicator" title="<?php esc_attr_e( 'Live update polling active (checks every 20 seconds while page is visible).', 'advanced-email-log' ); ?>">
					<span class="ael-live-dot"></span>
					<?php esc_html_e( 'Live Updates Active', 'advanced-email-log' ); ?>
				</span>
			<?php endif; ?>
		</div>

		<div class="ael-toolbar-right">
			<!-- Hidden form for bulk export -->
			<form id="ael-bulk-export-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=advanced-email-log' ) ); ?>" style="display:none;">
				<?php wp_nonce_field( 'ael_export_logs', 'ael_export_nonce' ); ?>
				<input type="hidden" name="ael_action" value="export_csv" />
				<input type="hidden" name="log_ids" id="ael-export-selected-ids" value="" />
				<input type="hidden" name="status" value="<?php echo isset( $_REQUEST['status'] ) ? esc_attr( sanitize_key( wp_unslash( $_REQUEST['status'] ) ) ) : ''; ?>" />
				<input type="hidden" name="s" value="<?php echo isset( $_REQUEST['s'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : ''; ?>" />
				<input type="hidden" name="date_range" value="<?php echo isset( $_REQUEST['date_range'] ) ? esc_attr( sanitize_key( wp_unslash( $_REQUEST['date_range'] ) ) ) : ''; ?>" />
				<input type="hidden" name="date_from" value="<?php echo isset( $_REQUEST['date_from'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) ) : ''; ?>" />
				<input type="hidden" name="date_to" value="<?php echo isset( $_REQUEST['date_to'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) ) : ''; ?>" />
			</form>
		</div>
	</div>

	<!-- Live update banner (hidden initially) -->
	<div class="ael-new-logs-banner" id="ael-new-logs-banner" style="display:none;">
		<span class="dashicons dashicons-bell"></span>
		<span class="ael-banner-message"></span>
		<button type="button" class="button button-small ael-banner-refresh-btn"><?php esc_html_e( 'Refresh Table', 'advanced-email-log' ); ?></button>
	</div>

	<form id="ael-logs-filter-form" method="get">
		<input type="hidden" name="page" value="advanced-email-log" />
		<?php if ( ! empty( $_REQUEST['status'] ) ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_REQUEST['status'] ) ) ); ?>" />
		<?php endif; ?>

		<div class="ael-table-search-row">
			<div class="ael-views-wrap">
				<?php $table->views(); ?>
			</div>
			<div class="ael-search-box-wrap">
				<?php $table->search_box( __( 'Search Recipient or Subject', 'advanced-email-log' ), 'ael-search' ); ?>
			</div>
		</div>

		<?php $table->display(); ?>
	</form>

	<!-- Import CSV Modal -->
	<?php if ( Capabilities::can_perform_operations() ) : ?>
	<div class="ael-modal-overlay" id="ael-import-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="ael-import-modal-title">
		<div class="ael-modal-container ael-import-modal-container">
			<div class="ael-modal-header">
				<div class="ael-modal-title-group">
					<h2 id="ael-import-modal-title" class="ael-modal-title">
						<span class="dashicons dashicons-upload" style="margin-right:6px;"></span>
						<?php esc_html_e( 'Import Email Logs from CSV', 'advanced-email-log' ); ?>
					</h2>
				</div>
				<button type="button" class="ael-modal-close-btn" id="ael-import-modal-close" aria-label="<?php esc_attr_e( 'Close', 'advanced-email-log' ); ?>">&times;</button>
			</div>

			<div class="ael-modal-body">
				<div class="ael-import-tabs">
					<button type="button" class="button ael-import-tab-btn active" data-tab="upload">
						<span class="dashicons dashicons-desktop"></span> <?php esc_html_e( 'Upload from Computer', 'advanced-email-log' ); ?>
					</button>
					<button type="button" class="button ael-import-tab-btn" data-tab="media">
						<span class="dashicons dashicons-admin-media"></span> <?php esc_html_e( 'Select from Media Library', 'advanced-email-log' ); ?>
					</button>
				</div>

				<!-- Result Message -->
				<div class="ael-import-result" id="ael-import-result" style="display:none;"></div>

				<!-- Source A: File Upload -->
				<div class="ael-import-pane active" id="ael-import-pane-upload">
					<div class="ael-upload-dropzone" id="ael-upload-dropzone">
						<span class="dashicons dashicons-media-spreadsheet" style="font-size:48px; width:48px; height:48px; color:#2271b1;"></span>
						<p><strong><?php esc_html_e( 'Choose a CSV file or drag and drop it here', 'advanced-email-log' ); ?></strong></p>
						<input type="file" id="ael-csv-file-input" accept=".csv" />
						<div class="ael-selected-filename" id="ael-selected-filename"></div>
					</div>
				</div>

				<!-- Source B: Media Library Picker -->
				<div class="ael-import-pane" id="ael-import-pane-media" style="display:none;">
					<div class="ael-media-picker-box">
						<p><?php esc_html_e( 'Select an existing CSV file from your WordPress Media Library.', 'advanced-email-log' ); ?></p>
						<button type="button" class="button button-secondary" id="ael-open-media-btn">
							<span class="dashicons dashicons-admin-media"></span> <?php esc_html_e( 'Open Media Library', 'advanced-email-log' ); ?>
						</button>
						<input type="hidden" id="ael-selected-media-id" value="" />
						<div class="ael-selected-media-name" id="ael-selected-media-name" style="margin-top:10px; font-weight:600;"></div>
					</div>
				</div>

				<!-- Duplicate Options -->
				<div class="ael-duplicate-options">
					<h4><?php esc_html_e( 'Duplicate Handling:', 'advanced-email-log' ); ?></h4>
					<label class="ael-radio-label">
						<input type="radio" name="ael_skip_duplicates" value="1" checked />
						<strong><?php esc_html_e( 'Skip duplicate logs (Recommended)', 'advanced-email-log' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Skips logs matching existing sent timestamp, recipient, and subject.', 'advanced-email-log' ); ?></span>
					</label>
					<label class="ael-radio-label">
						<input type="radio" name="ael_skip_duplicates" value="0" />
						<strong><?php esc_html_e( 'Import all entries (including duplicates)', 'advanced-email-log' ); ?></strong>
					</label>
				</div>
			</div>

			<div class="ael-modal-footer">
				<button type="button" class="button button-secondary" id="ael-import-cancel-btn"><?php esc_html_e( 'Cancel', 'advanced-email-log' ); ?></button>
				<button type="button" class="button button-primary" id="ael-start-import-btn">
					<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Import Logs Now', 'advanced-email-log' ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>
