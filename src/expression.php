<?php
/**
 * SQL expression node class.
 */
namespace WP_Super_Network;

class SQL_Bracket_Expression extends SQL_Node
{
	/**
	 * Post ID, if expression is targeting a specific post.
	 *
	 * @since 1.2.0
	 * @var int
	 */
	private $post_id = null;

	/**
	 * Post ID column, if expression is targeting a specific post.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private $post_id_column = null;

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

		if ( is_array( $node['sub_tree'] ) )
		{
			foreach ( $node['sub_tree'] as $subnode )
			{
				if ( $subnode['expr_type'] === 'bracket_expression' )
				{
					$transform = array( $subnode );
					$query->transform( $transform, $clause );
					$this->post_id = $query->post_id;
					$this->post_id_column = $query->post_id_column;
					continue;
				}

				if ( $subnode['expr_type'] === 'operator' )
				{
					if ( strtoupper( $subnode['base_expr'] ) === 'OR' )
					{
						$this->post_id = null;
						$this->post_id_column = null;
						return;
					}

					if ( $subnode['base_expr'] === '=' )
					{
						continue;
					}
				}

				if ( !isset( $this->post_id_column ) && $subnode['expr_type'] === 'colref' && in_array( $subnode['no_quotes']['parts'][0], SQL_Table::ID_COLS, true ) )
				{
					$this->post_id_column = $subnode['no_quotes']['parts'][0];
					continue;
				}

				if ( !isset( $this->post_id ) && $subnode['expr_type'] === 'const' && (int) $subnode['base_expr'] > 0 )
				{
					$this->post_id = (int) $subnode['base_expr'];
					continue;
				}

				if ( isset( $this->post_id ) xor isset( $this->post_id_column ) )
				{
					$this->post_id = null;
					$this->post_id_column = null;
				}
			}
		}
	}

	/**
	 * Getter.
	 *
	 * @since 1.2.0
	 *
	 * @param string
	 */
	public function __get( $key )
	{
		switch ( $key )
		{
			case 'post_id': return $this->post_id;
			case 'post_id_column': return $this->post_id_column;
		}
	}
}
