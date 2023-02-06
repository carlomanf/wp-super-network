<?php
/**
 * Main query class.
 */
namespace WP_Super_Network;

class Query
{
	/**
	 * Query before transformation.
	 *
	 * @since 1.2.0
	 *
	 * @var string
	 */
	private $original;

	/**
	 * Query after transformation.
	 *
	 * @since 1.2.0
	 *
	 * @var string
	 */
	private $transformed;

	/**
	 * Parsed query used for transformation.
	 *
	 * @since 1.2.0
	 *
	 * @var array
	 */
	private $parsed = array();

	/**
	 * Network of the query.
	 *
	 * @since 1.2.0
	 * @var WP_Super_Network\Network
	 */
	private $network;

	/**
	 * SQL parser.
	 *
	 * @since 1.2.0
	 * @var PHPSQLParser\PHPSQLParser
	 */
	private static $parser;

	/**
	 * SQL creator.
	 *
	 * @since 1.2.0
	 * @var PHPSQLParser\PHPSQLCreator
	 */
	private static $creator;

	/**
	 * Column list.
	 *
	 * @since 1.2.0
	 * @var array
	 */
	private $column_list = null;

	/**
	 * Expressions of WHERE and HAVING clauses.
	 *
	 * @since 1.2.0
	 * @var WP_Super_Network\SQL_Bracket_Expression[]
	 */
	private $expressions = array();

	/**
	 * SQL parser getter.
	 *
	 * @since 1.2.0
	 *
	 * @return PHPSQLParser\PHPSQLParser
	 */
	public static function parser()
	{
		if ( !isset( self::$parser ) )
		{
			self::$parser = new \PHPSQLParser\PHPSQLParser();
		}

		return self::$parser;
	}

	/**
	 * SQL creator getter.
	 *
	 * @since 1.2.0
	 *
	 * @return PHPSQLParser\PHPSQLCreator
	 */
	public static function creator()
	{
		if ( !isset( self::$creator ) )
		{
			self::$creator = new \PHPSQLParser\PHPSQLCreator();
		}

		return self::$creator;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param array
	 */
	public function __construct( $original, $network )
	{
		$this->original = $original;
		$this->network = $network;

		try
		{
			$apostrophe = strpos( $original, '\\\'' );
			$placeholder = null;

			if ( false !== $apostrophe && false !== strpos( $original, '--', $apostrophe ) )
			{
				$placeholder = uniqid( '', true );
				$this->parsed = self::parser()->parse( str_replace( '\\\'', $placeholder, $original ) );
			}
			else
			{
				$this->parsed = self::parser()->parse( $original );
			}

			// Some queries are not modified.
			$this->transformed = $this->transform( $this->parsed ) ? self::creator()->create( $this->parsed ) : $original;

			if ( isset( $placeholder ) )
			{
				$this->transformed = str_replace( $placeholder, '\\\'', $this->transformed );
			}
		}
		catch ( \PHPSQLParser\exceptions\UnsupportedFeatureException $uf )
		{
			$this->transformed = $original;
		}
		catch ( \PHPSQLParser\exceptions\UnableToCreateSQLException $utcsql )
		{
			$this->transformed = $original;
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
			case 'original': return $this->original;
			case 'transformed': return $this->transformed;
			case 'parsed': return $this->parsed;
			case 'network': return $this->network;
			case 'column_list': return $this->column_list;
			case 'replacements': return isset( $this->expressions['WHERE'] ) ? $this->expressions['WHERE']->replacements : WP_Super_Network::ENTITIES_TO_REPLACE;
			case 'meta_ids': return isset( $this->expressions['WHERE'] ) ? $this->expressions['WHERE']->meta_ids : array();
		}
	}

	/**
	 * Checks if ID is set and an integer.
	 *
	 * @since 1.2.0
	 *
	 * @param array $data Entity replacement data.
	 */
	public function id_set( $data )
	{
		return array_key_exists( 'id', $data ) && is_int( $data['id'] );
	}

	/**
	 * Checks if column is set and a string.
	 *
	 * @since 1.2.0
	 *
	 * @param array $data Entity replacement data.
	 */
	public function column_set( $data )
	{
		return array_key_exists( 'column', $data ) && is_string( $data['column'] );
	}

	/**
	 * Transform a single node of parsed SQL in the context of this query.
	 *
	 * @since 1.2.0
	 *
	 * @param array $node Single node of parsed SQL.
	 * @param string $clause The clause context of this node.
	 */
	private function transform_node( $node, $clause )
	{
		if ( empty( $node['expr_type'] ) )
		{
			return new SQL_Node( $node );
		}

		if ( $clause === 'INSERT' )
		{
			if ( $node['expr_type'] === 'column-list' )
			{
				$this->column_list = $node;
			}
		}

		switch ( $node['expr_type'] )
		{
			case 'table':
				switch ( $clause )
				{
					case 'INSERT':
						return new SQL_Table_For_Insert( $node, $this );
					case 'UPDATE':
						return new SQL_Table( $node, $this, false );
					case 'FROM':
						return new SQL_Table( $node, $this, empty( $this->parsed['DELETE'] ) );
					default:
						return new SQL_Table( $node, $this );
				}
			case 'bracket_expression':
			case 'in-list':
				return $this->expressions[ $clause ] = new SQL_Expression( $node, $this, $clause );
			case 'subquery':
				return new SQL_Subquery( $node, $this, $clause );
			default:
				return new SQL_Node( $node );
		}
	}

	/**
	 * Transform a tree of parsed SQL in the context of this query.
	 *
	 * @since 1.2.0
	 *
	 * @param array $parsed Tree of parsed SQL, passed by reference.
	 * @param string $clause The clause context of this tree.
	 */
	public function transform( &$parsed, $clause = '' )
	{
		$modified = false;

		if ( is_array( $parsed ) )
		{
			foreach ( array_reverse( array_keys( $parsed ) ) as $key )
			{
				if ( is_string( $key ) )
				{
					if ( in_array( $key, array( 'WHERE', 'HAVING' ), true ) )
					{
						$transform = array(
							array(
								'expr_type' => 'bracket_expression',
								'base_expr' => '(' . implode( ' ', array_column( $parsed[ $key ], 'base_expr' ) ) . ')',
								'sub_tree' => &$parsed[ $key ]
							)
						);

						$modified = $this->transform( $transform, $key ) || $modified;
					}
					else
					{
						$modified = $this->transform( $parsed[ $key ], $key ) || $modified;
					}
				}
				else
				{
					$transformed = $this->transform_node( $parsed[ $key ], $clause );
					$parsed[ $key ] = $transformed->transformed;
					$modified = $modified || $transformed->modified;
				}
			}
		}

		return $modified;
	}

	/**
	 * Add a condition to the WHERE clause of this query.
	 * Does not check whether the query type supports a WHERE clause.
	 *
	 * @since 1.2.0
	 *
	 * @param string $expression SQL expression to add to the query.
	 */
	public function condition( $expression )
	{
		if ( !isset( $this->transformed ) )
		{
			$parsed = self::parser()->parse( 'WHERE ' . $expression );

			if ( isset( $parsed['WHERE'] ) )
			{
				if ( isset( $this->parsed['WHERE'] ) )
				{
					$this->parsed['WHERE'][] = array(
						'expr_type' => 'operator',
						'base_expr' => 'AND',
						'sub_tree' => false
					);

					$this->parsed['WHERE'] = array_merge( $this->parsed['WHERE'], $parsed['WHERE'] );
				}
				else
				{
					$this->parsed['WHERE'] = $parsed['WHERE'];
				}
			}
		}
	}
}
