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
	 * Check if this blog's depth allows the user to upgrade it to network.
	 *
	 * @since 1.3.0
	 *
	 * @param int $user_id ID of the user to check. 0 for current user. Default 0.
	 *
	 * @return bool True if user can, false otherwise.
	 */
	public function can_be_upgraded( $user_id = 0 )
	{
		$user_id = $user_id === 0 ? get_current_user_id() : $user_id;
		return $this->check_capability( $this->id, $user_id ) && $this->check_depth( $this->id ) !== 0;
	}

	/**
	 * Check if the current user has the capability to upgrade a blog to network.
	 * Capability is automatically inherited by subnetworks.
	 *
	 * @since 1.3.0
	 *
	 * @param int $blog_id ID of the blog to check.
	 * @param int $user_id ID of the user to check.
	 *
	 * @return bool True if user can, false otherwise.
	 */
	private function check_capability( $blog_id, $user_id )
	{
		$blog = get_site( $blog_id );

		if ( $blog_id === 0 || $blog === null )
		{
			return false;
		}
		else
		{
			if ( user_can_for_site( $user_id, $blog_id, 'activate_network' ) )
			{
				return true;
			}
			else
			{
				$main_site_id = get_main_site_id( $blog->network_id );
				return $main_site_id === $blog_id ? false : $this->check_capability( $main_site_id, $user_id );
			}
		}
	}

	/**
	 * Returns a blog's depth as an integer, where -1 is unlimited and 0 is not allowed.
	 * Checks the `supernetwork_depth` option.
	 *
	 * @since 1.3.0
	 *
	 * @param int $blog_id ID of the blog to check.
	 *
	 * @return int Depth allowed for this blog.
	 */
	private function check_depth( $blog_id )
	{
		$blog = get_site( $blog_id );

		if ( $blog_id === 0 || $blog === null )
		{
			return -1;
		}

		$depth_allowed = (int) get_blog_option( $blog_id, 'supernetwork_depth', '-1' );
		$main_site_id = get_main_site_id( $blog->network_id );
		$max_depth_allowed = $main_site_id === $blog_id ? -2 : $this->check_depth( $main_site_id ) - 1;

		if ( $max_depth_allowed < 0 )
		{
			$max_depth_allowed++;
		}

		if ( $depth_allowed < 0 )
		{
			return $max_depth_allowed;
		}
		else
		{
			return $max_depth_allowed < 0 ? $depth_allowed : min( $depth_allowed, $max_depth_allowed );
		}
	}
}
