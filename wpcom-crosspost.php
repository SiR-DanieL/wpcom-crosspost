<?php
/**
 * Plugin Name: WP.com Cross-Post
 * Plugin URI: https://nicola.blog/
 * Description: Cross-Post from your WordPress.com blog to your self-hosted WordPress website
 * Version: 1.0.0
 * Author: Nicola Mustone
 * Author URI: https://nicola.blog/
 * Requires at least: 4.4
 * Tested up to: 4.6
 *
 * Text Domain: wpcom-crosspost
 * Domain Path: /languages/
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WPCOM_CrossPost {
	/**
	 * Plugin settings
	 * @var object
	 * @access private
	 */
	private $_settings = null;

	/**
	 * WPCOM_CrossPost_API instance
	 * @var WPCOM_CrossPost_API
	 * @access private
	 */
	private $_api = null;

	/**
	 * Inits the plugin and schedule the hooks.
	 */
	public function __construct() {
		define( 'WPCOM_CROSSPOST_VERSION', '1.0.0' );

		// Include API class
		require_once __DIR__ . '/class-wpcom-crosspost-api.php';

		// Include admin class
		if ( is_admin() ) {
			require_once __DIR__ . '/class-wpcom-crosspost-admin.php';
		}

		// Load settings
		$this->_settings = get_option( 'wpcom-crosspost-settings' );
		$this->_api      = WPCOM_CrossPost_API::instance();

		// Load localization files
		$this->load_textdomain();

		// Create and clear the schedule for creating cross-posts automatically every day
		register_activation_hook( __FILE__, array( $this, 'schedule_cross_posts_creation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'remove_schedule_cross_posts_creation' ) );

		// Hook the creation process to the scheduled hook
		add_action( 'wpcom_crossposts_create_posts', array( $this, 'create_cross_posts' ) );

		// Sets the cross-domain canonical URL on Yoast SEO
		add_filter( 'wpseo_canonical', array( $this, 'set_crossdomain_canonical_url' ) );
	}

	/**
	 * Schedules a event to create cross posts every day.
	 */
	public function schedule_cross_posts_creation() {
		if ( ! wp_next_scheduled ( 'wpcom_crossposts_create_posts' ) ) {
			wp_schedule_event( current_time( 'timestamp' ) + HOUR_IN_SECONDS, apply_filters( 'wpcom_crosspost_sync_frequency', 'daily' ), 'wpcom_crossposts_create_posts' );
		}
	}

	/**
	 * Clears the scheduled hook whenthe plugin is deactivated.
	 */
	public function remove_schedule_cross_posts_creation() {
		// Clear scheduled events
		wp_clear_scheduled_hook( 'wpcom_crossposts_create_posts' );

		// Clear options
		delete_option( 'wpcom-crosspost-settings', $this->_settings );
	}

	/**
	 * Gets posts published yesterday from a WP.com website
	 *
	 * @param  string|int $from
	 * @return object
	 */
	public function get_posts( $from = null ) {
		if ( $from !== null && ! is_integer( $from ) ) {
			$from = strtotime( $from );
		} else {
			$from = absint( $from );
		}

		$from = apply_filters( 'wpcom_crosspost_sinc_from', $from );

		$response = $this->_api->get_posts( $this->_settings['blog_id'], $from );

		if ( false !== $response && isset( $response->posts ) ) {
			return $response->posts;
		}

		return false;
	}

	/**
	 * Creates cross-posts from WP.com posts.
	 *
	 * @return bool
	 */
	public function create_cross_posts() {
		$posts = $this->get_posts( 'yesterday' );

		if ( ! function_exists( 'post_exists' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/post.php' );
		}

		if ( false !== $posts && count( $posts ) > 0 ) {
			foreach ( $posts as $post ) {
				// Set author ID to use
				$author = get_user_by( 'email', apply_filters( 'wpcom_crosspost_author_email', get_bloginfo( 'admin_email' ) ) );
				if ( ! is_wp_error( $author ) ) {
					$author = $author->ID;
				} else {
					$author = 0;
				}

				// Create array of tags if any
				$tags = array();
				if ( count( $post->tags ) > 0 ) {
					foreach ( $post->tags as $tag ) {
						array_push( $tags, esc_html( $tag->name ) );
					}
				}

				// Set post data
				$post_data = apply_filters( 'wpcom_crosspost_post_data', array(
					'post_date'      => $post->date,
					'post_title'     => esc_html( $post->title ),
					'post_name'      => sanitize_title( $post->slug ),
					'post_content'   => wp_kses_post( $post->excerpt ),
					'comment_status' => $this->_settings['close_comments'] === 'yes' ? 'closed' : 'open',
					'post_status'    => 'publish',
					'post_author'    => absint( $author ),
					'post_category'  => array( absint( $this->_settings['category'] ) ),
					'tags_input'     => $tags,
					'meta_input'     => array(
						/**
						 * Support for Yoast SEO's cross-site canonical URL.
						 *
						 * @uses wpseo_canonical
						 * @see  WPCOM_CrossPost::set_crossdomain_canonical_url
						 */
						'_wpcom-crosspost-original_url' => esc_url_raw( $post->URL ),
					)
				), $post, $this->_settings );

				// If the post does not exist, create it
				if ( ! post_exists( $post->title, '', $post->date ) ) {
					$post_id = wp_insert_post( $post_data );

					// Set post format to Link
					if ( 0 !== $post_id && ! is_wp_error( $post_id ) ) {
						set_post_format( $post_id, 'link' );
					}

					return $post_id;
				}
			}
		}

		return false;
	}

	/**
	 * Sets the canonical URL properly with Yoast SEO using the original post URL.
	 *
	 * @param  string $url
	 * @return sttring
	 */
	public function set_crossdomain_canonical_url( $url ) {
		global $post;

		if ( false !== get_post_meta( $post->ID, '_wpcom-crosspost-original_url', true ) ) {
			return get_post_meta( $post->ID, '_wpcom-crosspost-original_url', true );
		}

		return $url;
	}

	/**
	 * Loads localization files
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wpcom-crosspost', false, __DIR__ . '/languages' );
	}
}

new WPCOM_CrossPost();
