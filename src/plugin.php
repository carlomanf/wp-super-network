<?php
/**
 * Main plugin class.
 */
namespace WP_Super_Network;

class WP_Super_Network
{
	const ENTITIES_TO_REPLACE = array(
		'comments' => array(),
		'posts' => array(),
		'term_taxonomy' => array(),
		'terms' => array()
	);

	const TABLES_TO_REPLACE = array(
		'commentmeta' => array(
			'comment_id' => 'comments'
		),
		'comments' => array(
			'comment_ID' => 'comments',
			'comment_post_ID' => 'posts'
		),
		'postmeta' => array(
			'post_id' => 'posts'
		),
		'posts' => array(
			'ID' => 'posts',
			'post_parent' => 'posts'
		),
		'term_relationships' => array(
			'object_id' => 'posts',
			'term_taxonomy_id' => 'term_taxonomy'
		),
		'term_taxonomy' => array(
			'term_id' => 'terms',
			'term_taxonomy_id' => 'term_taxonomy'
		),
		'termmeta' => array(
			'term_id' => 'terms'
		),
		'terms' => array(
			'term_id' => 'terms'
		)
	);

	/**
	 * Static instance of the plugin.
	 */
	protected static $instance;

	/**
	 * Current network.
	 *
	 * @since 1.0.4
	 * @var Network
	 */
	private $network;

	/**
	 * Current network ID.
	 *
	 * @since 1.3.0
	 * @var int
	 */
	private $network_id;

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
			$this->network = new Network( get_network() );
			$this->network_id = get_current_network_id();
		}
	}

	/**
	 * Return current network.
	 *
	 * @since 1.0.6
	 * @return Network
	 */
	public function network()
	{
		return $this->network;
	}

	public function inherit_options( $value, $option )
	{
		$site = get_site();
		$main = isset( $site ) ? get_main_site_id( $site->network_id ) : -1;
		$inherit = $main > 0 && $main !== get_current_blog_id();

		if ( $option === 'supernetwork_depth' )
		{
			$max_depth = $inherit ? (int) get_blog_option( $main, 'supernetwork_depth', '-1' ) - 1 : -2;

			if ( $max_depth < 0 )
			{
				$max_depth++;
			}

			if ( (int) $value < 0 )
			{
				return (string) $max_depth;
			}
			else
			{
				return $max_depth < 0 ? $value : (string) min( (int) $value, $max_depth );
			}
		}

		if ( $inherit )
		{
			$value = array_merge( (array) $value, array_filter( (array) get_blog_option( $main, $option, array() ) ) );
		}

		return $value;
	}

	public function options( $value, $option, $default = false )
	{
		$site = get_site();
		$main = isset( $site ) ? get_main_site_id( $site->network_id ) : -1;

		if ( $main > 0 && $main !== get_current_blog_id() && $site->network_id === $this->network_id )
		{
			$value = get_blog_option( $main, $option, $default );
		}

		return $value;
	}

	public function add_option( $option, $value, $update = false )
	{
		if ( has_filter( 'pre_option_' . $option, array( $this, 'options' ) ) || in_array( $option, array( 'supernetwork_consolidated', 'supernetwork_options', 'supernetwork_post_types' ), true ) )
		{
			$site = get_site();
			$main = isset( $site ) ? get_main_site_id( $site->network_id ) : -1;

			if ( $main > 0 && $main !== get_current_blog_id() && $site->network_id === $this->network_id )
			{
				if ( current_user_can_for_blog( $main, 'manage_options' ) )
				{
					if ( $update )
					{
						update_blog_option( $main, $option, $value );
					}
					else
					{
						add_blog_option( $main, $option, $value );
					}
				}
			}
		}
	}

	public function update_option( $option, $old_value, $value )
	{
		$this->add_option( $option, $value, true );
	}

	/**
	 * Launch the initialization process.
	 *
	 * @since 1.0.4
	 */
	public function run()
	{
		add_filter( 'option_supernetwork_options', array( $this, 'inherit_options' ), 10, 2 );
		add_filter( 'option_supernetwork_post_types', array( $this, 'inherit_options' ), 10, 2 );
		add_filter( 'option_supernetwork_consolidated', array( $this, 'inherit_options' ), 10, 2 );
		add_filter( 'option_supernetwork_depth', array( $this, 'inherit_options' ), 10, 2 );

		add_filter( 'default_option_supernetwork_options', array( $this, 'inherit_options' ), 10, 2 );
		add_filter( 'default_option_supernetwork_post_types', array( $this, 'inherit_options' ), 10, 2 );
		add_filter( 'default_option_supernetwork_consolidated', array( $this, 'inherit_options' ), 10, 2 );
		add_filter( 'default_option_supernetwork_depth', array( $this, 'inherit_options' ), 10, 2 );

		$site = get_site();
		$main = isset( $site ) ? get_main_site_id( $site->network_id ) : -1;
		$main = $main > 0 ? $main : -1;

		foreach ( get_blog_option( $main, 'supernetwork_options', array() ) as $option => $val )
		{
			if ( $val && strpos( $option, '_' ) !== 0 && strpos( $option, 'supernetwork_' ) !== 0 )
			{
				// All 3 filters are required in case `pre_option_{$option}` returns false.
				add_filter( 'pre_option_' . $option, array( $this, 'options' ), 10, 3 );
				add_filter( 'option_' . $option, array( $this, 'options' ), 10, 2 );
				add_filter( 'default_option_' . $option, array( $this, 'options' ), 10, 2 );
			}
		}

		add_filter( 'add_option', array( $this, 'add_option' ), 10, 2 );
		add_filter( 'update_option', array( $this, 'update_option' ), 10, 3 );

		// Disable querying of meta ID. See issue #10
		add_filter( 'update_comment_metadata_by_mid', '__return_false' );
		add_filter( 'update_post_metadata_by_mid', '__return_false' );
		add_filter( 'update_term_metadata_by_mid', '__return_false' );
		add_filter( 'delete_comment_metadata_by_mid', '__return_false' );
		add_filter( 'delete_post_metadata_by_mid', '__return_false' );
		add_filter( 'delete_term_metadata_by_mid', '__return_false' );

		// Register network.
		add_action( 'plugins_loaded', array( $this->network, 'register' ) );

		// Activate subnetworks if needed.
		add_action( 'wp_initialize_site', array( $this, 'auto_activate_subnetwork' ) );

		if ( !$this->network->consolidated )
		{
			add_filter( 'admin_init', array( $this, 'update_db' ) );
			add_filter( 'page_row_actions', array( $this, 'republish' ), 10, 2 );
			add_filter( 'post_row_actions', array( $this, 'republish' ), 10, 2 );
		}
	}

	/**
	 * Automatically activate subnetwork for new sites if setting is enabled.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_Site $new_site The new site object.
	 */
	public function auto_activate_subnetwork( $new_site )
	{
		$main = get_main_site_id( $new_site->network_id );

		if ( !empty( get_blog_option( $main, 'supernetwork_consolidated', array() )['auto_activate'] ) )
		{
			if ( (int) get_blog_option( $new_site->id, 'supernetwork_depth', '-1' ) !== 0 )
			{
				$blog = new Blog( $new_site );
				$blog->upgrade_to_network();
			}
		}
	}

	/**
	 * Update database to republish a post.
	 *
	 * @since 1.0.5
	 */
	public function update_db()
	{
		if ( empty( $_GET['republish'] ) )
			return;

		$post = get_post( intval( $_GET['republish'] ) );

		if ( empty( $post ) || !current_user_can( 'edit_post', $post->ID ) )
			return;

		if ( empty( $_GET['revoke'] ) )
			update_post_meta( $post->ID, '_supernetwork_share', '1' );
		else
			delete_post_meta( $post->ID, '_supernetwork_share' );
	}

	/**
	 * Add a link to republish
	 *
	 * @since 1.0.4
	 */
	public function republish( $actions, $post )
	{
		$link = 'post' == $post->post_type ? admin_url( 'edit.php?republish=' . $post->ID ) : admin_url( 'edit.php?post_type=' . $post->post_type . '&republish=' . $post->ID );

		if ( in_array( (string) $post->ID, $this->network->republished, true ) )
		{
			$actions['republish'] = '<b style="color: #555;">' . __( 'Republished', 'supernetwork' ) . '</b>';

			if ( current_user_can( 'edit_post', $post->ID ) )
			{
				$actions['republish'] .= ' <a href="' . $link . '&revoke=1">(' . __( 'Revoke?', 'supernetwork' ) . ')</a>';
			}
		}
		else
		{
			if ( current_user_can( 'edit_post', $post->ID ) )
			{
				$collisions = $this->network->collisions;

				if ( array_intersect( get_comments( 'fields=ids&post_id=' . $post->ID ), $collisions['comments'] ) !== array() || in_array( (string) $post->ID, $collisions['posts'], true ) || array_intersect( wp_get_object_terms( $post->ID, array_keys( $GLOBALS['wp_taxonomies'] ), 'fields=tt_ids' ), $collisions['term_taxonomy'] ) !== array() || array_intersect( wp_get_object_terms( $post->ID, array_keys( $GLOBALS['wp_taxonomies'] ), 'fields=ids' ), $collisions['terms'] ) !== array() )
				{
					$actions['republish'] = '<i style="color: #888;">' . __( 'Can&apos;t Republish', 'supernetwork' ) . '</i>';
				}
				else
				{
					$actions['republish'] = '<a href="' . $link . '">' . __( 'Republish', 'supernetwork' ) . '</a>';
				}
			}
		}

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
