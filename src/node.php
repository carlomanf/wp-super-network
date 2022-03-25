<?php
/**
 * Main SQL node class.
 */
namespace WP_Super_Network;

class SQL_Node
{
	/**
	 * Node before transformation.
	 *
	 * @since 1.2.0
	 *
	 * @var array
	 */
	protected $original;

	/**
	 * Node after transformation.
	 *
	 * @since 1.2.0
	 *
	 * @var array
	 */
	protected $transformed;

	/**
	 * Whether the transformed node has modified the original node.
	 *
	 * @since 1.2.0
	 *
	 * @var bool
	 */
	protected $modified = false;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param array
	 */
	public function __construct( $node )
	{
		$this->original = $node;
		$this->transformed = $node;
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
			case 'modified': return $this->modified;
		}
	}
}
