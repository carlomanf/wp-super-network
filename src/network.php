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

	private $parser;
	private $creator;

	private function union( $table )
	{
		$tables = array();
		foreach ( $this->blogs as $blog )
		{
			$query = 'select * from ' . $GLOBALS['wpdb']->base_prefix;
			if ( $blog->id > 1 ) $query .= $blog->id . '_';
			$query .= $table;

			$tables[] = $query;
		}

		return implode( ' union ', $tables );
	}

	private function remove_collisions( &$parsed, $col )
	{
		$parsed['WHERE'] = isset( $parsed['WHERE'] ) ? array(
			array(
				'expr_type' => 'bracket_expression',
				'sub_tree' => $parsed['WHERE']
			)
		) : array();

		$parsed['WHERE'][] = array(
			'expr_type' => 'operator',
			'base_expr' => 'and'
		);

		$parsed['WHERE'][] = array(
			'expr_type' => 'bracket_expression',
			'sub_tree' => $this->parser->parse(
				'where ' . $col . ' not in (' . implode( ', ', $this->collisions ) . ')'
			)['WHERE']
		);

		return $parsed;
	}

	public function report_collisions()
	{
		if ( !empty( $this->collisions ) )
		{
			echo '<div class="notice notice-error"><p><strong>WP Super Network detected ' . count( $this->collisions ) . ' post ID collisions</strong> across your network! These posts have been temporarily hidden. To access them again, please turn off consolidated mode.</p></div>';
		}
	}

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

		$this->collisions = $GLOBALS['wpdb']->get_col( 'select ID from (' . $this->union( 'posts' ) . ') posts group by ID having count(*) > 1' );
		add_filter( 'admin_footer', array( $this, 'report_collisions' ) );

		$this->parser = new \PHPSQLParser\PHPSQLParser();
		$this->creator = new \PHPSQLParser\PHPSQLCreator();

		$this->consolidated = true;

		$this->page = new Settings_Page(
			array( 'supernetwork_options', 'supernetwork_post_types', 'supernetwork_consolidated' ),
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
		$results = $wpdb->get_results( "select distinct option_name from $wpdb->options where option_name not like '\_%' and option_name not like 'supernetwork\_%' order by option_name" );
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

		$section2 = new Settings_Section(
			'post_types',
			'Post Types'
		);

		add_filter( 'supernetwork_settings_field_args', array( $this, 'post_types' ), 10, 2 );

		$field2 = new Settings_Field(
			'supernetwork_post_types',
			'%s',
			'checkbox',
			'Defer to Network',
			array()
		);

		$section3 = new Settings_Section(
			'consolidated',
			'Consolidated Mode'
		);

		$field3 = new Settings_Field(
			'supernetwork_consolidated',
			'consolidated',
			'checkbox',
			'Consolidated Mode',
			'Turn on consolidated mode?'
		);

		$section->add( $field );
		$section2->add( $field2 );
		$section3->add( $field3 );
		$this->page->add( $section );
		$this->page->add( $section2 );
		$this->page->add( $section3 );
	}

	public function post_types( $args, $field )
	{
		if ( $field->database() === 'supernetwork_post_types' )
		{
			foreach ( get_post_types() as $type )
			{
				$args['labels'][ $type ] = $type;
			}
		}

		return $args;
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

	public function intercept_query( $query )
	{
		$parsed = $this->parser->parse( $query );

		if ( isset( $parsed['SELECT'] ) && isset( $parsed['FROM'] ) )
		{
			foreach ( $parsed['FROM'] as &$from )
			{
				if ( isset( $from['table'] ) && $from['table'] === $GLOBALS['wpdb']->posts )
				{
					$from = $this->parser->parse(
						'from (' . $this->union( 'posts' ) . ') ' . $GLOBALS['wpdb']->posts
					)['FROM'][0];

					$this->remove_collisions( $parsed, 'ID' );

					return $this->creator->create( $parsed );
				}
				
				if ( isset( $from['table'] ) && $from['table'] === $GLOBALS['wpdb']->postmeta )
				{
					$from = $this->parser->parse(
						'from (' . $this->union( 'postmeta' ) . ') ' . $GLOBALS['wpdb']->postmeta
					)['FROM'][0];

					$this->remove_collisions( $parsed, 'post_id' );
					
					return $this->creator->create( $parsed );
				}
			}
		}

		return $query;
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
