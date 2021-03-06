<?php
/**
 * Main network class.
 */
namespace WP_Super_Network;

class Network
{
	/**
	 * Supernetwork
	 *
	 * @since 1.0.4
	 * @var Network
	 */
	private $supernetwork;

	/**
	 * Subnetworks
	 *
	 * @since 1.0.4
	 * @var array
	 */
	private $subnetworks;

	/**
	 * Blogs in the network.
	 *
	 * @since 1.0.4
	 * @var array
	 */
	private $blogs;

	/**
	 * Is this network on consolidated mode.
	 *
	 * @since 1.0.6
	 * @var bool
	 */
	private $consolidated;

	/**
	 * Settings page.
	 *
	 * @since 1.0.7
	 * @var Settings_Page
	 */
	private $page;

	/**
	 * Constructor.
	 *
	 * Constructs the network.
	 *
	 * @since 1.0.4
	 *
	 * @param WP_Network
	 */
	public function __construct( $network )
	{
		$id = $network->__get( 'id' );
		$this->blogs = array();

		foreach ( get_sites( 'network_id=' . $id ) as $site )
		{
			array_push( $this->blogs, new Blog( $site ) );
		}

		$this->consolidated = false;

		$this->page = new Settings_Page(
			'supernetwork_options',
			'Network Settings',
			'Network',
			'manage_network_options',
			'supernetwork_options',
			'<p>Network settings.</p>',
			10,
			'options-general.php'
		);

		$section = new Settings_Section(
			'options',
			'Options'
		);

		global $wpdb;
		$results = $wpdb->get_results( "select distinct option_name from $wpdb->options where option_name not like '\_%' and option_name <> 'supernetwork_options' order by option_name" );
		$labels = array();

		foreach ( $results as $result )
		{
			$labels[ $result->option_name ] = $result->option_name;
		}

		$field = new Settings_Field(
			'supernetwork_options',
			'%s',
			'checkbox',
			'Defer to Network',
			$labels
		);

		$section->add( $field );
		$this->page->add( $section );
	}

	public function register_pages()
	{
		$this->page->register();
	}

	public function page()
	{
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">WP Super Network</h1>';
		echo '<h2>Republished Posts and Pages</h2>';
		$this->republished();
		echo '<h2>Upgrade to Network</h2>';
		$this->get_blogs_for_user();
		echo '</div>';
	}

	/**
	 * List all republished posts and pages
	 *
	 * @since 1.0.4
	 */
	public function republished()
	{
		$republished = $this->consolidate( 'meta_key=_supernetwork_share&post_type=any' );

		if ( empty( $republished ) )
		{
			echo 'This network has no republished posts or pages!';
			return;
		}

		foreach ( $republished as $post )
			echo '<a href="' . get_permalink( $post ) . '">' . $post->post_title . '</a><br>';
	}

	/**
	 * Perform a query in ad-hoc consolidated mode
	 *
	 * @since 1.0.6
	 */
	public function consolidate( $args )
	{
		$old = $this->consolidated;

		// Turn on consolidated mode temporarily
		$this->consolidated = true;
		$query = new \WP_Query( $args );

		$this->consolidated = $old;

		return $query->posts;
	}

	/**
	 * List all blogs for current user (network admin)
	 *
	 * @since 1.0.4
	 */
	public function get_blogs_for_user()
	{
		if ( empty( $this->blogs ) )
		{
			echo 'This network has no blogs!';
			return;
		}

		foreach ( $this->blogs as $blog )
		{
			echo $blog->wp_site->__get( 'blogname' ) . ' <a href="' . admin_url( 'network/admin.php?page=wp_super_network&upgrade=' . $blog->wp_site->__get( 'id' ) ) . '">(Upgrade?)</a><br>';
		}
	}

	/**
	 * Intercepts WP_Query to return posts from across the network.
	 * Only takes effect when consolidated mode is on.
	 *
	 * @since 1.0.5
	 */
	public function intercept_wp_query( $posts, $query )
	{
		// Turn off this filter if not allowed
		if ( !$this->consolidated )
			return $posts;

		// Prevent infinite loop
		if ( !empty( $GLOBALS['_wp_switched_stack'] ) )
			return $posts;

		foreach ( $this->blogs as $blog )
		{
			$id = $blog->wp_site->__get( 'id' );

			if ( $id == get_current_blog_id() )
				continue;
			
			switch_to_blog( $id );

			$new = new \WP_Query( $query->query );

			if ($new->posts)
			foreach ( $new->posts as $post )
			{
				if ( get_post_meta( $post->ID, '_supernetwork_share' ) )
					$posts[] = $post;
			}

			restore_current_blog();
		}

		return $posts;
	}

	public function intercept_permalink( $permalink, $the_post )
	{
		// Prevent infinite loop
		if ( !empty( $GLOBALS['_wp_switched_stack'] ) )
			return $permalink;
		
		foreach ( $this->blogs as $blog )
		{
			switch_to_blog( $blog->wp_site->__get( 'id' ) );

			// Try the post with the same ID on each site
			// and check if its guid matches
			wp_cache_flush();
			$a_post = get_post( $the_post->ID );

			if ( empty( $a_post ) || $the_post->guid != $a_post->guid )
			{
				// Couldn't find the post, or the guid didn't match
				restore_current_blog();
				continue;
			}
			else
			{
				// Correct site was found
				$permalink = get_permalink( $a_post );
				restore_current_blog();
				return $permalink;
			}
		}

		return '#';
	}
}
