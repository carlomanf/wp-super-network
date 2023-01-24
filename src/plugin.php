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

	public static function options( $value, $option, $default )
	{
		if ( !is_main_site() )
		{
			$main = get_main_site_id();

			if ( $main > 0 )
			{
				switch_to_blog( $main );
				$value = get_option( $option, $default );
				restore_current_blog();
			}
			else
			{
				if ( in_array( $option, array( 'supernetwork_consolidated', 'supernetwork_post_types', 'supernetwork_options' ), true ) )
				{
					$value = $default;
				}
			}

			if ( $value === false )
			{
				add_filter( 'option_' . $option, '__return_false' );
			}
		}

		return $value;
	}

	public static function add_option( $option, $value )
	{
		if ( !is_main_site() && has_filter( 'pre_option_' . $option, array( __CLASS__, 'options' ) ) )
		{
			$main = get_main_site_id();

			if ( $main > 0 && current_user_can_for_blog( $main, 'manage_options' ) )
			{
				switch_to_blog( $main );
				add_option( $option, $value );
				restore_current_blog();
			}
		}
	}

	public static function update_option( $option, $old_value, $value )
	{
		if ( !is_main_site() && has_filter( 'pre_option_' . $option, array( __CLASS__, 'options' ) ) )
		{
			$main = get_main_site_id();

			if ( $main > 0 && current_user_can_for_blog( $main, 'manage_options' ) )
			{
				switch_to_blog( $main );
				update_option( $option, $value );
				restore_current_blog();
			}
		}
	}

	/**
	 * Launch the initialization process.
	 *
	 * @since 1.0.4
	 */
	public function run()
	{
		// Complete this before accessing the option on next line
		add_filter( 'pre_option_supernetwork_options', array( __CLASS__, 'options' ), 10, 3 );
		add_filter( 'pre_option_supernetwork_post_types', array( __CLASS__, 'options' ), 10, 3 );
		add_filter( 'pre_option_supernetwork_consolidated', array( __CLASS__, 'options' ), 10, 3 );

		foreach ( get_option( 'supernetwork_options', array() ) as $option => $val )
		{
			if ( $val && strpos( $option, '_' ) !== 0 && strpos( $option, 'supernetwork_' ) !== 0 )
			{
				add_filter( 'pre_option_' . $option, array( __CLASS__, 'options' ), 10, 3 );
			}
		}

		add_filter( 'add_option', array( __CLASS__, 'add_option' ), 10, 2 );
		add_filter( 'update_option', array( __CLASS__, 'update_option' ), 10, 3 );

		// Disable querying of meta ID. See issue #10
		add_filter( 'update_comment_metadata_by_mid', '__return_false' );
		add_filter( 'update_post_metadata_by_mid', '__return_false' );
		add_filter( 'update_term_metadata_by_mid', '__return_false' );
		add_filter( 'delete_comment_metadata_by_mid', '__return_false' );
		add_filter( 'delete_post_metadata_by_mid', '__return_false' );
		add_filter( 'delete_term_metadata_by_mid', '__return_false' );

		$this->network->register();

		// Load functions
		add_filter( 'network_admin_menu', array( $this, 'summary' ) );

		if ( !$this->network->consolidated )
		{
			add_filter( 'admin_init', array( $this, 'update_db' ) );
			add_filter( 'page_row_actions', array( $this, 'republish' ), 10, 2 );
			add_filter( 'post_row_actions', array( $this, 'republish' ), 10, 2 );
		}
	}

	public function summary()
	{
		add_menu_page(
			'WP Super Network',
			'Super Network',
			'manage_network_options',
			'wp_super_network',
			array( $this->network, 'page' )
		);
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
