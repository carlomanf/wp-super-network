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
	 * Instantiate a WP_Super_Network object.
	 *
	 * Don't call the constructor directly, use the `WP_Super_Network::get_instance()`
	 * static method instead.
	 */
	public function __construct()
	{
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
		if ( 'funnel' != $post->post_type )
			return $actions;

		$url = admin_url( 'edit.php?post_type=funnel_int&post_parent=' . $post->ID );
		$actions['republish'] = '<a href="' . esc_url( $url ) . '">' . __( 'Republish', 'supernetwork' ) . '</a>';

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
