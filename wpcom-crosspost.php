<?php
/**
 * Plugin Name: WordPress.com Cross-Post
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
	 * Plugin version
	 */
	const VERSION = '1.0.0';

	/**
	 * WordPress.com API endpoint.
	 * @var string
	 */
	public $api_url = 'https://public-api.wordpress.com/rest/v1.1/sites/';

	/**
	 * Plugin settings
	 * @var object
	 * @access private
	 */
	private $_settings = null;

	/**
	 * Inits the plugin and schedule the hooks.
	 */
	public function __construct() {
		// Create and clear the schedule for creating cross-posts automatically every day
		register_activation_hook( __FILE__, array( $this, 'schedule_cross_posts_creation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'remove_schedule_cross_posts_creation' ) );

		// Hook the creation process to the scheduled hook
		add_action( 'wpcom_crossposts_create_posts', array( $this, 'create_cross_posts' ) );

		if ( is_admin() ) {
			require_once untrailingslashit( __DIR__ ) . '/class-wpcom-crosspost-admin.php';
		}

		$this->_settings = get_option( 'wpcom-crosspost-settings' );

		// Load localization files
		$this->load_textdomain();
	}

	/**
	 * Schedules a event to create cross posts every day.
	 */
	public function schedule_cross_posts_creation() {
		if ( ! wp_next_scheduled ( 'wpcom_crossposts_create_posts' ) ) {
			wp_schedule_event( current_time( 'timestamp' ), apply_filters( 'wpcom_crosspost_sync_frequency', 'daily' ), 'wpcom_crossposts_create_posts' );
		}
	}

	/**
	 * Clears the scheduled hook whenthe plugin is deactivated.
	 */
	public function remove_schedule_cross_posts_creation() {
		wp_clear_scheduled_hook( 'wpcom_crossposts_create_posts' );
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

		return $this->_make_api_call( '/posts', array(
			'after' => date( 'Y-m-d', $from ),
		) );
	}

	/**
	 * Creates cross-posts from WP.com posts.
	 *
	 * @param  string  $from
	 * @return bool
	 */
	public function create_cross_posts( $from = '-7 days' ) {
		$posts = $this->get_posts( $from );

		if ( ! function_exists( 'post_exists' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/post.php' );
		}

		if ( false !== $posts && count( $posts ) > 0 ) {
			foreach ( $posts as $post ) {
				$read_original = apply_filters( 'wpcom_crosspost_more_text', '<p>' . sprintf(
					__( 'Read more on %s', 'wpcom-crosspost' ),
					'<a class="more-link" href="' . esc_url( $post->URL ) . '" title="' . sanitize_title( $post->title ) . '">WIHT</a>'
				) . '</p>', $post );

				$author = get_user_by( 'email', apply_filters( 'wpcom_crosspost_author_email', get_bloginfo( 'admin_email' ) ) );
				if ( ! is_wp_error( $author ) ) {
					$author = $author->ID;
				} else {
					$author = 0;
				}

				$post_data = apply_filters( 'wpcom_crosspost_post_data', array(
					'post_date'      => $post->date,
					'post_title'     => esc_html( $post->title ),
					'post_name'      => sanitize_title( $post->slug ),
					'post_content'   => wp_kses_post( $post->excerpt . $read_original ),
					'comment_status' => $this->_settings['close_comments'] === 'yes' ? 'closed' : 'open',
					'post_status'    => 'publish',
					'post_author'    => $author,
					'post_category'  => array( $this->_settings['category'] ),
				), $post, $this->_settings );

				if ( ! post_exists( $post->title, '', $post->date ) ) {
					$post_id = wp_insert_post( $post_data );

					if ( 0 !== $post_id && ! is_wp_error( $post_id ) ) {
						set_post_format( $post_id, 'link' );
						update_post_meta( $post_id, '_wpcom-crosspost-original_url', $post->URL );
					}

					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Loads localization files
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wpcom-crosspost', false, untrailingslashit( __DIR__ ) . '/languages' );
	}

	/**
	 * Makes an API call to WordPress.com
	 *
	 * @param  string $endpoint
	 * @param  array  $params
	 * @return object|boolean
	 */
	private function _make_api_call( $endpoint, $params = array() ) {
		$endpoint = $this->api_url . $this->_settings['website'] . $endpoint;

		$params = apply_filters( 'wpcom_crosspost_api_call_params', wp_parse_args( $params, array(
			'after'  => date( 'Y-m-d', strtotime( '-1 month' ) ),
			'order'  => 'ASC',
			'fields' => 'title,slug,URL,date,excerpt',
			'status' => 'publish',
		) ) );

		$query     = http_build_query( $params );
		$endpoint .= '?'. $query;
		$data      = array( 'user-agent' => 'WPCOM-CrossPost/' . self::VERSION . ';' . home_url() );

		$response = wp_remote_get( $endpoint, $data );

		error_log( print_r( $response, true ) );

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$body  = json_decode( wp_remote_retrieve_body( $response ) );
			return $body->posts;
		}

		return false;
	}
}

new WPCOM_CrossPost();
