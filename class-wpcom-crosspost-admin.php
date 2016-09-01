<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WPCOM_CrossPost_Admin {
	/**
	 * Plugin settings
	 * @var array
	 */
	public $settings = array();

	/**
	 * Adds menu item and page and inits the settings.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		$this->init();

		// Check if cron jobs are enabled and eventually show an error message
		if ( defined( 'DISABLE_WP_CRON' ) && true === DISABLE_WP_CRON ) {
			add_action( 'admin_notices', array( $this, 'cron_disabled_error_notice' ) );
		}
	}

	/**
	 * Inits settings
	 */
	public function init() {
		$this->settings = get_option( 'wpcom-crosspost-settings' );
	}

	/**
	 * Adds the Settings menu item
	 */
	public function admin_menu() {
		add_options_page(
			'WP.com X-Post',
			'WP.com X-Post',
			'manage_options',
			'wpcom-crosspost.php',
			array( $this, 'settings_page_contents' )
		);
	}
	/**
	 * Prints the Settings page
	 */
	public function settings_page_contents() {
		?>
		<div class="wrap">
			<h2>WordPress.com Cross-Post Settings</h2>

			<form method="post" action="options.php">
				<?php wp_nonce_field ( 'update-options' ); ?>
				<?php settings_fields( 'wpcom-crosspost-settings' ); ?>
				<?php do_settings_sections( 'wpcom-crosspost-settings' ); ?>
				<p class="submit">
					<input name="Submit" type="submit" class="button-primary" value="Save Changes" />
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Registers all the settigns
	 */
	public function admin_init() {
		// General Settings
		register_setting( 'wpcom-crosspost-settings', 'wpcom-crosspost-settings', array( $this, 'validate_settings') );

		// Options
		add_settings_section( 'configuration', __( 'Configuration', 'wpcom-crosspost' ), false, 'wpcom-crosspost-settings' );
		add_settings_field( 'website', __( 'Website', 'wpcom-crosspost' ), array( $this, 'settings_field_website' ), 'wpcom-crosspost-settings', 'configuration' );
		add_settings_field( 'category', __( 'Category', 'wpcom-crosspost' ), array( $this, 'settings_field_category' ), 'wpcom-crosspost-settings', 'configuration' );
		add_settings_field( 'close_comments', __( 'Close Comments', 'wpcom-crosspost' ), array( $this, 'settings_field_close_comments' ), 'wpcom-crosspost-settings', 'configuration' );
	}

	/**
	 * Prints the Website field in the settings
	 */
	public function settings_field_website() {
		?>
		<input type="text" class="regular-text" id="website" name="wpcom-crosspost-settings[website]" value="<?php echo esc_attr( $this->settings['website'] ); ?>" placeholder="<?php esc_attr_e( 'awesome.wordpress.com', 'wpcom-crosspost' ); ?>" />
		<p class="description"><?php _e( 'Write your WordPress.com website domain here, <strong>without including</strong> the protocol (http:// or https://)', 'wpcom-crosspost' ); ?></p>
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
			'selected'         => $this->settings['category'],
			'show_option_none' => __( 'Select category', 'wpcom-crosspost' ),
			'orderby'          => 'name',
			'hide_empty'       => false,
		);

		wp_dropdown_categories( $args );
		?>
		<p class="description"><?php _e( 'Choose the category to use for your cross-posts.', 'wpcom-crosspost' ); ?></p>
		<?php
	}

	/**
	 * Prints the Close Comments field in the settings
	 */
	public function settings_field_close_comments() {
		?>
		<input type="checkbox" id="close_comments" name="wpcom-crosspost-settings[close_comments]" value="yes" <?php checked( 'yes', $this->settings['close_comments'] ); ?> /> <span class="description"><?php _e( 'Close comments on cross-posts.', 'wpcom-crosspost' ); ?></span>
		<?php
	}

	/**
	 * Validates and escapes the settings
	 *
	 * @param  array $settings
	 * @return array
	 */
	public function validate_settings( $settings ) {
		if ( isset( $settings['website'] ) ) {
			$settings['website'] = sanitize_text_field( $settings['website'] );
			$settings['website'] = preg_replace( '#http(s)?://#', '', $settings['website'] );
		}

		// Force negative value to avoid PHP errors
		if ( ! isset( $settings['close_comments'] ) ) {
			$settings['close_comments'] = 'no';
		}

		return $settings;
	}

	/**
	 * Shows an error message when DISABLE_WP_CRON is enabled in wp-config.php
	 */
	public function cron_disabled_error_notice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php _e( '<strong>Cron Jobs are disabled!</strong> WordPress.com Cross-Post will not sync posts. Please enable cron jobs by removing the code <code>define(\'DISABLE_WP_CRON\');</code> from your <code>wp-config.php</code> file.', 'wpcom-crosspost' ); ?></p>
		</div>
		<?php
	}
}

new WPCOM_CrossPost_Admin();
