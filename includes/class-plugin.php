<?php
/**
 * Main plugin orchestrator.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Log repository.
	 *
	 * @var Log_Repository
	 */
	public $repository;

	/**
	 * Mail logger.
	 *
	 * @var Mail_Logger
	 */
	public $logger;

	/**
	 * Exporter.
	 *
	 * @var Exporter
	 */
	public $exporter;

	/**
	 * Importer.
	 *
	 * @var Importer
	 */
	public $importer;

	/**
	 * Test mail handler.
	 *
	 * @var Test_Mail
	 */
	public $test_mail;

	/**
	 * Admin controller.
	 *
	 * @var Admin|null
	 */
	public $admin = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->repository = new Log_Repository();
		$this->logger     = new Mail_Logger( $this->repository );
		$this->exporter   = new Exporter( $this->repository );
		$this->importer   = new Importer( $this->repository );
		$this->test_mail  = new Test_Mail( $this->repository );
	}

	/**
	 * Initialize plugin hooks.
	 */
	public function init(): void {
		// Load textdomain.
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Bundled local translations fallback.
		load_plugin_textdomain( 'advanced-email-log', false, dirname( AEL_BASENAME ) . '/languages' );

		// Check database migrations.
		Database::check_updates();

		// Start mail listening pipeline.
		$this->logger->init();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Action routing check only; individual handlers strictly verify nonces.
		// Handle CSV export request (GET or POST).
		if ( isset( $_REQUEST['ael_action'] ) && 'export_csv' === $_REQUEST['ael_action'] ) {
			$this->exporter->handle_export_request();
		}

		// Handle Attachment download request.
		if ( isset( $_GET['ael_action'] ) && 'download_attachment' === $_GET['ael_action'] ) {
			$this->handle_attachment_download();
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Register privacy tools.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_privacy_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_privacy_erasers' ) );

		// Dashboard widget.
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );

		// Background auto-cleanup cron event.
		add_action( 'ael_auto_cleanup_logs', array( $this, 'run_auto_cleanup' ) );
		if ( ! wp_next_scheduled( 'ael_auto_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'hourly', 'ael_auto_cleanup_logs' );
		}

		// Admin hooks.
		if ( is_admin() ) {
			$this->admin = new Admin( $this->repository, $this->exporter, $this->importer, $this->test_mail );
			$this->admin->init();

			// Plugin action links.
			add_filter( 'plugin_action_links_' . AEL_BASENAME, array( $this, 'add_plugin_action_links' ) );
		}
	}

	/**
	 * Run scheduled background auto-cleanup of old email logs.
	 *
	 * @return int Number of deleted logs.
	 */
	public function run_auto_cleanup(): int {
		return $this->repository->run_auto_cleanup_from_settings();
	}

	/**
	 * Handle secure attachment file download.
	 */
	public function handle_attachment_download(): void {
		if ( ! Capabilities::can_view_logs() ) {
			wp_die( esc_html__( 'Permission denied.', 'advanced-email-log' ), 403 );
		}

		$log_id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$att_idx  = isset( $_GET['att_idx'] ) ? absint( $_GET['att_idx'] ) : 0;
		$token    = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( ! wp_verify_nonce( $token, 'ael_download_' . $log_id . '_' . $att_idx ) ) {
			wp_die( esc_html__( 'Security check failed. Invalid download token.', 'advanced-email-log' ), 403 );
		}

		$log = $this->repository->get_by_id( $log_id );
		if ( ! $log || empty( $log['attachments'] ) ) {
			wp_die( esc_html__( 'Email log or attachment metadata not found.', 'advanced-email-log' ), 404 );
		}

		$attachments = json_decode( $log['attachments'], true );
		if ( ! is_array( $attachments ) || ! isset( $attachments[ $att_idx ] ) ) {
			wp_die( esc_html__( 'Specified attachment not found in email log.', 'advanced-email-log' ), 404 );
		}

		$att = $attachments[ $att_idx ];
		$filename = sanitize_file_name( $att['filename'] );

		// Try locating the file in uploads or system temporary dir.
		$upload_dir = wp_upload_dir()['basedir'];
		$possible_paths = array(
			isset( $att['path'] ) ? $att['path'] : '',
			$upload_dir . '/' . $filename,
			$upload_dir . '/ael-tests/' . $filename,
		);

		$file_found = '';
		foreach ( $possible_paths as $p ) {
			if ( ! empty( $p ) && file_exists( $p ) && is_readable( $p ) ) {
				$real_path = realpath( $p );
				// Safety check: ensure file exists and is valid.
				if ( $real_path && file_exists( $real_path ) ) {
					$file_found = $real_path;
					break;
				}
			}
		}

		if ( empty( $file_found ) ) {
			wp_die( esc_html__( 'The attachment file is no longer present on the server (it may have been a temporary file removed after transmission).', 'advanced-email-log' ), 404 );
		}

		if ( ob_get_level() ) {
			ob_end_clean();
		}

		$mime_type = ! empty( $att['mime'] ) ? $att['mime'] : 'application/octet-stream';
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: ' . esc_attr( $mime_type ) );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $file_found ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $file_found );
		exit;
	}

	/**
	 * Add action links on plugins list page.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public function add_plugin_action_links( array $links ): array {
		$plugin_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=advanced-email-log' ) ) . '">' . esc_html__( 'View Logs', 'advanced-email-log' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=advanced-email-log&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'advanced-email-log' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

	/**
	 * Register WordPress personal data exporter.
	 *
	 * @param array $exporters Exporters list.
	 * @return array
	 */
	public function register_privacy_exporters( array $exporters ): array {
		$exporters['advanced-email-log'] = array(
			'exporter_friendly_name' => __( 'Advanced Email Log', 'advanced-email-log' ),
			'callback'               => array( $this->repository, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Register WordPress personal data eraser.
	 *
	 * @param array $erasers Erasers list.
	 * @return array
	 */
	public function register_privacy_erasers( array $erasers ): array {
		$erasers['advanced-email-log'] = array(
			'eraser_friendly_name' => __( 'Advanced Email Log', 'advanced-email-log' ),
			'callback'             => array( $this->repository, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Register dashboard widget if enabled.
	 */
	public function register_dashboard_widget(): void {
		if ( ! Capabilities::can_view_logs() ) {
			return;
		}

		if ( '1' !== (string) get_option( 'ael_dashboard_widget_enabled', '1' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'ael_dashboard_widget',
			esc_html__( 'Advanced Email Log — Activity', 'advanced-email-log' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render lightweight dashboard widget.
	 */
	public function render_dashboard_widget(): void {
		$stats = $this->repository->get_dashboard_stats();
		?>
		<div class="ael-dashboard-widget-content">
			<div class="ael-dash-metrics" style="display: flex; gap: 15px; margin-bottom: 15px;">
				<div style="flex: 1; background: #f6f7f7; padding: 12px; border-radius: 4px; text-align: center; border: 1px solid #dcdcde;">
					<span style="font-size: 22px; font-weight: 700; color: #1d2327; display: block;"><?php echo (int) $stats['today_total']; ?></span>
					<span style="font-size: 12px; color: #646970;"><?php esc_html_e( 'Sent Today', 'advanced-email-log' ); ?></span>
				</div>
				<div style="flex: 1; background: #f6f7f7; padding: 12px; border-radius: 4px; text-align: center; border: 1px solid #dcdcde;">
					<span style="font-size: 22px; font-weight: 700; color: #008a20; display: block;"><?php echo (int) $stats['today_accepted']; ?></span>
					<span style="font-size: 12px; color: #646970;"><?php esc_html_e( 'Accepted', 'advanced-email-log' ); ?></span>
				</div>
				<div style="flex: 1; background: #f6f7f7; padding: 12px; border-radius: 4px; text-align: center; border: 1px solid #dcdcde;">
					<span style="font-size: 22px; font-weight: 700; color: #d63638; display: block;"><?php echo (int) $stats['today_failed']; ?></span>
					<span style="font-size: 12px; color: #646970;"><?php esc_html_e( 'Failed', 'advanced-email-log' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $stats['recent_logs'] ) ) : ?>
				<table style="width: 100%; border-collapse: collapse; font-size: 12px;">
					<thead>
						<tr style="border-bottom: 1px solid #dcdcde; text-align: left;">
							<th style="padding: 6px 4px;"><?php esc_html_e( 'To', 'advanced-email-log' ); ?></th>
							<th style="padding: 6px 4px;"><?php esc_html_e( 'Subject', 'advanced-email-log' ); ?></th>
							<th style="padding: 6px 4px;"><?php esc_html_e( 'Status', 'advanced-email-log' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $stats['recent_logs'] as $log ) : ?>
							<tr style="border-bottom: 1px solid #f0f0f1;">
								<td style="padding: 6px 4px;"><?php echo esc_html( wp_trim_words( $log['to_email'], 4, '...' ) ); ?></td>
								<td style="padding: 6px 4px;"><?php echo esc_html( wp_trim_words( $log['subject'], 5, '...' ) ); ?></td>
								<td style="padding: 6px 4px;">
									<span style="display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; text-transform: uppercase;
										<?php
										if ( 'accepted' === $log['status'] ) {
											echo 'background: #e7f5ea; color: #007017;';
										} elseif ( 'failed' === $log['status'] ) {
											echo 'background: #fcf0f1; color: #b32d2e;';
										} else {
											echo 'background: #f6f7f7; color: #646970;';
										}
										?>
									">
										<?php echo esc_html( $log['status'] ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p style="color: #646970; font-style: italic;"><?php esc_html_e( 'No email activity recorded yet.', 'advanced-email-log' ); ?></p>
			<?php endif; ?>

			<p style="margin-top: 15px; margin-bottom: 0; text-align: right;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=advanced-email-log' ) ); ?>" class="button button-small">
					<?php esc_html_e( 'View All Logs &rarr;', 'advanced-email-log' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
