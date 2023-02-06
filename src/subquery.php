<?php
/**
 * SQL subquery node class.
 */
namespace WP_Super_Network;

class SQL_Subquery extends SQL_Node
{
	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param array
	 * @param WP_Super_Network\Query
	 * @param string
	 */
	public function __construct( $node, $query, $clause )
	{
		parent::__construct( $node );

		$original = $clause === 'FROM' ? $node['base_expr'] : trim( $node['base_expr'], '()' );
		$subquery = new Query( $original, $query->network );

		if ( $subquery->transformed !== $original )
		{
			$node['base_expr'] = $clause === 'FROM' ? $subquery->transformed : '(' . $subquery->transformed . ')';
			$node['sub_tree'] = $subquery->parsed;

			$this->transformed = $node;
			$this->modified = true;
		}
	}
}
