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
		if ( 'post' != $post->post_type && 'page' != $post->post_type )
			return $actions;

		if ( empty( get_post_meta( $post->ID, '_supernetwork_share' ) ) )
			$actions['republish'] = '<a href="">' . __( 'Republish', 'supernetwork' ) . '</a>';
		else
			$actions['republish'] = '<b style="color: #555;">' . __( 'Republished', 'supernetwork' ) . '</b> <a href="">(' . __( 'Revoke?', 'supernetwork' ) . ')</a>';

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
