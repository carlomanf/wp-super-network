<?php
/**
 * Main plugin class.
 */
namespace WP_Super_Network;

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

	function options( $value, $option )
	{
		if ( !is_main_site() )
		{
			switch_to_blog( get_main_site_id() );
			$value = get_option( $option );
			restore_current_blog();
		}

		return $value;
	}

	/**
	 * Launch the initialization process.
	 *
	 * @since 1.0.4
	 */
	public function run()
	{
		// Load functions
		add_filter( 'admin_init', array( $this, 'update_db' ) );
		add_filter( 'page_row_actions', array( $this, 'republish' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'republish' ), 10, 2 );
		add_filter( 'post_type_link', array( $this->network, 'intercept_permalink' ), 10, 2 );
		add_filter( 'post_link', array( $this->network, 'intercept_permalink' ), 10, 2 );
		add_filter( 'query', array( $this->network, 'intercept_query' ), 10, 2 );
		add_filter( 'wp_insert_post', array( $this->network, 'shared_auto_increment' ), 10, 3 );
		add_filter( 'network_admin_menu', array( $this, 'summary' ) );

		if ( is_main_site() ) $this->network->register_pages();

		// Complete this before accessing the option on next line
		add_filter( 'pre_option_supernetwork_options', array( $this, 'options' ), 10, 2 );
		add_filter( 'pre_option_supernetwork_post_types', array( $this, 'options' ), 10, 2 );
		add_filter( 'pre_option_supernetwork_consolidated', array( $this, 'options' ), 10, 2 );
		
		foreach ( (array) get_option( 'supernetwork_options' ) as $option => $val )
		{
			if ( $val && strpos( $option, '_' ) !== 0 && strpos( $option, 'supernetwork_' ) !== 0 )
			{
				add_filter( 'pre_option_' . $option, array( $this, 'options' ), 10, 2 );
			}
		}

		$this->network->consolidated = !empty( get_option( 'supernetwork_consolidated' )['consolidated'] );
	}

	public function summary()
	{
		add_menu_page(
			'WP Super Network',
			'Super Network',
			'create_sites',
			'wp_super_network',
			array( $this->network, 'page' )
		);
	}

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
		$post2 = get_post( $post->ID );

		if ( empty( $post2 ) || $post2->guid !== $post->guid )
		{
			$actions['republish'] = '<b style="color: #555;">' . __( 'Republished', 'supernetwork' ) . '</b>';
		}
		else
		{
			if ( get_post_meta( $post->ID, '_supernetwork_share' ) )
				$actions['republish'] = '<b style="color: #555;">' . __( 'Republished', 'supernetwork' ) . '</b> <a href="' . $link . '&revoke=1">(' . __( 'Revoke?', 'supernetwork' ) . ')</a>';
			else
				$actions['republish'] = '<a href="' . $link . '">' . __( 'Republish', 'supernetwork' ) . '</a>';
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
