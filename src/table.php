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
	 * @param bool $read_only Whether the query context is read only. If so, the table must not be replaced by a union.
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
				$use_union = !$read_only || ( $union = $query->network->union( $table_schema ) ) !== $table;
				$blog_to_replace = null;

				$replacements = $query->replacements;
				$meta_ids = $query->meta_ids;

				// Replace queries targeting a single entity.
				foreach ( $tables as $column => $entity )
				{
					if ( $query->id_set( $replacements[ $entity ] ) && $query->column_set( $replacements[ $entity ] ) && $replacements[ $entity ]['column'] === $column )
					{
						$blog_to_replace = $query->network->get_blog( $replacements[ $entity ]['id'], $entity );
						$use_union = false;
						break;
					}
				}

				// Replace update/delete for meta tables.
				if ( !isset( $blog_to_replace ) && !$read_only && in_array( $table_schema, array( 'commentmeta', 'postmeta', 'termmeta' ), true ) && !empty( $meta_ids ) && $meta_ids === $query->network->meta_ids() )
				{
					$blog_to_replace = $query->network->get_blog( $query->network->meta_object_id(), str_replace( 'meta', 's', $table_schema ) );
					$query->network->pop_meta_ids();
				}

				// Replace the table with another blog.
				if ( isset( $blog_to_replace ) )
				{
					$node['table'] = $blog_to_replace->table( $table_schema );

					$node['no_quotes'] = array(
						'delim' => false,
						'parts' => array( $node['table'] )
					);
				}
				else
				{
					// Replace the table with a union.
					if ( $read_only && $use_union )
					{
						$node['expr_type'] = 'subquery';
						$node['base_expr'] = $union;
						$node['sub_tree'] = $query->parser()->parse( $union );

						unset( $node['table'] );
						unset( $node['no_quotes'] );
					}
				}

				// For read only queries, add an alias if there is not already one.
				if ( $read_only && ( $use_union || isset( $blog_to_replace ) ) && isset( $node['alias'] ) && false === $node['alias'] )
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

				if ( isset( $blog_to_replace ) && isset( $node['table'] ) && isset( $node['alias'] ) && isset( $node['alias']['base_expr'] ) )
				{
					$node['base_expr'] = $node['table'] . ' ' . $node['alias']['base_expr'];
				}

				$where = array();

				// Exclude collisions and network-based post types.
				if ( !$use_union && isset( $node['table'] ) )
				{
					$blog = isset( $blog_to_replace ) ? $blog_to_replace : $query->network->get_blog_by_id( get_current_blog_id() );
					$alias = isset( $node['alias'] ) && !empty( $node['alias']['name'] ) ? $node['alias']['name'] : $node['table'];
					$query->network->exclude( $where, $table_schema, $blog, $alias );
					empty( $where ) or $query->condition( implode( ' AND ', $where ) );
				}

				if ( $read_only && $use_union || isset( $blog_to_replace ) || !empty( $where ) )
				{
					$this->transformed = $node;
					$this->modified = true;
				}

				return;
			}
		}
	}
}
