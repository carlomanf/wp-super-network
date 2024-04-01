<?php
/**
 * SQL table node class.
 */
namespace WP_Super_Network;

class SQL_Table extends SQL_Node
{
	/**
	 * Constructor.
	 * Replaces the table with either another table or a union, depending on the other information found in the WHERE clause.
	 *
	 * @since 1.2.0
	 *
	 * @param array $node SQL parse tree for this table.
	 * @param WP_Super_Network\Query $query Query context for this table.
	 * @param bool $read_only Whether the query context is read only. Default true.
	 */
	public function __construct( $node, $query, $read_only = true )
	{
		parent::__construct( $node );

		$table = array_reverse( $node['no_quotes']['parts'] )[0];

		// Find whether this is a replaceable core table.
		foreach ( WP_Super_Network::TABLES_TO_REPLACE as $table_schema => $tables )
		{
			$local_table = $GLOBALS['wpdb']->__get( $table_schema );

			if ( $table === $local_table )
			{
				$query->join( $this, $table_schema );

				$replacements = $query->replacements;
				$meta_ids = $query->meta_ids;
				$suggestion = $query->suggestion;

				// Replace queries targeting a single entity.
				foreach ( $tables as $column => $entity )
				{
					if ( $query->id_set( $replacements[ $entity ] ) && $query->column_set( $replacements[ $entity ] ) && $replacements[ $entity ]['column'] === $column )
					{
						$suggestion->suggest_blog( $query->network->get_blog( $replacements[ $entity ]['id'], $entity ) );
					}
				}

				$use_union = $read_only && $suggestion->fresh() && ( $union = $query->network->union( $table_schema ) ) !== $table;

				// if relationships table, add the join of either posts or taxonomy table if not joined already
				if ( $use_union )
				{
					// Replace the table with a union.
					$node['expr_type'] = 'subquery';
					$node['base_expr'] = $union;
					$node['sub_tree'] = $query->parser()->parse( $union );

					unset( $node['table'] );
					unset( $node['no_quotes'] );

					$this->alias( $node, $local_table );

					$this->transformed = $node;
					$this->modified = true;
				}
				else
				{
					// Replace update/delete for meta tables.
					if ( !$read_only && in_array( $table_schema, array( 'commentmeta', 'postmeta', 'termmeta' ), true ) && !empty( $meta_ids ) && $meta_ids === $query->network->meta_ids() )
					{
						$suggestion->suggest_blog( $query->network->get_blog( $query->network->meta_object_id(), str_replace( 'meta', 's', $table_schema ) ) );
						$query->network->pop_meta_ids();
					}

					// Replace the table with another blog.
					$query->transform_joins();
				}

				return;
			}
		}
	}

	/**
	 * Adds an alias to the table node.
	 *
	 * @since 1.3.0
	 *
	 * @param array $node SQL parse tree for this table, passed by reference.
	 * @param string $alias Alias for the table.
	 */
	private function alias( &$node, $alias )
	{
		// For read only queries, add an alias if there is not already one.
		if ( isset( $node['alias'] ) && false === $node['alias'] )
		{
			$node['alias'] = array(
				'as' => false,
				'name' => $alias,
				'no_quotes' => array(
					'delim' => false,
					'parts' => array( $alias )
				),
				'base_expr' => $alias
			);
		}
	}

	/**
	 * Replaces the table with another table if a suggestion was made after instantiation time.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_Super_Network\Blog|null $blog_to_replace Blog to replace table with, or null if no replacement.
	 * @param string $table_schema Schema for this table.
	 * @param WP_Super_Network\Query $query Query context for this table.
	 */
	public function transform_for_blog( $blog_to_replace, $table_schema, $query )
	{
		$node = $this->original;

		if ( isset( $blog_to_replace ) )
		{
			$node['table'] = $blog_to_replace->table( $table_schema );

			$node['no_quotes'] = array(
				'delim' => false,
				'parts' => array( $node['table'] )
			);

			$this->alias( $node, $GLOBALS['wpdb']->__get( $table_schema ) );

			if ( isset( $node['table'] ) && isset( $node['alias'] ) && isset( $node['alias']['base_expr'] ) )
			{
				$node['base_expr'] = $node['table'] . ' ' . $node['alias']['base_expr'];
			}
		}

		$where = array();

		// Exclude collisions and network-based post types.
		if ( isset( $node['table'] ) )
		{
			$blog = isset( $blog_to_replace ) ? $blog_to_replace : $query->network->get_blog_by_id( get_current_blog_id() );
			$alias = isset( $node['alias'] ) && !empty( $node['alias']['name'] ) ? $node['alias']['name'] : $node['table'];
			$query->network->exclude( $where, $table_schema, $blog, $alias );
			empty( $where ) or $query->condition( implode( ' AND ', $where ) );
		}

		$this->transformed = $node;
		$this->modified = isset( $blog_to_replace ) || !empty( $where );
	}
}
