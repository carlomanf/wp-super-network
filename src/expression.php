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
			foreach ( $node['sub_tree'] as $subnode )
			{
				// Nested expressions to be transformed recursively.
				if ( $subnode['expr_type'] === 'bracket_expression' || $subnode['expr_type'] === 'in-list' )
				{
					$transform = array( $subnode );
					$query->transform( $transform, $clause );

					// Update replacements based on transformed expression.
					foreach ( $this->replacements as $entity => &$data )
					{
						$replacements = $query->replacements;
						if ( $query->id_set( $replacements[ $entity ] ) ) $data['id'] = $replacements[ $entity ]['id'];
						if ( $query->column_set( $replacements[ $entity ] ) ) $data['column'] = $replacements[ $entity ]['column'];
					}

					continue;
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
							continue 2;
						}
					}
				}

				// Update replacements based on a positive integer subnode.
				if ( $subnode['expr_type'] === 'const' && (int) $subnode['base_expr'] > 0 )
				{
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

					if ( $replaced ) continue;
				}

				// If this section is reached and ID and column were not both found, they should both be erased.
				foreach ( $this->replacements as $entity => &$data )
				{
					if ( $query->id_set( $data ) xor $query->column_set( $data ) )
					{
						$data = array();
					}
				}
			}
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
		}
	}
}
