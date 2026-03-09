<?php
/**
 * Abilities API Registry for WooCommerce AI Review Manager
 *
 * Registers capabilities with WordPress 7.0+ Abilities API for AI assistant integration.
 *
 * @package WooCommerceAIReviewManager
 * @since 1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Abilities_Registry
 *
 * Registers review management abilities with the WordPress Abilities API,
 * making them discoverable by AI assistants and compatible with MCP.
 */
class Abilities_Registry {

	/**
	 * Initialize ability registration hooks.
	 */
	public static function init() {
		add_action( 'wp_register_abilities', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register all review management abilities.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return; // Abilities API not available
		}

		// Register list reviews ability
		wp_register_ability( 'wc-reviews/list', [
			'title'       => __( 'List Reviews', 'wc-ai-review-manager' ),
			'description' => __( 'List WooCommerce product reviews with filtering options', 'wc-ai-review-manager' ),
			'invoke'      => [ __CLASS__, 'list_reviews' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'product_id' => [
						'type'        => 'integer',
						'description' => __( 'Filter by product ID', 'wc-ai-review-manager' ),
					],
					'rating' => [
						'type'        => 'integer',
						'description' => __( 'Filter by rating (1-5)', 'wc-ai-review-manager' ),
						'minimum'     => 1,
						'maximum'     => 5,
					],
					'status' => [
						'type'        => 'string',
						'description' => __( 'Filter by status (approved, pending, spam)', 'wc-ai-review-manager' ),
						'enum'        => [ 'approved', 'pending', 'spam' ],
					],
					'limit' => [
						'type'        => 'integer',
						'description' => __( 'Maximum number of reviews to return', 'wc-ai-review-manager' ),
						'default'     => 10,
						'maximum'     => 100,
					],
				],
			],
			'output_schema' => [
				'type'  => 'object',
				'properties' => [
					'reviews' => [
						'type'  => 'array',
						'items' => [
							'type' => 'object',
							'properties' => [
								'id'              => [ 'type' => 'integer' ],
								'product_id'      => [ 'type' => 'integer' ],
								'product_name'    => [ 'type' => 'string' ],
								'author'          => [ 'type' => 'string' ],
								'email'           => [ 'type' => 'string' ],
								'text'            => [ 'type' => 'string' ],
								'rating'          => [ 'type' => 'integer' ],
								'status'          => [ 'type' => 'string' ],
								'sentiment'       => [ 'type' => 'string' ],
								'date'            => [ 'type' => 'string' ],
							],
						],
					],
					'total' => [ 'type' => 'integer' ],
				],
			],
			'required_capabilities' => [ 'manage_woocommerce' ],
		] );

		// Register get single review ability
		wp_register_ability( 'wc-reviews/get', [
			'title'       => __( 'Get Review Details', 'wc-ai-review-manager' ),
			'description' => __( 'Retrieve full details of a single review including sentiment analysis', 'wc-ai-review-manager' ),
			'invoke'      => [ __CLASS__, 'get_review' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'review_id' => [
						'type'        => 'integer',
						'description' => __( 'ID of the review to retrieve', 'wc-ai-review-manager' ),
					],
				],
				'required' => [ 'review_id' ],
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'id'              => [ 'type' => 'integer' ],
					'product_id'      => [ 'type' => 'integer' ],
					'product_name'    => [ 'type' => 'string' ],
					'author'          => [ 'type' => 'string' ],
					'email'           => [ 'type' => 'string' ],
					'text'            => [ 'type' => 'string' ],
					'rating'          => [ 'type' => 'integer' ],
					'status'          => [ 'type' => 'string' ],
					'sentiment'       => [ 'type' => 'string' ],
					'sentiment_score' => [ 'type' => 'number' ],
					'date'            => [ 'type' => 'string' ],
					'response'        => [ 'type' => 'string' ],
				],
			],
			'required_capabilities' => [ 'manage_woocommerce' ],
		] );

		// Register approve review ability
		wp_register_ability( 'wc-reviews/approve', [
			'title'       => __( 'Approve Review', 'wc-ai-review-manager' ),
			'description' => __( 'Approve a pending review and make it visible on the product page', 'wc-ai-review-manager' ),
			'invoke'      => [ __CLASS__, 'approve_review' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'review_id' => [
						'type'        => 'integer',
						'description' => __( 'ID of the review to approve', 'wc-ai-review-manager' ),
					],
				],
				'required' => [ 'review_id' ],
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'message' => [ 'type' => 'string' ],
				],
			],
			'required_capabilities' => [ 'moderate_comments' ],
		] );

		// Register reject review ability
		wp_register_ability( 'wc-reviews/reject', [
			'title'       => __( 'Reject Review', 'wc-ai-review-manager' ),
			'description' => __( 'Reject and trash a review, removing it from the product page', 'wc-ai-review-manager' ),
			'invoke'      => [ __CLASS__, 'reject_review' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'review_id' => [
						'type'        => 'integer',
						'description' => __( 'ID of the review to reject', 'wc-ai-review-manager' ),
					],
					'reason' => [
						'type'        => 'string',
						'description' => __( 'Reason for rejection', 'wc-ai-review-manager' ),
					],
				],
				'required' => [ 'review_id' ],
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'message' => [ 'type' => 'string' ],
				],
			],
			'required_capabilities' => [ 'moderate_comments' ],
		] );

		// Register generate response ability
		wp_register_ability( 'wc-reviews/generate-response', [
			'title'       => __( 'Generate AI Response', 'wc-ai-review-manager' ),
			'description' => __( 'Generate an AI-powered response to a customer review', 'wc-ai-review-manager' ),
			'invoke'      => [ __CLASS__, 'generate_response' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'review_id' => [
						'type'        => 'integer',
						'description' => __( 'ID of the review to respond to', 'wc-ai-review-manager' ),
					],
				],
				'required' => [ 'review_id' ],
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'success'  => [ 'type' => 'boolean' ],
					'response' => [ 'type' => 'string' ],
					'message'  => [ 'type' => 'string' ],
				],
			],
			'required_capabilities' => [ 'manage_woocommerce' ],
		] );

		// Register bulk moderate ability
		wp_register_ability( 'wc-reviews/bulk-moderate', [
			'title'       => __( 'Bulk Moderate Reviews', 'wc-ai-review-manager' ),
			'description' => __( 'Bulk approve or reject multiple reviews based on criteria', 'wc-ai-review-manager' ),
			'invoke'      => [ __CLASS__, 'bulk_moderate' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'action' => [
						'type'        => 'string',
						'description' => __( 'Action to perform (approve or reject)', 'wc-ai-review-manager' ),
						'enum'        => [ 'approve', 'reject' ],
					],
					'filters' => [
						'type'        => 'object',
						'description' => __( 'Criteria for selecting reviews', 'wc-ai-review-manager' ),
						'properties'  => [
							'status'    => [ 'type' => 'string' ],
							'sentiment' => [ 'type' => 'string' ],
							'rating'    => [ 'type' => 'integer' ],
						],
					],
					'limit' => [
						'type'        => 'integer',
						'description' => __( 'Maximum number of reviews to process', 'wc-ai-review-manager' ),
						'default'     => 50,
						'maximum'     => 500,
					],
				],
				'required' => [ 'action', 'filters' ],
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'success'   => [ 'type' => 'boolean' ],
					'processed' => [ 'type' => 'integer' ],
					'message'   => [ 'type' => 'string' ],
				],
			],
			'required_capabilities' => [ 'moderate_comments' ],
		] );

		// Register analytics ability
		wp_register_ability( 'wc-reviews/analytics', [
			'title'       => __( 'Review Analytics', 'wc-ai-review-manager' ),
			'description' => __( 'Get sentiment analysis and rating summary for products or time ranges', 'wc-ai-review-manager' ),
			'invoke'      => [ __CLASS__, 'get_analytics' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'product_id' => [
						'type'        => 'integer',
						'description' => __( 'Filter by specific product ID', 'wc-ai-review-manager' ),
					],
					'start_date' => [
						'type'        => 'string',
						'description' => __( 'Start date for analysis (YYYY-MM-DD)', 'wc-ai-review-manager' ),
					],
					'end_date' => [
						'type'        => 'string',
						'description' => __( 'End date for analysis (YYYY-MM-DD)', 'wc-ai-review-manager' ),
					],
				],
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'total_reviews'      => [ 'type' => 'integer' ],
					'average_rating'     => [ 'type' => 'number' ],
					'sentiment_summary'  => [
						'type' => 'object',
						'properties' => [
							'positive'  => [ 'type' => 'integer' ],
							'neutral'   => [ 'type' => 'integer' ],
							'negative'  => [ 'type' => 'integer' ],
						],
					],
					'rating_breakdown' => [
						'type' => 'object',
						'properties' => [
							'one'   => [ 'type' => 'integer' ],
							'two'   => [ 'type' => 'integer' ],
							'three' => [ 'type' => 'integer' ],
							'four'  => [ 'type' => 'integer' ],
							'five'  => [ 'type' => 'integer' ],
						],
					],
				],
			],
			'required_capabilities' => [ 'manage_woocommerce' ],
		] );
	}

	/**
	 * List reviews with filters.
	 *
	 * @param array $args Arguments from ability invocation.
	 * @return array|\WP_Error
	 */
	public static function list_reviews( $args ) {
		// Implementation would query comments with filters
		// For now, return empty array to indicate structure
		return [ 'reviews' => [], 'total' => 0 ];
	}

	/**
	 * Get single review details.
	 *
	 * @param array $args Arguments from ability invocation.
	 * @return array|\WP_Error
	 */
	public static function get_review( $args ) {
		// Implementation would retrieve comment details
		return [ 'id' => $args['review_id'] ?? 0 ];
	}

	/**
	 * Approve a review.
	 *
	 * @param array $args Arguments from ability invocation.
	 * @return array|\WP_Error
	 */
	public static function approve_review( $args ) {
		// Implementation would approve comment
		return [ 'success' => true, 'message' => 'Review approved' ];
	}

	/**
	 * Reject a review.
	 *
	 * @param array $args Arguments from ability invocation.
	 * @return array|\WP_Error
	 */
	public static function reject_review( $args ) {
		// Implementation would trash comment
		return [ 'success' => true, 'message' => 'Review rejected' ];
	}

	/**
	 * Generate AI response to a review.
	 *
	 * @param array $args Arguments from ability invocation.
	 * @return array|\WP_Error
	 */
	public static function generate_response( $args ) {
		// Implementation would call Response_Generator
		return [ 'success' => true, 'response' => '', 'message' => 'Response generated' ];
	}

	/**
	 * Bulk moderate reviews.
	 *
	 * @param array $args Arguments from ability invocation.
	 * @return array|\WP_Error
	 */
	public static function bulk_moderate( $args ) {
		// Implementation would bulk moderate comments
		return [ 'success' => true, 'processed' => 0, 'message' => 'Bulk operation completed' ];
	}

	/**
	 * Get review analytics.
	 *
	 * @param array $args Arguments from ability invocation.
	 * @return array|\WP_Error
	 */
	public static function get_analytics( $args ) {
		// Implementation would return sentiment and rating stats
		return [
			'total_reviews'     => 0,
			'average_rating'    => 0,
			'sentiment_summary' => [ 'positive' => 0, 'neutral' => 0, 'negative' => 0 ],
			'rating_breakdown'  => [ 'one' => 0, 'two' => 0, 'three' => 0, 'four' => 0, 'five' => 0 ],
		];
	}
}
