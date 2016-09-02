<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WPCOM_CrossPost_API {
	/**
	 * WPCOM_CrossPost_API instance
	 * @var WPCOM_CrossPost_API
	 * @static
	 * @access private
	 */
	private static $_instance = null;

	/**
	 * Plugin settings
	 * @var array
	 * @access private
	 */
	private $_settings = array();

	/**
	 * WordPress.com API endpoint.
	 * @var string
	 * @access private
	 */
	private $_api_url = 'https://public-api.wordpress.com/rest/v1.1';

	/**
	 * WordPress.com Request Token endpoint.
	 * @var string
	 * @access private
	 */
	private $_request_token_url = 'https://public-api.wordpress.com/oauth2/token';

	/**
	 * Main WPCOM_CrossPost_API Instance.
	 *
	 * Ensures only one instance of WPCOM_CrossPost_API is loaded or can be loaded.
	 *
	 * @static
	 * @return WPCOM_CrossPost_API
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private function __construct() {}

	/**
	 * Gets the authorization token from the code.
	 *
	 * @return bool
	 */
	public function get_token( $client_id, $secret, $code ) {
		$params = array(
			'client_id'     => absint( $client_id ),
			'client_secret' => esc_html( $secret ),
			'code'          => esc_html( $code ),
			'redirect_uri'  => admin_url( 'options-general.php?page=wpcom-crosspost' ),
			'grant_type'    => 'authorization_code',
		);

		$response = wp_remote_post( $this->_request_token_url, array(
			'user-agent' => 'WPCOM-CrossPost/' . WPCOM_CROSSPOST_VERSION . ';' . home_url(),
			'body'       => $params
		) );

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$body                = json_decode( wp_remote_retrieve_body( $response ) );
			$settings            = get_option( 'wpcom-crosspost-settings' );
			$settings['token']   = $body->access_token;
			$settings['blog_id'] = $body->blog_id;

			update_option( 'wpcom-crosspost-settings', $settings );

			return true;
		}

		return false;
	}

	/**
	 * Returns latest posts from a specific date.
	 *
	 * @param  int $blog_id
	 * @param  int $from
	 * @return object
	 */
	public function get_posts( $blog_id, $from ) {
		return $this->_make_api_call( '/sites/' . $blog_id . '/posts', array(
			'after'  => date( 'Y-m-d', $from ),
			'order'  => 'ASC',
			'fields' => 'title,slug,URL,date,excerpt,tags',
			'status' => 'publish',
		) );
	}

	/**
	 * Makes an API call to WordPress.com
	 *
	 * @param  string $endpoint
	 * @param  array  $params
	 * @return object|boolean
	 * @access private
	 */
	private function _make_api_call( $endpoint, $params = array() ) {
		$endpoint  = esc_url( $this->_api_url . $endpoint );
		$params    = apply_filters( 'wpcom_crosspost_api_call_params', $params );
		$query     = http_build_query( $params );
		$endpoint .= '?'. $query;
		$data      = array(
			'user-agent' => 'WPCOM-CrossPost/' . WPCOM_CROSSPOST_VERSION . ';' . home_url(),
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->_settings['token']
			)
		);

		$response = wp_remote_get( $endpoint, $data );

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$body  = json_decode( wp_remote_retrieve_body( $response ) );
			return $body;
		}

		return false;
	}
}
