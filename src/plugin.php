<?php
/**
 * Main plugin class.
 */
namespace WP_Super_Network;

class WP_Super_Network
{
	const TABLES_TO_REPLACE = array(
		'comment_post_ID' => 'comments',
		'ID' => 'posts',
		'object_id' => 'term_relationships',
		'post_id' => 'postmeta',
		'post_parent' => 'posts'
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
		// Load functions
		add_filter( 'post_type_link', array( $this->network, 'intercept_permalink_for_post' ), 10, 2 );
		add_filter( 'post_link', array( $this->network, 'intercept_permalink_for_post' ), 10, 2 );
		add_filter( 'page_link', array( $this->network, 'intercept_permalink' ), 10, 2 );
		add_filter( 'preview_post_link', array( $this->network, 'intercept_preview_link' ), 10, 2 );
		add_filter( 'supernetwork_preview_link', array( $this->network, 'replace_preview_link' ), 10, 2 );
		add_filter( 'user_has_cap', array( $this->network, 'intercept_capability' ), 10, 4 );
		add_filter( 'pre_handle_404', array( $this->network, 'singular_access' ), 10, 2 );
		add_action( 'wp', array( $this->network, 'preview_access' ) );
		add_filter( 'query', array( $this->network, 'intercept_query' ), 10, 2 );
		add_filter( 'wp_insert_post', array( $this->network, 'shared_auto_increment' ), 10, 3 );
		add_filter( 'admin_enqueue_scripts', array( $this->network, 'add_new_post' ) );
		add_filter( 'network_admin_menu', array( $this, 'summary' ) );

		$this->network->register_pages();

		// Complete this before accessing the option on next line
		add_filter( 'pre_option_supernetwork_options', array( __CLASS__, 'options' ), 10, 3 );
		add_filter( 'pre_option_supernetwork_post_types', array( __CLASS__, 'options' ), 10, 3 );
		add_filter( 'pre_option_supernetwork_consolidated', array( __CLASS__, 'options' ), 10, 3 );
		
		foreach ( (array) get_option( 'supernetwork_options' ) as $option => $val )
		{
			if ( $val && strpos( $option, '_' ) !== 0 && strpos( $option, 'supernetwork_' ) !== 0 )
			{
				add_filter( 'pre_option_' . $option, array( __CLASS__, 'options' ), 10, 3 );
			}
		}

		add_filter( 'add_option', array( __CLASS__, 'add_option' ), 10, 2 );
		add_filter( 'update_option', array( __CLASS__, 'update_option' ), 10, 3 );

		$this->network->consolidated = !empty( get_option( 'supernetwork_consolidated' )['consolidated'] );

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
				if ( in_array( (string) $post->ID, $this->network->collisions, true ) )
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
