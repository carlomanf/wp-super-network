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

	/**
	 * Keeps track of ID collisions.
	 *
	 * @since 1.2.0
	 * @var array
	 */
	private $collisions = WP_Super_Network::ENTITIES_TO_REPLACE;

	/**
	 * Cache that maps ID's to blogs.
	 *
	 * @since 1.2.0
	 * @var array
	 */
	private $blog_cache = WP_Super_Network::ENTITIES_TO_REPLACE;

	private $republished = array();
	private $post_types = array();

	private $meta_ids = array();

	public function shared_auto_increment( $post_ID, $post, $update )
	{
		if ( !$update && $this->consolidated )
		{
			$this->set_auto_increment( $post_ID + 1 );
		}
	}

	public function auto_increment_comments( $id, $comment )
	{
		if ( $this->consolidated || in_array( (string) $comment->comment_post_ID, $this->republished, true ) )
		{
			$this->set_auto_increment( $id + 1, 'comments' );
		}
	}

	public function auto_increment_terms( $term_id, $tt_id, $taxonomy, $args )
	{
		if ( $this->consolidated )
		{
			$this->set_auto_increment( $tt_id + 1, 'term_taxonomy' );
			$this->set_auto_increment( $term_id + 1, 'terms' );
		}
	}

	public function auto_increment_term_relationships( $object_id, $tt_id, $taxonomy )
	{
		if ( !$this->consolidated && in_array( (string) $object_id, $this->republished, true ) )
		{
			$this->set_auto_increment( $tt_id + 1, 'term_taxonomy' );
			$term = get_term_by( 'term_taxonomy_id', $tt_id ) and $this->set_auto_increment( $term->term_id + 1, 'terms' );
		}
	}

	private function set_auto_increment( $new, $entity = 'posts' )
	{
		foreach ( $this->blogs as $blog )
		{
			if ( $new > (int) $GLOBALS['wpdb']->get_var( 'SELECT `auto_increment` FROM `information_schema`.`tables` WHERE `table_schema` = \'' . DB_NAME . '\' AND `table_name` = \'' . $blog->table( $entity ) . '\'' ) )
			{
				$GLOBALS['wpdb']->query( 'ALTER TABLE `' . $blog->table( $entity ) . '` AUTO_INCREMENT = ' . (string) $new );
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
		// No need to do anything if not consolidated and no republished posts.
		if ( !$this->consolidated && ( empty( $this->republished ) || empty( $post_cols = array_keys( WP_Super_Network::TABLES_TO_REPLACE[ $table ], 'posts', true ) ) ) )
		{
			return $GLOBALS['wpdb']->__get( $table );
		}

		$tables = array();

		foreach ( $this->blogs as $blog )
		{
			$where = array();
			$this->exclude( $where, $table, $blog );

			if ( !$this->consolidated )
			{
				// Add republished posts.
				if ( $blog->table( $table ) !== $GLOBALS['wpdb']->__get( $table ) )
				{
					foreach ( $post_cols as $col )
					{
						// Post parent is currently not being handled.
						$col === 'post_parent' or $where[] = '`' . $col . '` IN (' . implode( ', ', $this->republished ) . ')';
					}
				}
			}

			$tables[] = 'SELECT * FROM `' . $blog->table( $table ) . ( empty( $where ) ? '`' : ( '` WHERE ' . implode( ' AND ', $where ) ) );
		}

		return implode( ' UNION ALL ', $tables );
	}

	public function exclude( &$where, $table, $blog, $alias = '' )
	{
		if ( $this->consolidated )
		{
			empty( $alias ) or $alias .= '.';

			// Exclude any entities involved in collisions.
			foreach ( array_keys( $this->collisions ) as $entity )
			{
				if ( !empty( $this->collisions[ $entity ] ) )
				{
					foreach ( array_keys( WP_Super_Network::TABLES_TO_REPLACE[ $table ], $entity, true ) as $col )
					{
						$where[] = $alias . '`' . $col . '` NOT IN (' . implode( ', ', $this->collisions[ $entity ] ) . ')';
					}
				}
			}

			// Exclude network-based post types.
			if ( $table === 'posts' && !empty( $this->post_types ) && !$blog->is_network() )
			{
				$where[] = $alias . '`post_type` NOT IN (\'' . implode( '\', \'', $this->post_types ) . '\')';
			}
		}
	}

	public function report_collisions()
	{
		$term_collisions = count( $this->collisions['term_taxonomy'] ) + count( $this->collisions['terms'] );

		if ( $this->consolidated && ( !empty( $this->collisions['posts'] ) || $term_collisions > 0 || !empty( $this->collisions['comments'] ) ) && current_user_can( 'manage_network_options' ) )
		{
			echo '<div class="notice notice-error"><p><strong>WP Super Network detected ';

			echo empty( $this->collisions['posts'] ) ? '' : count( $this->collisions['posts'] ) . ' post ID collisions';

			if ( !empty( $this->collisions['posts'] ) && $term_collisions > 0 )
			{
				echo empty( $this->collisions['comments'] ) ? ' and ' : ', ';
			}

			echo $term_collisions > 0 ? $term_collisions . ' taxonomy term ID collisions' : '';

			if ( !empty( $this->collisions['comments'] ) )
			{
				echo ( !empty( $this->collisions['posts'] ) || $term_collisions > 0 ) ? ' and ' : '';
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
			'<strong>Warning:</strong> Consolidated mode is recommended for fresh networks. If you have pre-existing data (e.g. posts and pages) on your network, some of it may not be compatible with consolidated mode.'
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

	private function push_meta_ids( $meta_ids, $object_id )
	{
		$this->meta_ids[] = array( $meta_ids, $object_id );
	}

	public function meta_ids()
	{
		return empty( $this->meta_ids ) ? array() : $this->meta_ids[ count( $this->meta_ids ) - 1 ][0];
	}

	public function meta_object_id()
	{
		return empty( $this->meta_ids ) ? 0 : $this->meta_ids[ count( $this->meta_ids ) - 1 ][1];
	}

	public function pop_meta_ids()
	{
		array_pop( $this->meta_ids );
	}

	public function delete_meta( $meta_ids, $object_id )
	{
		$this->push_meta_ids( $meta_ids, $object_id );
		return $meta_ids;
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
		add_filter( 'wp_insert_comment', array( $this, 'auto_increment_comments' ), 10, 2 );
		add_filter( 'created_term', array( $this, 'auto_increment_terms' ), 10, 4 );
		add_filter( 'added_term_relationship', array( $this, 'auto_increment_term_relationships' ), 10, 3 );
		add_filter( 'admin_enqueue_scripts', array( $this, 'add_new_post' ) );
		add_filter( 'admin_footer', array( $this, 'report_collisions' ) );
		add_filter( 'delete_comment_meta', array( $this, 'delete_meta' ), 10, 2 );
		add_filter( 'delete_post_meta', array( $this, 'delete_meta' ), 10, 2 );
		add_filter( 'delete_term_meta', array( $this, 'delete_meta' ), 10, 2 );

		// Comments must be queried before posts so as not to mask any comment ID collisions.
		// Taxonomy terms must be queried before terms so as not to mask any taxonomy term ID collisions.
		foreach ( array_keys( $this->collisions ) as $entity )
		{
			// `ID` comes before `post_parent` in the `posts` sub-array, so `ID` will be correctly returned.
			$id = array_search( $entity, WP_Super_Network::TABLES_TO_REPLACE[ $entity ], true );

			$this->collisions[ $entity ] = $GLOBALS['wpdb']->get_col( 'SELECT `' . $id . '` FROM `' . $GLOBALS['wpdb']->__get( $entity ) . '` GROUP BY `' . $id . '` HAVING COUNT(*) > 1 ORDER BY `' . $id . '` ASC' );
		}

		$consolidated = !empty( get_option( 'supernetwork_consolidated', array() )['consolidated'] );

		if ( $consolidated )
		{
			$this->post_types = array_keys( get_option( 'supernetwork_post_types', array() ) );
		}
		else
		{
			$extra_where = '';
			$extra_where_2 = '';
			$relationships = $GLOBALS['wpdb']->term_relationships;
			$taxonomy = $GLOBALS['wpdb']->term_taxonomy;

			// The `AND 1=1` is to avoid a bug in the SQL parser caused by consecutive brackets.
			empty( $this->collisions['comments'] ) or $extra_where .= ' AND `post_id` NOT IN (SELECT `comment_post_ID` FROM `' . $GLOBALS['wpdb']->comments . '` WHERE `comment_ID` IN (' . implode( ', ', $this->collisions[ 'comments' ] ) . ') AND 1=1)';

			if ( !empty( $this->collisions['term_taxonomy'] ) )
			{
				$extra_where .= ' AND `post_id` NOT IN (SELECT `object_id` FROM `' . $relationships . '` WHERE `term_taxonomy_id` IN (' . implode( ', ', $this->collisions[ 'term_taxonomy' ] ) . ') AND 1=1)';
				$extra_where_2 = ' AND `' . $relationships . '`.`term_taxonomy_id` NOT IN (' . implode( ', ', $this->collisions[ 'term_taxonomy' ] ) . ')';
			}

			empty( $this->collisions['terms'] ) or $extra_where .= ' AND `post_id` NOT IN (SELECT `object_id` FROM `' . $relationships . '` LEFT JOIN `' . $taxonomy . '` ON `' . $relationships . '`.`term_taxonomy_id` = `' . $taxonomy . '`.`term_taxonomy_id` WHERE `term_id` IN (' . implode( ', ', $this->collisions[ 'terms' ] ) . ')' . $extra_where_2 . ' AND 1=1)';

			// This query requires post collisions, but not the other collisions, to be recorded.
			$old_comment_collisions = $this->collisions['comments'];
			$old_taxonomy_term_collisions = $this->collisions['term_taxonomy'];
			$old_term_collisions = $this->collisions['terms'];

			foreach ( array( 'comments', 'term_taxonomy', 'terms' ) as $entity ) $this->collisions[ $entity ] = array();

			$this->republished = $GLOBALS['wpdb']->get_col( 'SELECT DISTINCT `post_id` FROM `' . $GLOBALS['wpdb']->postmeta . '` WHERE `meta_key` = \'_supernetwork_share\'' . $extra_where . ' ORDER BY `post_id` DESC' );

			$this->collisions['comments'] = $old_comment_collisions;
			$this->collisions['term_taxonomy'] = $old_taxonomy_term_collisions;
			$this->collisions['terms'] = $old_term_collisions;
		}

		$this->consolidated = $consolidated;

		if ( !$this->consolidated && !empty( $this->republished ) )
		{
			$this->set_auto_increment( $this->republished[0] + 1 );
		}
	}

	/**
	 * Selects an entity type to be first in line for collision elimination, based on a calculated score.
	 *
	 * The score for each entity type is the median ID of its collisions, divided by the median ID overall.
	 * A score of 1 means the collisions are evenly distributed.
	 * A lower score means the collisions are concentrated on the lower ID's, which are more likely to be older entities with more references and importance.
	 *
	 * The entity type with the lowest score is selected for collision elimination.
	 *
	 * @since 1.2.0
	 */
	private function get_entity_by_median_score()
	{
		// Temporarily empty collisions.
		$old = $this->collisions;
		$this->collisions = WP_Super_Network::ENTITIES_TO_REPLACE;
		$scores = WP_Super_Network::ENTITIES_TO_REPLACE;

		foreach ( $scores as $entity => &$score )
		{
			// `ID` comes before `post_parent` in the `posts` sub-array, so `ID` will be correctly returned.
			$id = array_search( $entity, WP_Super_Network::TABLES_TO_REPLACE[ $entity ], true );

			$collisions = $GLOBALS['wpdb']->get_col( 'SELECT `' . $id . '` FROM `' . $GLOBALS['wpdb']->__get( $entity ) . '` WHERE `' . $id . '` IN (SELECT `' . $id . '` FROM `' . $GLOBALS['wpdb']->__get( $entity ) . '` GROUP BY `' . $id . '` HAVING COUNT(*) > 1) ORDER BY `' . $id . '` ASC' );
			$all = $GLOBALS['wpdb']->get_col( 'SELECT `' . $id . '` FROM `' . $GLOBALS['wpdb']->__get( $entity ) . '` ORDER BY `' . $id . '` ASC' );

			$score = empty( $collisions ) ? PHP_FLOAT_MAX : $collisions[ floor( count( $collisions ) / 2 ) ] / $all[ floor( count( $all ) / 2 ) ];
		}

		$this->collisions = $old;
		return array_search( min( $scores ), $scores );
	}

	/**
	 * Force deletes a term from a taxonomy.
	 *
	 * @since 1.2.0
	 */
	private function force_delete_taxonomy_term( $term_id, $taxonomy )
	{
		// Temporarily register taxonomy to force deletion.
		if ( !( $exists = taxonomy_exists( $taxonomy ) ) ) register_taxonomy( $taxonomy, array() );

		// 0 is returned if the default term was not deleted.
		$success = 0 !== wp_delete_term( $term_id, $taxonomy );

		if ( !$exists ) unregister_taxonomy( $taxonomy );
		return $success;
	}

	public function page()
	{
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">WP Super Network</h1>';

		if ( $this->consolidated )
		{
		$entity = $this->get_entity_by_median_score();

		$this->consolidated = false;

			echo '<h2>ID Collisions</h2>';

		if ( !empty( $this->collisions[ $entity ] ) && isset( $_POST[ 'supernetwork_' . $entity . '_collision_' . $this->collisions[ $entity ][0] ] ) )
		{
			$success = true;

			foreach ( $this->blogs as $blog )
			{
				if ( $blog->id !== (int) explode( '_', $_POST[ 'supernetwork_' . $entity . '_collision_' . $this->collisions[ $entity ][0] ] )[0] )
				{
					switch_to_blog( $blog->id );

					if ( $entity === 'comments' ) wp_delete_comment( (int) $this->collisions['comments'][0], true );
					if ( $entity === 'posts' ) wp_delete_post( (int) $this->collisions['posts'][0], true );

					if ( $entity === 'term_taxonomy' )
					{
						$term = $GLOBALS['wpdb']->get_row( 'SELECT `taxonomy`, `term_id` FROM `' . $GLOBALS['wpdb']->term_taxonomy . '` WHERE `term_taxonomy_id` = ' . $this->collisions['term_taxonomy'][0], ARRAY_A );

						if ( isset( $term['term_id'] ) && isset( $term['taxonomy'] ) )
						{
							$success = $this->force_delete_taxonomy_term( (int) $term['term_id'], $term['taxonomy'] ) && $success;
						}
					}

					if ( $entity === 'terms' )
					{
						$results = $GLOBALS['wpdb']->get_results( 'SELECT `taxonomy`, `term_id` FROM `' . $GLOBALS['wpdb']->term_taxonomy . '` WHERE `term_id` = ' . $this->collisions['terms'][0] );

						foreach ( $results as $result )
						{
							$success = $this->force_delete_taxonomy_term( (int) $result['term_id'], $result['taxonomy'] ) && $success;
						}
					}

					restore_current_blog();
				}
			}

			// Start again if collision was eliminated.
			if ( $success )
			{
				array_shift( $this->collisions[ $entity ] );
				$this->consolidated = true;
				$entity = $this->get_entity_by_median_score();
				$this->consolidated = false;
			}
		}

		if ( empty( $this->collisions[ $entity ] ) )
		{
			echo '<p>No collisions on this network!</p>';
		}
		else
		{
			// `ID` comes before `post_parent` in the `posts` sub-array, so `ID` will be correctly returned.
			$id = array_search( $entity, WP_Super_Network::TABLES_TO_REPLACE[ $entity ], true );
			$id = 'terms' === $entity ? 't.' . $id : $id;

			$fields = array(
				'comments' => array(
					'post_title',
					'comment_content',
					'comment_author',
					'comment_author_email'
				),
				'posts' => array(
					'post_title',
					'post_content',
					'post_type',
					'post_status'
				),
				'term_taxonomy' => array(
					'name',
					'description',
					'taxonomy',
					'count'
				),
				'terms' => array(
					'name',
					'description',
					'taxonomy',
					'count'
				)
			);

			$labels = array(
				'comments' => array(
					'Site Containing Post/Comment',
					'Post Containing Comment',
					'Comment Preview',
					'Comment Author',
					'Comment Author Email'
				),
				'posts' => array(
					'Site Containing Post',
					'Post Title',
					'Post Preview',
					'Post Type',
					'Post Status'
				),
				'term_taxonomy' => array(
					'Site Containing Taxonomy Term',
					'Taxonomy Term Name',
					'Taxonomy Term Description',
					'Taxonomy',
					'Post Count'
				),
				'terms' => array(
					'Site Containing Taxonomy Term',
					'Taxonomy Term Name',
					'Taxonomy Term Description',
					'Taxonomy',
					'Post Count'
				)
			);

			echo '<p>Consolidated mode is designed for fresh networks. When activated on an existing network, a large number of ID collisions are inevitable. However, you may be able to eliminate some collisions when the ID refers to a post of low importance, such as a revision or autosave.</p>';
			echo '<p>The below tables allow you to eliminate post, taxonomy term and comment ID collisions, one ID at a time. For each ID, you must select just ONE entity to keep. All others with the same ID will be immediately and irretrievably deleted.</p>';

			if ( in_array( $entity, array( 'term_taxonomy', 'terms' ), true ) ) echo '<div class="notice notice-warning inline"><p><strong>Note:</strong> Default taxonomy terms can not be deleted! If a default taxonomy term is in a collision, you must go to Writing Settings and select a new default term for the taxonomy before you can eliminate the collision.</p></div>';

			echo '<form method="post" action="">';
			echo '<table class="widefat">';
			echo '<thead><tr><th scope="col">Keep?</th><th scope="col">' . $labels[ $entity ][0] . '</th><th scope="col">' . $labels[ $entity ][1] . '</th><th scope="col">' . $labels[ $entity ][2] . '</th><th scope="col">' . $labels[ $entity ][3] . '</th><th scope="col">' . $labels[ $entity ][4] . '</th></tr></thead>';
			echo '<tbody>';

			foreach ( $this->blogs as $blog )
			{
				$tables = array(
					'comments' => $blog->table( 'comments' ) . ' LEFT JOIN ' . $blog->table( 'posts' ) . ' ON comment_post_ID = ID',
					'posts' => $blog->table( 'posts' ),
					'term_taxonomy' => $blog->table( 'term_taxonomy' ) . ' tt LEFT JOIN ' . $blog->table( 'terms' ) . ' t ON tt.term_id = t.term_id',
					'terms' => $blog->table( 'term_taxonomy' ) . ' tt LEFT JOIN ' . $blog->table( 'terms' ) . ' t ON tt.term_id = t.term_id'
				);

				$rows = $GLOBALS['wpdb']->get_results( 'SELECT `' . $fields[ $entity ][0] . '`, SUBSTRING(`' . $fields[ $entity ][1] . '`, 1, 500) AS `' . $fields[ $entity ][1] . '_preview`, `' . $fields[ $entity ][2] . '`, `' . $fields[ $entity ][3] . '` FROM ' . $tables[ $entity ] . ' WHERE `' . $id . '` = ' . $this->collisions[ $entity ][0], ARRAY_A );

				// There may be more than one row if terms are not split.
				foreach ( $rows as $key => $row )
				{
					echo '<tr>';
					echo '<td><input type="radio" id="supernetwork__' . esc_attr( $blog->id ) . '_' . $key . '" value="' . esc_attr( $blog->id ) . '" name="supernetwork_' . $entity . '_collision_' . $this->collisions[ $entity ][0] . '"></td>';
					echo '<td><label for="supernetwork__' . esc_attr( $blog->id ) . '_' . $key . '">' . esc_html( $blog->name ) . '</label></td>';
					echo '<td><label for="supernetwork__' . esc_attr( $blog->id ) . '_' . $key . '">' . esc_html( $row[ $fields[ $entity ][0] ] ) . '</label></td>';
					echo '<td><label for="supernetwork__' . esc_attr( $blog->id ) . '_' . $key . '">' . esc_html( $row[ $fields[ $entity ][1] . '_preview' ] ) . '</label></td>';
					echo '<td><label for="supernetwork__' . esc_attr( $blog->id ) . '_' . $key . '">' . esc_html( $row[ $fields[ $entity ][2] ] ) . '</label></td>';
					echo '<td><label for="supernetwork__' . esc_attr( $blog->id ) . '_' . $key . '">' . esc_html( $row[ $fields[ $entity ][3] ] ) . '</label></td>';
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
		if ( !doing_filter( 'supernetwork_preview_link' ) && !is_null( $blog = $this->get_blog( $post_ID ) ) )
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
		if ( $wp_query->is_singular() && !is_admin() && ( !defined( 'REST_REQUEST' ) || !REST_REQUEST ) && !is_preview() && isset( $wp_query->post ) && !is_null( $this->get_blog( $wp_query->post->ID ) ) )
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
		if ( is_preview() && !is_null( $blog = $this->get_blog( get_the_ID() ) ) )
		{
			switch_to_blog( $blog->id );
		}
	}

	public function get_blog( $id, $entity = 'posts' )
	{
		if ( in_array( $entity, array_keys( WP_Super_Network::ENTITIES_TO_REPLACE ), true ) && !in_array( (string) $id, $this->collisions[ $entity ], true ) && ( $this->consolidated || !empty( $this->republished ) && $entity !== 'posts' || in_array( (string) $id, $this->republished, true ) ) )
		{
			if ( isset( $this->blog_cache[ $entity ][ $id ] ) ) return get_current_blog_id() === $this->blog_cache[ $entity ][ $id ]->id ? null : $this->blog_cache[ $entity ][ $id ];

			$republished = null;
			$extra_where = '';

			// `ID` comes before `post_parent` in the `posts` sub-array, so `ID` will be correctly returned.
			$col = array_search( $entity, WP_Super_Network::TABLES_TO_REPLACE[ $entity ], true );
			$select = $entity === 'posts' ? 'post_type' : $col;

			$old_consolidated = $this->consolidated;
			$old_republished = $this->republished;

			$this->consolidated = false;
			$this->republished = array();

			foreach ( $this->blogs as $blog )
			{
				if ( !$old_consolidated )
				{
					isset( $republished ) or $republished = '(' . implode( ', ', $old_republished ) . ')';

					// The `AND 1=1` is to avoid a bug in the SQL parser caused by consecutive brackets.
					$entity !== 'comments' or $extra_where = ' AND `comment_post_ID` IN ' . $republished . ' AND 1=1';
					$entity !== 'term_taxonomy' or $extra_where = ' AND EXISTS (SELECT * FROM `' . $blog->table( 'term_relationships' ) . '` WHERE `term_taxonomy_id` = ' . $id . ' AND `object_id` IN ' . $republished . ' AND 1=1)';
					$entity !== 'terms' or $extra_where = ' AND EXISTS (SELECT * FROM `' . $blog->table( 'term_relationships' ) . '` WHERE `term_taxonomy_id` IN (SELECT `term_taxonomy_id` FROM ' . $blog->table( 'term_taxonomy' ) . ' WHERE `term_id` = ' . $id . ') AND `object_id` IN ' . $republished . ' AND 1=1)';
				}

				$result = $GLOBALS['wpdb']->get_var( 'SELECT `' . $select . '` FROM `' . $blog->table( $entity ) . '` WHERE `' . $col . '` = ' . $id . $extra_where . ' LIMIT 1' );

				if ( !empty( $result ) )
				{
					$this->consolidated = $old_consolidated;
					$this->republished = $old_republished;

					$this->blog_cache[ $entity ][ $id ] = $this->consolidated && in_array( $result, $this->post_types, true ) && !$blog->is_network() ? null : $blog;
					return get_current_blog_id() === $blog->id ? null : $this->blog_cache[ $entity ][ $id ];
				}
			}

			$this->consolidated = $old_consolidated;
			$this->republished = $old_republished;
		}

		return $this->blog_cache[ $entity ][ $id ] = null;
	}

	public function get_blog_by_id( $id )
	{
		return isset( $this->blogs[ $id ] ) ? $this->blogs[ $id ] : null;
	}
}
