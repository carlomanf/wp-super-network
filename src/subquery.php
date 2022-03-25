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
	 */
	public function __construct( $node, $query )
	{
		parent::__construct( $node );

		if ( isset( $node['sub_tree'] ) )
		{
			$this->modified = $this->modified || $query->transform( $node['sub_tree'] );
			$this->transformed = $node;
		}
	}
}
