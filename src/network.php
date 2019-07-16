<?php

/**
 * Main network class.
 */
class WPSN_Network
{
	/**
	 * Supernetwork
	 *
	 * @since 1.0.4
	 * @var WPSN_Network
	 */
	public $supernetwork;

	/**
	 * Subnetworks
	 *
	 * @since 1.0.4
	 * @var array
	 */
	public $subnetworks;

	/**
	 * Blogs in the network.
	 *
	 * @since 1.0.4
	 * @var array
	 */
	public $blogs;

	/**
	 * Republished posts and pages for this network
	 *
	 * @since 1.0.4
	 * @var array
	 */
	public $republished;

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
