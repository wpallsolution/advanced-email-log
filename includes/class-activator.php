<?php
/**
 * Plugin activation and deactivation handler.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 */
class Activator {

	/**
	 * Default plugin options.
	 */
	public static function get_default_options(): array {
		return array(
			'ael_logging_enabled'          => '1',
			'ael_live_updates_enabled'     => '1',
			'ael_rows_per_page'            => 20,
			'ael_log_email_body'           => '1',
			'ael_dashboard_widget_enabled' => '1',
			'ael_delete_data_on_uninstall' => '0',
			'ael_allowed_roles'            => array( 'administrator' ),
		);
	}

	/**
	 * Activate plugin.
	 *
	 * @param bool $network_wide Whether activated across multisite network.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {
			if ( function_exists( 'get_sites' ) && class_exists( '\WP_Site_Query' ) ) {
				$blog_ids = get_sites( array( 'fields' => 'ids' ) );
			} else {
				global $wpdb;
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Multisite blog discovery for network activation.
				$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( (int) $blog_id );
				self::activate_single_site();
				restore_current_blog();
			}
		} else {
			self::activate_single_site();
		}
	}

	/**
	 * Run single site activation.
	 */
	private static function activate_single_site(): void {
		Database::create_tables();

		// Set default options if not already present.
		$defaults = self::get_default_options();
		foreach ( $defaults as $option_name => $default_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $default_value, '', false );
			}
		}

		// Ensure capabilities are set.
		Capabilities::sync_role_capabilities();
	}

	/**
	 * Deactivate plugin.
	 *
	 * @param bool $network_wide Whether deactivated across multisite network.
	 */
	public static function deactivate( bool $network_wide = false ): void {
		// No intrusive changes on deactivation to preserve user data and state.
	}
}
