<?php
/**
 * View template for Log Details modal dialog.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ael-modal-overlay" id="ael-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="ael-modal-title">
	<div class="ael-modal-container">
		<div class="ael-modal-header">
			<div class="ael-modal-title-group">
				<h2 id="ael-modal-title" class="ael-modal-title">
					<span class="ael-modal-subject-text"><?php esc_html_e( 'Email Details', 'advanced-email-log' ); ?></span>
				</h2>
				<span class="ael-modal-status-badge" id="ael-modal-status-badge"></span>
			</div>
			<button type="button" class="ael-modal-close-btn" id="ael-modal-close-btn" aria-label="<?php esc_attr_e( 'Close', 'advanced-email-log' ); ?>">
				&times;
			</button>
		</div>

		<div class="ael-modal-nav-tabs">
			<button type="button" class="ael-modal-tab-btn active" data-tab="overview">
				<span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Overview & Diagnostics', 'advanced-email-log' ); ?>
			</button>
			<button type="button" class="ael-modal-tab-btn" data-tab="preview">
				<span class="dashicons dashicons-desktop"></span> <?php esc_html_e( 'HTML Preview', 'advanced-email-log' ); ?>
			</button>
			<button type="button" class="ael-modal-tab-btn" data-tab="source">
				<span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Raw Body', 'advanced-email-log' ); ?>
			</button>
			<button type="button" class="ael-modal-tab-btn" data-tab="headers">
				<span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Full Headers', 'advanced-email-log' ); ?>
			</button>
		</div>

		<div class="ael-modal-body">
			<!-- Loading Spinner -->
			<div class="ael-modal-loading" id="ael-modal-loading">
				<span class="spinner is-active"></span>
				<p><?php esc_html_e( 'Loading email details...', 'advanced-email-log' ); ?></p>
			</div>

			<!-- Tab 1: Overview & Diagnostics -->
			<div class="ael-modal-tab-pane active" id="ael-pane-overview">
				<!-- Diagnostic Detailed Error Callout (SMTP style) -->
				<div class="ael-error-banner" id="ael-modal-error-banner" style="display:none;">
					<div class="ael-error-header">
						<span class="dashicons dashicons-warning" style="font-size:20px;"></span>
						<strong><?php esc_html_e( 'Transport Delivery Failure Detected', 'advanced-email-log' ); ?></strong>
					</div>
					<div class="ael-error-body-text" id="ael-modal-error-text" style="margin-top:6px; font-family:monospace; background:rgba(255,255,255,0.7); padding:8px 10px; border-radius:3px;"></div>
					<div class="ael-error-tip" id="ael-modal-error-tip" style="margin-top:8px; font-size:12px; color:#611a1b;"></div>
				</div>

				<table class="ael-meta-table">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Log ID:', 'advanced-email-log' ); ?></th>
							<td><strong id="ael-modal-id"></strong></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Sent Time (Local):', 'advanced-email-log' ); ?></th>
							<td><span id="ael-modal-time-local"></span> <span class="ael-time-utc" id="ael-modal-time-utc"></span></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Recipient (To):', 'advanced-email-log' ); ?></th>
							<td><strong id="ael-modal-to"></strong></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Sender (From):', 'advanced-email-log' ); ?></th>
							<td><span id="ael-modal-from"></span></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Subject:', 'advanced-email-log' ); ?></th>
							<td><span id="ael-modal-subject"></span></td>
						</tr>
						<tr id="ael-row-cc" style="display:none;">
							<th><?php esc_html_e( 'CC:', 'advanced-email-log' ); ?></th>
							<td><span id="ael-modal-cc"></span></td>
						</tr>
						<tr id="ael-row-bcc" style="display:none;">
							<th><?php esc_html_e( 'BCC:', 'advanced-email-log' ); ?></th>
							<td><span id="ael-modal-bcc"></span></td>
						</tr>
						<tr id="ael-row-reply-to" style="display:none;">
							<th><?php esc_html_e( 'Reply-To:', 'advanced-email-log' ); ?></th>
							<td><span id="ael-modal-reply-to"></span></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Content Type:', 'advanced-email-log' ); ?></th>
							<td><code id="ael-modal-content-type"></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Triggering IP:', 'advanced-email-log' ); ?></th>
							<td><code id="ael-modal-ip"></code></td>
						</tr>
						<tr id="ael-row-attachments" style="display:none;">
							<th><?php esc_html_e( 'Attachments:', 'advanced-email-log' ); ?></th>
							<td>
								<ul class="ael-attachments-list" id="ael-modal-attachments-list"></ul>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Tab 2: Sandboxed HTML Preview -->
			<div class="ael-modal-tab-pane" id="ael-pane-preview">
				<div class="ael-preview-wrapper">
					<div class="ael-preview-bar">
						<div class="ael-preview-bar-left">
							<span class="dashicons dashicons-shield"></span>
							<small><?php esc_html_e( 'Rendered in a strongly sandboxed, script-disabled environment.', 'advanced-email-log' ); ?></small>
						</div>
						<div class="ael-preview-bar-right">
							<button type="button" class="button button-small ael-preview-mode-btn active" data-mode="desktop">
								<span class="dashicons dashicons-desktop"></span> <?php esc_html_e( 'Desktop', 'advanced-email-log' ); ?>
							</button>
							<button type="button" class="button button-small ael-preview-mode-btn" data-mode="mobile">
								<span class="dashicons dashicons-smartphone"></span> <?php esc_html_e( 'Mobile', 'advanced-email-log' ); ?>
							</button>
						</div>
					</div>
					<div class="ael-iframe-holder desktop-mode" id="ael-iframe-holder">
						<iframe id="ael-details-iframe" class="ael-details-iframe" sandbox="allow-same-origin" src="about:blank" title="<?php esc_attr_e( 'Email Preview', 'advanced-email-log' ); ?>"></iframe>
					</div>
				</div>
			</div>

			<!-- Tab 3: Plain Text / Raw Body -->
			<div class="ael-modal-tab-pane" id="ael-pane-source">
				<div class="ael-source-toolbar">
					<button type="button" class="button button-small" id="ael-copy-body-btn">
						<span class="dashicons dashicons-clipboard"></span> <span class="ael-copy-btn-text"><?php esc_html_e( 'Copy Raw Body', 'advanced-email-log' ); ?></span>
					</button>
				</div>
				<pre class="ael-code-box"><code id="ael-modal-raw-body"></code></pre>
			</div>

			<!-- Tab 4: Full Headers -->
			<div class="ael-modal-tab-pane" id="ael-pane-headers">
				<div class="ael-source-toolbar">
					<button type="button" class="button button-small" id="ael-copy-headers-btn">
						<span class="dashicons dashicons-clipboard"></span> <span class="ael-copy-headers-text"><?php esc_html_e( 'Copy Full Headers', 'advanced-email-log' ); ?></span>
					</button>
				</div>
				<div id="ael-no-headers-msg" style="display:none; color: #646970; font-style: italic; padding: 10px 0;">
					<?php esc_html_e( 'No custom headers were recorded for this message.', 'advanced-email-log' ); ?>
				</div>
				<pre class="ael-code-box"><code id="ael-modal-raw-headers"></code></pre>
			</div>
		</div>

		<div class="ael-modal-footer">
			<button type="button" class="button button-secondary" id="ael-modal-footer-close-btn">
				<?php esc_html_e( 'Close', 'advanced-email-log' ); ?>
			</button>
		</div>
	</div>
</div>
