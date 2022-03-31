<?php
/**
 * SQL table node class.
 */
namespace WP_Super_Network;

class SQL_Table extends SQL_Node
{
	const ID_COLS = array(
		'comments' => 'comment_post_ID',
		'postmeta' => 'post_id',
		'posts' => 'ID',
		'term_relationships' => 'object_id'
	);

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

		foreach ( array_keys( self::ID_COLS ) as $table_schema )
		{
			$local_table = $GLOBALS['wpdb']->__get( $table_schema );

			if ( $table === $local_table )
			{
				if ( ( $union = $query->network->union( $table_schema ) ) === $table )
				{
					continue;
				}

				if ( is_int( $query->post_id ) && $query->post_id_column === self::ID_COLS[ $table_schema ] )
				{
					$blog_to_replace = $query->network->get_blog( $query->post_id );
				}

				if ( isset( $blog_to_replace ) )
				{
					$node['table'] = $blog_to_replace->table( $table_schema );

					$node['no_quotes'] = array(
						'delim' => false,
						'parts' => array( $node['table'] )
					);

					$node['base_expr'] = $node['table'];
				}
				else
				{
					$node['expr_type'] = 'subquery';
					$node['base_expr'] = $union;
					$node['sub_tree'] = $query->parser()->parse( $union );

					unset( $node['table'] );
					unset( $node['no_quotes'] );
				}

				if ( false === $node['alias'] )
				{
					$node['alias'] = array(
						'as' => false,
						'name' => $local_table,
						'no_quotes' => array(
							'delim' => false,
							'parts' => array( $local_table )
						),
						'base_expr' => $local_table
					);
				}

				break;
			}
		}

		$this->transformed = $node;
		$this->modified = true;
	}
}
