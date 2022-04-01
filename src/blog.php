<?php
/**
 * Main blog class.
 */
namespace WP_Super_Network;

class Blog
{
	/**
	 * Site object
	 *
	 * @since 1.0.4
	 * @var WP_Site
	 */
	public $wp_site;

	/**
	 * Constructor.
	 *
	 * Constructs the site.
	 *
	 * @since 1.0.4
	 */
	public function __construct( $blog )
	{
		$this->wp_site = $blog;
	}

	public function __get( $key )
	{
		if ( $key === 'id' )
		{
			return (int) $this->wp_site->blog_id;
		}
		
		if ( $key === 'network_id' )
		{
			return $this->wp_site->site_id;
		}

		if ( $key === 'name' )
		{
			return $this->wp_site->blogname;
		}
	}
	
	public function table( $name )
	{
		$id = $this->wp_site->blog_id;
		$table = $GLOBALS['wpdb']->base_prefix;
		if ( $id > 1 ) $table .= $id . '_';
		$table .= $name;
		return $table;
	}

	public function is_network()
	{
		return is_main_site( $this->__get( 'id' ), $this->__get( 'network_id' ) );
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
