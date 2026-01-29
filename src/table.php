<?php
/**
 * SQL table node class.
 */
namespace WP_Super_Network;

class SQL_Table extends SQL_Node
{
	/**
	 * Whether the query context is read only.
	 *
	 * @since 1.3.0
	 * @var bool
	 */
	private $read_only;

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

		$this->read_only = $read_only;
		$table = array_reverse( $node['no_quotes']['parts'] )[0];

		// Find whether this is a replaceable core table.
		$table_schema = $query->network->get_table_schema( $table );

		if ( !empty( $table_schema ) )
			{
				$query->join( $this, $table_schema );

				$replacements = $query->replacements;
				$meta_ids = $query->meta_ids;
				$suggestion = $query->suggestion;

				// Replace queries targeting a single entity.
			foreach ( WP_Super_Network::TABLES_TO_REPLACE[ $table_schema ] as $column => $entity )
				{
					if ( $query->id_set( $replacements[ $entity ] ) && $query->column_set( $replacements[ $entity ] ) && $replacements[ $entity ]['column'] === $column )
					{
						$suggestion->suggest_blog( $query->network->get_blog( $replacements[ $entity ]['id'], $entity ) );
					}
				}

			// No need to union if not a read only query, a blog is suggested or not consolidated and no republished posts.
			if ( $read_only && $suggestion->fresh() && ( $query->network->consolidated || !empty( $query->network->republished ) && !empty( array_keys( WP_Super_Network::TABLES_TO_REPLACE[ $table_schema ], 'posts', true ) ) ) )
			{
				$subquery = array();

				foreach ( $query->network->blogs as $blog )
				{
					$subquery[] = $this->subquery( $blog, $table_schema, $query );
				}

				$subquery = implode( ' UNION ALL ', $subquery );

					// Replace the table with a union.
					$node['expr_type'] = 'subquery';
				$node['base_expr'] = $subquery;
				$node['sub_tree'] = $query->parser()->parse( $subquery );

					unset( $node['table'] );
					unset( $node['no_quotes'] );

				$this->alias( $node, $table );

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
		$table = array_reverse( $node['no_quotes']['parts'] )[0];
		$semi_join_relationships = $this->read_only && $table_schema === 'term_relationships' && !$query->joined( 'posts' ) && !$query->joined( 'term_taxonomy' );

		if ( $semi_join_relationships )
		{
			isset( $blog_to_replace ) or $blog_to_replace = $query->network->get_blog_by_id( get_current_blog_id() );

			// Semi-join term_relationships with posts and/or term_taxonomy depending on queried IDs
			$node['expr_type'] = 'subquery';
			$node['base_expr'] = $this->subquery( $blog_to_replace, 'term_relationships', $query );
			$node['sub_tree'] = $query->parser()->parse( $node['base_expr'] );

			unset( $node['table'] );
			unset( $node['no_quotes'] );
		}
		else
		{
			if ( isset( $blog_to_replace ) )
			{
				$node['table'] = $blog_to_replace->table( $table_schema );

				$node['no_quotes'] = array(
					'delim' => false,
					'parts' => array( $node['table'] )
				);
			}
		}

		if ( isset( $blog_to_replace ) )
		{
			$this->read_only and $this->alias( $node, $table );

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
			$this->exclude( $where, $blog, $table_schema, $query, $alias );
			empty( $where ) or $query->condition( implode( ' AND ', $where ) );
		}

		$this->transformed = $node;
		$this->modified = $semi_join_relationships || isset( $blog_to_replace ) || !empty( $where );
	}

	/**
	 * Constructs a subquery for the given blog and table.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_Super_Network\Blog $blog Blog context.
	 * @param string $table Table schema.
	 * @param WP_Super_Network\Query $query Query context.
	 *
	 * @return string Subquery SQL statement.
	 */
	private function subquery( $blog, $table, $query )
	{
		$alias = '';
		$cols = '*';

		if ( $table === 'term_relationships' )
		{
			$alias = 'tr';
			$cols = $alias . '.' . $cols;
		}

		$from = '`' . $blog->table( $table ) . '`';
		$where = array();

		$this->exclude( $where, $blog, $table, $query, $alias );

		if ( $table === 'term_relationships' )
		{
			$from .= ' ' . $alias;

			foreach ( array( 'posts' => 'p', 'term_taxonomy' => 'tt' ) as $join => $join_alias )
			{
				if ( !$query->id_set( $query->replacements[ $join ] ) && !$query->column_set( $query->replacements[ $join ] ) )
				{
					$from .= ' INNER JOIN `' . $blog->table( $join ) . '` ' . $join_alias;
					$from .= ' ON ' . $alias . '.`' . array_search( $join, WP_Super_Network::TABLES_TO_REPLACE[ $table ] ) . '` = ' . $join_alias . '.`' . array_search( $join, WP_Super_Network::TABLES_TO_REPLACE[ $join ] ) . '`';
					$this->exclude( $where, $blog, $join, $query, $join_alias );
				}
			}
		}

		if ( !$query->network->consolidated )
		{
			// Add republished posts.
			if ( get_current_blog_id() !== $blog->id )
			{
				foreach ( array_keys( WP_Super_Network::TABLES_TO_REPLACE[ $table ], 'posts', true ) as $col )
				{
					// Post parent is currently not being handled.
					$col === 'post_parent' or $where[] = '`' . $col . '` IN (' . implode( ', ', $query->network->republished ) . ')';
				}
			}
		}

		$where = implode( ' AND ', $where );
		return 'SELECT ' . $cols . ' FROM ' . $from . ( empty( $where ) ? '' : ( ' WHERE ' . $where ) );
	}

	/**
	 * Adds exclusion conditions to the WHERE clause for collisions and network-based post types.
	 *
	 * @since 1.3.0
	 *
	 * @param array $where Array of WHERE conditions, passed by reference.
	 * @param WP_Super_Network\Blog $blog Blog context.
	 * @param string $table Table schema.
	 * @param WP_Super_Network\Query $query Query context.
	 * @param string $alias Table alias, if any.
	 */
	private function exclude( &$where, $blog, $table, $query, $alias = '' )
	{
		if ( $query->network->consolidated )
		{
			empty( $alias ) or $alias .= '.';

			// Exclude any entities involved in collisions.
			foreach ( array_keys( $query->network->collisions ) as $entity )
			{
				if ( !empty( $query->network->collisions[ $entity ] ) )
				{
					foreach ( array_keys( WP_Super_Network::TABLES_TO_REPLACE[ $table ], $entity, true ) as $col )
					{
						$where[] = $alias . '`' . $col . '` NOT IN (' . implode( ', ', $query->network->collisions[ $entity ] ) . ')';
					}
				}
			}

			// Exclude network-based post types.
			if ( $table === 'posts' && !empty( $query->network->post_types ) && !$blog->is_network() )
			{
				$where[] = $alias . '`post_type` NOT IN (\'' . implode( '\', \'', $query->network->post_types ) . '\')';
			}
		}
	}
}
