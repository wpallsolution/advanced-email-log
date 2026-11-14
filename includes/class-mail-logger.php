<?php
/**
 * Universal mail logger capturing wp_mail activity.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mail_Logger
 */
class Mail_Logger {

	/**
	 * Log repository instance.
	 *
	 * @var Log_Repository
	 */
	protected $repository;

	/**
	 * Stack of pending logs for correlation in LIFO order.
	 *
	 * @var array
	 */
	protected $pending_stack = array();

	/**
	 * Captured PHPMailer transport details.
	 *
	 * @var array
	 */
	protected $transport_info = array();

	/**
	 * Constructor.
	 *
	 * @param Log_Repository $repository Log repository.
	 */
	public function __construct( Log_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Register WordPress mail hooks.
	 */
	public function init(): void {
		// Priority 99999 ensures we observe after other plugins modify args, or catch pre_wp_mail short-circuits.
		add_filter( 'pre_wp_mail', array( $this, 'capture_pre_wp_mail' ), 99999, 2 );
		add_filter( 'wp_mail', array( $this, 'capture_wp_mail' ), 99999 );
		add_action( 'phpmailer_init', array( $this, 'capture_phpmailer_init' ), 99999, 1 );
		add_action( 'wp_mail_succeeded', array( $this, 'capture_wp_mail_succeeded' ), 10, 1 );
		add_action( 'wp_mail_failed', array( $this, 'capture_wp_mail_failed' ), 10, 1 );
		add_action( 'shutdown', array( $this, 'handle_shutdown' ) );
	}

	/**
	 * Check if logging is currently enabled.
	 *
	 * @return bool
	 */
	public function is_logging_enabled(): bool {
		return '1' === (string) get_option( 'ael_logging_enabled', '1' );
	}

	/**
	 * Capture PHPMailer transport configuration.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	public function capture_phpmailer_init( $phpmailer ): void {
		if ( ! is_object( $phpmailer ) ) {
			return;
		}

		$this->transport_info = array(
			'mailer'      => isset( $phpmailer->Mailer ) ? $phpmailer->Mailer : 'mail',
			'host'        => isset( $phpmailer->Host ) ? $phpmailer->Host : '',
			'port'        => isset( $phpmailer->Port ) ? $phpmailer->Port : 25,
			'smtp_auth'   => isset( $phpmailer->SMTPAuth ) ? (bool) $phpmailer->SMTPAuth : false,
			'smtp_secure' => isset( $phpmailer->SMTPSecure ) ? $phpmailer->SMTPSecure : '',
			'sender'      => isset( $phpmailer->Sender ) ? $phpmailer->Sender : '',
		);
	}

	/**
	 * Capture short-circuited mail via pre_wp_mail filter.
	 *
	 * @param null|bool $return Short-circuit return value.
	 * @param array     $args   Mail arguments.
	 * @return null|bool
	 */
	public function capture_pre_wp_mail( $return, $args ) {
		if ( null === $return || ! $this->is_logging_enabled() ) {
			return $return;
		}

		try {
			$status = ( true === $return ) ? 'accepted' : 'failed';
			$parsed = $this->parse_mail_args( $args );

			$data = array_merge(
				$parsed,
				array(
					'status'        => $status,
					'error_message' => ( false === $return ) ? __( 'Short-circuited by a pre_wp_mail filter returning false.', 'advanced-email-log' ) : null,
					'extra_data'    => array(
						'short_circuit' => true,
						'transport'     => $this->transport_info,
					),
				)
			);

			$this->repository->insert( $data );
		} catch ( \Throwable $e ) {
			// Fail gracefully without breaking mail flow.
		}

		return $return;
	}

	/**
	 * Capture wp_mail arguments and insert a pending record.
	 *
	 * @param array $args wp_mail arguments.
	 * @return array
	 */
	public function capture_wp_mail( array $args ): array {
		if ( ! $this->is_logging_enabled() ) {
			return $args;
		}

		try {
			$parsed = $this->parse_mail_args( $args );

			// Check email body logging setting.
			$log_body_enabled = '1' === (string) get_option( 'ael_log_email_body', '1' );
			if ( ! $log_body_enabled ) {
				$parsed['body'] = __( '(Email body logging disabled in settings)', 'advanced-email-log' );
			}

			$parsed['status']     = 'pending';
			$parsed['extra_data'] = array(
				'transport' => $this->transport_info,
			);

			$log_id = $this->repository->insert( $parsed );

			if ( $log_id > 0 ) {
				$signature = $this->generate_signature( $parsed );
				$this->pending_stack[] = array(
					'id'        => $log_id,
					'signature' => $signature,
					'time'      => microtime( true ),
				);
			}
		} catch ( \Throwable $e ) {
			// Fail gracefully without breaking mail flow.
		}

		return $args;
	}

	/**
	 * Handle observable success via wp_mail_succeeded.
	 *
	 * @param array $mail_data Mail data from hook.
	 */
	public function capture_wp_mail_succeeded( array $mail_data ): void {
		if ( empty( $this->pending_stack ) ) {
			return;
		}

		try {
			$log_entry = $this->pop_matching_pending( $mail_data );
			if ( $log_entry ) {
				$this->repository->update(
					$log_entry['id'],
					array(
						'status'        => 'accepted',
						'error_message' => null,
						'extra_data'    => array(
							'transport' => $this->transport_info,
							'result'    => 'accepted_by_transport',
						),
					)
				);
			}
		} catch ( \Throwable $e ) {
			// Defend against any database or logging exception.
		}
	}

	/**
	 * Handle observable failure via wp_mail_failed.
	 *
	 * @param \WP_Error $wp_error Error object.
	 */
	public function capture_wp_mail_failed( $wp_error ): void {
		if ( empty( $this->pending_stack ) ) {
			return;
		}

		try {
			$mail_data = is_object( $wp_error ) && method_exists( $wp_error, 'get_error_data' )
				? (array) $wp_error->get_error_data( 'wp_mail_failed' )
				: array();

			$raw_error = '';
			if ( is_wp_error( $wp_error ) ) {
				$raw_error    = $wp_error->get_error_message();
				$extra_errors = $wp_error->get_error_messages();
				if ( count( $extra_errors ) > 1 ) {
					$raw_error = implode( ' | ', $extra_errors );
				}
			} else {
				$raw_error = __( 'wp_mail reported an unknown error.', 'advanced-email-log' );
			}

			// Format human-friendly diagnostic explanation.
			$formatted_error = $this->format_friendly_error( $raw_error );

			$log_entry = $this->pop_matching_pending( $mail_data );
			if ( $log_entry ) {
				$this->repository->update(
					$log_entry['id'],
					array(
						'status'        => 'failed',
						'error_message' => $formatted_error,
						'extra_data'    => array(
							'raw_error' => $raw_error,
							'transport' => $this->transport_info,
						),
					)
				);
			}
		} catch ( \Throwable $e ) {
			// Defend against any logging exception.
		}
	}

	/**
	 * Build user-friendly explanation from raw error strings.
	 *
	 * @param string $raw_error Raw error message.
	 * @return string
	 */
	protected function format_friendly_error( string $raw_error ): string {
		if ( empty( $raw_error ) ) {
			return __( 'Unknown mailer error occurred during dispatch.', 'advanced-email-log' );
		}

		if ( stripos( $raw_error, 'connect' ) !== false && stripos( $raw_error, 'failed' ) !== false ) {
			return sprintf( /* translators: %s: raw error */ __( 'SMTP Connection Failure: Could not establish connection to the remote mail server. (%s)', 'advanced-email-log' ), $raw_error );
		}

		if ( stripos( $raw_error, 'authenticate' ) !== false || stripos( $raw_error, 'password' ) !== false ) {
			return sprintf( /* translators: %s: raw error */ __( 'SMTP Authentication Error: Username or password rejected by the mail server. (%s)', 'advanced-email-log' ), $raw_error );
		}

		if ( stripos( $raw_error, 'invalid' ) !== false && stripos( $raw_error, 'address' ) !== false ) {
			return sprintf( /* translators: %s: raw error */ __( 'Recipient Address Error: An invalid or malformed email address was provided. (%s)', 'advanced-email-log' ), $raw_error );
		}

		if ( stripos( $raw_error, 'data not accepted' ) !== false ) {
			return sprintf( /* translators: %s: raw error */ __( 'Mail Server Rejected Content: Email message body or headers were rejected by the server. (%s)', 'advanced-email-log' ), $raw_error );
		}

		return $raw_error;
	}

	/**
	 * Handle PHP shutdown: mark any remaining pending records as unknown.
	 */
	public function handle_shutdown(): void {
		if ( empty( $this->pending_stack ) ) {
			return;
		}

		try {
			foreach ( $this->pending_stack as $entry ) {
				$this->repository->update(
					$entry['id'],
					array(
						'status'        => 'unknown',
						'error_message' => __( 'Send process terminated without transport confirmation (e.g. fatal PHP error, process timeout, or unobservable mailer).', 'advanced-email-log' ),
					)
				);
			}
			$this->pending_stack = array();
		} catch ( \Throwable $e ) {
			// Graceful cleanup.
		}
	}

	/**
	 * Pop matching log entry from pending stack in LIFO order.
	 *
	 * @param array $mail_data Mail data.
	 * @return array|null
	 */
	protected function pop_matching_pending( array $mail_data ): ?array {
		if ( empty( $this->pending_stack ) ) {
			return null;
		}

		$parsed    = $this->parse_mail_args( $mail_data );
		$signature = $this->generate_signature( $parsed );

		// Look backwards from the top of the stack (LIFO).
		for ( $i = count( $this->pending_stack ) - 1; $i >= 0; $i-- ) {
			if ( $this->pending_stack[ $i ]['signature'] === $signature ) {
				$matched = $this->pending_stack[ $i ];
				array_splice( $this->pending_stack, $i, 1 );
				return $matched;
			}
		}

		// Fallback: pop the top item if exact signature matching misses due to minor arg mutation.
		return array_pop( $this->pending_stack );
	}

	/**
	 * Generate payload signature for correlation.
	 *
	 * @param array $parsed Parsed mail data.
	 * @return string
	 */
	protected function generate_signature( array $parsed ): string {
		$to      = isset( $parsed['to_email'] ) ? $parsed['to_email'] : '';
		$subject = isset( $parsed['subject'] ) ? $parsed['subject'] : '';
		$prefix  = isset( $parsed['body'] ) ? substr( (string) $parsed['body'], 0, 100 ) : '';
		return md5( $to . '|' . $subject . '|' . $prefix );
	}

	/**
	 * Parse raw wp_mail arguments into normalized structure.
	 *
	 * @param array $args Raw mail arguments.
	 * @return array
	 */
	public function parse_mail_args( array $args ): array {
		$to          = isset( $args['to'] ) ? $args['to'] : '';
		$subject     = isset( $args['subject'] ) ? (string) $args['subject'] : '';
		$message     = isset( $args['message'] ) ? (string) $args['message'] : '';
		$headers     = isset( $args['headers'] ) ? $args['headers'] : '';
		$attachments = isset( $args['attachments'] ) ? $args['attachments'] : array();

		// Normalize recipients.
		$to_email = $this->normalize_recipients( $to );

		// Parse headers.
		$parsed_headers = $this->parse_headers( $headers );

		// Determine From.
		$from_email = ! empty( $parsed_headers['from'] ) ? $parsed_headers['from'] : $this->get_default_from();

		// Determine Content-Type.
		$content_type = ! empty( $parsed_headers['content_type'] ) ? $parsed_headers['content_type'] : 'text/plain';

		// Parse attachment metadata (with file path if safely readable).
		$attachment_meta = $this->parse_attachments( $attachments );

		// Client IP safely.
		$ip = $this->get_client_ip();

		return array(
			'created_at'   => gmdate( 'Y-m-d H:i:s' ),
			'to_email'     => $to_email,
			'from_email'   => $from_email,
			'subject'      => $subject,
			'body'         => $message,
			'content_type' => $content_type,
			'cc'           => ! empty( $parsed_headers['cc'] ) ? $parsed_headers['cc'] : null,
			'bcc'          => ! empty( $parsed_headers['bcc'] ) ? $parsed_headers['bcc'] : null,
			'reply_to'     => ! empty( $parsed_headers['reply_to'] ) ? $parsed_headers['reply_to'] : null,
			'attachments'  => $attachment_meta,
			'headers'      => $parsed_headers['raw_lines'],
			'ip_address'   => $ip,
		);
	}

	/**
	 * Normalize recipient field (array or string) into comma-separated string.
	 *
	 * @param mixed $to Recipient(s).
	 * @return string
	 */
	protected function normalize_recipients( $to ): string {
		if ( is_array( $to ) ) {
			$to = implode( ', ', array_map( 'trim', array_filter( $to ) ) );
		}
		return trim( (string) $to );
	}

	/**
	 * Parse headers string or array into associative fields.
	 *
	 * @param mixed $headers Headers input.
	 * @return array
	 */
	public function parse_headers( $headers ): array {
		$result = array(
			'from'         => '',
			'cc'           => '',
			'bcc'          => '',
			'reply_to'     => '',
			'content_type' => 'text/plain',
			'raw_lines'    => array(),
		);

		$lines = array();
		if ( is_array( $headers ) ) {
			$lines = $headers;
		} elseif ( is_string( $headers ) && ! empty( $headers ) ) {
			$lines = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		}

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( empty( $line ) ) {
				continue;
			}

			$result['raw_lines'][] = $line;

			if ( false === strpos( $line, ':' ) ) {
				continue;
			}

			list( $name, $value ) = explode( ':', $line, 2 );
			$name  = strtolower( trim( $name ) );
			$value = trim( $value );

			switch ( $name ) {
				case 'from':
					$result['from'] = $value;
					break;
				case 'cc':
					$result['cc'] = ! empty( $result['cc'] ) ? $result['cc'] . ', ' . $value : $value;
					break;
				case 'bcc':
					$result['bcc'] = ! empty( $result['bcc'] ) ? $result['bcc'] . ', ' . $value : $value;
					break;
				case 'reply-to':
					$result['reply_to'] = ! empty( $result['reply_to'] ) ? $result['reply_to'] . ', ' . $value : $value;
					break;
				case 'content-type':
					if ( stripos( $value, 'text/html' ) !== false ) {
						$result['content_type'] = 'text/html';
					} else {
						$result['content_type'] = 'text/plain';
					}
					break;
			}
		}

		return $result;
	}

	/**
	 * Get default WordPress from address.
	 *
	 * @return string
	 */
	protected function get_default_from(): string {
		$admin_email = get_option( 'admin_email' );
		$sitename    = wp_parse_url( network_home_url(), PHP_URL_HOST );
		if ( empty( $sitename ) ) {
			$sitename = 'localhost';
		}
		return 'wordpress@' . preg_replace( '#^www\.#', '', strtolower( $sitename ) );
	}

	/**
	 * Extract safe attachment metadata.
	 *
	 * @param mixed $attachments Attachments.
	 * @return array
	 */
	protected function parse_attachments( $attachments ): array {
		$meta = array();
		if ( empty( $attachments ) ) {
			return $meta;
		}

		if ( is_string( $attachments ) ) {
			$attachments = array( $attachments );
		}

		if ( ! is_array( $attachments ) ) {
			return $meta;
		}

		foreach ( $attachments as $file_path ) {
			if ( ! is_string( $file_path ) || empty( $file_path ) ) {
				continue;
			}

			$filename = sanitize_file_name( basename( $file_path ) );
			$size     = file_exists( $file_path ) ? filesize( $file_path ) : 0;
			$type     = function_exists( 'wp_check_filetype' ) ? wp_check_filetype( $filename ) : array( 'type' => '' );

			$meta[] = array(
				'filename' => $filename,
				'size'     => $size,
				'mime'     => ! empty( $type['type'] ) ? $type['type'] : 'application/octet-stream',
				'path'     => file_exists( $file_path ) ? $file_path : '',
			);
		}

		return $meta;
	}

	/**
	 * Retrieve client IP safely.
	 *
	 * @return string
	 */
	protected function get_client_ip(): string {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		return $ip;
	}
}
