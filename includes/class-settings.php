<?php
/**
 * Settings class - handles admin settings page
 *
 * @package WC_AI_Review_Manager
 */

namespace WC_AI_Review_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	const SETTINGS_KEY = 'wc_ai_review_manager_settings';

	/**
	 * Initialize settings
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Add admin menu page
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'AI Review Manager', 'wc-ai-review-manager' ),
			__( 'AI Review Manager', 'wc-ai-review-manager' ),
			'manage_woocommerce',
			'wc-ai-review-manager',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public static function register_settings() {
		register_setting(
			'wc_ai_review_manager_group',
			self::SETTINGS_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'wc_ai_review_manager_section',
			__( 'WooCommerce AI Review Manager Settings', 'wc-ai-review-manager' ),
			array( __CLASS__, 'render_section' ),
			'wc_ai_review_manager_group'
		);

		// Enable plugin toggle
		add_settings_field(
			'enabled',
			__( 'Enable AI Review Manager', 'wc-ai-review-manager' ),
			array( __CLASS__, 'render_enabled_field' ),
			'wc_ai_review_manager_group',
			'wc_ai_review_manager_section'
		);

		// Auto-invite toggle
		add_settings_field(
			'auto_invite_enabled',
			__( 'Send Automatic Review Invitations', 'wc-ai-review-manager' ),
			array( __CLASS__, 'render_auto_invite_field' ),
			'wc_ai_review_manager_group',
			'wc_ai_review_manager_section'
		);

		// Days after purchase
		add_settings_field(
			'days_after_purchase',
			__( 'Days After Purchase to Send Invitation', 'wc-ai-review-manager' ),
			array( __CLASS__, 'render_days_field' ),
			'wc_ai_review_manager_group',
			'wc_ai_review_manager_section'
		);

		// Auto-respond toggle
		add_settings_field(
			'auto_respond_negative',
			__( 'Auto-generate Responses for Negative Reviews', 'wc-ai-review-manager' ),
			array( __CLASS__, 'render_auto_respond_field' ),
			'wc_ai_review_manager_group',
			'wc_ai_review_manager_section'
		);

		// Sentiment threshold
		add_settings_field(
			'negative_threshold',
			__( 'Negative Sentiment Threshold (0-1)', 'wc-ai-review-manager' ),
			array( __CLASS__, 'render_threshold_field' ),
			'wc_ai_review_manager_group',
			'wc_ai_review_manager_section'
		);
	}

	/**
	 * Render settings section
	 */
	public static function render_section() {
		echo wp_kses_post( wpautop( __( 'Configure the WooCommerce AI Review Manager plugin. AI inference is powered by the WordPress AI Client.', 'wc-ai-review-manager' ) ) );
	}

	/**
	 * Render enabled field
	 */
	public static function render_enabled_field() {
		$settings = self::get_settings();
		$enabled  = isset( $settings['enabled'] ) ? $settings['enabled'] : 1;
		?>
		<input type="checkbox" 
			name="<?php echo esc_attr( self::SETTINGS_KEY . '[enabled]' ); ?>" 
			value="1"
			<?php checked( 1, $enabled ); ?> />
		<span class="description">
			<?php esc_html_e( 'Enable the review collection and analysis system', 'wc-ai-review-manager' ); ?>
		</span>
		<?php
	}

	/**
	 * Render auto-invite field
	 */
	public static function render_auto_invite_field() {
		$settings            = self::get_settings();
		$auto_invite_enabled = isset( $settings['auto_invite_enabled'] ) ? $settings['auto_invite_enabled'] : 1;
		?>
		<input type="checkbox" 
			name="<?php echo esc_attr( self::SETTINGS_KEY . '[auto_invite_enabled]' ); ?>" 
			value="1"
			<?php checked( 1, $auto_invite_enabled ); ?> />
		<span class="description">
			<?php esc_html_e( 'Automatically send review invitations when orders are completed', 'wc-ai-review-manager' ); ?>
		</span>
		<?php
	}

	/**
	 * Render days after purchase field
	 */
	public static function render_days_field() {
		$settings              = self::get_settings();
		$days_after_purchase   = isset( $settings['days_after_purchase'] ) ? $settings['days_after_purchase'] : 7;
		?>
		<input type="number" 
			name="<?php echo esc_attr( self::SETTINGS_KEY . '[days_after_purchase]' ); ?>" 
			value="<?php echo esc_attr( $days_after_purchase ); ?>" 
			min="1"
			max="365"
			class="small-text" />
		<span class="description">
			<?php esc_html_e( 'Days to wait after order completion before sending the review invitation', 'wc-ai-review-manager' ); ?>
		</span>
		<?php
	}

	/**
	 * Render auto-respond field
	 */
	public static function render_auto_respond_field() {
		$settings                = self::get_settings();
		$auto_respond_negative   = isset( $settings['auto_respond_negative'] ) ? $settings['auto_respond_negative'] : 1;
		?>
		<input type="checkbox" 
			name="<?php echo esc_attr( self::SETTINGS_KEY . '[auto_respond_negative]' ); ?>" 
			value="1"
			<?php checked( 1, $auto_respond_negative ); ?> />
		<span class="description">
			<?php esc_html_e( 'Generate AI responses for negative reviews automatically', 'wc-ai-review-manager' ); ?>
		</span>
		<?php
	}

	/**
	 * Render negative sentiment threshold field
	 */
	public static function render_threshold_field() {
		$settings            = self::get_settings();
		$negative_threshold  = isset( $settings['negative_threshold'] ) ? $settings['negative_threshold'] : 0.4;
		?>
		<input type="number" 
			name="<?php echo esc_attr( self::SETTINGS_KEY . '[negative_threshold]' ); ?>" 
			value="<?php echo esc_attr( $negative_threshold ); ?>" 
			min="0"
			max="1"
			step="0.01"
			class="small-text" />
		<span class="description">
			<?php esc_html_e( 'Scores below this value are marked as negative (0.4 recommended)', 'wc-ai-review-manager' ); ?>
		</span>
		<?php
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $settings Settings array.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( $settings ) {
		$sanitized = array();

		$sanitized['enabled']              = isset( $settings['enabled'] ) ? 1 : 0;
		$sanitized['auto_invite_enabled']  = isset( $settings['auto_invite_enabled'] ) ? 1 : 0;
		$sanitized['auto_respond_negative'] = isset( $settings['auto_respond_negative'] ) ? 1 : 0;

		if ( isset( $settings['days_after_purchase'] ) ) {
			$sanitized['days_after_purchase'] = max( 1, min( 365, absint( $settings['days_after_purchase'] ) ) );
		} else {
			$sanitized['days_after_purchase'] = 7;
		}

		if ( isset( $settings['negative_threshold'] ) ) {
			$sanitized['negative_threshold'] = max( 0, min( 1, floatval( $settings['negative_threshold'] ) ) );
		} else {
			$sanitized['negative_threshold'] = 0.4;
		}

		return $sanitized;
	}

	/**
	 * Get settings
	 *
	 * @return array Settings array.
	 */
	public static function get_settings() {
		return get_option( self::SETTINGS_KEY, array() );
	}

	/**
	 * Get a specific setting value
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed Setting value or default.
	 */
	public static function get_setting( $key, $default = '' ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_wc-ai-review-manager' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wc-ai-review-manager-admin',
			WC_AI_REVIEW_MANAGER_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WC_AI_REVIEW_MANAGER_VERSION
		);
	}

	/**
	 * Render settings page
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'wc-ai-review-manager' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Review Manager Settings', 'wc-ai-review-manager' ); ?></h1>
			
			<?php settings_errors(); ?>
			
			<form method="post" action="options.php">
				<?php settings_fields( 'wc_ai_review_manager_group' ); ?>
				<?php do_settings_sections( 'wc_ai_review_manager_group' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
