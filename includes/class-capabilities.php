<?php
/**
 * User roles and capabilities manager.
 *
 * @package WPAllSolution\AdvancedEmailLog
 */

namespace WPAllSolution\AdvancedEmailLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Capabilities
 */
class Capabilities {

	/**
	 * Capability to view email logs and details (read-only).
	 */
	const CAP_VIEW_LOGS = 'ael_view_logs';

	/**
	 * Capability to manage plugin settings.
	 */
	const CAP_MANAGE_SETTINGS = 'ael_manage_settings';

	/**
	 * Capability to perform write/operational actions (trash, delete, import, test email).
	 */
	const CAP_PERFORM_OPERATIONS = 'ael_perform_operations';

	/**
	 * Option storing allowed roles for viewing logs.
	 */
	const OPTION_ALLOWED_ROLES = 'ael_allowed_roles';

	/**
	 * Option storing whether checked roles can perform operations.
	 */
	const OPTION_ALLOW_ROLE_OPERATIONS = 'ael_allow_role_operations';

	/**
	 * Determine if current user is an Administrator.
	 *
	 * @return bool
	 */
	public static function is_administrator(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( is_multisite() && is_super_admin() ) {
			return true;
		}

		$user = wp_get_current_user();
		if ( $user && ! empty( $user->roles ) && in_array( 'administrator', (array) $user->roles, true ) ) {
			return true;
		}

		// Fallback check: user has manage_options and can activate plugins.
		return current_user_can( 'activate_plugins' ) || ( current_user_can( 'manage_options' ) && ! current_user_can( self::CAP_VIEW_LOGS ) );
	}

	/**
	 * Check if operation permissions are granted to non-admin checked roles.
	 *
	 * @return bool
	 */
	public static function are_role_operations_allowed(): bool {
		return '1' === (string) get_option( self::OPTION_ALLOW_ROLE_OPERATIONS, '0' );
	}

	/**
	 * Check if the current user can view email logs.
	 *
	 * @return bool
	 */
	public static function can_view_logs(): bool {
		if ( self::is_administrator() ) {
			return true;
		}
		return current_user_can( self::CAP_VIEW_LOGS );
	}

	/**
	 * Check if the current user can perform operations (trash, delete, clear logs, import, test email).
	 *
	 * @return bool
	 */
	public static function can_perform_operations(): bool {
		if ( self::is_administrator() ) {
			return true;
		}

		if ( ! self::are_role_operations_allowed() ) {
			return false;
		}

		return current_user_can( self::CAP_PERFORM_OPERATIONS ) || current_user_can( self::CAP_VIEW_LOGS );
	}

	/**
	 * Check if the current user can manage plugin settings.
	 *
	 * @return bool
	 */
	public static function can_manage_settings(): bool {
		if ( self::is_administrator() ) {
			return true;
		}

		if ( ! self::are_role_operations_allowed() ) {
			return false;
		}

		return current_user_can( self::CAP_MANAGE_SETTINGS );
	}

	/**
	 * Get the list of roles currently permitted to view logs.
	 *
	 * @return array
	 */
	public static function get_allowed_roles(): array {
		$roles = get_option( self::OPTION_ALLOWED_ROLES, array( 'administrator' ) );
		if ( ! is_array( $roles ) ) {
			$roles = array( 'administrator' );
		}
		if ( ! in_array( 'administrator', $roles, true ) ) {
			$roles[] = 'administrator';
		}
		return array_values( array_unique( $roles ) );
	}

	/**
	 * Sync capabilities across WordPress roles based on allowed roles list and operation flag.
	 *
	 * @param array     $allowed_roles    Array of role names.
	 * @param bool|null $allow_operations Whether checked roles get write operations.
	 */
	public static function sync_role_capabilities( array $allowed_roles = array(), ?bool $allow_operations = null ): void {
		if ( empty( $allowed_roles ) ) {
			$allowed_roles = self::get_allowed_roles();
		}

		if ( null === $allow_operations ) {
			$allow_operations = self::are_role_operations_allowed();
		}

		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$all_roles = wp_roles();
		if ( ! $all_roles || empty( $all_roles->roles ) ) {
			return;
		}

		foreach ( $all_roles->roles as $role_key => $role_data ) {
			$role_obj = get_role( $role_key );
			if ( ! $role_obj ) {
				continue;
			}

			// Administrators always have all capabilities.
			if ( 'administrator' === $role_key ) {
				if ( ! $role_obj->has_cap( self::CAP_VIEW_LOGS ) ) {
					$role_obj->add_cap( self::CAP_VIEW_LOGS );
				}
				if ( ! $role_obj->has_cap( self::CAP_MANAGE_SETTINGS ) ) {
					$role_obj->add_cap( self::CAP_MANAGE_SETTINGS );
				}
				if ( ! $role_obj->has_cap( self::CAP_PERFORM_OPERATIONS ) ) {
					$role_obj->add_cap( self::CAP_PERFORM_OPERATIONS );
				}
				continue;
			}

			// Non-administrator roles.
			if ( in_array( $role_key, $allowed_roles, true ) ) {
				// Grant view logs (read-only base).
				if ( ! $role_obj->has_cap( self::CAP_VIEW_LOGS ) ) {
					$role_obj->add_cap( self::CAP_VIEW_LOGS );
				}

				// Only grant write operations and settings if explicitly enabled by admin.
				if ( $allow_operations ) {
					if ( ! $role_obj->has_cap( self::CAP_PERFORM_OPERATIONS ) ) {
						$role_obj->add_cap( self::CAP_PERFORM_OPERATIONS );
					}
					if ( ! $role_obj->has_cap( self::CAP_MANAGE_SETTINGS ) ) {
						$role_obj->add_cap( self::CAP_MANAGE_SETTINGS );
					}
				} else {
					if ( $role_obj->has_cap( self::CAP_PERFORM_OPERATIONS ) ) {
						$role_obj->remove_cap( self::CAP_PERFORM_OPERATIONS );
					}
					if ( $role_obj->has_cap( self::CAP_MANAGE_SETTINGS ) ) {
						$role_obj->remove_cap( self::CAP_MANAGE_SETTINGS );
					}
				}
			} else {
				// Revoke all custom caps if role is not allowed.
				if ( $role_obj->has_cap( self::CAP_VIEW_LOGS ) ) {
					$role_obj->remove_cap( self::CAP_VIEW_LOGS );
				}
				if ( $role_obj->has_cap( self::CAP_PERFORM_OPERATIONS ) ) {
					$role_obj->remove_cap( self::CAP_PERFORM_OPERATIONS );
				}
				if ( $role_obj->has_cap( self::CAP_MANAGE_SETTINGS ) ) {
					$role_obj->remove_cap( self::CAP_MANAGE_SETTINGS );
				}
			}
		}

		update_option( self::OPTION_ALLOWED_ROLES, array_values( array_unique( $allowed_roles ) ), false );
		update_option( self::OPTION_ALLOW_ROLE_OPERATIONS, $allow_operations ? '1' : '0', false );
	}

	/**
	 * Remove custom capabilities from all roles (on uninstall).
	 */
	public static function remove_all_capabilities(): void {
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$all_roles = wp_roles();
		if ( ! $all_roles || empty( $all_roles->roles ) ) {
			return;
		}

		foreach ( array_keys( $all_roles->roles ) as $role_key ) {
			$role_obj = get_role( $role_key );
			if ( $role_obj ) {
				$role_obj->remove_cap( self::CAP_VIEW_LOGS );
				$role_obj->remove_cap( self::CAP_MANAGE_SETTINGS );
				$role_obj->remove_cap( self::CAP_PERFORM_OPERATIONS );
			}
		}

		delete_option( self::OPTION_ALLOWED_ROLES );
		delete_option( self::OPTION_ALLOW_ROLE_OPERATIONS );
	}
}
