<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WPCOM_CrossPost_Admin {
	/**
	 * Plugin settings
	 * @var array
	 * @access private
	 */
	private $_settings = array();

	/**
	 * WPCOM_CrossPost_API instance
	 * @var WPCOM_CrossPost_API
	 * @access private
	 */
	private $_api = null;

	/**
	 * Adds menu item and page and inits the settings.
	 */
	public function __construct() {
		$this->_api = WPCOM_CrossPost_API::instance();

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		$this->_settings = wp_parse_args( get_option( 'wpcom-crosspost-settings' ), array(
			'client_id'      => '',
			'client_secret'  => '',
			'code'           => '',
			'token'          => '',
			'blog_id'        => 0,
			'category'       => 0,
			'close_comments' => 'yes'
		) );

		// Check if cron jobs are enabled and eventually show an error message
		if ( defined( 'DISABLE_WP_CRON' ) && true === DISABLE_WP_CRON ) {
			add_action( 'admin_notices', array( $this, 'cron_disabled_error_notice' ) );
		}
	}

	/**
	 * Adds the Settings menu item
	 */
	public function admin_menu() {
		add_options_page(
			__( 'WP.com X-Post', 'wpcom-crosspost' ),
			__( 'WP.com X-Post', 'wpcom-crosspost' ),
			'manage_options',
			'wpcom-crosspost',
			array( $this, 'settings_page_contents' )
		);
	}
	/**
	 * Prints the Settings page
	 */
	public function settings_page_contents() {
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'WordPress.com Cross-Post Settings', 'wpcom-crosspost' ); ?></h2>

			<form method="post" action="options.php">
				<?php wp_nonce_field ( 'update-options' ); ?>
				<?php settings_fields( 'wpcom-crosspost-settings' ); ?>
				<?php do_settings_sections( 'wpcom-crosspost-settings' ); ?>
				<p class="submit">
					<input name="submit" type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'wpcom-crosspost' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Registers all the settigns
	 */
	public function admin_init() {
		// Update auth code before to request the token
		if ( isset( $_GET['code'] ) && empty( $this->_settings['code'] ) ) {
			$this->_settings['code'] = esc_html( $_GET['code'] );
			update_option( 'wpcom-crosspost-settings', $this->_settings );
		}

		// If we have the code but not the token, get it
		if ( ! empty( $this->_settings['code'] ) && empty( $this->_settings['token'] ) ) {
			$this->_api->get_token( $this->_settings['client_id'], $this->_settings['client_secret'], $this->_settings['code'] );
		}

		// Delete options if disconnecting
		if ( isset( $_GET['action'] ) && esc_html( $_GET['action'] ) === 'disconnect_client' ) {
			$this->_disconnect_wpcom();
		}

		// General Settings
		register_setting( 'wpcom-crosspost-settings', 'wpcom-crosspost-settings', array( $this, 'validate_settings') );

		// Options
		add_settings_section( 'auth', __( 'Authentication', 'wpcom-crosspost' ), false, 'wpcom-crosspost-settings' );

		if ( empty( $this->_settings['client_id'] ) && empty( $this->_settings['client_secret'] ) ) {
			add_settings_field( 'create_app', __( 'App', 'wpcom-crosspost' ), array( $this, 'settings_field_create_app' ), 'wpcom-crosspost-settings', 'auth' );
		}

		if ( ! empty( $this->_settings['code'] ) && ! empty( $this->_settings['token'] ) ) {
			add_settings_field( 'disconnect_client', __( 'Disconnect', 'wpcom-crosspost' ), array( $this, 'settings_field_disconnect_client' ), 'wpcom-crosspost-settings', 'auth' );
		}

		add_settings_field( 'client_id', __( 'Client ID', 'wpcom-crosspost' ), array( $this, 'settings_field_client_id' ), 'wpcom-crosspost-settings', 'auth' );
		add_settings_field( 'client_secret', __( 'Client Secret', 'wpcom-crosspost' ), array( $this, 'settings_field_client_secret' ), 'wpcom-crosspost-settings', 'auth' );

		if ( ! empty( $this->_settings['client_id'] ) && ! empty( $this->_settings['client_secret'] )
			&& ( empty( $this->_settings['code'] ) || empty( $this->_settings['token'] ) ) ) {
			add_settings_field( 'connect_client', __( 'Connect', 'wpcom-crosspost' ), array( $this, 'settings_field_connect_client' ), 'wpcom-crosspost-settings', 'auth' );
		}

		add_settings_section( 'configuration', __( 'Configuration', 'wpcom-crosspost' ), false, 'wpcom-crosspost-settings' );

		add_settings_field( 'category', __( 'Category', 'wpcom-crosspost' ), array( $this, 'settings_field_category' ), 'wpcom-crosspost-settings', 'configuration' );
		add_settings_field( 'close_comments', __( 'Close Comments', 'wpcom-crosspost' ), array( $this, 'settings_field_close_comments' ), 'wpcom-crosspost-settings', 'configuration' );
	}

	/**
	 * Prints the Create App button in the settings
	 */
	public function settings_field_create_app() {
		$params = array(
			'title'        => get_bloginfo( 'name' ),
			'description'  => '',
			'url'          => home_url(),
			'redirect_uri' => admin_url( 'options-general.php?page=wpcom-crosspost' )
		);

		$connect_url = 'https://developer.wordpress.com/apps/new/?' . http_build_query( $params );
		?>
		<a href="<?php echo esc_url( $connect_url ); ?>" class="button" target="_blank"><?php esc_html_e( 'Create a WordPress.com App', 'wpcom-crosspost' ); ?></a>
		<p class="description"><?php esc_html_e( 'To use this plugin you need to create a WordPress.com App. Click this button to create one.', 'wpcom-crosspost' ); ?></p>
		<?php
	}

	/**
	 * Prints the Client ID field in the settings
	 */
	public function settings_field_client_id() {
		?>
		<input type="text" class="regular-text" id="website" name="wpcom-crosspost-settings[client_id]" value="<?php echo esc_attr( $this->_settings['client_id'] ); ?>" />
		<p class="description"><?php esc_html_e( 'Write your WordPress.com App client ID here.', 'wpcom-crosspost' ); ?></p>
		<?php
	}

	/**
	 * Prints the Client Secret field in the settings
	 */
	public function settings_field_client_secret() {
		?>
		<input type="text" class="regular-text" id="website" name="wpcom-crosspost-settings[client_secret]" value="<?php echo esc_attr( $this->_settings['client_secret'] ); ?>" />
		<p class="description"><?php esc_html_e( 'Write your WordPress.com App client secret here.', 'wpcom-crosspost' ); ?></p>
		<?php
	}

	/**
	 * Prints the Connect button in the settings
	 */
	public function settings_field_connect_client() {
		$params = array(
			'client_id'     => $this->_settings['client_id'],
			'redirect_uri'  => admin_url( 'options-general.php?page=wpcom-crosspost' ),
			'response_type' => 'code',
		);

		$connect_url = 'https://public-api.wordpress.com/oauth2/authorize?' . http_build_query( $params );
		?>
		<a href="<?php echo esc_url( $connect_url ); ?>" class="button button-primary"><?php esc_html_e( 'Connect to WordPress.com', 'wpcom-crosspost' ); ?></a>
		<p class="description"><?php esc_html_e( 'Connect this self-hosted site to your WordPress.com account and choose the blog to use.', 'wpcom-crosspost' ); ?></p>
		<?php
	}

	/**
	 * Prints the Disconnect button in the settings
	 */
	public function settings_field_disconnect_client() {
		?>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=wpcom-crosspost&action=disconnect_client' ) ) ?>" class="button primary"><?php esc_html_e( 'Disconnect WordPress.com', 'wpcom-crosspost' ); ?></a>
		<p class="description"><?php esc_html_e( 'Disconnect this self-hosted site from your WordPress.com account.', 'wpcom-crosspost' ); ?></p>
		<?php
	}

	/**
	 * Prints the Category field in the settings
	 */
	public function settings_field_category() {
		$args = array(
			'name'             => 'wpcom-crosspost-settings[category]',
			'id'               => 'category',
			'hierarchical'     => true,
			'selected'         => absint( $this->_settings['category'] ),
			'show_option_none' => __( 'Select category', 'wpcom-crosspost' ),
			'orderby'          => 'name',
			'hide_empty'       => false,
		);

		wp_dropdown_categories( $args );
		?>
		<p class="description"><?php esc_html_e( 'Choose the category to use for your cross-posts.', 'wpcom-crosspost' ); ?></p>
		<?php
	}

	/**
	 * Prints the Close Comments field in the settings
	 */
	public function settings_field_close_comments() {
		?>
		<input type="checkbox" id="close_comments" name="wpcom-crosspost-settings[close_comments]" value="yes" <?php checked( 'yes', $this->_settings['close_comments'] ); ?> /> <span class="description"><?php esc_html_e( 'Close comments on cross-posts.', 'wpcom-crosspost' ); ?></span>
		<?php
	}

	/**
	 * Validates and escapes the settings
	 *
	 * @param  array $settings
	 * @return array
	 */
	public function validate_settings( $settings ) {
		if ( isset( $settings['client_id'] ) ) {
			$settings['client_id'] = esc_html( $settings['client_id'] );
		}

		if ( isset( $settings['category'] ) ) {
			$settings['category'] = absint( $settings['category'] );
		}

		if ( isset( $settings['client_secret'] ) ) {
			$settings['client_secret'] = esc_html( $settings['client_secret'] );
		}

		// Force negative value to avoid PHP errors
		if ( ! isset( $settings['close_comments'] ) ) {
			$settings['close_comments'] = 'no';
		} else {
			$settings['close_comments'] = 'yes';
		}

		return $settings;
	}

	/**
	 * Shows an error message when DISABLE_WP_CRON is enabled in wp-config.php
	 */
	public function cron_disabled_error_notice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( '<strong>Cron Jobs are disabled!</strong> WordPress.com Cross-Post will not sync posts. Please enable cron jobs by removing the code <code>define(\'DISABLE_WP_CRON\');</code> from your <code>wp-config.php</code> file.', 'wpcom-crosspost' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Deletes connection options when disconnecting WordPress.com.
	 */
	private function _disconnect_wpcom() {
		$this->_settings['code']    = '';
		$this->_settings['token']   = '';
		$this->_settings['blog_id'] = 0;

		update_option( 'wpcom-crosspost-settings', $this->_settings );
	}
}

new WPCOM_CrossPost_Admin();
