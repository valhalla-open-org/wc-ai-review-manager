<?php
/**
 * Review Collector class - handles automatic review invitation sending
 *
 * @package WC_AI_Review_Manager
 */

namespace WC_AI_Review_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Review_Collector {

	/**
	 * Initialize review collector
	 */
	public static function init() {
		// Hook into order completion
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_completed' ) );

		// Scheduled event for batch sending invitations
		add_action( 'wc_ai_review_manager_send_invitations', array( __CLASS__, 'send_pending_invitations' ) );

		// Schedule the event if not already scheduled
		if ( ! wp_next_scheduled( 'wc_ai_review_manager_send_invitations' ) ) {
			wp_schedule_event( time(), 'daily', 'wc_ai_review_manager_send_invitations' );
		}
	}

	/**
	 * Handle order completion event
	 *
	 * @param int $order_id Order ID.
	 */
	public static function on_order_completed( $order_id ) {
		if ( ! Settings::get_setting( 'enabled', 1 ) ) {
			return;
		}

		if ( ! Settings::get_setting( 'auto_invite_enabled', 1 ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$days_to_wait = Settings::get_setting( 'days_after_purchase', 7 );

		// Schedule sending of invitations
		$delay_seconds = $days_to_wait * DAY_IN_SECONDS;

		wp_schedule_single_event(
			time() + $delay_seconds,
			'wc_ai_review_manager_send_review_invitation',
			array( $order_id )
		);

		// Also add a hook for immediate processing (in case scheduled events are unreliable)
		add_action( 'wc_ai_review_manager_send_review_invitation', array( __CLASS__, 'send_invitation' ), 10, 1 );
	}

	/**
	 * Send review invitation for a specific order
	 *
	 * @param int $order_id Order ID.
	 */
	public static function send_invitation( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Get order items
		$items = $order->get_items();

		if ( empty( $items ) ) {
			return;
		}

		$customer = $order->get_user();
		$customer_email = $order->get_billing_email();
		$customer_name  = $order->get_formatted_billing_full_name();

		// Send invitation for each product in the order
		foreach ( $items as $item ) {
			$product_id = $item->get_product_id();
			$product    = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			// Check if invitation was already sent for this product/customer combo
			if ( self::has_invitation_been_sent( $order_id, $product_id, $customer_email ) ) {
				continue;
			}

			// Check if customer already left a review
			if ( self::customer_has_reviewed_product( $product_id, $customer ) ) {
				continue;
			}

			// Log invitation
			$logged = Database::log_invitation(
				$order_id,
				$product_id,
				$customer_email,
				$customer_name,
				$product->get_name()
			);

			if ( $logged ) {
				// Send email invitation
				self::send_invitation_email(
					$customer_email,
					$customer_name,
					$product,
					$order
				);
			}
		}
	}

	/**
	 * Check if invitation was already sent for this product/customer combo
	 *
	 * @param int    $order_id Order ID.
	 * @param int    $product_id Product ID.
	 * @param string $customer_email Customer email.
	 * @return bool True if invitation was already sent.
	 */
	private static function has_invitation_been_sent( $order_id, $product_id, $customer_email ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wc_ai_review_invitations';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table 
				WHERE order_id = %d AND product_id = %d AND customer_email = %s",
				absint( $order_id ),
				absint( $product_id ),
				sanitize_email( $customer_email )
			)
		);

		return $count > 0;
	}

	/**
	 * Check if customer has already reviewed this product
	 *
	 * @param int      $product_id Product ID.
	 * @param WP_User|false $customer Customer object or false.
	 * @return bool True if customer has reviewed product.
	 */
	private static function customer_has_reviewed_product( $product_id, $customer ) {
		$args = array(
			'post_id'  => $product_id,
			'type'     => 'comment',
			'status'   => 'approve',
			'count'    => true,
		);

		if ( $customer ) {
			$args['user_id'] = $customer->ID;
		}

		$count = get_comments( $args );

		return $count > 0;
	}

	/**
	 * Send invitation email with improved copy
	 *
	 * @param string  $customer_email Customer email.
	 * @param string  $customer_name Customer name.
	 * @param WC_Product $product Product object.
	 * @param WC_Order $order Order object.
	 */
	private static function send_invitation_email( $customer_email, $customer_name, $product, $order ) {
		$to      = $customer_email;
		$subject = sprintf(
			__( '⭐ Share Your Thoughts About %s', 'wc-ai-review-manager' ),
			$product->get_name()
		);

		$product_url = get_permalink( $product->get_id() );
		$product_url = add_query_arg( 'review-form', '1', $product_url );

		$first_name = explode( ' ', $customer_name )[0];

		$message = sprintf(
			__( "Hi %s,

We hope you're enjoying your recent purchase of '%s'!

Your feedback truly matters. Product reviews help us improve our offerings and help other customers make confident decisions. It usually takes just 2-3 minutes to write a review.

Would you take a moment to share your experience?

%s

Whether it's a quick star rating or detailed feedback, we'd love to hear from you!

Thank you for supporting us,
%s Team",
				'wc-ai-review-manager'
			),
			esc_html( $first_name ),
			esc_html( $product->get_name() ),
			esc_url( $product_url ),
			esc_html( get_bloginfo( 'name' ) )
		);

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		/**
		 * Filter invitation email subject
		 *
		 * @param string  $subject Email subject.
		 * @param WC_Product $product Product object.
		 * @param WC_Order $order Order object.
		 */
		$subject = apply_filters( 'wc_ai_review_manager_invitation_subject', $subject, $product, $order );

		/**
		 * Filter invitation email message
		 *
		 * @param string  $message Email message.
		 * @param string  $customer_name Customer name.
		 * @param WC_Product $product Product object.
		 * @param WC_Order $order Order object.
		 */
		$message = apply_filters( 'wc_ai_review_manager_invitation_message', $message, $customer_name, $product, $order );

		$sent = wp_mail( $to, $subject, $message, $headers );

		if ( ! $sent ) {
			error_log( 'Failed to send review invitation for product ' . $product->get_id() . ' to ' . $customer_email );
		}

		/**
		 * Fires when invitation email is sent
		 *
		 * @param string  $customer_email Customer email.
		 * @param WC_Product $product Product.
		 * @param WC_Order $order Order.
		 * @param bool    $sent Whether email was sent successfully.
		 */
		do_action( 'wc_ai_review_manager_invitation_sent', $customer_email, $product, $order, $sent );
	}

	/**
	 * Send pending invitations (scheduled event)
	 */
	public static function send_pending_invitations() {
		if ( ! Settings::get_setting( 'auto_invite_enabled', 1 ) ) {
			return;
		}

		// Find orders completed N days ago
		$days_to_wait = Settings::get_setting( 'days_after_purchase', 7 );
		$date_before  = gmdate( 'Y-m-d H:i:s', time() - ( $days_to_wait * DAY_IN_SECONDS ) );
		$date_after   = gmdate( 'Y-m-d H:i:s', time() - ( ( $days_to_wait + 1 ) * DAY_IN_SECONDS ) );

		$orders = wc_get_orders(
			array(
				'status'    => 'completed',
				'date_completed_after' => $date_after,
				'date_completed_before' => $date_before,
				'limit'     => 100,
			)
		);

		foreach ( $orders as $order ) {
			self::send_invitation( $order->get_id() );
		}
	}

	/**
	 * Get invitation stats
	 *
	 * @param int $days Days to look back.
	 * @return array Stats array.
	 */
	public static function get_invitation_stats( $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wc_ai_review_invitations';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as total_invitations,
					SUM(invitation_opened) as opened_invitations
				FROM $table
				WHERE invitation_sent_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				absint( $days )
			)
		);
	}
}
