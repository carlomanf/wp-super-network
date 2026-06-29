<?php
/**
 * Interceptor class.
 */
namespace WP_Super_Network;

class Interceptor
{
	/**
	 * Network instance.
	 *
	 * @since 1.3.0
	 * @var Network
	 */
	private $network;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param Network $network Network instance.
	 */
	public function __construct( $network )
	{
		$this->network = $network;
	}

	/**
	 * Intercept permalink for posts, pages, taxonomy archives, and attachment source links.
	 *
	 * @since 1.3.0
	 *
	 * @param string $permalink The original permalink.
	 * @param int $post_ID The post ID.
	 * @return string The transformed permalink.
	 */
	public function intercept_permalink( $permalink, $post_ID )
	{
		if ( !doing_filter( 'supernetwork_preview_link' ) && !is_null( $blog = $this->network->get_blog( $post_ID ) ) )
		{
			switch_to_blog( $blog->id );
			$permalink = get_permalink( $post_ID );
			restore_current_blog();
		}

		return $permalink;
	}

	/**
	 * Intercept permalink for posts.
	 *
	 * @since 1.3.0
	 *
	 * @param string $permalink The original permalink.
	 * @param WP_Post $post The post object.
	 * @return string The transformed permalink.
	 */
	public function intercept_permalink_for_post( $permalink, $post )
	{
		return $this->intercept_permalink( $permalink, $post->ID );
	}

	/**
	 * Intercept preview link.
	 *
	 * @since 1.3.0
	 *
	 * @param string $preview_link The original preview link.
	 * @param WP_Post $post The post object.
	 * @return string The transformed preview link.
	 */
	public function intercept_preview_link( $preview_link, $post )
	{
		return doing_filter( 'supernetwork_preview_link' ) ? $preview_link : apply_filters( 'supernetwork_preview_link', $preview_link, $post );
	}

	/**
	 * Replace preview link.
	 *
	 * @since 1.3.0
	 *
	 * @param string $preview_link The original preview link.
	 * @param WP_Post $post The post object.
	 * @return string The transformed preview link.
	 */
	public function replace_preview_link( $preview_link, $post )
	{
		$query_args = array();
		parse_str( parse_url( $preview_link, PHP_URL_QUERY ), $query_args );
		return get_preview_post_link( $post, array_intersect_key( $query_args, array( 'preview_nonce' => null, 'preview_id' => null ) ) );
	}

	public function intercept_term_link( $termlink, $term, $taxonomy )
	{
		if ( !is_null( $blog = $this->network->get_blog( $term->term_id, 'terms' ) ) )
		{
			switch_to_blog( $blog->id );
			$termlink = get_term_link( $term, $taxonomy );
			restore_current_blog();
		}

		return $termlink;
	}

	public function intercept_attachment_url( $url, $attachment_id )
	{
		if ( !is_null( $blog = $this->network->get_blog( $attachment_id ) ) )
		{
			switch_to_blog( $blog->id );
			$url = wp_get_attachment_url( $attachment_id );
			restore_current_blog();
		}

		return $url;
	}

	public function intercept_attached_file( $file, $attachment_id )
	{
		if ( !is_null( $blog = $this->network->get_blog( $attachment_id ) ) )
		{
			switch_to_blog( $blog->id );
			$file = get_attached_file( $attachment_id );
			restore_current_blog();
		}

		return $file;
	}

	public function intercept_srcset_meta( $image_meta, $size_array, $image_src, $attachment_id )
	{
		if ( !is_null( $blog = $this->network->get_blog( $attachment_id ) ) )
		{
			switch_to_blog( $blog->id );
		}

		return $image_meta;
	}

	public function intercept_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id )
	{
		if ( $this->network->get_blog( $attachment_id ) !== null )
		{
			restore_current_blog();
		}

		return $sources;
	}

	public function intercept_upload_dir( $upload_dir )
	{
		$blog_id = in_array( 'attachment', $this->network->post_types, true ) ? get_main_site_id() : 0;

		foreach ( array( 'file' => 'post', 'async-upload' => 'post_id' ) as $file => $var )
		{
			if ( isset( $_FILES[ $file ] ) && isset( $_REQUEST[ $var ] ) )
			{
				break;
			}
		}

		if ( $blog_id === 0 && isset( $_FILES[ $file ] ) && isset( $_REQUEST[ $var ] ) && !is_null( $blog = $this->network->get_blog( (int) $_REQUEST[ $var ] ) ) )
		{
			$blog_id = $blog->id;
		}

		if ( $blog_id > 0 && $blog_id !== get_current_blog_id() )
		{
			switch_to_blog( $blog_id );
			$upload_dir = wp_upload_dir();
			restore_current_blog();
		}

		return $upload_dir;
	}

	public function intercept_capability( $allcaps, $caps, $args, $user )
	{
		$blog = null;

		if ( in_array( $args[0], array( 'add_post_meta', 'delete_post', 'delete_post_meta', 'edit_post', 'edit_post_meta', 'publish_post', 'read_post' ), true ) )
		{
			$blog = $this->network->get_blog( get_post( $args[2] )->ID );
		}

		if ( $args[0] === 'edit_block_binding' && isset( $args[2]->post ) )
		{
			$blog = $this->network->get_blog( (int) $args[2]->post->ID );
		}

		if ( in_array( $args[0], array( 'add_term_meta', 'assign_term', 'edit_term', 'edit_term_meta', 'delete_term', 'delete_term_meta' ), true ) )
		{
			$blog = $this->network->get_blog( get_term( $args[2] )->term_id, 'terms' );
		}

		if ( in_array( $args[0], array( 'add_comment_meta', 'delete_comment_meta', 'edit_comment', 'edit_comment_meta' ), true ) )
		{
			$blog = $this->network->get_blog( (int) get_comment( $args[2] )->comment_ID, 'comments' );
		}

		if ( isset( $blog ) )
		{
			return ( new \WP_User( $user, '', $blog->id ) )->allcaps;
		}

		return $allcaps;
	}

	/**
	 * Register all add_filter calls.
	 *
	 * @since 1.3.0
	 */
	public function register_filters()
	{
		add_filter( 'post_type_link', array( $this, 'intercept_permalink_for_post' ), 10, 2 );
		add_filter( 'post_link', array( $this, 'intercept_permalink_for_post' ), 10, 2 );
		add_filter( 'page_link', array( $this, 'intercept_permalink' ), 10, 2 );
		add_filter( 'preview_post_link', array( $this, 'intercept_preview_link' ), 10, 2 );
		add_filter( 'supernetwork_preview_link', array( $this, 'replace_preview_link' ), 10, 2 );
		add_filter( 'term_link', array( $this, 'intercept_term_link' ), 10, 3 );
		add_filter( 'wp_get_attachment_url', array( $this, 'intercept_attachment_url' ), 10, 2 );
		add_filter( 'get_attached_file', array( $this, 'intercept_attached_file' ), 10, 2 );
		add_filter( 'wp_calculate_image_srcset_meta', array( $this, 'intercept_srcset_meta' ), 10, 4 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'intercept_srcset' ), 10, 5 );
		add_filter( 'upload_dir', array( $this, 'intercept_upload_dir' ) );
		add_filter( 'user_has_cap', array( $this, 'intercept_capability' ), 10, 4 );
	}
}
