<?php
/**
 * SQL table node class.
 */
namespace WP_Super_Network;

class SQL_Table_For_Insert extends SQL_Node
{
	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param array
	 * @param WP_Super_Network\Query
	 */
	public function __construct( $node, $query )
	{
		parent::__construct( $node );

		$table = array_reverse( $node['no_quotes']['parts'] )[0];

		$table_to_replace = null;
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

					if ( 'post_type' === $col && $GLOBALS['wpdb']->__get( 'posts' ) === $table )
					{
						$post_type_position = $key;
					}

					if ( 'ID' !== $col && isset( WP_Super_Network::TABLES_TO_REPLACE[ $col ] ) )
					{
						$table_to_replace = WP_Super_Network::TABLES_TO_REPLACE[ $col ];
					}

					if ( isset( $table_to_replace ) && $GLOBALS['wpdb']->__get( $table_to_replace ) === $table )
					{
						$position_to_check = $key;
					}

					if ( isset( $position_to_check ) && ( isset( $post_type_position ) || $table !== 'posts' ) )
					{
						break;
					}
				}
			}
		}

		if ( isset( $table_to_replace ) && isset( $position_to_check ) && isset( $query->parsed['VALUES'] ) )
		{
			foreach ( $query->parsed['VALUES'] as $record )
			{
				if ( $record['expr_type'] === 'record' && isset( $record['data'][ $position_to_check ] ) )
				{
					if ( $table_to_replace === 'posts' && isset( $post_type_position ) && $query->network->consolidated && in_array( substr( $record['data'][ $post_type_position ]['base_expr'], 1, -1 ), $query->network->post_types, true ) && ( $main = get_main_site_id() ) > 0 )
					{
						$blog_to_replace = new Blog( \WP_Site::get_instance( $main ) );
					}
					else
					{
						$blog_to_replace = $query->network->get_blog( (int) $record['data'][ $position_to_check ]['base_expr'] );
					}

					if ( is_null( $replaced_blog ) )
					{
						$replaced_blog = $blog_to_replace;
					}
					else
					{
						if ( !is_null( $blog_to_replace ) && $blog_to_replace->id !== $replaced_blog->id )
						{
							return;
						}
					}
				}
			}
		}

		if ( !isset( $replaced_blog ) && $GLOBALS['wpdb']->__get( 'posts' ) === $table && $query->network->consolidated && isset( $_GET['blog_id'] ) && did_action( 'load-post-new.php' ) )
		{
			$replaced_blog = $query->network->get_blog_by_id( (int) $_GET['blog_id'] );
		}

		if ( isset( $replaced_blog ) )
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
