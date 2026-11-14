<?php
/**
 * Streaming CSV exporter for email logs.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Exporter
 */
class Exporter {

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
	 * Process CSV download request.
	 */
	public function handle_export_request(): void {
		// Nonce & permission validation.
		if ( ! isset( $_REQUEST['ael_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['ael_export_nonce'] ) ), 'ael_export_logs' ) ) {
			wp_die( esc_html__( 'Security check failed. Invalid export nonce.', 'advanced-email-log' ), 403 );
		}

		if ( ! Capabilities::can_view_logs() ) {
			wp_die( esc_html__( 'You do not have permission to export email logs.', 'advanced-email-log' ), 403 );
		}

		$args = array();

		// Specific selected IDs export.
		if ( ! empty( $_REQUEST['log_ids'] ) ) {
			$raw_ids = wp_unslash( $_REQUEST['log_ids'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( is_string( $raw_ids ) ) {
				$args['include_ids'] = array_map( 'absint', explode( ',', $raw_ids ) );
			} elseif ( is_array( $raw_ids ) ) {
				$args['include_ids'] = array_map( 'absint', $raw_ids );
			}
		} else {
			// Filtered results export.
			if ( ! empty( $_REQUEST['status'] ) ) {
				$args['status'] = sanitize_key( wp_unslash( $_REQUEST['status'] ) );
			}
			if ( ! empty( $_REQUEST['s'] ) ) {
				$args['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
			}
			if ( ! empty( $_REQUEST['date_range'] ) ) {
				$args['date_range'] = sanitize_key( wp_unslash( $_REQUEST['date_range'] ) );
			}
			if ( ! empty( $_REQUEST['date_from'] ) ) {
				$args['date_from'] = sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) );
			}
			if ( ! empty( $_REQUEST['date_to'] ) ) {
				$args['date_to'] = sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) );
			}
		}

		$this->stream_csv( $args );
		exit;
	}

	/**
	 * Stream CSV output directly to browser.
	 *
	 * @param array $args Query filter arguments.
	 */
	protected function stream_csv( array $args ): void {
		$filename = 'advanced-email-logs-' . gmdate( 'Y-m-d-His' ) . '.csv';

		// Clean output buffer to prevent corrupted CSV.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			return;
		}

		// Write UTF-8 Byte Order Mark (BOM) for seamless Microsoft Excel compatibility.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputs
		fputs( $output, "\xEF\xBB\xBF" );

		// Header columns.
		$headers = array(
			'Log ID',
			'Sent Date (Local)',
			'Sent Date (UTC)',
			'Status',
			'To',
			'From',
			'Subject',
			'CC',
			'BCC',
			'Reply-To',
			'Content Type',
			'Attachments',
			'IP Address',
			'Error Diagnostic',
			'Email Body',
		);

		fputcsv( $output, $headers );

		$limit        = 500;
		$offset       = 0;
		$local_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		do {
			$chunk = $this->repository->get_logs_for_export( $args, $limit, $offset );
			if ( empty( $chunk ) ) {
				break;
			}

			foreach ( $chunk as $row ) {
				// Parse attachment filenames for clean CSV display.
				$attachments_summary = '';
				if ( ! empty( $row['attachments'] ) ) {
					$att_list = json_decode( $row['attachments'], true );
					if ( is_array( $att_list ) ) {
						$names = array();
						foreach ( $att_list as $att ) {
							if ( ! empty( $att['filename'] ) ) {
								$names[] = $att['filename'];
							}
						}
						$attachments_summary = implode( '; ', $names );
					}
				}

				$utc_timestamp = strtotime( $row['created_at'] . ' UTC' );
				$local_time    = wp_date( $local_format, $utc_timestamp );

				$csv_row = array(
					$this->escape_formula( (string) $row['id'] ),
					$this->escape_formula( (string) $local_time ),
					$this->escape_formula( (string) $row['created_at'] ),
					$this->escape_formula( ucfirst( (string) $row['status'] ) ),
					$this->escape_formula( (string) $row['to_email'] ),
					$this->escape_formula( (string) $row['from_email'] ),
					$this->escape_formula( (string) $row['subject'] ),
					$this->escape_formula( (string) ( $row['cc'] ?? '' ) ),
					$this->escape_formula( (string) ( $row['bcc'] ?? '' ) ),
					$this->escape_formula( (string) ( $row['reply_to'] ?? '' ) ),
					$this->escape_formula( (string) $row['content_type'] ),
					$this->escape_formula( $attachments_summary ),
					$this->escape_formula( (string) $row['ip_address'] ),
					$this->escape_formula( (string) ( $row['error_message'] ?? '' ) ),
					$this->escape_formula( (string) $row['body'] ),
				);

				fputcsv( $output, $csv_row );
			}

			$offset += $limit;
			if ( function_exists( 'flush' ) ) {
				flush();
			}
		} while ( count( $chunk ) === $limit );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
	}

	/**
	 * Neutralize CSV Formula Injection (DDE attack vectors).
	 * Prepends single quote if cell starts with dangerous characters.
	 *
	 * @param string $value Cell string.
	 * @return string
	 */
	public function escape_formula( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$first_char = substr( $value, 0, 1 );
		if ( in_array( $first_char, array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}

		return $value;
	}
}
