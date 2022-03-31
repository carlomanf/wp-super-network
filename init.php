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

// Load superclasses.
require_once SUPER_NETWORK_DIR . 'src/node.php';

// Load classes.
require_once SUPER_NETWORK_DIR . 'src/blog.php';
require_once SUPER_NETWORK_DIR . 'src/expression.php';
require_once SUPER_NETWORK_DIR . 'src/field.php';
require_once SUPER_NETWORK_DIR . 'src/insert.php';
require_once SUPER_NETWORK_DIR . 'src/network.php';
require_once SUPER_NETWORK_DIR . 'src/page.php';
require_once SUPER_NETWORK_DIR . 'src/plugin.php';
require_once SUPER_NETWORK_DIR . 'src/query.php';
require_once SUPER_NETWORK_DIR . 'src/section.php';
require_once SUPER_NETWORK_DIR . 'src/subquery.php';
require_once SUPER_NETWORK_DIR . 'src/table.php';

// Initialize the plugin.
$GLOBALS['supernetwork'] = new WP_Super_Network();
$GLOBALS['supernetwork']->run();
