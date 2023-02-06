<?php
/**
 * SQL expression node class.
 */
namespace WP_Super_Network;

class SQL_Expression extends SQL_Node
{
	/**
	 * Table replacement data, if expression is targeting a specific entity.
	 *
	 * @since 1.2.0
	 * @var array
	 */
	private $replacements = WP_Super_Network::ENTITIES_TO_REPLACE;

	/**
	 * Meta ID's that expression is specifying.
	 *
	 * @since 1.2.0
	 * @var array
	 */
	private $meta_ids = array();

	/**
	 * Whether the meta ID column was found.
	 *
	 * @since 1.2.0
	 * @var bool
	 */
	private $meta_id_column = false;

	/**
	 * Constructor.
	 * Reads the expression to potentially suggest a table to be replaced.
	 *
	 * @since 1.2.0
	 *
	 * @param array $node SQL parse tree for this expression.
	 * @param WP_Super_Network\Query $query Query context for this expression.
	 * @param string $clause Clause context for this expression.
	 */
	public function __construct( $node, $query, $clause )
	{
		parent::__construct( $node );

		if ( is_array( $node['sub_tree'] ) )
		{
			foreach ( $node['sub_tree'] as &$subnode )
			{
				// Nested expressions to be transformed recursively.
				if ( in_array( $subnode['expr_type'], array( 'bracket_expression', 'in-list', 'subquery' ), true ) )
				{
					$transform = array( &$subnode );
					$this->modified = $query->transform( $transform, $clause ) || $this->modified;
					$this->transformed = $node;

					if ( $subnode['expr_type'] !== 'subquery' )
					{
						// Update replacements based on transformed expression.
						foreach ( $this->replacements as $entity => &$data )
						{
							$replacements = $query->replacements;
							if ( $query->id_set( $replacements[ $entity ] ) ) $data['id'] = $replacements[ $entity ]['id'];
							if ( $query->column_set( $replacements[ $entity ] ) ) $data['column'] = $replacements[ $entity ]['column'];
						}

						// Update meta ID's.
						$meta_ids = $query->meta_ids;
						if ( !empty( $meta_ids ) ) $this->meta_ids = $meta_ids;

						continue;
					}
				}

				// Update replacements based on an operator subnode.
				if ( $subnode['expr_type'] === 'operator' )
				{
					// No replacements to be made if an OR expression is present.
					if ( strtoupper( $subnode['base_expr'] ) === 'OR' )
					{
						foreach ( $this->replacements as $entity => &$data )
						{
							$data = array();
						}

						$this->meta_ids = array();
						$this->meta_id_column = false;

						return;
					}

					// Equality is handled by skipping to the next subnode.
					if ( $subnode['base_expr'] === '=' || strtoupper( $subnode['base_expr'] ) === 'IN' )
					{
						continue;
					}
				}

				// Update replacements based on a column reference.
				if ( $subnode['expr_type'] === 'colref' )
				{
					$replacements = call_user_func_array( 'array_merge', WP_Super_Network::TABLES_TO_REPLACE );
					$col = array_reverse( $subnode['no_quotes']['parts'] )[0];

					foreach ( $this->replacements as $entity => &$data )
					{
						// Check if a replaceable column was found for the first time.
						if ( !$query->column_set( $data ) && isset( $replacements[ $col ] ) && $replacements[ $col ] === $entity )
						{
							$data['column'] = $col;
							$this->clear_meta_id();
							continue 2;
						}
					}

					// Meta ID column was found.
					if ( $col === 'meta_id' )
					{
						$this->meta_id_column = true;
						$this->clear_entity_id( $query );
						continue;
					}
				}

				// Update replacements based on a positive integer subnode.
				if ( $subnode['expr_type'] === 'const' && (int) $subnode['base_expr'] > 0 )
				{
					// Add to meta ID's only if the column has not been found.
					if ( !$this->meta_id_column )
					{
						$this->meta_ids[] = (string) $subnode['base_expr'];
					}

					$replaced = false;

					foreach ( $this->replacements as $entity => &$data )
					{
						// Check if a positive integer was found for the first time.
						if ( !$query->id_set( $data ) )
						{
							$replaced = true;
							$data['id'] = (int) $subnode['base_expr'];
						}
					}

					// Clear entity and/or meta if needed.
					if ( $this->meta_id_column ) $this->clear_meta_id();
					if ( !$replaced ) $this->clear_entity_id( $query );

					continue;
				}

				// If this section is reached, both entity and meta should be checked to potentially be cleared.
				$this->clear_entity_id( $query );
				$this->clear_meta_id();
			}
		}
	}

	/**
	 * If an entity ID and column were not both found, they should both be erased.
	 *
	 * @since 1.2.0
	 *
	 * @param WP_Super_Network\Query $query Query context for this expression.
	 */
	private function clear_entity_id( $query )
	{
		foreach ( $this->replacements as $entity => &$data )
		{
			if ( $query->id_set( $data ) xor $query->column_set( $data ) )
			{
				$data = array();
			}
		}
	}

	/**
	 * If a set of meta ID's and column were not both found, they should both be erased.
	 *
	 * @since 1.2.0
	 */
	private function clear_meta_id()
	{
		if ( !empty( $this->meta_ids ) xor $this->meta_id_column )
		{
			$this->meta_ids = array();
			$this->meta_id_column = false;
		}
	}

	/**
	 * Getter.
	 *
	 * @since 1.2.0
	 *
	 * @param string $key Key.
	 */
	public function __get( $key )
	{
		switch ( $key )
		{
			case 'replacements': return $this->replacements;
			case 'meta_ids': return $this->meta_ids;
		}

		return parent::__get( $key );
	}
}
