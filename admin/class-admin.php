<?php

/**
 * Admin interface and AJAX controllers.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Admin
 */
class Admin
{

	/**
	 * Log repository instance.
	 *
	 * @var Log_Repository
	 */
	protected $repository;

	/**
	 * Exporter instance.
	 *
	 * @var Exporter
	 */
	protected $exporter;

	/**
	 * Importer instance.
	 *
	 * @var Importer
	 */
	protected $importer;

	/**
	 * Test mail instance.
	 *
	 * @var Test_Mail
	 */
	protected $test_mail;

	/**
	 * Main menu hook suffix.
	 *
	 * @var string
	 */
	protected $hook_suffix = '';

	/**
	 * Constructor.
	 *
	 * @param Log_Repository $repository Log repository.
	 * @param Exporter       $exporter   Exporter.
	 * @param Importer       $importer   Importer.
	 * @param Test_Mail      $test_mail  Test mail handler.
	 */
	public function __construct(Log_Repository $repository, Exporter $exporter, Importer $importer, Test_Mail $test_mail)
	{
		$this->repository = $repository;
		$this->exporter   = $exporter;
		$this->importer   = $importer;
		$this->test_mail  = $test_mail;
	}

	/**
	 * Initialize admin hooks.
	 */
	public function init(): void
	{
		add_action('admin_menu', array($this, 'register_admin_menu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('admin_init', array($this, 'handle_save_settings'));
		add_action('admin_init', array($this, 'handle_bulk_actions'));

		// AJAX endpoints.
		add_action('wp_ajax_ael_get_log_details', array($this, 'ajax_get_log_details'));
		add_action('wp_ajax_ael_preview_html', array($this, 'ajax_preview_html'));
		add_action('wp_ajax_ael_poll_new_logs', array($this, 'ajax_poll_new_logs'));
		add_action('wp_ajax_ael_send_test_email', array($this, 'ajax_send_test_email'));

		// Trash, Delete & Import AJAX.
		add_action('wp_ajax_ael_trash_log', array($this, 'ajax_trash_log'));
		add_action('wp_ajax_ael_restore_log', array($this, 'ajax_restore_log'));
		add_action('wp_ajax_ael_delete_log', array($this, 'ajax_delete_log'));
		add_action('wp_ajax_ael_empty_trash', array($this, 'ajax_empty_trash'));
		add_action('wp_ajax_ael_clear_all_logs', array($this, 'ajax_clear_all_logs'));
		add_action('wp_ajax_ael_import_csv', array($this, 'ajax_import_csv'));
		add_action('wp_ajax_ael_get_analytics_data', array($this, 'ajax_get_analytics_data'));
		add_action('wp_ajax_ael_run_manual_cleanup', array($this, 'ajax_run_manual_cleanup'));
	}

	/**
	 * Register plugin admin menu.
	 */
	public function register_admin_menu(): void
	{
		$this->hook_suffix = add_menu_page(
			__('Advanced Email Log', 'advanced-email-log'),
			__('Email Log', 'advanced-email-log'),
			Capabilities::CAP_VIEW_LOGS,
			'advanced-email-log',
			array($this, 'render_admin_page'),
			'dashicons-email-alt2',
			26
		);
	}

	/**
	 * Enqueue admin stylesheet and scripts.
	 *
	 * @param string $hook Screen hook.
	 */
	public function enqueue_assets(string $hook): void
	{
		if ($hook !== $this->hook_suffix) {
			return;
		}

		// Enqueue WP Media scripts for CSV media library picker.
		wp_enqueue_media();

		wp_enqueue_style(
			'ael-admin-css',
			AEL_URL . 'admin/css/admin.css',
			array(),
			AEL_VERSION
		);

		// Chart.js local vendor library for Analytics.
		wp_enqueue_script(
			'ael-chartjs',
			AEL_URL . 'admin/js/chart.min.js',
			array(),
			'4.4.1',
			true
		);

		wp_enqueue_script(
			'ael-admin-js',
			AEL_URL . 'admin/js/admin.js',
			array('jquery', 'ael-chartjs'),
			AEL_VERSION,
			true
		);

		$latest_id = $this->repository->get_latest_id();

		wp_localize_script(
			'ael-admin-js',
			'aelData',
			array(
				'ajaxUrl'            => admin_url('admin-ajax.php'),
				'nonce'              => wp_create_nonce('ael_admin_nonce'),
				'exportNonce'        => wp_create_nonce('ael_export_logs'),
				'exportUrl'          => admin_url('admin.php?page=advanced-email-log&ael_action=export_csv'),
				'previewUrl'         => admin_url('admin-ajax.php?action=ael_preview_html'),
				'liveUpdatesEnabled' => '1' === (string) get_option('ael_live_updates_enabled', '1'),
				'latestId'           => $latest_id,
				'canPerformOperations' => Capabilities::can_perform_operations(),
				'canManageSettings'    => Capabilities::can_manage_settings(),
				'i18n'               => array(
					'loading'           => __('Loading email details...', 'advanced-email-log'),
					'errorLoading'      => __('Failed to load email details. Please try again.', 'advanced-email-log'),
					'noLogsSelected'    => __('Please select at least one log entry.', 'advanced-email-log'),
					'confirmTrash'      => __('Are you sure you want to move the selected log(s) to Trash?', 'advanced-email-log'),
					'confirmDelete'     => __('Are you sure you want to permanently delete the selected log(s)? This cannot be undone.', 'advanced-email-log'),
					'confirmEmptyTrash' => __('Are you sure you want to permanently empty all logs from Trash?', 'advanced-email-log'),
					'confirmClearAll'   => __('WARNING: Are you sure you want to delete ALL email logs? This action will permanently remove all logs and cannot be undone!', 'advanced-email-log'),
					'sending'           => __('Sending test email...', 'advanced-email-log'),
					/* translators: %d: number of new emails */
					'newLogsNotice'     => __('%d new email(s) logged. Click to refresh.', 'advanced-email-log'),
					'copied'            => __('Copied to clipboard!', 'advanced-email-log'),
					'importing'         => __('Importing logs, please wait...', 'advanced-email-log'),
					'confirmRunCleanup' => __('Are you sure you want to run auto-delete now? Logs older than the chosen threshold will be permanently deleted.', 'advanced-email-log'),
					'runningCleanup'    => __('Cleaning up old logs...', 'advanced-email-log'),
				),
			)
		);
	}

	/**
	 * Handle standard table bulk actions (Move to trash, Restore, Delete permanently).
	 */
	public function handle_bulk_actions(): void
	{
		if (! isset($_GET['page']) || 'advanced-email-log' !== $_GET['page']) {
			return;
		}

		$action = '';
		if (! empty($_REQUEST['action']) && '-1' !== $_REQUEST['action']) {
			$action = sanitize_key(wp_unslash($_REQUEST['action']));
		} elseif (! empty($_REQUEST['action2']) && '-1' !== $_REQUEST['action2']) {
			$action = sanitize_key(wp_unslash($_REQUEST['action2']));
		}

		if (empty($action) || 'export' === $action) {
			return;
		}

		if (! Capabilities::can_perform_operations()) {
			wp_die(esc_html__('You do not have permission to perform bulk operations on email logs.', 'advanced-email-log'), 403);
		}

		check_admin_referer('bulk-email_logs');

		$log_ids = isset($_REQUEST['log_ids']) ? array_map('absint', (array) $_REQUEST['log_ids']) : array();
		if (empty($log_ids)) {
			return;
		}

		$message = '';
		switch ($action) {
			case 'trash':
				$count = $this->repository->move_to_trash($log_ids);
				$message = sprintf( /* translators: %d: count */_n('%d email log moved to Trash.', '%d email logs moved to Trash.', $count, 'advanced-email-log'), $count);
				break;
			case 'restore':
				$count = $this->repository->restore_from_trash($log_ids);
				$message = sprintf( /* translators: %d: count */_n('%d email log restored from Trash.', '%d email logs restored from Trash.', $count, 'advanced-email-log'), $count);
				break;
			case 'delete':
				$count = $this->repository->delete_permanently($log_ids);
				$message = sprintf( /* translators: %d: count */_n('%d email log permanently deleted.', '%d email logs permanently deleted.', $count, 'advanced-email-log'), $count);
				break;
		}

		if (! empty($message)) {
			add_settings_error('ael_table_messages', 'ael_action_success', $message, 'updated');
		}
	}

	/**
	 * Handle settings form submission.
	 */
	public function handle_save_settings(): void
	{
		if (! isset($_POST['ael_save_settings'])) {
			return;
		}

		if (! check_admin_referer('ael_save_settings_action', 'ael_settings_nonce')) {
			wp_die(esc_html__('Security check failed. Please refresh and try again.', 'advanced-email-log'), 403);
		}

		if (! Capabilities::can_manage_settings()) {
			wp_die(esc_html__('You do not have permission to manage Advanced Email Log settings.', 'advanced-email-log'), 403);
		}

		// Sanitize and update settings.
		$logging_enabled = isset($_POST['ael_logging_enabled']) ? '1' : '0';
		update_option('ael_logging_enabled', $logging_enabled);

		$live_updates = isset($_POST['ael_live_updates_enabled']) ? '1' : '0';
		update_option('ael_live_updates_enabled', $live_updates);

		$log_body = isset($_POST['ael_log_email_body']) ? '1' : '0';
		update_option('ael_log_email_body', $log_body);

		$dashboard_widget = isset($_POST['ael_dashboard_widget_enabled']) ? '1' : '0';
		update_option('ael_dashboard_widget_enabled', $dashboard_widget);

		$delete_data = isset($_POST['ael_delete_data_on_uninstall']) ? '1' : '0';
		update_option('ael_delete_data_on_uninstall', $delete_data);

		$rows_per_page = isset($_POST['ael_rows_per_page']) ? max(5, min(500, (int) $_POST['ael_rows_per_page'])) : 20;
		update_option('ael_rows_per_page', $rows_per_page);

		// Auto delete logs settings.
		$auto_delete_enabled = isset($_POST['ael_auto_delete_enabled']) ? '1' : '0';
		update_option('ael_auto_delete_enabled', $auto_delete_enabled);

		$auto_delete_value = isset($_POST['ael_auto_delete_value']) ? max(1, (int) $_POST['ael_auto_delete_value']) : 30;
		update_option('ael_auto_delete_value', $auto_delete_value);

		$allowed_units = array('seconds', 'minutes', 'hours', 'days', 'months', 'years');
		$auto_delete_unit = isset($_POST['ael_auto_delete_unit']) && in_array($_POST['ael_auto_delete_unit'], $allowed_units, true)
			? sanitize_key($_POST['ael_auto_delete_unit'])
			: 'days';
		update_option('ael_auto_delete_unit', $auto_delete_unit);

		$include_trash = isset($_POST['ael_auto_delete_include_trash']) ? '1' : '0';
		update_option('ael_auto_delete_include_trash', $include_trash);

		// Role operations permission.
		$allow_operations = isset($_POST['ael_allow_role_operations']) ? '1' : '0';
		update_option(Capabilities::OPTION_ALLOW_ROLE_OPERATIONS, $allow_operations, false);

		// User roles synchronization.
		$allowed_roles = isset($_POST['ael_allowed_roles']) && is_array($_POST['ael_allowed_roles'])
			? array_map('sanitize_key', $_POST['ael_allowed_roles'])
			: array('administrator');

		Capabilities::sync_role_capabilities($allowed_roles, '1' === $allow_operations);

		add_settings_error(
			'ael_settings_messages',
			'ael_settings_updated',
			__('Settings saved successfully.', 'advanced-email-log'),
			'updated'
		);
	}

	/**
	 * Render main admin dashboard with tabs.
	 */
	public function render_admin_page(): void
	{
		if (! Capabilities::can_view_logs()) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'advanced-email-log'), 403);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation tab display parameter.
		$current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'logs';
		$valid_tabs  = array('logs', 'analytics', 'test-email', 'settings', 'recommended');

		if (! in_array($current_tab, $valid_tabs, true)) {
			$current_tab = 'logs';
		}

		// Test email tab restriction: only users permitted to perform operations.
		if ('test-email' === $current_tab && ! Capabilities::can_perform_operations()) {
			$current_tab = 'logs';
		}

		// Settings tab restriction: only users permitted to manage settings.
		if ('settings' === $current_tab && ! Capabilities::can_manage_settings()) {
			$current_tab = 'logs';
		}

?>
		<div class="wrap ael-admin-wrap">
			<div class="ael-header-bar">
				<div class="ael-header-title">
					<h1 class="wp-heading-inline">
						<span class="dashicons dashicons-email-alt2"></span>
						<?php esc_html_e('Advanced Email Log', 'advanced-email-log'); ?>
						<span class="ael-version-pill">v<?php echo esc_html(AEL_VERSION); ?></span>
					</h1>
					<p class="ael-header-subtitle">
						<?php esc_html_e('Universal, secure, and reliable email logging for WordPress.', 'advanced-email-log'); ?>
					</p>
				</div>
				<div class="ael-header-actions">
					<a href="https://wpallsolution.com/" target="_blank" rel="noopener noreferrer" class="ael-brand-link">
						<?php esc_html_e('by WP All Solution', 'advanced-email-log'); ?> &rarr;
					</a>
				</div>
			</div>

			<?php settings_errors('ael_settings_messages'); ?>
			<?php settings_errors('ael_table_messages'); ?>

			<nav class="nav-tab-wrapper ael-nav-tab-wrapper">
				<a href="<?php echo esc_url(admin_url('admin.php?page=advanced-email-log&tab=logs')); ?>" class="nav-tab <?php echo 'logs' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Email Logs', 'advanced-email-log'); ?>
				</a>
				<a href="<?php echo esc_url(admin_url('admin.php?page=advanced-email-log&tab=analytics')); ?>" class="nav-tab <?php echo 'analytics' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e('Analytics', 'advanced-email-log'); ?>
				</a>
				<?php if (Capabilities::can_perform_operations()) : ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=advanced-email-log&tab=test-email')); ?>" class="nav-tab <?php echo 'test-email' === $current_tab ? 'nav-tab-active' : ''; ?>">
						<span class="dashicons dashicons dashicons-email"></span> <?php esc_html_e('Test Email', 'advanced-email-log'); ?>
					</a>
				<?php endif; ?>
				<?php if (Capabilities::can_manage_settings()) : ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=advanced-email-log&tab=settings')); ?>" class="nav-tab <?php echo 'settings' === $current_tab ? 'nav-tab-active' : ''; ?>">
						<span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Settings', 'advanced-email-log'); ?>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url(admin_url('admin.php?page=advanced-email-log&tab=recommended')); ?>" class="nav-tab <?php echo 'recommended' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Recommended Plugins', 'advanced-email-log'); ?>
				</a>
			</nav>

			<div class="ael-tab-content">
				<?php
				switch ($current_tab) {
					case 'logs':
						$this->render_logs_tab();
						break;
					case 'analytics':
						$this->render_analytics_tab();
						break;
					case 'test-email':
						$this->render_test_email_tab();
						break;
					case 'settings':
						$this->render_settings_tab();
						break;
					case 'recommended':
						$this->render_recommended_tab();
						break;
				}
				?>
			</div>

			<?php include AEL_PATH . 'admin/views/log-details.php'; ?>
		</div>
<?php
	}

	/**
	 * Render Logs Tab.
	 */
	protected function render_logs_tab(): void
	{
		$table = new Logs_Table($this->repository);
		$table->prepare_items();
		include AEL_PATH . 'admin/views/logs.php';
	}

	/**
	 * Render Analytics Tab.
	 */
	protected function render_analytics_tab(): void
	{
		$repository = $this->repository;
		include AEL_PATH . 'admin/views/analytics.php';
	}

	/**
	 * Render Test Email Tab.
	 */
	protected function render_test_email_tab(): void
	{
		include AEL_PATH . 'admin/views/test-email.php';
	}

	/**
	 * Render Settings Tab.
	 */
	protected function render_settings_tab(): void
	{
		include AEL_PATH . 'admin/views/settings.php';
	}

	/**
	 * Render Recommended Plugins Tab.
	 */
	protected function render_recommended_tab(): void
	{
		include AEL_PATH . 'admin/views/recommended.php';
	}

	/**
	 * AJAX endpoint to fetch full log details for modal.
	 */
	public function ajax_get_log_details(): void
	{
		check_ajax_referer('ael_admin_nonce', 'nonce');

		if (! Capabilities::can_view_logs()) {
			wp_send_json_error(array('message' => __('Permission denied.', 'advanced-email-log')), 403);
		}

		$log_id = isset($_POST['id']) ? absint($_POST['id']) : 0;
		if ($log_id <= 0) {
			wp_send_json_error(array('message' => __('Invalid log ID.', 'advanced-email-log')), 400);
		}

		$log = $this->repository->get_by_id($log_id);
		if (! $log) {
			wp_send_json_error(array('message' => __('Log not found.', 'advanced-email-log')), 404);
		}

		// Calculate formatted timestamps.
		$utc_timestamp = strtotime($log['created_at'] . ' UTC');
		$local_format  = get_option('date_format') . ' ' . get_option('time_format');
		$local_time    = wp_date($local_format, $utc_timestamp);

		// Parse attachment list safely with download URLs.
		$attachments = array();
		if (! empty($log['attachments'])) {
			$raw_att = json_decode($log['attachments'], true);
			if (is_array($raw_att)) {
				foreach ($raw_att as $idx => $att) {
					$att['download_url'] = admin_url('admin.php?page=advanced-email-log&ael_action=download_attachment&id=' . $log['id'] . '&att_idx=' . $idx . '&token=' . wp_create_nonce('ael_download_' . $log['id'] . '_' . $idx));
					$attachments[] = $att;
				}
			}
		}

		// Parse raw headers safely.
		$headers = array();
		if (! empty($log['headers'])) {
			$raw_headers = json_decode($log['headers'], true);
			if (is_array($raw_headers)) {
				$headers = $raw_headers;
			}
		}

		// Build actionable troubleshooting tip for failed emails.
		$troubleshooting_tip = '';
		if ('failed' === $log['status']) {
			$err = (string) $log['error_message'];
			if (stripos($err, 'connect') !== false || stripos($err, 'timeout') !== false || stripos($err, 'host') !== false) {
				$troubleshooting_tip = __('Recommendation: Check your SMTP host, port, and firewall rules. The mail server could not be reached.', 'advanced-email-log');
			} elseif (stripos($err, 'auth') !== false || stripos($err, 'password') !== false || stripos($err, 'user') !== false) {
				$troubleshooting_tip = __('Recommendation: Authentication failed. Verify your SMTP username and password in your mail/SMTP plugin configuration.', 'advanced-email-log');
			} elseif (stripos($err, 'recipient') !== false || stripos($err, 'address') !== false || stripos($err, 'syntax') !== false) {
				$troubleshooting_tip = __('Recommendation: The recipient email address appears malformed or rejected by the receiving mail provider.', 'advanced-email-log');
			} else {
				$troubleshooting_tip = __('Recommendation: The WordPress mail pipeline reported a transport error. Ensure your server mail daemon or authenticated SMTP plugin is correctly configured.', 'advanced-email-log');
			}
		}

		$response = array(
			'id'                  => (int) $log['id'],
			'status'              => $log['status'],
			'status_label'        => ucfirst($log['status']),
			'created_at_utc'      => $log['created_at'],
			'created_at_local'    => $local_time,
			'to'                  => $log['to_email'],
			'from'                => $log['from_email'],
			'subject'             => ! empty($log['subject']) ? $log['subject'] : __('(No Subject)', 'advanced-email-log'),
			'content_type'        => $log['content_type'],
			'cc'                  => $log['cc'] ?? '',
			'bcc'                 => $log['bcc'] ?? '',
			'reply_to'            => $log['reply_to'] ?? '',
			'ip_address'          => $log['ip_address'],
			'error_message'       => $log['error_message'],
			'troubleshooting_tip' => $troubleshooting_tip,
			'attachments'         => $attachments,
			'headers'             => $headers,
			'body_raw'            => $log['body'],
			'body_is_html'        => (false !== stripos($log['content_type'], 'text/html')) || (false !== strpos($log['body'], '<html')) || (false !== strpos($log['body'], '</')),
			'preview_token'       => wp_create_nonce('ael_preview_' . $log['id']),
		);

		wp_send_json_success($response);
	}

	/**
	 * AJAX endpoint to serve sandboxed HTML preview inside iframe.
	 */
	public function ajax_preview_html(): void
	{
		$log_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
		$token  = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

		if (! wp_verify_nonce($token, 'ael_preview_' . $log_id)) {
			wp_die(esc_html__('Security check failed. Invalid preview token.', 'advanced-email-log'), 403);
		}

		if (! Capabilities::can_view_logs()) {
			wp_die(esc_html__('Permission denied.', 'advanced-email-log'), 403);
		}

		$log = $this->repository->get_by_id($log_id);
		if (! $log) {
			wp_die(esc_html__('Email log not found.', 'advanced-email-log'), 404);
		}

		// Security Headers: Block scripts, frame nesting outside admin, sniffing.
		header('Content-Type: text/html; charset=UTF-8');
		header('X-Content-Type-Options: nosniff');
		header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src * data:; font-src data:;");
		header('X-Frame-Options: SAMEORIGIN');

		// If email is plain text, display wrapped in clean pre tag.
		if (false === stripos($log['content_type'], 'text/html') && false === strpos($log['body'], '<html')) {
			echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:20px;color:#2c3338;line-height:1.6;font-size:14px;white-space:pre-wrap;word-break:break-word;}</style></head><body>' . esc_html($log['body']) . '</body></html>';
			exit;
		}

		// Email is HTML: output body. The iframe sandbox + CSP default-src 'none' guarantees that scripts cannot run.
		echo $log['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * AJAX endpoint for live polling new emails.
	 */
	public function ajax_poll_new_logs(): void
	{
		check_ajax_referer('ael_admin_nonce', 'nonce');

		if (! Capabilities::can_view_logs()) {
			wp_send_json_error(array('message' => __('Permission denied.', 'advanced-email-log')), 403);
		}

		$since_id  = isset($_POST['since_id']) ? absint($_POST['since_id']) : 0;
		$latest_id = $this->repository->get_latest_id();

		$has_new   = ($latest_id > $since_id && $since_id > 0);
		$new_count = 0;

		if ($has_new) {
			$new_count = $this->repository->count_new_logs($since_id);
		}

		wp_send_json_success(
			array(
				'has_new'   => $has_new,
				'count'     => $new_count,
				'latest_id' => $latest_id,
			)
		);
	}

	/**
	 * AJAX endpoint to send test email.
	 */
	public function ajax_send_test_email(): void
	{
		check_ajax_referer('ael_admin_nonce', 'nonce');

		if (! Capabilities::can_perform_operations()) {
			wp_send_json_error(array('message' => __('Permission denied. You do not have permission to send test emails.', 'advanced-email-log')), 403);
		}

		$to      = isset($_POST['to']) ? sanitize_email(wp_unslash($_POST['to'])) : '';
		$subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';

		$result = $this->test_mail->send($to, $subject);

		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result);
		}
	}

	/**
	 * AJAX endpoint: Move single or multiple logs to Trash.
	 */
	public function ajax_trash_log(): void
	{
		check_ajax_referer('ael_admin_nonce', 'nonce');

		if (! Capabilities::can_perform_operations()) {
			wp_send_json_error(array('message' => __('Permission denied. You do not have permission to move email logs to trash.', 'advanced-email-log')), 403);
		}

		$ids = isset($_POST['ids']) ? array_map('absint', (array) $_POST['ids']) : array();
		if (empty($ids)) {
			wp_send_json_error(array('message' => __('No logs specified.', 'advanced-email-log')), 400);
		}

		$count = $this->repository->move_to_trash($ids);
		/* translators: %d: number of logs */
		wp_send_json_success(array('count' => $count, 'message' => sprintf(__('%d log(s) moved to Trash.', 'advanced-email-log'), $count)));
	}

	/**
	 * AJAX endpoint: Restore log(s) from Trash.
	 */
	public function ajax_restore_log(): void
	{
		check_ajax_referer('ael_admin_nonce', 'nonce');

		if (! Capabilities::can_perform_operations()) {
			wp_send_json_error(array('message' => __('Permission denied. You do not have permission to restore email logs.', 'advanced-email-log')), 403);
		}

		$ids = isset($_POST['ids']) ? array_map('absint', (array) $_POST['ids']) : array();
		if (empty($ids)) {
			wp_send_json_error(array('message' => __('No logs specified.', 'advanced-email-log')), 400);
		}

		$count = $this->repository->restore_from_trash($ids);
		/* translators: %d: number of logs */
		wp_send_json_success(array('count' => $count, 'message' => sprintf(__('%d log(s) restored.', 'advanced-email-log'), $count)));
	}

	/**
	 * AJAX endpoint: Delete log(s) permanently.
	 */
	public function ajax_delete_log(): void
	{
		check_ajax_referer('ael_admin_nonce', 'nonce');

		if (! Capabilities::can_perform_operations()) {
			wp_send_json_error(array('message' => __('Permission denied. You do not have permission to permanently delete email logs.', 'advanced-email-log')), 403);
		}

		$ids = isset($_POST['ids']) ? array_map('absint', (array) $_POST['ids']) : array();
		if (empty($ids)) {
			wp_send_json_error(array('message' => __('No logs specified.', 'advanced-email-log')), 400);
		}

		$count = $this->repository->delete_permanently($ids);
		/* translators: %d: number of logs */
		wp_send_json_success(array('count' => $count, 'message' => sprintf(__('%d log(s) permanently deleted.', 'advanced-email-log'), $count)));
	}

	/**
	 * AJAX endpoint: Empty all logs in Trash.
	 */
	public function ajax_empty_trash(): void
	{
		check_ajax_referer('ael_admin_nonce', 'nonce');

		if (! Capabilities::can_perform_operations()) {
			wp_send_json_error(array('message' => __('Permission denied. You do not have permission to empty trash.', 'advanced-email-log')), 403);
		}

		$count = $this->repository->empty_trash();
		wp_send_json_success(array('count' => $count, 'message' => __('Trash has been emptied successfully.', 'advanced-email-log')));
	}

	/**
	 * AJAX endpoint: Clear all logs permanently.
	 */
	public function ajax_clear_all_logs(): void
	{
		check_ajax_referer('ael_admin_nonce', 'nonce');

		if (! Capabilities::can_perform_operations()) {
			wp_send_json_error(array('message' => __('Permission denied. You do not have permission to clear email logs.', 'advanced-email-log')), 403);
		}

		$count = $this->repository->clear_all_logs();
		wp_send_json_success(array('message' => __('All email logs have been cleared successfully.', 'advanced-email-log')));
	}

	/**
	 * AJAX endpoint: Import CSV from uploaded file or WP Media.
	 */
	public function ajax_import_csv(): void
	{
		check_ajax_referer('ael_admin_nonce', 'nonce');

		if (! Capabilities::can_perform_operations()) {
			wp_send_json_error(array('message' => __('Permission denied. You do not have permission to import email logs.', 'advanced-email-log')), 403);
		}

		$skip_duplicates = isset($_POST['skip_duplicates']) && '1' === (string) sanitize_text_field(wp_unslash($_POST['skip_duplicates']));
		$temp_file       = '';

		// 1. Direct file upload from computer.
		if (isset($_FILES['csv_file']) && ! empty($_FILES['csv_file']['tmp_name'])) {
			$file_error = isset($_FILES['csv_file']['error']) ? (int) $_FILES['csv_file']['error'] : UPLOAD_ERR_CANT_WRITE;
			if (UPLOAD_ERR_OK !== $file_error) {
				/* translators: %d: error code */
				wp_send_json_error(array('message' => sprintf(__('File upload failed with error code: %d', 'advanced-email-log'), $file_error)), 400);
			}
			$temp_file = sanitize_text_field(wp_unslash($_FILES['csv_file']['tmp_name']));
		} elseif (! empty($_POST['media_id'])) {
			// 2. Import from WordPress Media Library.
			$media_id = absint($_POST['media_id']);
			$attached = get_attached_file($media_id);
			if (! $attached || ! file_exists($attached)) {
				wp_send_json_error(array('message' => __('Selected media file could not be found on server.', 'advanced-email-log')), 404);
			}
			$temp_file = $attached;
		}

		if (empty($temp_file) || ! file_exists($temp_file)) {
			wp_send_json_error(array('message' => __('Please select a valid CSV file to import.', 'advanced-email-log')), 400);
		}

		$result = $this->importer->import_csv_file($temp_file, $skip_duplicates);

		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result, 400);
		}
	}

	/**
	 * AJAX endpoint: Fetch aggregated analytics data for dashboard and charts.
	 */
	public function ajax_get_analytics_data(): void
	{
		check_ajax_referer('ael_admin_nonce', 'nonce');

		if (! Capabilities::can_view_logs()) {
			wp_send_json_error(array('message' => __('Permission denied.', 'advanced-email-log')), 403);
		}

		$date_range = isset($_POST['date_range']) ? sanitize_key(wp_unslash($_POST['date_range'])) : 'last_7_days';
		$date_from  = isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : '';
		$date_to    = isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : '';
		$ip_address = isset($_POST['ip_address']) ? sanitize_text_field(wp_unslash($_POST['ip_address'])) : '';

		$filters = array(
			'date_range' => $date_range,
			'date_from'  => $date_from,
			'date_to'    => $date_to,
			'ip_address' => $ip_address,
		);

		$summary  = $this->repository->get_analytics_summary($filters);
		$timeline = $this->repository->get_analytics_timeline($filters);
		$top_ips  = $this->repository->get_top_ip_stats($filters, 8);
		$errors   = $this->repository->get_top_error_reasons($filters, 5);

		wp_send_json_success(
			array(
				'summary'  => $summary,
				'timeline' => $timeline,
				'top_ips'  => $top_ips,
				'errors'   => $errors,
			)
		);
	}

	/**
	 * AJAX endpoint: Manually trigger auto-delete log cleanup.
	 */
	public function ajax_run_manual_cleanup(): void
	{
		check_ajax_referer('ael_admin_nonce', 'nonce');

		if (! Capabilities::can_manage_settings()) {
			wp_send_json_error(array('message' => __('Permission denied. Settings management permission required.', 'advanced-email-log')), 403);
		}

		$value         = isset($_POST['value']) ? max(1, (int) $_POST['value']) : (int) get_option('ael_auto_delete_value', 30);
		$allowed_units = array('seconds', 'minutes', 'hours', 'days', 'months', 'years');
		$unit          = isset($_POST['unit']) && in_array($_POST['unit'], $allowed_units, true)
			? sanitize_key($_POST['unit'])
			: (string) get_option('ael_auto_delete_unit', 'days');
		$include_trash = isset($_POST['include_trash']) ? '1' === (string) sanitize_text_field(wp_unslash($_POST['include_trash'])) : ('1' === (string) get_option('ael_auto_delete_include_trash', '1'));

		$count = $this->repository->auto_delete_old_logs($value, $unit, $include_trash);

		/* translators: 1: number of deleted logs, 2: time value, 3: time unit */
		$format = __('Cleanup complete: %1$d log(s) older than %2$d %3$s were permanently deleted.', 'advanced-email-log');
		$message = sprintf(
			$format,
			$count,
			$value,
			$unit
		);

		wp_send_json_success(array('count' => $count, 'message' => $message));
	}
}
