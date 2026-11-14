<?php

/**
 * View template for Test Email tab.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template view file loaded within controller method.

$ael_current_user = wp_get_current_user();
$ael_default_to   = $ael_current_user->user_email ?: get_option('admin_email');
$ael_site_name    = get_bloginfo('name');
?>
<div class="ael-test-email-container">
	<div class="ael-card ael-test-card">
		<div class="ael-card-header">
			<h2>
				<span class="dashicons dashicons dashicons-email" style="vertical-align:middle; margin-top:-2px;"></span>
				<?php esc_html_e('Send a Diagnostic Test Email', 'advanced-email-log'); ?>
			</h2>
			<p>
				<?php esc_html_e('Verify that your WordPress mail pipeline is active and logging events properly. The email will be sent through the native wp_mail() function.', 'advanced-email-log'); ?>
			</p>
		</div>

		<div class="ael-card-body">
			<!-- Result Alert Banner -->
			<div class="ael-test-result-box" id="ael-test-result-box" style="display:none;"></div>

			<form id="ael-test-email-form" method="post">
				<?php wp_nonce_field('ael_admin_nonce', 'ael_test_nonce'); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="ael_test_to"><?php esc_html_e('Send To Email Address', 'advanced-email-log'); ?> <span class="required" style="color:#d63638;">*</span></label>
							</th>
							<td>
								<input type="email" name="ael_test_to" id="ael_test_to" class="regular-text" value="<?php echo esc_attr($ael_default_to); ?>" required />
								<p class="description">
									<?php esc_html_e('The recipient address where the test email will be sent.', 'advanced-email-log'); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ael_test_subject"><?php esc_html_e('Custom Subject (Optional)', 'advanced-email-log'); ?></label>
							</th>
							<td>
								<?php
								/* translators: %s: site name */
								$ael_placeholder_subject = sprintf(__('Advanced Email Log — Diagnostic Test from %s', 'advanced-email-log'), $ael_site_name);
								?>
								<input type="text" name="ael_test_subject" id="ael_test_subject" class="regular-text" placeholder="<?php echo esc_attr($ael_placeholder_subject); ?>" />
								<p class="description">
									<?php esc_html_e('Leave blank to use the default branded test email subject.', 'advanced-email-log'); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<div class="ael-form-actions">
					<button type="submit" class="button button-primary ael-send-test-btn" id="ael-send-test-btn">
						<span class="dashicons dashicons-email-alt"></span>
						<span><?php esc_html_e('Send Test Email', 'advanced-email-log'); ?></span>
					</button>
					<span class="spinner" id="ael-test-spinner" style="float:none; margin: 0 10px;"></span>
				</div>
			</form>
		</div>
	</div>

	<!-- Information Notice Box -->
	<div class="ael-info-card">
		<h3><span class="dashicons dashicons-info" style="vertical-align:middle; margin-top:-2px;"></span> <?php esc_html_e('Understanding WordPress Mail Delivery', 'advanced-email-log'); ?></h3>
		<ul>
			<li>
				<strong><?php esc_html_e('Accepted vs Delivered:', 'advanced-email-log'); ?></strong>
				<?php esc_html_e('When WordPress marks an email as "Accepted", it confirms that wp_mail() successfully handed the email off to your local web server or configured SMTP transport without throwing an error. It does not guarantee that spam filters, receiving mail servers, or the recipient inbox have delivered or accepted the message.', 'advanced-email-log'); ?>
			</li>
			<li>
				<strong><?php esc_html_e('SMTP Configuration:', 'advanced-email-log'); ?></strong>
				<?php esc_html_e('Advanced Email Log is designed to monitor and record email activity without modifying your mail routes. If your server is having trouble delivering emails to Gmail, Yahoo, or Outlook, consider configuring an authenticated SMTP plugin alongside Advanced Email Log.', 'advanced-email-log'); ?>
			</li>
		</ul>
	</div>
</div>