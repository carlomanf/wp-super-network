<?php namespace WP_Super_Network;

/**
 * Main network class.
 */
class Network extends \WP_Network
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
	 * @since 1.0.4
	 *
	 * @param WP_Network
	 */
	public function __construct( $network )
	{
		$id = $network->__get( 'id' );
		$this->blogs = get_sites( 'network_id=' . $id );
		$this->republished = get_posts( 'meta_key=_supernetwork_share' );
	}

	/**
	 * List all republished posts and pages
	 *
	 * @since 1.0.4
	 */
	public function republished()
	{
		if ( empty( $this->republished ) )
		{
			echo 'This network has no republished posts or pages!';
			return;
		}

		foreach ( $this->republished as $post )
			echo $post->post_name . '<br>';
	}

	/**
	 * List all blogs for current user (network admin)
	 *
	 * @since 1.0.4
	 */
	public function get_blogs_for_user()
	{
		if ( empty( $this->blogs ) )
		{
			echo 'This network has no blogs!';
			return;
		}

		foreach ( $this->blogs as $blog )
			echo $blog->__get( 'blogname' ) . '<br>';
	}
}
