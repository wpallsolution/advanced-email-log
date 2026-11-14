<?php
/**
 * Data repository for email logs.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Repository pattern class encapsulating all custom table database queries with strict input sanitization and parameter preparation.

/**
 * Class Log_Repository
 */
class Log_Repository {

	/**
	 * Insert a new email log.
	 *
	 * @param array $data Log entry data.
	 * @return int Inserted ID or 0 on failure.
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );

		$defaults = array(
			'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			'status'        => 'pending',
			'to_email'      => '',
			'from_email'    => '',
			'subject'       => '',
			'body'          => '',
			'content_type'  => 'text/plain',
			'cc'            => null,
			'bcc'           => null,
			'reply_to'      => null,
			'attachments'   => null,
			'headers'       => null,
			'ip_address'    => '',
			'error_message' => null,
			'extra_data'    => null,
		);

		$row = wp_parse_args( $data, $defaults );

		// Format JSON fields if arrays are provided.
		if ( is_array( $row['attachments'] ) ) {
			$row['attachments'] = wp_json_encode( $row['attachments'] );
		}
		if ( is_array( $row['headers'] ) ) {
			$row['headers'] = wp_json_encode( $row['headers'] );
		}
		if ( is_array( $row['extra_data'] ) ) {
			$row['extra_data'] = wp_json_encode( $row['extra_data'] );
		}

		$result = $wpdb->insert(
			$table_name,
			array(
				'created_at'    => $row['created_at'],
				'status'        => sanitize_key( $row['status'] ),
				'to_email'      => sanitize_textarea_field( $row['to_email'] ),
				'from_email'    => sanitize_text_field( $row['from_email'] ),
				'subject'       => sanitize_text_field( $row['subject'] ),
				'body'          => (string) $row['body'], // Raw email body preserved for authentic preview.
				'content_type'  => sanitize_text_field( $row['content_type'] ),
				'cc'            => ! empty( $row['cc'] ) ? sanitize_textarea_field( $row['cc'] ) : null,
				'bcc'           => ! empty( $row['bcc'] ) ? sanitize_textarea_field( $row['bcc'] ) : null,
				'reply_to'      => ! empty( $row['reply_to'] ) ? sanitize_text_field( $row['reply_to'] ) : null,
				'attachments'   => $row['attachments'],
				'headers'       => $row['headers'],
				'ip_address'    => sanitize_text_field( $row['ip_address'] ),
				'error_message' => ! empty( $row['error_message'] ) ? sanitize_textarea_field( $row['error_message'] ) : null,
				'extra_data'    => $row['extra_data'],
			),
			array(
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
			)
		);

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Check if a duplicate email log exists.
	 *
	 * @param string $created_at Timestamp.
	 * @param string $to_email   Recipient.
	 * @param string $subject    Subject.
	 * @return bool
	 */
	public function is_duplicate( string $created_at, string $to_email, string $subject ): bool {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE created_at = %s AND to_email = %s AND subject = %s LIMIT 1",
				$created_at,
				$to_email,
				$subject
			)
		);
	}

	/**
	 * Update an existing email log by ID.
	 *
	 * @param int   $id   Log ID.
	 * @param array $data Data to update.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		if ( $id <= 0 || empty( $data ) ) {
			return false;
		}

		$table_name = esc_sql( Database::get_table_name() );
		$update     = array();
		$formats    = array();

		if ( isset( $data['status'] ) ) {
			$update['status'] = sanitize_key( $data['status'] );
			$formats[]        = '%s';
		}
		if ( isset( $data['error_message'] ) ) {
			$update['error_message'] = sanitize_textarea_field( $data['error_message'] );
			$formats[]               = '%s';
		}
		if ( isset( $data['extra_data'] ) ) {
			$update['extra_data'] = is_array( $data['extra_data'] ) ? wp_json_encode( $data['extra_data'] ) : $data['extra_data'];
			$formats[]            = '%s';
		}

		if ( empty( $update ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table_name,
			$update,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get a single email log by ID.
	 *
	 * @param int $id Log ID.
	 * @return array|null
	 */
	public function get_by_id( int $id ): ?array {
		global $wpdb;

		if ( $id <= 0 ) {
			return null;
		}

		$table_name = esc_sql( Database::get_table_name() );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Move logs to trash.
	 *
	 * @param int|array $ids Single ID or array of IDs.
	 * @return int Number of updated rows.
	 */
	public function move_to_trash( $ids ): int {
		global $wpdb;

		$id_list = array_filter( array_map( 'absint', (array) $ids ) );
		if ( empty( $id_list ) ) {
			return 0;
		}

		$table_name   = Database::get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $id_list ), '%d' ) );

		// Record previous status in extra_data or update directly to trash.
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"UPDATE {$table_name} SET status = 'trash' WHERE id IN ({$placeholders})",
			$id_list
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return (int) $wpdb->query( $query );
	}

	/**
	 * Restore logs from trash.
	 *
	 * @param int|array $ids Single ID or array of IDs.
	 * @return int Number of restored rows.
	 */
	public function restore_from_trash( $ids ): int {
		global $wpdb;

		$id_list = array_filter( array_map( 'absint', (array) $ids ) );
		if ( empty( $id_list ) ) {
			return 0;
		}

		$table_name   = Database::get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $id_list ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"UPDATE {$table_name} SET status = 'accepted' WHERE id IN ({$placeholders}) AND status = 'trash'",
			$id_list
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return (int) $wpdb->query( $query );
	}

	/**
	 * Delete logs permanently.
	 *
	 * @param int|array $ids Single ID or array of IDs.
	 * @return int Number of deleted rows.
	 */
	public function delete_permanently( $ids ): int {
		global $wpdb;

		$id_list = array_filter( array_map( 'absint', (array) $ids ) );
		if ( empty( $id_list ) ) {
			return 0;
		}

		$table_name   = Database::get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $id_list ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"DELETE FROM {$table_name} WHERE id IN ({$placeholders})",
			$id_list
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return (int) $wpdb->query( $query );
	}

	/**
	 * Empty all items currently in trash.
	 *
	 * @return int Number of deleted rows.
	 */
	public function empty_trash(): int {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query( "DELETE FROM {$table_name} WHERE status = 'trash'" );
	}

	/**
	 * Clear all logs permanently.
	 *
	 * @return int Number of deleted rows.
	 */
	public function clear_all_logs(): int {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query( "TRUNCATE TABLE {$table_name}" );
	}

	/**
	 * Build SQL WHERE conditions based on arguments.
	 *
	 * @param array $args Query arguments.
	 * @return array Array containing SQL string and parameters.
	 */
	private function build_where( array $args ): array {
		global $wpdb;

		$where_clauses = array( '1=1' );
		$params        = array();

		// Specific IDs filter (e.g. for bulk export or actions).
		if ( ! empty( $args['include_ids'] ) && is_array( $args['include_ids'] ) ) {
			$sanitized_ids = array_filter( array_map( 'absint', $args['include_ids'] ) );
			if ( ! empty( $sanitized_ids ) ) {
				$placeholders    = implode( ', ', array_fill( 0, count( $sanitized_ids ), '%d' ) );
				$where_clauses[] = "id IN ({$placeholders})";
				foreach ( $sanitized_ids as $id ) {
					$params[] = $id;
				}
			}
		}

		// Status filter handling.
		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$status_val = sanitize_key( $args['status'] );
			if ( in_array( $status_val, array( 'accepted', 'failed', 'pending', 'unknown', 'trash' ), true ) ) {
				$where_clauses[] = 'status = %s';
				$params[]        = $status_val;
			}
		} else {
			// By default, exclude trashed items from general view.
			$where_clauses[] = "status != 'trash'";
		}

		// Search term across recipient and subject.
		if ( ! empty( $args['search'] ) ) {
			$search_term     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where_clauses[] = '(to_email LIKE %s OR subject LIKE %s)';
			$params[]        = $search_term;
			$params[]        = $search_term;
		}

		// Date filter handling.
		if ( ! empty( $args['date_range'] ) ) {
			$range = sanitize_key( $args['date_range'] );
			$now   = current_time( 'timestamp', true ); // UTC

			switch ( $range ) {
				case 'today':
					$start = gmdate( 'Y-m-d 00:00:00', $now );
					$end   = gmdate( 'Y-m-d 23:59:59', $now );
					break;
				case 'yesterday':
					$yesterday = strtotime( '-1 day', $now );
					$start     = gmdate( 'Y-m-d 00:00:00', $yesterday );
					$end       = gmdate( 'Y-m-d 23:59:59', $yesterday );
					break;
				case 'last_7_days':
					$start = gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days', $now ) );
					$end   = gmdate( 'Y-m-d 23:59:59', $now );
					break;
				case 'last_30_days':
					$start = gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days', $now ) );
					$end   = gmdate( 'Y-m-d 23:59:59', $now );
					break;
				case 'this_month':
					$start = gmdate( 'Y-m-01 00:00:00', $now );
					$end   = gmdate( 'Y-m-t 23:59:59', $now );
					break;
				case 'last_month':
					$first_last_month = strtotime( 'first day of last month', $now );
					$last_last_month  = strtotime( 'last day of last month', $now );
					$start            = gmdate( 'Y-m-d 00:00:00', $first_last_month );
					$end              = gmdate( 'Y-m-d 23:59:59', $last_last_month );
					break;
				default:
					$start = '';
					$end   = '';
					break;
			}

			if ( $start && $end ) {
				$where_clauses[] = 'created_at >= %s AND created_at <= %s';
				$params[]        = $start;
				$params[]        = $end;
			}
		}

		if ( ! empty( $args['date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$params[]        = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$params[]        = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		// Specific IP address filter.
		if ( ! empty( $args['ip_address'] ) ) {
			$where_clauses[] = 'ip_address = %s';
			$params[]        = sanitize_text_field( $args['ip_address'] );
		}

		$sql_where = implode( ' AND ', $where_clauses );
		return array( $sql_where, $params );
	}

	/**
	 * Retrieve a list of email logs.
	 *
	 * @param array $args Query parameters.
	 * @return array
	 */
	public function get_logs( array $args = array() ): array {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );

		$per_page = isset( $args['per_page'] ) ? max( 1, min( 500, (int) $args['per_page'] ) ) : 20;
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		// Allowed orderby columns.
		$allowed_orderby = array(
			'id'         => 'id',
			'created_at' => 'created_at',
			'status'     => 'status',
			'to_email'   => 'to_email',
			'subject'    => 'subject',
		);

		$orderby = isset( $args['orderby'] ) && isset( $allowed_orderby[ $args['orderby'] ] )
			? $allowed_orderby[ $args['orderby'] ]
			: 'created_at';

		$order = isset( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		list( $sql_where, $params ) = $this->build_where( $args );

		$query = "SELECT id, created_at, status, to_email, from_email, subject, body, content_type, cc, bcc, reply_to, attachments, ip_address, error_message 
			FROM {$table_name} 
			WHERE {$sql_where} 
			ORDER BY {$orderby} {$order} 
			LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare( $query, $params );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results  = $wpdb->get_results( $prepared, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get total count matching query arguments.
	 *
	 * @param array $args Query parameters.
	 * @return int
	 */
	public function get_total_count( array $args = array() ): int {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );
		list( $sql_where, $params ) = $this->build_where( $args );

		$query = "SELECT COUNT(id) FROM {$table_name} WHERE {$sql_where}";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared = $wpdb->prepare( $query, $params );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			return (int) $wpdb->get_var( $prepared );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Get counts grouped by status (excluding trash from all count).
	 *
	 * @return array
	 */
	public function get_status_counts(): array {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );
		$counts     = array(
			'all'      => 0,
			'accepted' => 0,
			'failed'   => 0,
			'pending'  => 0,
			'unknown'  => 0,
			'trash'    => 0,
		);

		$results = $wpdb->get_results(
			"SELECT status, COUNT(id) as total FROM {$table_name} GROUP BY status",
			ARRAY_A
		);

		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$status = $row['status'];
				$count  = (int) $row['total'];
				if ( isset( $counts[ $status ] ) ) {
					$counts[ $status ] = $count;
				}
				// Trash is separate, not counted in 'all'.
				if ( 'trash' !== $status ) {
					$counts['all'] += $count;
				}
			}
		}

		return $counts;
	}

	/**
	 * Get latest inserted ID.
	 *
	 * @return int
	 */
	public function get_latest_id(): int {
		global $wpdb;
		$table_name = esc_sql( Database::get_table_name() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table_name}" );
	}

	/**
	 * Count new logs created since a specific ID.
	 *
	 * @param int $since_id ID threshold.
	 * @return int
	 */
	public function count_new_logs( int $since_id ): int {
		global $wpdb;
		$table_name = esc_sql( Database::get_table_name() );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_name} WHERE id > %d AND status != 'trash'", $since_id ) );
		return false !== $count ? (int) $count : 0;
	}

	/**
	 * Get summary stats for today and recent entries.
	 *
	 * @return array
	 */
	public function get_dashboard_stats(): array {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );
		$today_start = gmdate( 'Y-m-d 00:00:00' );
		$today_end   = gmdate( 'Y-m-d 23:59:59' );

		$today_total    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_name} WHERE status != 'trash' AND created_at >= %s AND created_at <= %s", $today_start, $today_end ) );
		$today_accepted = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_name} WHERE status = 'accepted' AND created_at >= %s AND created_at <= %s", $today_start, $today_end ) );
		$today_failed   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_name} WHERE status = 'failed' AND created_at >= %s AND created_at <= %s", $today_start, $today_end ) );

		$recent_logs = $wpdb->get_results( "SELECT id, created_at, status, to_email, subject FROM {$table_name} WHERE status != 'trash' ORDER BY id DESC LIMIT 5", ARRAY_A );

		return array(
			'today_total'    => $today_total,
			'today_accepted' => $today_accepted,
			'today_failed'   => $today_failed,
			'recent_logs'    => is_array( $recent_logs ) ? $recent_logs : array(),
		);
	}

	/**
	 * Retrieve logs for CSV export (streaming chunk).
	 *
	 * @param array $args Query parameters.
	 * @param int   $limit Maximum rows to export in single chunk.
	 * @param int   $offset Offset for pagination.
	 * @return array
	 */
	public function get_logs_for_export( array $args = array(), int $limit = 500, int $offset = 0 ): array {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );
		list( $sql_where, $params ) = $this->build_where( $args );

		$query = "SELECT id, created_at, status, to_email, from_email, subject, body, content_type, cc, bcc, reply_to, attachments, ip_address, error_message 
			FROM {$table_name} 
			WHERE {$sql_where} 
			ORDER BY id DESC 
			LIMIT %d OFFSET %d";

		$params[] = $limit;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare( $query, $params );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results  = $wpdb->get_results( $prepared, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Export personal data for WordPress Core Privacy tools.
	 *
	 * @param string $email_address Recipient email address.
	 * @param int    $page Page number.
	 * @return array
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );
		$limit      = 50;
		$offset     = ( $page - 1 ) * $limit;

		$search_email = '%' . $wpdb->esc_like( $email_address ) . '%';
		$query        = $wpdb->prepare(
			"SELECT id, created_at, status, to_email, subject, ip_address FROM {$table_name} WHERE to_email LIKE %s ORDER BY id ASC LIMIT %d OFFSET %d",
			$search_email,
			$limit,
			$offset
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows       = $wpdb->get_results( $query, ARRAY_A );
		$data_to_export = array();

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$item_data = array(
					array(
						'name'  => __( 'Log ID', 'advanced-email-log' ),
						'value' => $row['id'],
					),
					array(
						'name'  => __( 'Sent Date (UTC)', 'advanced-email-log' ),
						'value' => $row['created_at'],
					),
					array(
						'name'  => __( 'Status', 'advanced-email-log' ),
						'value' => ucfirst( $row['status'] ),
					),
					array(
						'name'  => __( 'Recipient', 'advanced-email-log' ),
						'value' => $row['to_email'],
					),
					array(
						'name'  => __( 'Subject', 'advanced-email-log' ),
						'value' => $row['subject'],
					),
					array(
						'name'  => __( 'Triggering IP', 'advanced-email-log' ),
						'value' => $row['ip_address'],
					),
				);

				$data_to_export[] = array(
					'group_id'    => 'advanced_email_logs',
					'group_label' => __( 'Advanced Email Logs', 'advanced-email-log' ),
					'item_id'     => 'ael-log-' . $row['id'],
					'data'        => $item_data,
				);
			}
		}

		$done = count( $rows ) < $limit;

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Erase personal data for WordPress Core Privacy tools.
	 * Anonymizes recipient email address, subject, and triggering IP.
	 *
	 * @param string $email_address Recipient email address.
	 * @param int    $page Page number.
	 * @return array
	 */
	public function erase_personal_data( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$table_name   = Database::get_table_name();
		$limit        = 50;
		$offset       = ( $page - 1 ) * $limit;
		$search_email = '%' . $wpdb->esc_like( $email_address ) . '%';

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE to_email LIKE %s ORDER BY id ASC LIMIT %d OFFSET %d",
				$search_email,
				$limit,
				$offset
			)
		);

		$items_removed = 0;
		if ( ! empty( $ids ) ) {
			foreach ( $ids as $id ) {
				$wpdb->update(
					$table_name,
					array(
						'to_email'   => '[erased@personal-data.local]',
						'body'       => '[Personal data erased by GDPR request]',
						'ip_address' => '0.0.0.0',
					),
					array( 'id' => (int) $id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
				$items_removed++;
			}
		}

		$done = count( $ids ) < $limit;

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => $done,
		);
	}

	/**
	 * Get summary counts and percentages for analytics dashboard.
	 *
	 * @param array $args Query filters.
	 * @return array
	 */
	public function get_analytics_summary( array $args = array() ): array {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );
		list( $sql_where, $params ) = $this->build_where( $args );

		$query = "SELECT 
				COUNT(id) as total,
				SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
				SUM(CASE WHEN status = 'unknown' THEN 1 ELSE 0 END) as unknown_count
			FROM {$table_name} 
			WHERE {$sql_where}";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared = $wpdb->prepare( $query, $params );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$row = $wpdb->get_row( $prepared, ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$row = $wpdb->get_row( $query, ARRAY_A );
		}

		$total          = isset( $row['total'] ) ? (int) $row['total'] : 0;
		$accepted_count = isset( $row['accepted_count'] ) ? (int) $row['accepted_count'] : 0;
		$failed_count   = isset( $row['failed_count'] ) ? (int) $row['failed_count'] : 0;
		$pending_count  = isset( $row['pending_count'] ) ? (int) $row['pending_count'] : 0;
		$unknown_count  = isset( $row['unknown_count'] ) ? (int) $row['unknown_count'] : 0;

		return array(
			'total'          => $total,
			'accepted_count' => $accepted_count,
			'accepted_pct'   => $total > 0 ? round( ( $accepted_count / $total ) * 100, 1 ) : 0.0,
			'failed_count'   => $failed_count,
			'failed_pct'     => $total > 0 ? round( ( $failed_count / $total ) * 100, 1 ) : 0.0,
			'pending_count'  => $pending_count,
			'pending_pct'    => $total > 0 ? round( ( $pending_count / $total ) * 100, 1 ) : 0.0,
			'unknown_count'  => $unknown_count,
			'unknown_pct'    => $total > 0 ? round( ( $unknown_count / $total ) * 100, 1 ) : 0.0,
		);
	}

	/**
	 * Get timeline data points for analytics activity chart.
	 *
	 * @param array $args Query filters.
	 * @return array
	 */
	public function get_analytics_timeline( array $args = array() ): array {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );
		list( $sql_where, $params ) = $this->build_where( $args );

		$range     = isset( $args['date_range'] ) ? sanitize_key( $args['date_range'] ) : '';
		$is_hourly = in_array( $range, array( 'today', 'yesterday' ), true );
		$date_fmt  = $is_hourly ? '%H:00' : '%Y-%m-%d';
		// When the query goes through $wpdb->prepare(), literal % signs must be escaped as %%.
		$fmt_sql   = ! empty( $params ) ? str_replace( '%', '%%', $date_fmt ) : $date_fmt;

		$query = "SELECT 
				DATE_FORMAT(created_at, '{$fmt_sql}') as time_point,
				COUNT(id) as total,
				SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
			FROM {$table_name}
			WHERE {$sql_where}
			GROUP BY time_point
			ORDER BY time_point ASC";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared = $wpdb->prepare( $query, $params );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$rows = $wpdb->get_results( $prepared, ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$rows = $wpdb->get_results( $query, ARRAY_A );
		}

		$labels   = array();
		$accepted = array();
		$failed   = array();
		$totals   = array();

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $r ) {
				$tp = $r['time_point'];
				if ( ! $is_hourly ) {
					$ts       = strtotime( $tp );
					$labels[] = $ts ? gmdate( 'M j', $ts ) : $tp;
				} else {
					$labels[] = $tp;
				}
				$accepted[] = (int) $r['accepted_count'];
				$failed[]   = (int) $r['failed_count'];
				$totals[]   = (int) $r['total'];
			}
		}

		return array(
			'labels'   => $labels,
			'accepted' => $accepted,
			'failed'   => $failed,
			'totals'   => $totals,
		);
	}

	/**
	 * Get top IP addresses with failure rates for spam/bot detection.
	 *
	 * @param array $args Query filters.
	 * @param int   $limit Maximum IPs to return.
	 * @return array
	 */
	public function get_top_ip_stats( array $args = array(), int $limit = 8 ): array {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );
		list( $sql_where, $params ) = $this->build_where( $args );

		$query = "SELECT 
				ip_address,
				COUNT(id) as total,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
				SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count
			FROM {$table_name}
			WHERE {$sql_where} AND ip_address IS NOT NULL AND ip_address != '' AND ip_address != '0.0.0.0'
			GROUP BY ip_address
			ORDER BY failed_count DESC, total DESC
			LIMIT %d";

		$params[] = $limit;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare( $query, $params );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results( $prepared, ARRAY_A );

		$result = array();
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$total  = (int) $row['total'];
				$failed = (int) $row['failed_count'];
				$rate   = $total > 0 ? round( ( $failed / $total ) * 100, 1 ) : 0.0;

				$result[] = array(
					'ip_address'     => $row['ip_address'],
					'total'          => $total,
					'failed_count'   => $failed,
					'accepted_count' => (int) $row['accepted_count'],
					'failure_rate'   => $rate,
					'is_suspicious'  => ( $failed >= 3 && $rate >= 50.0 ),
				);
			}
		}

		return $result;
	}

	/**
	 * Get most frequent error messages for failure diagnosis.
	 *
	 * @param array $args Query filters.
	 * @param int   $limit Max reasons.
	 * @return array
	 */
	public function get_top_error_reasons( array $args = array(), int $limit = 5 ): array {
		global $wpdb;

		$table_name = esc_sql( Database::get_table_name() );
		list( $sql_where, $params ) = $this->build_where( $args );

		$query = "SELECT 
				error_message,
				COUNT(id) as count
			FROM {$table_name}
			WHERE {$sql_where} AND status = 'failed' AND error_message IS NOT NULL AND error_message != ''
			GROUP BY error_message
			ORDER BY count DESC
			LIMIT %d";

		$params[] = $limit;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare( $query, $params );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results( $prepared, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Calculate cutoff date for a given value and unit.
	 *
	 * @param int    $value Time value.
	 * @param string $unit  Unit (seconds, minutes, hours, days, months, years).
	 * @return string MySQL UTC datetime string.
	 */
	public function calculate_cutoff_date( int $value, string $unit ): string {
		$value = max( 1, $value );
		$now   = time();

		switch ( strtolower( $unit ) ) {
			case 'seconds':
			case 'second':
				$cutoff = $now - $value;
				break;
			case 'minutes':
			case 'minute':
				$cutoff = $now - ( $value * 60 );
				break;
			case 'hours':
			case 'hour':
				$cutoff = $now - ( $value * 3600 );
				break;
			case 'months':
			case 'month':
				$cutoff = strtotime( "-{$value} months", $now );
				if ( false === $cutoff ) {
					$cutoff = $now - ( $value * 30 * 86400 );
				}
				break;
			case 'years':
			case 'year':
				$cutoff = strtotime( "-{$value} years", $now );
				if ( false === $cutoff ) {
					$cutoff = $now - ( $value * 365 * 86400 );
				}
				break;
			case 'days':
			case 'day':
			default:
				$cutoff = $now - ( $value * 86400 );
				break;
		}

		return gmdate( 'Y-m-d H:i:s', $cutoff );
	}

	/**
	 * Automatically delete logs older than a given interval.
	 *
	 * @param int    $value         Threshold value.
	 * @param string $unit          Time unit (seconds, minutes, hours, days, months, years).
	 * @param bool   $include_trash Whether to also delete trashed logs older than cutoff.
	 * @return int Number of deleted logs.
	 */
	public function auto_delete_old_logs( int $value, string $unit, bool $include_trash = true ): int {
		global $wpdb;

		$table_name  = Database::get_table_name();
		$cutoff_date = $this->calculate_cutoff_date( $value, $unit );

		if ( $include_trash ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = "DELETE FROM {$table_name} WHERE created_at < %s";
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = "DELETE FROM {$table_name} WHERE created_at < %s AND status != 'trash'";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare( $query, $cutoff_date );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$deleted = $wpdb->query( $prepared );

		return false !== $deleted ? (int) $deleted : 0;
	}

	/**
	 * Run auto cleanup according to saved plugin settings.
	 *
	 * @return int Number of deleted logs.
	 */
	public function run_auto_cleanup_from_settings(): int {
		$enabled = '1' === (string) get_option( 'ael_auto_delete_enabled', '0' );
		if ( ! $enabled ) {
			return 0;
		}

		$value         = (int) get_option( 'ael_auto_delete_value', 30 );
		$unit          = (string) get_option( 'ael_auto_delete_unit', 'days' );
		$include_trash = '1' === (string) get_option( 'ael_auto_delete_include_trash', '1' );

		return $this->auto_delete_old_logs( $value, $unit, $include_trash );
	}
}
