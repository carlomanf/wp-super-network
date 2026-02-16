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
	 * ID of the subnetwork based on this site.
	 * 0 if none.
	 * Lazily set by `is_network` method.
	 *
	 * @since 1.3.0
	 * @var int|null
	 */
	private $subnetwork_id = null;

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
		while ( !isset( $this->subnetwork_id ) )
		{
			$this->subnetwork_id = 0;

			foreach ( get_networks( 'fields=ids' ) as $network_id )
			{
				if ( is_main_site( $this->id, $network_id ) )
				{
					$this->subnetwork_id = $network_id;
					break;
				}
			}
		}

		return $this->subnetwork_id > 0;
	}

	/**
	 * Pop blog out of its network and create a new network.
	 *
	 * @since 1.0.4
	 * @since 1.3.0 Added `$network` parameter and return type.
	 *
	 * @param WP_Super_Network\Network|null $network Network of this blog, if known.
	 *
	 * @return WP_Super_Network\Network The network the blog was upgraded to.
	 */
	public function upgrade_to_network( $network = null )
	{
		if ( !$this->is_network() )
		{
			$this->subnetwork_id = (int) $GLOBALS['wpdb']->get_var( 'SELECT `auto_increment` FROM `information_schema`.`tables` WHERE `table_schema` = \'' . DB_NAME . '\' AND `table_name` = \'' . $GLOBALS['wpdb']->base_prefix . 'site\'' );
			require_once ABSPATH . '/wp-admin/includes/schema.php';
			switch_to_blog( $this->id );
			populate_network( $this->subnetwork_id, $this->wp_site->domain, get_option( 'admin_email' ), $this->name, $this->wp_site->path, is_subdomain_install() );
			update_network_option( $this->subnetwork_id, 'main_site', $this->id );
			restore_current_blog();
		}

		return new Network( get_network( $this->subnetwork_id ), $network );
	}

	/**
	 * Check if this blog's depth allows the current user to upgrade it to network.
	 *
	 * @since 1.3.0
	 *
	 * @return bool True if user can, false otherwise.
	 */
	public function depth_allowed()
	{
		return current_user_can_for_blog( $this->id, 'activate_network' );
	}
}
