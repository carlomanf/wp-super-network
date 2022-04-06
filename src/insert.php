<?php
/**
 * SQL table node class.
 */
namespace WP_Super_Network;

class SQL_Table_For_Insert extends SQL_Node
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

		$table = array_reverse( $node['no_quotes']['parts'] )[0];

		$table_to_replace = false;
		$position_to_check = null;
		$replaced_blog = null;

		if ( is_array( $query->column_list ) )
		{
			foreach ( $query->column_list['sub_tree'] as $key => $colref )
			{
				$col = array_reverse( $colref['no_quotes']['parts'] )[0];

				if ( $colref['expr_type'] === 'colref' && isset( WP_Super_Network::TABLES_TO_REPLACE[ $col ] ) )
				{
					$table_to_replace = WP_Super_Network::TABLES_TO_REPLACE[ $col ];

					if ( $GLOBALS['wpdb']->__get( $table_to_replace ) === $table )
					{
						$position_to_check = $key;
						break;
					}
				}
			}
		}

		if ( false !== $table_to_replace && isset( $position_to_check ) && isset( $query->parsed['VALUES'] ) )
		{
			foreach ( $query->parsed['VALUES'] as $record )
			{
				if ( $record['expr_type'] === 'record' && isset( $record['data'][ $position_to_check ] ) )
				{
					$blog_to_replace = $query->network->get_blog( (int) $record['data'][ $position_to_check ]['base_expr'] );

					if ( is_null( $replaced_blog ) )
					{
						$replaced_blog = $blog_to_replace;
					}
					else
					{
						if ( !is_null( $blog_to_replace ) && $blog_to_replace->id !== $replaced_blog->id )
						{
							return;
						}
					}
				}
			}

			if ( isset( $replaced_blog ) )
			{
				$this->transformed['table'] = $replaced_blog->table( $table_to_replace );

				$this->transformed['no_quotes'] = array(
					'delim' => false,
					'parts' => array( $this->transformed['table'] )
				);

				$this->transformed['base_expr'] = $this->transformed['table'];

				$this->modified = true;
			}
		}
	}
}
