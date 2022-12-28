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

	private $collisions = array( 'posts' => array(), 'term_taxonomy' => array(), 'comments' => array() );
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
			if ( $new > (int) $GLOBALS['wpdb']->get_var( 'SELECT `auto_increment` FROM `information_schema`.`tables` WHERE `table_schema` = \'' . DB_NAME . '\' AND `table_name` = \'' . $blog->table( 'posts' ) . '\'' ) )
			{
				$GLOBALS['wpdb']->query( 'ALTER TABLE `' . $blog->table( 'posts' ) . '` AUTO_INCREMENT = ' . (string) $new );
			}
		}
	}

	public function __get( $key )
	{
		if ( $key === 'consolidated' )
		{
			return $this->consolidated;
		}

		if ( $key === 'post_collisions' )
		{
			return $this->collisions['posts'];
		}

		if ( $key === 'term_collisions' )
		{
			return $this->collisions['term_taxonomy'];
		}

		if ( $key === 'comment_collisions' )
		{
			return $this->collisions['comments'];
		}

		if ( $key === 'republished' )
		{
			return $this->republished;
		}

		if ( $key === 'post_types' )
		{
			return $this->post_types;
		}
	}

	public function union( $table )
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
				if ( !empty( $this->collisions['posts'] ) )
				{
					foreach ( array_keys( WP_Super_Network::TABLES_TO_REPLACE, $table, true ) as $col )
					{
						$where[] = '`' . $col . '` NOT IN (' . implode( ', ', $this->collisions['posts'] ) . ')';
					}
				}

				if ( $table === 'posts' && !empty( $this->post_types ) && !$blog->is_network() )
				{
					$where[] = '`post_type` NOT IN (\'' . implode( '\', \'', $this->post_types ) . '\')';
				}
			}
			else
			{
				if ( $blog->table( $table ) !== $GLOBALS['wpdb']->__get( $table ) )
				{
					foreach ( array_keys( WP_Super_Network::TABLES_TO_REPLACE, $table, true ) as $col )
					{
						$where[] = '`' . $col . '` IN (' . implode( ', ', $this->republished ) . ')';
					}
				}
			}

			$tables[] = 'SELECT * FROM `' . $blog->table( $table ) . ( empty( $where ) ? '`' : ( '` WHERE ' . implode( ' AND ', $where ) ) );
		}

		return implode( ' UNION ALL ', $tables );
	}

	public function report_collisions()
	{
		if ( $this->consolidated && ( !empty( $this->collisions['posts'] ) || !empty( $this->collisions['term_taxonomy'] ) || !empty( $this->collisions['comments'] ) ) && current_user_can( 'manage_network_options' ) )
		{
			echo '<div class="notice notice-error"><p><strong>WP Super Network detected ';

			echo empty( $this->collisions['posts'] ) ? '' : count( $this->collisions['posts'] ) . ' post ID collisions';

			if ( !empty( $this->collisions['posts'] ) && !empty( $this->collisions['term_taxonomy'] ) )
			{
				echo empty( $this->collisions['comments'] ) ? ' and ' : ', ';
			}

			echo empty( $this->collisions['term_taxonomy'] ) ? '' : count( $this->collisions['term_taxonomy'] ) . ' term ID collisions';

			if ( !empty( $this->collisions['comments'] ) )
			{
				echo ( !empty( $this->collisions['posts'] ) || !empty( $this->collisions['term_taxonomy'] ) ) ? ' and ' : '';
				echo count( $this->collisions['comments'] ) . ' comment ID collisions';
			}

			echo '</strong> across your network! These entities have been temporarily hidden. To access them again, please turn off consolidated mode.</p></div>';
		}
	}

	/**
	 * Constructor.
	 *
	 * Queries sent during this method do not get modified, as the filter is applied later.
	 *
	 * @since 1.0.4
	 *
	 * @param WP_Network
	 */
	public function __construct( $network )
	{
		foreach ( get_sites( 'network_id=' . $network->id ) as $site )
		{
			$this->blogs[ (int) $site->blog_id ] = new Blog( $site );
		}

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
		$results = $wpdb->get_results( "SELECT DISTINCT `option_name` FROM $wpdb->options WHERE `option_name` NOT LIKE '\_%' AND `option_name` NOT LIKE 'supernetwork\_%' ORDER BY `option_name`" );
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

	public function add_new_post( $hook_suffix )
	{
		$post_type = empty( $_GET['post_type'] ) ? 'post' : $_GET['post_type'];

		if ( $hook_suffix === 'edit.php' && $this->consolidated && !in_array( $post_type, $this->post_types, true ) )
		{
			wp_enqueue_script(
				'supernetwork-post-new',
				SUPER_NETWORK_URL . 'assets/js/post-new.js',
				array(),
				null,
				true
			);

			$blogs = array();
			$type = get_post_type_object( $post_type );
			$edit = isset( $post_type ) ? $type->cap->edit_posts : 'do_not_allow';
			$create = isset( $post_type ) ? $type->cap->create_posts : 'do_not_allow';

			foreach ( $this->blogs as $blog )
			{
				$id = $blog->id;

				if ( current_user_can_for_blog( $id, $edit ) && current_user_can_for_blog( $id, $create ) )
				{
					$blogs[ $id ] = $blog->name;
				}
			}

			wp_add_inline_script(
				'supernetwork-post-new',
				'var blogs = ' . json_encode( $blogs ) . '; var currentId = "' . get_current_blog_id() . '";',
				'before'
			);
		}
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

	public function register()
	{
		$this->page->register();

		add_filter( 'post_type_link', array( $this, 'intercept_permalink_for_post' ), 10, 2 );
		add_filter( 'post_link', array( $this, 'intercept_permalink_for_post' ), 10, 2 );
		add_filter( 'page_link', array( $this, 'intercept_permalink' ), 10, 2 );
		add_filter( 'preview_post_link', array( $this, 'intercept_preview_link' ), 10, 2 );
		add_filter( 'supernetwork_preview_link', array( $this, 'replace_preview_link' ), 10, 2 );
		add_filter( 'user_has_cap', array( $this, 'intercept_capability' ), 10, 4 );
		add_filter( 'pre_handle_404', array( $this, 'singular_access' ), 10, 2 );
		add_action( 'wp', array( $this, 'preview_access' ) );
		add_filter( 'query', array( $this, 'intercept_query' ), 10, 2 );
		add_filter( 'wp_insert_post', array( $this, 'shared_auto_increment' ), 10, 3 );
		add_filter( 'admin_enqueue_scripts', array( $this, 'add_new_post' ) );
		add_filter( 'admin_footer', array( $this, 'report_collisions' ) );

		// Comments must be queried before posts so as not to mask any comment ID collisions.
		foreach ( array( 'comments' => 'comment_ID', 'term_taxonomy' => 'term_taxonomy_id', 'posts' => 'ID' ) as $entity => $id )
		{
			$this->collisions[ $entity ] = $GLOBALS['wpdb']->get_col( 'SELECT `' . $id . '` FROM (' . $this->union( $entity ) . ') `' . $entity . '` GROUP BY `' . $id . '` HAVING COUNT(*) > 1 ORDER BY `' . $id . '` ASC' );
		}

		$this->republished = $GLOBALS['wpdb']->get_col( 'SELECT `post_id` FROM (' . $this->union( 'postmeta' ) . ') `postmeta` WHERE `meta_key` = \'_supernetwork_share\' ORDER BY `post_id` DESC' );
		$this->post_types = array_keys( get_option( 'supernetwork_post_types', array() ) );

		$this->consolidated = !empty( get_option( 'supernetwork_consolidated', array() )['consolidated'] );

		if ( !$this->consolidated && !empty( $this->republished ) )
		{
			$this->set_auto_increment( $this->republished[0] + 1 );
		}
	}

	public function page()
	{
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">WP Super Network</h1>';

		if ( $this->consolidated )
		{
			$this->consolidated = false;

			echo '<h2>Post ID Collisions</h2>';

			if ( !empty( $this->collisions['posts'] ) && isset( $_POST[ 'supernetwork_post_collision_' . $this->collisions['posts'][0] ] ) )
			{
				foreach ( $this->blogs as $blog )
				{
					if ( $blog->id !== (int) $_POST[ 'supernetwork_post_collision_' . $this->collisions['posts'][0] ] )
					{
						switch_to_blog( $blog->id );
						wp_delete_post( (int) $this->collisions['posts'][0], true );
						restore_current_blog();
					}
				}

				array_shift( $this->collisions['posts'] );
			}

			if ( empty( $this->collisions['posts'] ) )
			{
				echo '<p>No collisions on this network!</p>';
			}
			else
			{
				echo '<p>Consolidated mode is designed for fresh networks. When activated on an existing network, a large number of ID collisions are inevitable. However, you may be able to eliminate some collisions when the ID refers to a post of low importance, such as a revision or autosave.</p>';
				echo '<p>The below tables allow you to eliminate post, term and comment ID collisions, one ID at a time. For each ID, you must select just ONE entity to keep. All others with the same ID will be immediately and irretrievably deleted.</p>';

				echo '<form method="post" action="">';
				echo '<table class="widefat">';
				echo '<thead><tr><th scope="col">Keep?</th><th scope="col">Site Containing Post</th><th scope="col">Post Title</th><th scope="col">Post Preview</th><th scope="col">Post Type</th><th scope="col">Post Status</th></tr></thead>';
				echo '<tbody>';

				foreach ( $this->blogs as $blog )
				{
					$row = $GLOBALS['wpdb']->get_row( 'SELECT `post_title`, SUBSTRING(`post_content`, 1, 500) AS `post_preview`, `post_type`, `post_status` FROM `' . $blog->table( 'posts' ) . '` WHERE `ID` = ' . $this->collisions['posts'][0], ARRAY_A );

					if ( !empty( $row ) )
					{
						echo '<tr>';
						echo '<td><input type="radio" id="supernetwork__' . esc_attr( $blog->id ) . '" value="' . esc_attr( $blog->id ) . '" name="supernetwork_post_collision_' . $this->collisions['posts'][0] . '"></td>';
						echo '<td><label for="supernetwork__' . esc_attr( $blog->id ) . '">' . esc_html( $blog->name ) . '</label></td>';
						echo '<td><label for="supernetwork__' . esc_attr( $blog->id ) . '">' . esc_html( $row['post_title'] ) . '</label></td>';
						echo '<td><label for="supernetwork__' . esc_attr( $blog->id ) . '">' . esc_html( $row['post_preview'] ) . '</label></td>';
						echo '<td><label for="supernetwork__' . esc_attr( $blog->id ) . '">' . esc_html( $row['post_type'] ) . '</label></td>';
						echo '<td><label for="supernetwork__' . esc_attr( $blog->id ) . '">' . esc_html( $row['post_status'] ) . '</label></td>';
						echo '</tr>';
					}
				}

				echo '</tbody></table>';
				submit_button( 'Keep Selected and Delete All Others' );
				echo '</form>';
			}

			$this->consolidated = true;
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
		$cache = wp_cache_get( 'supernetwork_queries', $query );

		if ( $cache )
		{
			return $cache->transformed;
		}
		else
		{
			$transformed = new Query( $query, $this );
			wp_cache_set( 'supernetwork_queries', $transformed, $query );
			return $transformed->transformed;
		}
	}

	public function intercept_permalink( $permalink, $post_ID )
	{
		if ( !doing_filter( 'supernetwork_preview_link' ) && !is_null( $blog = $this->get_blog( $post_ID ) ) && $blog->id !== get_current_blog_id() )
		{
			switch_to_blog( $blog->id );
			$permalink = get_permalink( $post_ID );
			restore_current_blog();
		}

		return $permalink;
	}

	public function intercept_permalink_for_post( $permalink, $post )
	{
		return $this->intercept_permalink( $permalink, $post->ID );
	}

	public function intercept_preview_link( $preview_link, $post )
	{
		return doing_filter( 'supernetwork_preview_link' ) ? $preview_link : apply_filters( 'supernetwork_preview_link', $preview_link, $post );
	}

	public function replace_preview_link( $preview_link, $post )
	{
		$query_args = array();
		parse_str( parse_url( $preview_link, PHP_URL_QUERY ), $query_args );
		return get_preview_post_link( $post, array_intersect_key( $query_args, array( 'preview_nonce' => null, 'preview_id' => null ) ) );
	}

	public function intercept_capability( $allcaps, $caps, $args, $user )
	{
		if ( in_array( $args[0], array( 'delete_post', 'edit_post', 'publish_post', 'read_post' ), true ) )
		{
			if ( !is_null( $blog = $this->get_blog( get_post( $args[2] )->ID ) ) )
			{
				return ( new \WP_User( $user, '', $blog->id ) )->allcaps;
			}
		}

		return $allcaps;
	}

	public function singular_access( $false, $wp_query )
	{
		if ( $wp_query->is_singular() && !is_admin() && ( !defined( 'REST_REQUEST' ) || !REST_REQUEST ) && !is_preview() && isset( $wp_query->post ) && !is_null( $blog = $this->get_blog( $wp_query->post->ID ) ) && get_current_blog_id() !== $blog->id )
		{
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return true;
		}

		return $false;
	}

	public function preview_access()
	{
		if ( is_preview() && !is_null( $blog = $this->get_blog( get_the_ID() ) ) && get_current_blog_id() !== $blog->id )
		{
			switch_to_blog( $blog->id );
		}
	}

	public function get_blog( $id, $entity = 'posts' )
	{
		if ( $this->consolidated && !in_array( (string) $id, $this->collisions[ $entity ], true ) )
		{
			$old_republished = $this->republished;

			$this->consolidated = false;
			$this->republished = array();

			foreach ( $this->blogs as $blog )
			{
				if ( !empty( $GLOBALS['wpdb']->get_col( 'SELECT `ID` FROM `' . $blog->table( $entity ) . '` WHERE `ID` = ' . $id . ' LIMIT 1' ) ) )
				{
					$this->consolidated = true;
					$this->republished = $old_republished;

					return $blog;
				}
			}

			$this->consolidated = true;
			$this->republished = $old_republished;
		}

		return null;
	}

	public function get_blog_by_id( $id )
	{
		return isset( $this->blogs[ $id ] ) ? $this->blogs[ $id ] : null;
	}
}
