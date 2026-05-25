<?php
/**
 * Plugin Name: WP Super Network
 * Plugin URI:
 * Description: Share content between sites and create offspring networks.
 * Version: 1.3.0-beta1
 * Requires at least: 6.1
 * Requires PHP: 7.2
 * Author: Ask Carlo
 * Author URI: https://askcarlo.com
 * Text Domain: supernetwork
 * Network: true
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) )
{
	die;
}

if ( ! defined( 'MULTISITE' ) || ! MULTISITE )
{
	add_action( 'plugins_loaded', 'supernetwork_init_deactivation' );

	/**
	 * Initialise deactivation functions.
	 */
	function supernetwork_init_deactivation()
	{
		if ( current_user_can( 'activate_plugins' ) )
		{
			add_action( 'admin_init', 'supernetwork_deactivate' );
			add_action( 'admin_notices', 'supernetwork_deactivation_notice' );
		}
	}

	/**
	 * Deactivate the plugin.
	 */
	function supernetwork_deactivate()
	{
		deactivate_plugins( plugin_basename( __FILE__ ) );
	}

	/**
	 * Show deactivation admin notice.
	 */
	function supernetwork_deactivation_notice()
	{
		$notice = '<strong>WP Super Network</strong> requires multisite to run. This is currently not a multisite, so the plugin has been <strong>deactivated</strong>.';
		?>
		<div class="updated"><p><?php echo wp_kses_post( $notice ); ?></p></div>
		<?php
		if ( isset( $_GET['activate'] ) ) // WPCS: input var okay, CSRF okay.
		{
			unset( $_GET['activate'] ); // WPCS: input var okay.
		}
	}

	return false;
}

/**
 * Polyfill for `user_can_for_site` introduced in version 6.7.
 */
if ( !function_exists( 'user_can_for_site' ) )
{
	function user_can_for_site( $user, $site_id, $capability, ...$args ) {
		if ( ! is_object( $user ) ) {
			$user = get_userdata( $user );
		}

		if ( empty( $user ) ) {
			// User is logged out, create anonymous user object.
			$user = new WP_User( 0 );
			$user->init( new stdClass() );
		}

		// Check if the blog ID is valid.
		if ( ! is_numeric( $site_id ) || $site_id <= 0 ) {
			return false;
		}

		$switched = is_multisite() ? switch_to_blog( $site_id ) : false;

		$can = user_can( $user->ID, $capability, ...$args );

		if ( $switched ) {
			restore_current_blog();
		}

		return $can;
	}
}

/**
 * Load plugin initialisation file.
 */
require plugin_dir_path( __FILE__ ) . '/init.php';
