<?php
/**
 * CSV Importer for email logs.
 *
 * Supports direct file upload and import from WordPress Media Library,
 * with duplicate detection and progress tracking.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Importer
 */
class Importer {

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
	 * Process CSV import from uploaded file or media attachment ID.
	 *
	 * @param string $file_path       Local server path to CSV file.
	 * @param mixed  $skip_duplicates Whether to skip duplicate entries (bool or 'skip'/'all').
	 * @return array
	 */
	public function import_csv_file( string $file_path, $skip_duplicates = true ): array {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'CSV file could not be read or does not exist.', 'advanced-email-log' ),
			);
		}

		$do_skip = is_bool( $skip_duplicates ) ? $skip_duplicates : ( 'all' !== $skip_duplicates && '0' !== $skip_duplicates && false !== $skip_duplicates );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			return array(
				'success' => false,
				'message' => __( 'Unable to open CSV file for reading.', 'advanced-email-log' ),
			);
		}

		// Check for and strip UTF-8 BOM if present.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fread, WordPress.WP.AlternativeFunctions.file_system_operations_fread
		$bom = fread( $handle, 3 );
		if ( "\xEF\xBB\xBF" !== $bom ) {
			rewind( $handle );
		}

		$header = fgetcsv( $handle );
		if ( false === $header || empty( $header ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );
			return array(
				'success' => false,
				'message' => __( 'The uploaded file appears to be empty or has invalid CSV headers.', 'advanced-email-log' ),
			);
		}

		// Normalize headers (lowercase, trimmed).
		$header_map = array();
		foreach ( $header as $col_idx => $col_name ) {
			$clean_name = strtolower( trim( (string) $col_name ) );
			// Strip formula defense prefix if any.
			$clean_name = ltrim( $clean_name, "'" );

			if ( strpos( $clean_name, 'sent at' ) !== false || strpos( $clean_name, 'date' ) !== false || strpos( $clean_name, 'created' ) !== false ) {
				$header_map['created_at'] = $col_idx;
			} elseif ( strpos( $clean_name, 'status' ) !== false ) {
				$header_map['status'] = $col_idx;
			} elseif ( $clean_name === 'to' || strpos( $clean_name, 'recipient' ) !== false ) {
				$header_map['to_email'] = $col_idx;
			} elseif ( $clean_name === 'from' || strpos( $clean_name, 'sender' ) !== false ) {
				$header_map['from_email'] = $col_idx;
			} elseif ( strpos( $clean_name, 'subject' ) !== false ) {
				$header_map['subject'] = $col_idx;
			} elseif ( $clean_name === 'cc' ) {
				$header_map['cc'] = $col_idx;
			} elseif ( $clean_name === 'bcc' ) {
				$header_map['bcc'] = $col_idx;
			} elseif ( strpos( $clean_name, 'reply' ) !== false ) {
				$header_map['reply_to'] = $col_idx;
			} elseif ( strpos( $clean_name, 'content' ) !== false ) {
				$header_map['content_type'] = $col_idx;
			} elseif ( strpos( $clean_name, 'attachment' ) !== false ) {
				$header_map['attachments'] = $col_idx;
			} elseif ( strpos( $clean_name, 'ip' ) !== false ) {
				$header_map['ip_address'] = $col_idx;
			} elseif ( strpos( $clean_name, 'error' ) !== false ) {
				$header_map['error_message'] = $col_idx;
			} elseif ( strpos( $clean_name, 'body' ) !== false || strpos( $clean_name, 'message' ) !== false ) {
				$header_map['body'] = $col_idx;
			}
		}

		if ( ! isset( $header_map['to_email'] ) && ! isset( $header_map['subject'] ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );
			return array(
				'success' => false,
				'message' => __( 'CSV file is missing essential recipient ("To") or "Subject" columns.', 'advanced-email-log' ),
			);
		}

		$imported = 0;
		$skipped  = 0;
		$row_num  = 1;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row_num++;

			// Skip empty rows.
			if ( empty( array_filter( $row ) ) ) {
				continue;
			}

			$get_val = function ( $key, $default = '' ) use ( $row, $header_map ) {
				if ( isset( $header_map[ $key ] ) && isset( $row[ $header_map[ $key ] ] ) ) {
					$val = trim( (string) $row[ $header_map[ $key ] ] );
					// Strip single quote from CSV formula defense.
					if ( 0 === strpos( $val, "'" ) && strlen( $val ) > 1 ) {
						$next_char = substr( $val, 1, 1 );
						if ( in_array( $next_char, array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
							$val = substr( $val, 1 );
						}
					}
					return $val;
				}
				return $default;
			};

			$created_at   = $get_val( 'created_at', gmdate( 'Y-m-d H:i:s' ) );
			$raw_status   = strtolower( $get_val( 'status', 'accepted' ) );
			$status       = in_array( $raw_status, array( 'accepted', 'failed', 'pending', 'unknown', 'trash' ), true ) ? $raw_status : 'accepted';
			$to_email     = $get_val( 'to_email', '' );
			$from_email   = $get_val( 'from_email', '' );
			$subject      = $get_val( 'subject', '' );
			$body         = $get_val( 'body', '' );
			$content_type = $get_val( 'content_type', 'text/html' );
			$cc           = $get_val( 'cc', null );
			$bcc          = $get_val( 'bcc', null );
			$reply_to     = $get_val( 'reply_to', null );
			$ip_address   = $get_val( 'ip_address', '' );
			$error_msg    = $get_val( 'error_message', null );

			// Check attachments field.
			$raw_att      = $get_val( 'attachments', '' );
			$attachments  = null;
			if ( ! empty( $raw_att ) ) {
				// If JSON array.
				$json_test = json_decode( $raw_att, true );
				if ( is_array( $json_test ) ) {
					$attachments = $json_test;
				} else {
					// Comma/semicolon separated filenames.
					$filenames = preg_split( '/[;,]/', $raw_att );
					$att_meta  = array();
					foreach ( $filenames as $fn ) {
						$fn = trim( $fn );
						if ( ! empty( $fn ) ) {
							$att_meta[] = array(
								'filename' => sanitize_file_name( $fn ),
								'size'     => 0,
								'mime'     => 'application/octet-stream',
							);
						}
					}
					$attachments = $att_meta;
				}
			}

			// Validate created_at datetime.
			$timestamp = strtotime( $created_at );
			if ( false === $timestamp ) {
				$created_at = gmdate( 'Y-m-d H:i:s' );
			} else {
				$created_at = gmdate( 'Y-m-d H:i:s', $timestamp );
			}

			// Duplicate check.
			if ( $do_skip && $this->repository->is_duplicate( $created_at, $to_email, $subject ) ) {
				$skipped++;
				continue;
			}

			$data = array(
				'created_at'    => $created_at,
				'status'        => $status,
				'to_email'      => $to_email,
				'from_email'    => $from_email,
				'subject'       => $subject,
				'body'          => $body,
				'content_type'  => $content_type,
				'cc'            => $cc,
				'bcc'           => $bcc,
				'reply_to'      => $reply_to,
				'attachments'   => $attachments,
				'ip_address'    => $ip_address,
				'error_message' => $error_msg,
			);

			$insert_id = $this->repository->insert( $data );
			if ( $insert_id > 0 ) {
				$imported++;
			} else {
				$skipped++;
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		return array(
			'success'  => true,
			'imported' => $imported,
			'skipped'  => $skipped,
			'total'    => $imported + $skipped,
			'message'  => sprintf(
				/* translators: 1: Number imported, 2: Number skipped */
				__( 'Import complete! %1$d email log(s) imported successfully (%2$d duplicate(s) or invalid row(s) skipped).', 'advanced-email-log' ),
				$imported,
				$skipped
			),
		);
	}
}
