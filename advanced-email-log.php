<?php
/**
 * Plugin Name:       Advanced Email Log
 * Plugin URI:        https://store.wpallsolution.com/plugins/advanced-email-log/
 * Description:       Universal, lightweight, and secure email logger for WordPress. Tracks wp_mail activity with status monitoring, search, filters, CSV export, and email preview.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      8.0
 * Author:            WP All Solution
 * Author URI:        https://wpallsolution.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       advanced-email-log
 * Domain Path:       /languages
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'AEL_VERSION', '1.0.0' );
define( 'AEL_FILE', __FILE__ );
define( 'AEL_PATH', plugin_dir_path( __FILE__ ) );
define( 'AEL_URL', plugin_dir_url( __FILE__ ) );
define( 'AEL_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for Advanced Email Log classes.
 *
 * @param string $class Class name.
 */
spl_autoload_register( function ( $class ) {
	$prefix = 'WPAllSolution\\AdvancedEmailLog\\';
	if ( 0 !== strpos( $class, $prefix ) ) {
		return;
	}

	$relative_class = substr( $class, strlen( $prefix ) );
	$parts          = explode( '\\', $relative_class );
	$class_name     = array_pop( $parts );

	// Convert ClassName to class-classname.php
	$file_name = 'class-' . strtolower( str_replace( '_', '-', preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_name ) ) ) . '.php';

	$sub_path = '';
	if ( ! empty( $parts ) ) {
		$sub_path = strtolower( implode( DIRECTORY_SEPARATOR, $parts ) ) . DIRECTORY_SEPARATOR;
	}

	$paths = array(
		AEL_PATH . 'includes/' . $sub_path . $file_name,
		AEL_PATH . 'admin/' . $sub_path . $file_name,
	);

	foreach ( $paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
} );

// Check PHP version requirement.
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action( 'admin_notices', function () {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Advanced Email Log requires PHP 8.0 or higher. Please upgrade your PHP version.', 'advanced-email-log' ); ?></p>
		</div>
		<?php
	} );
	return;
}

/**
 * Register activation hook.
 */
register_activation_hook( AEL_FILE, function ( $network_wide ) {
	Activator::activate( (bool) $network_wide );
} );

/**
 * Register deactivation hook.
 */
register_deactivation_hook( AEL_FILE, function ( $network_wide ) {
	Activator::deactivate( (bool) $network_wide );
} );

/**
 * Initialize the plugin.
 */
add_action( 'plugins_loaded', function () {
	Plugin::get_instance()->init();
} );
