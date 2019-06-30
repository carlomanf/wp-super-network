<?php
/**
 * Plugin Name: WP Super Network
 * Plugin URI:
 * Description: Share content between sites and create offspring networks.
 * Version: 1.0.4-dev
 * Author: Ask Carlo
 * Author URI: https://askcarlo.com
 * Text Domain: supernetwork
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) )
{
	die;
}

if ( false )
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
		$notice = sprintf(
			// Translators: 1: Required PHP version, 2: Current PHP version.
			'<strong>WP Super Network</strong> requires PHP %1$s to run. This site uses %2$s, so the plugin has been <strong>deactivated</strong>.',
			'7.1',
			PHP_VERSION
		);
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
 * Load plugin initialisation file.
 */
require plugin_dir_path( __FILE__ ) . '/init.php';
