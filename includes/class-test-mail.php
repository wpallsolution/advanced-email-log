<?php
/**
 * Test email dispatch and diagnostic handler.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Test_Mail
 */
class Test_Mail {

	/**
	 * Log repository instance.
	 *
	 * @var Log_Repository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param Log_Repository $repository Log repository.
	 */
	public function __construct( Log_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Send test email and return diagnostic result.
	 *
	 * @param string $to Recipient email.
	 * @param string $custom_subject Custom subject line.
	 * @return array
	 */
	public function send( string $to, string $custom_subject = '' ): array {
		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please provide a valid recipient email address.', 'advanced-email-log' ),
			);
		}

		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();
		$subject   = ! empty( $custom_subject ) ? sanitize_text_field( $custom_subject ) : sprintf( /* translators: %s: Site Name */ __( 'Advanced Email Log — Diagnostic Test from %s', 'advanced-email-log' ), $site_name );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		$message = $this->build_test_email_html( $to, $subject, $site_name, $site_url );

		// Hook into error capture to grab any wp_mail_failed error details.
		$captured_error = '';
		$error_callback = function ( $wp_error ) use ( &$captured_error ) {
			if ( is_wp_error( $wp_error ) ) {
				$captured_error = $wp_error->get_error_message();
			}
		};

		add_action( 'wp_mail_failed', $error_callback );

		// Dispatch via core wp_mail()
		$sent = wp_mail( $to, $subject, $message, $headers );

		remove_action( 'wp_mail_failed', $error_callback );

		// Locate the generated log ID (most recent log matching this recipient and subject).
		$latest_log = null;
		$logs       = $this->repository->get_logs(
			array(
				'per_page' => 1,
				'search'   => $to,
			)
		);
		if ( ! empty( $logs[0] ) ) {
			$latest_log = $logs[0];
		}

		return array(
			'success'        => $sent,
			'log_id'         => $latest_log ? $latest_log['id'] : 0,
			'status'         => $sent ? 'accepted' : 'failed',
			'error'          => $captured_error,
			'message'        => $sent
				? __( 'The test email was successfully accepted by your WordPress mail transport.', 'advanced-email-log' )
				: sprintf( /* translators: %s: Error message */ __( 'Failed to dispatch test email. Transport error: %s', 'advanced-email-log' ), $captured_error ?: __( 'Unknown mailer error', 'advanced-email-log' ) ),
		);
	}

	/**
	 * Build responsive HTML for test email with WP All Solution branding.
	 *
	 * @param string $to Recipient.
	 * @param string $subject Subject.
	 * @param string $site_name Site name.
	 * @param string $site_url Site URL.
	 * @return string
	 */
	protected function build_test_email_html( string $to, string $subject, string $site_name, string $site_url ): string {
		$local_format   = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$time_formatted = wp_date( $local_format );
		$time_utc       = gmdate( 'Y-m-d H:i:s' ) . ' UTC';

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $subject ); ?></title>
	<style>
		body { margin: 0; padding: 0; background-color: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; color: #1e293b; line-height: 1.6; }
		.email-wrapper { width: 100%; padding: 40px 10px; background-color: #f0f2f5; }
		.email-container { max-width: 620px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
		.brand-header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; padding: 28px 32px; border-bottom: 4px solid #2563eb; }
		.brand-badge { display: inline-block; background: rgba(37,99,235,0.25); border: 1px solid rgba(59,130,246,0.5); color: #93c5fd; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 3px 10px; border-radius: 20px; margin-bottom: 8px; }
		.brand-title { margin: 0; font-size: 22px; font-weight: 700; color: #ffffff; letter-spacing: -0.3px; }
		.brand-subtitle { margin: 4px 0 0; font-size: 13px; color: #94a3b8; }
		.email-body { padding: 32px; }
		.status-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 5px solid #16a34a; border-radius: 6px; padding: 14px 18px; margin-bottom: 24px; }
		.status-card-title { margin: 0; font-size: 15px; font-weight: 700; color: #15803d; display: flex; align-items: center; }
		.status-card-desc { margin: 4px 0 0; font-size: 13px; color: #166534; }
		.section-heading { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin: 24px 0 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }
		.details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px; }
		.details-table th { width: 30%; text-align: left; padding: 8px 10px; background-color: #f8fafc; color: #475569; font-weight: 600; border-bottom: 1px solid #f1f5f9; }
		.details-table td { padding: 8px 10px; color: #0f172a; border-bottom: 1px solid #f1f5f9; word-break: break-all; }
		.highlight-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 24px 0 10px; }
		.highlight-title { margin: 0 0 6px; font-size: 13px; font-weight: 700; color: #0f172a; }
		.highlight-text { margin: 0; font-size: 12px; color: #64748b; line-height: 1.5; }
		.brand-footer { background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 24px 32px; text-align: center; }
		.footer-logo { font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
		.footer-logo span { color: #2563eb; }
		.footer-text { font-size: 12px; color: #64748b; margin: 4px 0 0; }
		.footer-link { color: #2563eb; text-decoration: none; font-weight: 600; }
		.footer-link:hover { text-decoration: underline; }
	</style>
</head>
<body>
	<div class="email-wrapper">
		<div class="email-container">
			<!-- Header with WP All Solution Branding -->
			<div class="brand-header">
				<div class="brand-badge">WP All Solution</div>
				<h1 class="brand-title">Advanced Email Log</h1>
				<div class="brand-subtitle">Automated WordPress Mail Pipeline Verification</div>
			</div>

			<div class="email-body">
				<!-- Status Card -->
				<div class="status-card">
					<div class="status-card-title">&#10003; Diagnostic Mail Successfully Dispatched!</div>
					<div class="status-card-desc">WordPress native <code>wp_mail()</code> triggered this transmission and received acceptance from your mail server.</div>
				</div>

				<div class="section-heading">Transmission Metadata</div>
				<table class="details-table">
					<tr>
						<th>Website</th>
						<td><strong><a href="<?php echo esc_url( $site_url ); ?>" style="color:#2563eb; text-decoration:none;"><?php echo esc_html( $site_name ); ?></a></strong></td>
					</tr>
					<tr>
						<th>Recipient</th>
						<td><code><?php echo esc_html( $to ); ?></code></td>
					</tr>
					<tr>
						<th>Subject</th>
						<td><?php echo esc_html( $subject ); ?></td>
					</tr>
					<tr>
						<th>Dispatched At</th>
						<td><?php echo esc_html( $time_formatted ); ?> <span style="color:#94a3b8; font-size:11px;">(<?php echo esc_html( $time_utc ); ?>)</span></td>
					</tr>
					<tr>
						<th>PHP Version</th>
						<td><code>PHP <?php echo esc_html( PHP_VERSION ); ?></code></td>
					</tr>
					<tr>
						<th>WordPress</th>
						<td><code>WP <?php echo esc_html( get_bloginfo( 'version' ) ); ?></code></td>
					</tr>
				</table>

				<!-- Information Box -->
				<div class="highlight-box">
					<div class="highlight-title">Understanding Inbox Delivery</div>
					<p class="highlight-text">
						Receiving this test in your inbox verifies that your WordPress mail pipeline and mail transport are functioning properly. You can review the full record, MIME details, and raw headers anytime in your WordPress admin under <strong>Email Log &rarr; Email Logs</strong>.
					</p>
				</div>
			</div>

			<!-- Footer with WP All Solution Brand Highlight -->
			<div class="brand-footer">
				<div class="footer-logo">WP <span>All Solution</span></div>
				<p class="footer-text">
					Engineered with excellence by <a href="https://wpallsolution.com/" target="_blank" rel="noopener noreferrer" class="footer-link">WP All Solution</a> • Premium WordPress &amp; WooCommerce Plugins
				</p>
				<p class="footer-text" style="font-size: 11px; color: #94a3b8; margin-top: 6px;">
					&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> WP All Solution. All rights reserved.
				</p>
			</div>
		</div>
	</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}
}
