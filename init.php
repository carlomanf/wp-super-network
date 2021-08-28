<?php
/**
 * Initialise the plugin
 */
namespace WP_Super_Network;

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

// Load libraries.
require_once SUPER_NETWORK_DIR . 'vendor/autoload.php';

// Load classes.
require_once SUPER_NETWORK_DIR . 'src/blog.php';
require_once SUPER_NETWORK_DIR . 'src/field.php';
require_once SUPER_NETWORK_DIR . 'src/network.php';
require_once SUPER_NETWORK_DIR . 'src/page.php';
require_once SUPER_NETWORK_DIR . 'src/plugin.php';
require_once SUPER_NETWORK_DIR . 'src/section.php';

// Initialize the plugin.
$GLOBALS['supernetwork'] = new WP_Super_Network();
$GLOBALS['supernetwork']->run();
