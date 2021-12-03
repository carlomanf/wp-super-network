<?php
/**
 * Main network class.
 */
namespace WP_Super_Network;

class Network
{
	const ID_COLS = array(
		'comments' => 'comment_post_ID',
		'postmeta' => 'post_id',
		'posts' => 'ID',
		'term_relationships' => 'object_id'
	);

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
	private $blogs = array();

	/**
	 * Is this network on consolidated mode.
	 *
	 * @since 1.0.6
	 * @var bool
	 */
	private $consolidated = true;

	/**
	 * Settings page.
	 *
	 * @since 1.0.7
	 * @var Settings_Page
	 */
	private $page;

	private $parser;
	private $creator;

	private $collisions = array();
	private $republished = array();
	private $post_types = array();

	public function shared_auto_increment( $post_ID, $post, $update )
	{
		if ( !$update && $this->consolidated )
		{
			$this->set_auto_increment( $post_ID + 1 );
		}
	}

	private function set_auto_increment( $new )
	{
		foreach ( $this->blogs as $blog )
		{
			if ( $new > (int) $GLOBALS['wpdb']->get_var( 'select auto_increment from information_schema.tables where table_schema = \'' . DB_NAME . '\' and table_name = \'' . $blog->table( 'posts' ) . '\'' ) )
			{
				$GLOBALS['wpdb']->query( 'alter table ' . $blog->table( 'posts' ) . ' auto_increment = ' . (string) $new );
			}
		}
	}

	public function __get( $key )
	{
		if ( $key === 'consolidated' )
		{
			return $this->consolidated;
		}

		if ( $key === 'collisions' )
		{
			return $this->collisions;
		}
	}

	public function __set( $key, $value )
	{
		if ( $key === 'consolidated' )
		{
			if ( $value === true )
			{
				$this->consolidated = true;
			}
			
			if ( $value === false )
			{
				$this->consolidated = false;

				if ( !empty( $this->republished ) )
				{
					$this->set_auto_increment( $this->republished[0] + 1 );
				}
			}
		}
	}

	private function union( $table )
	{
		if ( !$this->consolidated && empty( $this->republished ) )
		{
			return $GLOBALS['wpdb']->__get( $table );
		}

		$tables = array();

		foreach ( $this->blogs as $blog )
		{
			$where = array();

			if ( $this->consolidated )
			{
				if ( !empty( $this->collisions ) )
				{
					$where[] = self::ID_COLS[ $table ] . ' not in (' . implode( ', ', $this->collisions ) . ')';
				}

				if ( $table === 'posts' && !empty( $this->post_types ) && !$blog->is_network() )
				{
					$where[] = 'post_type not in (\'' . implode( '\', \'', $this->post_types ) . '\')';
				}
			}
			else
			{
				if ( $blog->table( $table ) !== $GLOBALS['wpdb']->__get( $table ) )
				{
					$where[] = self::ID_COLS[ $table ] . ' in (' . implode( ', ', $this->republished ) . ')';
				}
			}

			$tables[] = 'select * from ' . $blog->table( $table ) . ( empty( $where ) ? '' : ( ' where ' . implode( ' and ', $where ) ) );
		}

		return implode( ' union ', $tables );
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
		foreach ( get_sites( 'network_id=' . $network->id ) as $site )
		{
			array_push( $this->blogs, new Blog( $site ) );
		}

		add_filter( 'admin_footer', array( $this, 'report_collisions' ) );

		$this->parser = new \PHPSQLParser\PHPSQLParser();
		$this->creator = new \PHPSQLParser\PHPSQLCreator();

		$this->collisions = $GLOBALS['wpdb']->get_col( 'select ID from (' . $this->union( 'posts' ) . ') posts group by ID having count(*) > 1 order by ID asc' );
		$this->republished = $GLOBALS['wpdb']->get_col( 'select post_id from (' . $this->union( 'postmeta' ) . ') postmeta where meta_key = \'_supernetwork_share\' order by post_id desc' );
		$this->post_types = array_keys( (array) get_option( 'supernetwork_post_types' ) );

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

			$id = $this->collisions[0];

			if ( isset( $_POST['supernetwork_post_collision_' . $id ] ) )
			{
				$this->consolidated = false;

				foreach ( $this->blogs as $blog )
				{
					switch_to_blog( $blog->id );
					
					if ( empty( $GLOBALS['wpdb']->get_col( 'select ID from ' . $blog->table( 'posts' ) . ' where guid = \'' . $_POST['supernetwork_post_collision_' . $id ] . '\' limit 1' ) ) )
					{
						wp_delete_post( (int) $id, true );
					}

					restore_current_blog();
				}

				$this->consolidated = true;

				array_shift( $this->collisions );
				$id = $this->collisions[0];
			}

			$old_collisions = $this->collisions;
			$old_post_types = $this->post_types;

			$this->collisions = array();
			$this->post_types = array();

			foreach ( $GLOBALS['wpdb']->get_results( 'select guid, post_title, substring(post_content, 1, 500) as post_preview, post_type, post_status from ' . $GLOBALS['wpdb']->posts . ' where ID = ' . $id, ARRAY_A ) as $site )
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

			$this->collisions = $old_collisions;
			$this->post_types = $old_post_types;

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
		$republished = $this->consolidate( 'posts_per_page=-1&meta_key=_supernetwork_share&post_type=any&post_status=any' );

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
		return $this->creator->create(
			$this->modify_query(
				$this->parser->parse( $query )
			)
		);
	}

	private function modify_query( $parsed )
	{
		if ( !isset( $parsed['INSERT'] ) && !isset( $parsed['UPDATE'] ) && !isset( $parsed['DELETE'] ) )
		{
			if ( isset( $parsed['UNION'] ) )
			{
				foreach ( $parsed['UNION'] as &$query )
				{
					$query = $this->modify_query( $query );
				}
			}
			else
			{
				if ( isset( $parsed['SELECT'] ) && isset( $parsed['FROM'] ) )
				{
					foreach ( $parsed['FROM'] as &$from )
					{
						if ( !empty( $from['sub_tree'] ) )
						{
							$from['sub_tree'] = $this->modify_query( $from['sub_tree'] );
						}
						else
						{
							foreach ( array_keys( self::ID_COLS ) as $table )
							{
								if ( isset( $from['table'] ) && $from['table'] === $GLOBALS['wpdb']->__get( $table ) )
								{
									if ( ( $union = $this->union( $table ) ) === $from['table'] )
									{
										continue;
									}

									$from['expr_type'] = 'subquery';
									$from['sub_tree'] = $this->parser->parse( $union );
									$from['alias'] = array(
										'as' => false,
										'name' => $GLOBALS['wpdb']->__get( $table ),
										'no_quotes' => array(
											'delim' => false,
											'parts' => array( $GLOBALS['wpdb']->__get( $table ) )
										),
										'base_expr' => $GLOBALS['wpdb']->__get( $table )
									);

									unset( $from['table'] );
								}
							}
						}
					}

					foreach ( array( 'WHERE', 'HAVING' ) as $clause )
					{
						if ( isset( $parsed[ $clause ] ) )
						{
							foreach ( $parsed[ $clause ] as &$subclause )
							{
								if ( !empty( $subclause['sub_tree'] ) )
								{
									$subclause['sub_tree'] = $this->modify_query( $subclause['sub_tree'] );
								}
							}
						}
					}
				}
			}
		}

		return $parsed;
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
