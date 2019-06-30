<?php

/**
 * Main blog class.
 */
class WPSN_Blog
{

	/**
	 * Constructor.
	 *
	 * Constructs the site.
	 *
	 * @since 1.0.4
	 */
	public function __construct()
	{
	}

	/**
	 * Pop blog out of its network and create a new network.
	 *
	 * @since 1.0.4
	 */
	public function upgrade_to_network()
	{
		if ( function_exists( 'get_network' ) )
			update_option( '_supernetwork_parent_site', (string) get_network()->get_main_site_id() );
	}
}
