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

		foreach ( array_keys( self::ID_COLS ) as $table )
		{
			$local_table = $GLOBALS['wpdb']->__get( $table );

			if ( isset( $node['table'] ) && $node['table'] === $local_table )
			{
				if ( ( $union = $query->network->union( $table ) ) === $node['table'] )
				{
					continue;
				}

				$node['expr_type'] = 'subquery';
				$node['sub_tree'] = $query->parser()->parse( $union );

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

				unset( $node['table'] );
				break;
			}
		}

		$this->transformed = $node;
		$this->modified = true;
	}
}
