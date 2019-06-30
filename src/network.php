<?php

/**
 * Main network class.
 */
class WPSN_Network
{
	/**
	 * Blogs in the network.
	 *
	 * @since 1.0.4
	 * @var array
	 */
	public $blogs;

	/**
	 * Constructor.
	 *
	 * Constructs the network.
	 *
	 * @since 1.0.2
	 *
	 * @param array         $blogs        Optional. Blogs to add to this network.
	 *                                    Default empty array.
	 */
	public function __construct( $blogs = array() )
	{
		$this->blogs = $blogs;
	}
}
