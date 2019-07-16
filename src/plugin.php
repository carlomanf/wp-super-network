<?php
/**
 * Main plugin file
 */

/**
 * Main plugin class.
 */
class WP_Super_Network
{
	/**
	 * Static instance of the plugin.
	 */
	protected static $instance;

	/**
	 * Current network.
	 *
	 * @since 1.0.4
	 * @var WPSN_Network
	 */
	public $network;

	/**
	 * Instantiate a WP_Super_Network object.
	 *
	 * Don't call the constructor directly, use the `WP_Super_Network::get_instance()`
	 * static method instead.
	 */
	public function __construct()
	{
		if ( function_exists( 'get_network' ) )
		{
			$this->network = new WPSN_Network( array() );
		}
	}

	/**
	 * Launch the initialization process.
	 *
	 * @since 1.0.4
	 */
	public function run()
	{
		// Load functions
		add_filter( 'page_row_actions', array( $this, 'republish' ), 10, 2 );
	}

	/**
	 * Add a link to republish
	 *
	 * @since 1.0.4
	 */
	public function republish( $actions, $post ) {
		if ( 'post' != $post->post_type && 'page' != $post->post_type )
			return $actions;

		$link = 'page' == $post->post_type ? admin_url( 'edit.php?post_type=page&republish=' . $post->ID ) : admin_url( 'edit.php?republish=' . $post->ID );

		if ( empty( get_post_meta( $post->ID, '_supernetwork_share' ) ) )
			$actions['republish'] = '<a href="' . $link . '">' . __( 'Republish', 'supernetwork' ) . '</a>';
		else
			$actions['republish'] = '<b style="color: #555;">' . __( 'Republished', 'supernetwork' ) . '</b> <a href="' . $link . '">(' . __( 'Revoke?', 'supernetwork' ) . ')</a>';

		//update_post_meta( $post->ID, '_supernetwork_share', '1' );

		return $actions;
	}

	/**
	 * Load the plugin text domain.
	 *
	 * @since 1.0.4
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain( 'supernetwork', false, 'wp-super-network/languages/' );
	}
}
