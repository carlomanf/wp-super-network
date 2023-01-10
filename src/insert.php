<?php
/**
 * SQL table node class.
 */
namespace WP_Super_Network;

class SQL_Table_For_Insert extends SQL_Node
{
	/**
	 * Constructor.
	 * Replaces the table with another table, depending on the values being inserted.
	 *
	 * @since 1.2.0
	 *
	 * @param array $node SQL parse tree for this table.
	 * @param WP_Super_Network\Query $query Query context for this table.
	 */
	public function __construct( $node, $query )
	{
		parent::__construct( $node );

		$table = array_reverse( $node['no_quotes']['parts'] )[0];
		$is_posts_table = $GLOBALS['wpdb']->__get( 'posts' ) === $table;

		$table_to_replace = null;
		$entity_to_replace = null;
		$position_to_check = null;
		$post_type_position = null;
		$replaced_blog = null;

		if ( is_array( $query->column_list ) )
		{
			foreach ( $query->column_list['sub_tree'] as $key => $colref )
			{
				if ( $colref['expr_type'] === 'colref' )
				{
					$col = array_reverse( $colref['no_quotes']['parts'] )[0];

					// If it's a replaceable column, note the table to replace.
					foreach ( WP_Super_Network::TABLES_TO_REPLACE as $table_schema => $tables )
					{
						if ( isset( $tables[ $col ] ) && ( $table_schema !== $tables[ $col ] || $col === 'post_parent' ) && $GLOBALS['wpdb']->__get( $table_schema ) === $table )
						{
							$table_to_replace = $table_schema;
							$entity_to_replace = $tables[ $col ];
							$position_to_check = $key;
							break;
						}
					}

					// Note the position of the post type column.
					if ( 'post_type' === $col && $is_posts_table )
					{
						$post_type_position = $key;
					}

					// If all data is collected, stop scanning columns.
					if ( isset( $position_to_check ) && ( isset( $post_type_position ) || !$is_posts_table ) )
					{
						break;
					}
				}
			}
		}

		if ( isset( $query->parsed['VALUES'] ) )
		{
			foreach ( $query->parsed['VALUES'] as $record )
			{
				if ( $record['expr_type'] === 'record' )
				{
					// Ensure posts table is replaced for network-based post types.
					if ( $is_posts_table && isset( $post_type_position ) && $query->network->consolidated && in_array( substr( $record['data'][ $post_type_position ]['base_expr'], 1, -1 ), $query->network->post_types, true ) && ( $main = get_main_site_id() ) > 0 )
					{
						$blog_to_replace = new Blog( \WP_Site::get_instance( $main ) );
					}
					else
					{
						// Replace blog if a replaceable column was found.
						if ( isset( $entity_to_replace ) && isset( $position_to_check ) && isset( $record['data'][ $position_to_check ] ) )
						{
							$blog_to_replace = $query->network->get_blog( (int) $record['data'][ $position_to_check ]['base_expr'], $entity_to_replace );
						}
						else
						{
							$blog_to_replace = $query->network->get_blog_by_id( get_current_blog_id() );
						}
					}

					if ( !isset( $replaced_blog ) )
					{
						$replaced_blog = $blog_to_replace;
					}
					else
					{
						// If two records need to be inserted into different blogs, the query can't be transformed.
						if ( $blog_to_replace->id !== $replaced_blog->id )
						{
							return;
						}
					}
				}
			}
		}

		// Replace table for arbitrary post creation.
		if ( !isset( $replaced_blog ) && $is_posts_table && $query->network->consolidated && isset( $_GET['blog_id'] ) && did_action( 'load-post-new.php' ) )
		{
			$replaced_blog = $query->network->get_blog_by_id( (int) $_GET['blog_id'] );
		}

		// Replace the blog.
		if ( isset( $replaced_blog ) && $replaced_blog->id !== get_current_blog_id() && isset( $table_to_replace ) )
		{
			$this->transformed['table'] = $replaced_blog->table( $table_to_replace );

			$this->transformed['no_quotes'] = array(
				'delim' => false,
				'parts' => array( $this->transformed['table'] )
			);

			$this->transformed['base_expr'] = $this->transformed['table'];

			$this->modified = true;
		}
	}
}
