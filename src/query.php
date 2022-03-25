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
			$this->parsed = self::parser()->parse( $original );

			// Insert, update and delete queries are not currently modified.
			$this->transformed = $this->transform( $this->parsed ) ? self::creator()->create( $this->parsed ) : $original;
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
	 * @param string
	 */
	public function __get( $key )
	{
		switch ( $key )
		{
			case 'original': return $this->original;
			case 'transformed': return $this->transformed;
			case 'parsed': return $this->parsed;
			case 'network': return $this->network;
		}
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

		if ( $node['expr_type'] === 'subquery' )
		{
			return new SQL_Subquery( $node, $this );
		}

		if ( $node['expr_type'] === 'table' && !in_array( $clause, array( 'DELETE', 'INSERT', 'UPDATE' ), true ) )
		{
			return new SQL_Table( $node, $this );
		}

		return new SQL_Node( $node );
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
					$modified = $modified || $this->transform( $parsed[ $key ], $key );
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
}