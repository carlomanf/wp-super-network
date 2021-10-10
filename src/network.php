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

	private $collisions;

	public function shared_auto_increment( $post_ID, $post, $update )
	{
		if ( !$update )
		{
			foreach ( $this->blogs as $blog )
			{
				$GLOBALS['wpdb']->query( 'alter table ' . $blog->table( 'posts' ) . ' auto_increment = ' . (string) ($post_ID + 1) );
			}
		}
	}

	public function __set( $key, $value )
	{
		if ( $key = 'consolidated' )
		{
			if ( $value === true )
			{
				$this->collisions = $GLOBALS['wpdb']->get_col( 'select ID from (' . $this->union( 'posts' ) . ') posts group by ID having count(*) > 1' );
				$this->consolidated = true;
			}
			
			if ( $value === false )
			{
				$this->collisions = array();
				$this->consolidated = false;
			}
		}
	}

	private function union( $table )
	{
		$tables = array();
		foreach ( $this->blogs as $blog )
		{
			$where = '';
			
			if ( $table === 'posts' && !$blog->is_network() )
			{
				$where = ' where post_type not in (\'' . implode( '\', \'', array_keys( (array) get_option( 'supernetwork_post_types' ) ) ) . '\')';
			}
			
			$tables[] = 'select * from ' . $blog->table( $table ) . $where;
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
		if ( $this->consolidated && !empty( $this->collisions ) )
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

		add_filter( 'admin_footer', array( $this, 'report_collisions' ) );

		$this->parser = new \PHPSQLParser\PHPSQLParser();
		$this->creator = new \PHPSQLParser\PHPSQLCreator();

		$this->__set( 'consolidated', false );

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
			'Options',
			'This setting only takes effect when consolidated mode is turned on.'
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
			'Post Types',
			'This setting only takes effect when consolidated mode is turned on.'
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
			'Turn on consolidated mode?',
			'<strong>Warning:</strong> Consolidated mode is highly unstable. It is strongly suggested that you have a REGULAR backup regime in place for your database, before activating consolidated mode.'
		);

		$section->add( $field );
		$section2->add( $field2 );
		$section3->add( $field3 );
		$this->page->add( $section3 );
		$this->page->add( $section2 );
		$this->page->add( $section );
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

		if ( $this->consolidated )
		{
			echo '<h2>Post ID Collisions</h2>';
			echo '<p>Consolidated mode is designed for fresh networks. When activated on an existing network, a large number of ID collisions are inevitable. However, you may be able to eliminate some collisions when the ID refers to a post of low importance, such as a revision or autosave.</p>';
			echo '<p>The below table allows you to eliminate post ID collisions, one ID at a time. For each ID, you must select just ONE post to keep. All others with the same ID will be immediately and irretrievably deleted.</p>';
	
			echo '<form method="post" action="">';
			echo '<table class="widefat">';
			echo '<thead><tr><th scope="col">Keep?</th><th scope="col">GUID</th><th scope="col">Post Title</th><th scope="col">Post Preview</th><th scope="col">Post Type</th><th scope="col">Post Status</th></tr></thead>';
			echo '<tbody>';

			$id = min( $this->collisions );

			if ( isset( $_POST['supernetwork_post_collision_' . $id ] ) )
			{
				$this->__set( 'consolidated', false );
				
				foreach ( $this->blogs as $blog )
				{
					switch_to_blog( $blog->id );
					
					if ( empty( $GLOBALS['wpdb']->get_col( 'select ID from ' . $blog->table( 'posts' ) . ' where guid = \'' . $_POST['supernetwork_post_collision_' . $id ] . '\' limit 1' ) ) )
					{
						wp_delete_post( (int) $id, true );
					}

					restore_current_blog();
				}

				$this->__set( 'consolidated', true );
				$id = min( $this->collisions );
			}
			
			foreach ( $GLOBALS['wpdb']->get_results( 'select guid, post_title, substring(post_content, 1, 500) as post_preview, post_type, post_status from (' . $this->union( 'posts' ) . ') posts where ID = ' . $id, ARRAY_A ) as $site )
			{
				echo '<tr>';
				echo '<td><input type="radio" id="supernetwork__' . esc_textarea( $site['guid'] ) . '" value="' . esc_textarea( $site['guid'] ) . '" name="supernetwork_post_collision_' . $id . '"></td>';
				echo '<td><label for="supernetwork__' . esc_textarea( $site['guid'] ) . '">' . esc_textarea( $site['guid'] ) . '</label></td>';
				echo '<td><label for="supernetwork__' . esc_textarea( $site['guid'] ) . '">' . esc_textarea( $site['post_title'] ) . '</label></td>';
				echo '<td><label for="supernetwork__' . esc_textarea( $site['guid'] ) . '">' . esc_textarea( $site['post_preview'] ) . '</label></td>';
				echo '<td><label for="supernetwork__' . esc_textarea( $site['guid'] ) . '">' . esc_textarea( $site['post_type'] ) . '</label></td>';
				echo '<td><label for="supernetwork__' . esc_textarea( $site['guid'] ) . '">' . esc_textarea( $site['post_status'] ) . '</label></td>';
				echo '</tr>';
			}
	
			echo '</tbody></table>';
			submit_button( 'Keep Selected and Delete All Others' );
			echo '</form>';
		}
		else
		{
			echo '<h2>Republished Posts and Pages</h2>';
			$this->republished();
		}
		
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
		if ( $this->consolidated )
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
