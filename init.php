<?php
/**
 * Initialise the plugin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) )
{
	die;
}

if ( ! defined( 'SUPER_NETWORK_DIR' ) )
{
	define( 'SUPER_NETWORK_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'SUPER_NETWORK_URL' ) )
{
	define( 'SUPER_NETWORK_URL', plugin_dir_url( __FILE__ ) );
}

// Load classes.
require SUPER_NETWORK_DIR . '/src/blog.php';
require SUPER_NETWORK_DIR . '/src/network.php';
require SUPER_NETWORK_DIR . '/src/plugin.php';

// Initialize the plugin.
$GLOBALS['supernetwork'] = new WP_Super_Network();
$GLOBALS['supernetwork']->run();
