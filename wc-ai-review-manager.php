<?php
/**
 * Plugin Name: WooCommerce AI Review Manager
 * Description: Automatically collect reviews, analyze sentiment with AI, and generate responses for WooCommerce stores
 * Version: 1.0.0
 * Author: Odin Collective
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-ai-review-manager
 * Domain Path: /languages
 * Requires: 5.6
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC Tested up to: 8.5
 *
 * @package WC_AI_Review_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'WC_AI_REVIEW_MANAGER_VERSION', '1.0.0' );
define( 'WC_AI_REVIEW_MANAGER_PLUGIN_FILE', __FILE__ );
define( 'WC_AI_REVIEW_MANAGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_AI_REVIEW_MANAGER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_AI_REVIEW_MANAGER_INC_DIR', WC_AI_REVIEW_MANAGER_PLUGIN_DIR . 'includes/' );

/**
 * Check if WooCommerce is active
 */
function wc_ai_review_manager_check_woocommerce() {
	if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
		add_action( 'admin_notices', 'wc_ai_review_manager_woocommerce_missing_notice' );
		return false;
	}
	return true;
}

/**
 * Display WooCommerce missing notice
 */
function wc_ai_review_manager_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error is-dismissible">
		<p><?php esc_html_e( 'WooCommerce AI Review Manager requires WooCommerce to be installed and activated.', 'wc-ai-review-manager' ); ?></p>
	</div>
	<?php
}

/**
 * Initialize the plugin
 */
function wc_ai_review_manager_init() {
	if ( ! wc_ai_review_manager_check_woocommerce() ) {
		return;
	}

	// Load required files
	require_once WC_AI_REVIEW_MANAGER_INC_DIR . 'class-sentiment-analyzer.php';
	require_once WC_AI_REVIEW_MANAGER_INC_DIR . 'class-review-collector.php';
	require_once WC_AI_REVIEW_MANAGER_INC_DIR . 'class-review-response-generator.php';
	require_once WC_AI_REVIEW_MANAGER_INC_DIR . 'class-dashboard.php';
	require_once WC_AI_REVIEW_MANAGER_INC_DIR . 'class-settings.php';
	require_once WC_AI_REVIEW_MANAGER_INC_DIR . 'class-database.php';
	require_once WC_AI_REVIEW_MANAGER_INC_DIR . 'class-email-templates.php';

	// Initialize core classes
	WC_AI_Review_Manager\Database::init();
	WC_AI_Review_Manager\Settings::init();
	WC_AI_Review_Manager\Review_Collector::init();
	WC_AI_Review_Manager\Review_Response_Generator::init();
	WC_AI_Review_Manager\Dashboard::init();
	WC_AI_Review_Manager\Email_Templates::init();
}

add_action( 'plugins_loaded', 'wc_ai_review_manager_init' );

/**
 * Activation hook
 */
function wc_ai_review_manager_activate() {
	if ( ! wc_ai_review_manager_check_woocommerce() ) {
		wp_die( esc_html__( 'WooCommerce is required to activate WooCommerce AI Review Manager.', 'wc-ai-review-manager' ) );
	}

	// Create database tables
	require_once WC_AI_REVIEW_MANAGER_INC_DIR . 'class-database.php';
	WC_AI_Review_Manager\Database::create_tables();

	// Set default options
	update_option( 'wc_ai_review_manager_enabled', 1 );
	update_option( 'wc_ai_review_manager_auto_invite_enabled', 1 );
	update_option( 'wc_ai_review_manager_days_after_purchase', 7 );
}
register_activation_hook( __FILE__, 'wc_ai_review_manager_activate' );

/**
 * Deactivation hook
 */
function wc_ai_review_manager_deactivate() {
	// Clean up scheduled events
	wp_clear_scheduled_hook( 'wc_ai_review_manager_send_invitations' );
}
register_deactivation_hook( __FILE__, 'wc_ai_review_manager_deactivate' );

/**
 * Load text domain for translations
 */
function wc_ai_review_manager_load_textdomain() {
	load_plugin_textdomain( 'wc-ai-review-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'wc_ai_review_manager_load_textdomain' );
