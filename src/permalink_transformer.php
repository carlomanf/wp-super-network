<?php
/**
 * Permalink Transformer class.
 */
namespace WP_Super_Network;

class Permalink_Transformer
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
        if ( !doing_filter( 'supernetwork_preview_link' ) && !is_null( $blog = $this->network->get_blog( $post_ID ) ) ) {
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
    }
}
