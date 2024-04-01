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
		$is_relationships_table = $GLOBALS['wpdb']->__get( 'term_relationships' ) === $table;

		$table_to_replace = null;
		$entities_to_replace = array();
		$post_type_position = null;

		$suggestion = new Suggestion();

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
							$entities_to_replace[ $tables[ $col ] ] = $key;
							break;
						}
					}

					// Note the position of the post type column.
					if ( 'post_type' === $col && $is_posts_table )
					{
						$post_type_position = $key;
					}

					// If all data is collected, stop scanning columns.
					if ( !empty( $entities_to_replace ) && !$is_relationships_table && ( isset( $post_type_position ) || !$is_posts_table ) )
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
						// If two records need to be inserted into different blogs, the query can't be transformed.
						if ( !$suggestion->suggest_blog( new Blog( \WP_Site::get_instance( $main ) ) ) )
						{
							return;
						}
					}
					else
					{
						// Replace blog if a replaceable column was found.
						if ( !empty( $entities_to_replace ) )
						{
							foreach ( $entities_to_replace as $entity => $position )
							{
								if ( isset( $record['data'][ $position ] ) )
								{
									// If two columns are suggesting different blogs, the query can't be transformed.
									if ( !$suggestion->suggest_blog( $query->network->get_blog( (int) $record['data'][ $position ]['base_expr'], $entity ) ) )
									{
										return;
									}
								}
							}
						}
						else
						{
							// If two records need to be inserted into different blogs, the query can't be transformed.
							if ( !$suggestion->suggest_blog( null ) )
							{
								return;
							}
						}
					}
				}
			}
		}

		$replaced_blog = $suggestion->get();

		// Replace table for arbitrary post creation.
		if ( !isset( $replaced_blog ) && $is_posts_table && $query->network->consolidated && isset( $_GET['blog_id'] ) && (int) $_GET['blog_id'] !== get_current_blog_id() && did_action( 'load-post-new.php' ) )
		{
			$replaced_blog = $query->network->get_blog_by_id( (int) $_GET['blog_id'] );
		}

		// Replace the blog.
		if ( isset( $replaced_blog ) && isset( $table_to_replace ) )
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
