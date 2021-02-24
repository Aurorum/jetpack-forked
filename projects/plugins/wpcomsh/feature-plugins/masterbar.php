<?php
/**
 * Customizations to the Masterbar module available in Jetpack.
 * We want that feature to always be available on Atomic sites.
 *
 * @package wpcomsh
 */

/**
 * Force-enable the Masterbar module
 * If you use a version of Jetpack that supports it,
 * and if it is not already enabled.
 */
function wpcomsh_activate_masterbar_module() {
	if ( ! defined( 'JETPACK__VERSION' ) ) {
		return;
	}

	// Masterbar was introduced in Jetpack 4.8.
	if ( version_compare( JETPACK__VERSION, '4.8', '<' ) ) {
		return;
	}

	if ( ! Jetpack::is_module_active( 'masterbar' ) ) {
		Jetpack::activate_module( 'masterbar', false, false );
	}
}
add_action( 'init', 'wpcomsh_activate_masterbar_module', 0, 0 );

/**
 * Remove Masterbar from the old Module list.
 * Available at wp-admin/admin.php?page=jetpack_modules
 *
 * @param array $items Array of Jetpack modules.
 * @return array
 */
function wpcomsh_rm_masterbar_module_list( $items ) {
	if ( isset( $items['masterbar'] ) ) {
		unset( $items['masterbar'] );
	}
	return $items;
}
add_filter( 'jetpack_modules_list_table_items', 'wpcomsh_rm_masterbar_module_list' );

/**
 * Check if the current request is an API request to the `wpcom/v2/admin-menu` endpoint.
 * @return bool
 */
function wpcomsh_is_admin_menu_api_request() {
	return 0 === strpos( $_SERVER['REQUEST_URI'], '/?rest_route=%2Fwpcom%2Fv2%2Fadmin-menu' );
}

/**
 * Sets WP_ADMIN constant on API requests for admin menus.
 *
 * Attempt to increase our chances that third-party plugins will
 * register their menu items based on `is_admin()` returning true.
 *
 * This has to run before plugins are loaded.
 */
function wpcomsh_mimic_admin_page_load() {
	if ( wpcomsh_is_admin_menu_api_request() ) {
		// Display errors can cause the API request to fail due to the PHP notice
		// triggered by `$pagenow` not being correctly determined when `WP_ADMIN`
		// is forced on a non-WP Admin page.
		@ini_set( 'display_errors', false ); // phpcs:ignore

		define( 'WP_ADMIN', true );
		require_once ABSPATH . 'wp-admin/includes/admin.php';
	}
}
add_action( 'muplugins_loaded', 'wpcomsh_mimic_admin_page_load' );

/**
 * Prints the calypso page link for changing a color scheme.
 **/
function wpcomsh_admin_color_scheme_picker_disabled() {
	printf(
		'<a target="_blank" href="%1$s">%2$s</a>',
		esc_url( 'https://wordpress.com/me/account' ),
		esc_html( __( 'Set your color scheme on WordPress.com.', 'wpcomsh' ) )
	);
}

/**
 * Hides the "Admin Color Scheme" entry on /wp-admin/profile.php,
 * and adds an action that prints a calypso page link.
 **/
function wpcomsh_hide_color_schemes() {
	remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
	add_action( 'admin_color_scheme_picker', 'wpcomsh_admin_color_scheme_picker_disabled' );
}
add_action( 'load-profile.php', 'wpcomsh_hide_color_schemes' );

/**
 * Gets data from the `wpcom.getUser` XMLRPC response and set it as user options. This is hooked
 * into the `setted_transient` action that is triggered everytime the XMLRPC response is read.
 *
 * @see https://github.com/Automattic/jetpack/blob/57ca1d524a6f6e446c5a3891d3024c71a6b0684b/projects/packages/connection/src/class-manager.php#L676
 *
 * @param string $transient  The name of the transient.
 * @param mixed  $value      Transient value.
 * @param int    $expiration Time until expiration in seconds.
 */
function wpcomsh_set_connected_user_data_as_user_options( $transient, $value, $expiration ) {
	if ( 0 !== strpos( $transient, 'jetpack_connected_user_data_' . get_current_user_id() ) ) {
		return;
	}

	if ( ! $value || ! is_array( $value ) ) {
		return;
	}

	if ( isset( $value['color_scheme'] ) ) {
		update_user_option( get_current_user_id(), 'admin_color', $value['color_scheme'] );
	}

	if ( ! empty( $value['is_nav_unification_enabled'] ) ) {
		update_user_option( get_current_user_id(), 'wpcom_is_nav_unification_enabled', true );
	} else {
		update_user_option( get_current_user_id(), 'wpcom_is_nav_unification_enabled', false );
	}
}
add_action( 'setted_transient', 'wpcomsh_set_connected_user_data_as_user_options', 10, 3 );

/**
 * Enables the nav-unification feature pbAPfg-Ou-p2
 * via `jetpack_load_admin_menu_class` filter that lives in Jetpack
 * https://github.com/Automattic/jetpack/blob/507142b09bae12b58e84c0c2b7d20024563f170d/modules%2Fmasterbar.php#L29
 *
 * Should add_filter for all a12s and all api requests for the admin-menu ( eg from calypso ).
 * Should add_filter depending on the current rollout segment.
 * CURRENT ROLLOUT SEGMENT: 5% of single site users.
 */
function wpcomsh_activate_nav_unification( $should_activate_nav_unification ) {
	// Loads for all API requests to the admin-menu endpoint (i.e. Calypso).
	if ( wpcomsh_is_admin_menu_api_request() ) {
		return true;
	}

	// Check if nav unification has been enabled for current user.
	$is_nav_unification_enabled = get_user_option( 'wpcom_is_nav_unification_enabled' );
	if ( $is_nav_unification_enabled ) {
		return true;
	}

	// Otherwise, keep using the previous value of the filter.
	return $should_activate_nav_unification;
}
add_filter( 'jetpack_load_admin_menu_class', 'wpcomsh_activate_nav_unification' );
