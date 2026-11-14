<?php
/**
 * Database schema and table management.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Database
 */
class Database {

	/**
	 * Database schema version.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option name for db version.
	 */
	const DB_VERSION_OPTION = 'ael_db_version';

	/**
	 * Base table name without prefix.
	 */
	const TABLE_NAME = 'ael_email_logs';

	/**
	 * Get full table name with WordPress prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create or upgrade the custom email logs table.
	 *
	 * @return bool
	 */
	public static function create_tables(): bool {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			to_email text NOT NULL,
			from_email varchar(255) NOT NULL DEFAULT '',
			subject text NOT NULL,
			body longtext NOT NULL,
			content_type varchar(64) NOT NULL DEFAULT 'text/plain',
			cc text DEFAULT NULL,
			bcc text DEFAULT NULL,
			reply_to text DEFAULT NULL,
			attachments longtext DEFAULT NULL,
			headers longtext DEFAULT NULL,
			ip_address varchar(45) NOT NULL DEFAULT '',
			error_message text DEFAULT NULL,
			extra_data longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status_idx (status),
			KEY created_at_idx (created_at),
			KEY status_created_at_idx (status, created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );

		return true;
	}

	/**
	 * Check if database update is required.
	 */
	public static function check_updates(): void {
		$installed_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );
		if ( version_compare( (string) $installed_version, self::DB_VERSION, '<' ) ) {
			self::create_tables();
		}
	}

	/**
	 * Drop table upon uninstall.
	 */
	public static function drop_tables(): void {
		global $wpdb;
		$table_name = esc_sql( self::get_table_name() );
		// Prepared query is not applicable for DROP TABLE with dynamic identifier, so safely validate table name.
		if ( $table_name === $wpdb->prefix . self::TABLE_NAME ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table drop routine invoked strictly on confirmed uninstallation.
			$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
		}
		delete_option( self::DB_VERSION_OPTION );
	}
}
