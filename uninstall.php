<?php
/**
 * Advanced Email Log Uninstall Routine.
 *
 * Triggered when the user deletes the plugin via the WordPress admin.
 * Respects user privacy preference: only deletes tables and options if configured.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-database.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-capabilities.php';

/**
 * Perform single-site uninstall cleanup.
 */
function ael_uninstall_single_site(): void {
	$delete_data = '1' === (string) get_option( 'ael_delete_data_on_uninstall', '0' );

	if ( $delete_data ) {
		// Drop custom database table.
		Database::drop_tables();

		// Remove all plugin options.
		$options = array(
			'ael_logging_enabled',
			'ael_live_updates_enabled',
			'ael_rows_per_page',
			'ael_log_email_body',
			'ael_dashboard_widget_enabled',
			'ael_delete_data_on_uninstall',
			'ael_allowed_roles',
			'ael_allow_role_operations',
			'ael_auto_delete_enabled',
			'ael_auto_delete_value',
			'ael_auto_delete_unit',
			'ael_auto_delete_include_trash',
			'ael_db_version',
		);

		foreach ( $options as $opt ) {
			delete_option( $opt );
		}

		// Unschedule background cleanup cron.
		$timestamp = wp_next_scheduled( 'ael_auto_cleanup_logs' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ael_auto_cleanup_logs' );
		}

		// Clean capabilities.
		Capabilities::remove_all_capabilities();
	}
}

/**
 * Run full uninstall routine across single or multisite.
 */
function ael_uninstall(): void {
	if ( function_exists( 'is_multisite' ) && is_multisite() ) {
		if ( function_exists( 'get_sites' ) && class_exists( '\WP_Site_Query' ) ) {
			$blog_ids = get_sites( array( 'fields' => 'ids' ) );
		} else {
			global $wpdb;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
		if ( ! empty( $blog_ids ) ) {
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( (int) $blog_id );
				ael_uninstall_single_site();
				restore_current_blog();
			}
		}
	} else {
		ael_uninstall_single_site();
	}
}

ael_uninstall();
