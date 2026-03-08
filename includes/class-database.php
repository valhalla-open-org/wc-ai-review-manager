<?php
/**
 * Database class - handles table creation and schema
 *
 * @package WC_AI_Review_Manager
 */

namespace WC_AI_Review_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Database {

	/**
	 * Initialize database module
	 */
	public static function init() {
		// Hook into admin_init for table creation on plugin update
		add_action( 'admin_init', array( __CLASS__, 'check_schema_version' ) );
	}

	/**
	 * Create plugin tables
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$version         = WC_AI_REVIEW_MANAGER_VERSION;
		$stored_version  = get_option( 'wc_ai_review_manager_db_version' );

		// Only create tables if version doesn't match
		if ( $stored_version === $version ) {
			return;
		}

		// Table for tracking review invitations sent
		$table_invitations = $wpdb->prefix . 'wc_ai_review_invitations';
		$sql[]             = "CREATE TABLE IF NOT EXISTS $table_invitations (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			order_id bigint(20) NOT NULL,
			customer_email varchar(255) NOT NULL,
			customer_name varchar(255) DEFAULT NULL,
			product_id bigint(20) NOT NULL,
			product_name varchar(255) DEFAULT NULL,
			invitation_sent_date datetime DEFAULT CURRENT_TIMESTAMP,
			invitation_opened bool DEFAULT 0,
			opened_date datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY product_id (product_id),
			KEY invitation_sent_date (invitation_sent_date)
		) $charset_collate;";

		// Table for tracking AI-generated responses
		$table_responses = $wpdb->prefix . 'wc_ai_review_responses';
		$sql[]           = "CREATE TABLE IF NOT EXISTS $table_responses (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			review_id bigint(20) NOT NULL,
			product_id bigint(20) NOT NULL,
			customer_email varchar(255) NOT NULL,
			review_content longtext DEFAULT NULL,
			sentiment varchar(20) DEFAULT NULL,
			sentiment_score decimal(4,2) DEFAULT NULL,
			ai_response_generated longtext DEFAULT NULL,
			ai_response_used bool DEFAULT 0,
			custom_response longtext DEFAULT NULL,
			response_status varchar(20) DEFAULT 'pending',
			created_date datetime DEFAULT CURRENT_TIMESTAMP,
			updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY review_id (review_id),
			KEY product_id (product_id),
			KEY sentiment (sentiment),
			KEY response_status (response_status),
			KEY created_date (created_date)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		update_option( 'wc_ai_review_manager_db_version', $version );
	}

	/**
	 * Check if schema needs updating
	 */
	public static function check_schema_version() {
		$current_version = get_option( 'wc_ai_review_manager_db_version' );
		if ( $current_version !== WC_AI_REVIEW_MANAGER_VERSION ) {
			self::create_tables();
		}
	}

	/**
	 * Log review invitation sent
	 *
	 * @param int    $order_id Order ID.
	 * @param int    $product_id Product ID.
	 * @param string $customer_email Customer email.
	 * @param string $customer_name Customer name.
	 * @param string $product_name Product name.
	 * @return int|false Insert ID or false on failure.
	 */
	public static function log_invitation( $order_id, $product_id, $customer_email, $customer_name, $product_name ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wc_ai_review_invitations';

		return $wpdb->insert(
			$table,
			array(
				'order_id'       => absint( $order_id ),
				'product_id'     => absint( $product_id ),
				'customer_email' => sanitize_email( $customer_email ),
				'customer_name'  => sanitize_text_field( $customer_name ),
				'product_name'   => sanitize_text_field( $product_name ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Create or update AI response record
	 *
	 * @param int    $review_id Review ID from WordPress.
	 * @param int    $product_id Product ID.
	 * @param string $customer_email Customer email.
	 * @param string $review_content Review text.
	 * @param string $sentiment Sentiment (positive, neutral, negative).
	 * @param float  $sentiment_score Score from 0 to 1.
	 * @param string $ai_response Generated AI response.
	 * @return int|false Insert ID or false on failure.
	 */
	public static function create_response_record( $review_id, $product_id, $customer_email, $review_content, $sentiment, $sentiment_score, $ai_response ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wc_ai_review_responses';

		$wpdb->insert(
			$table,
			array(
				'review_id'             => absint( $review_id ),
				'product_id'            => absint( $product_id ),
				'customer_email'        => sanitize_email( $customer_email ),
				'review_content'        => wp_kses_post( $review_content ),
				'sentiment'             => sanitize_text_field( $sentiment ),
				'sentiment_score'       => floatval( $sentiment_score ),
				'ai_response_generated' => wp_kses_post( $ai_response ),
				'response_status'       => 'pending',
			),
			array( '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Get sentiment statistics for dashboard
	 *
	 * @param int $days Number of days to look back.
	 * @return array Array of sentiment counts.
	 */
	public static function get_sentiment_stats( $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wc_ai_review_responses';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sentiment, COUNT(*) as count 
				FROM $table 
				WHERE created_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
				GROUP BY sentiment",
				absint( $days )
			),
			OBJECT_K
		);
	}

	/**
	 * Get sentiment stats by product
	 *
	 * @param int $product_id Product ID.
	 * @param int $days Number of days to look back.
	 * @return array Array of sentiment counts.
	 */
	public static function get_product_sentiment_stats( $product_id, $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wc_ai_review_responses';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sentiment, COUNT(*) as count 
				FROM $table 
				WHERE product_id = %d AND created_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
				GROUP BY sentiment",
				absint( $product_id ),
				absint( $days )
			),
			OBJECT_K
		);
	}

	/**
	 * Get pending responses awaiting shop owner action
	 *
	 * @param int $limit Results limit.
	 * @return array Array of response records.
	 */
	public static function get_pending_responses( $limit = 10 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wc_ai_review_responses';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table 
				WHERE response_status = 'pending'
				ORDER BY created_date DESC
				LIMIT %d",
				absint( $limit )
			)
		);
	}

	/**
	 * Update response status
	 *
	 * @param int    $response_id Response record ID.
	 * @param string $status New status.
	 * @param string $custom_response Custom response if using that.
	 * @return bool Success or failure.
	 */
	public static function update_response_status( $response_id, $status, $custom_response = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wc_ai_review_responses';

		$data = array( 'response_status' => sanitize_text_field( $status ) );

		if ( ! empty( $custom_response ) ) {
			$data['custom_response'] = wp_kses_post( $custom_response );
			$data['ai_response_used'] = 0;
		}

		return $wpdb->update(
			$table,
			$data,
			array( 'id' => absint( $response_id ) ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);
	}
}
