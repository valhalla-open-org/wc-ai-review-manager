<?php
/**
 * Review Response Generator class - generates AI responses for reviews
 *
 * @package WC_AI_Review_Manager
 */

namespace WC_AI_Review_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Review_Response_Generator {

	/**
	 * Initialize response generator
	 */
	public static function init() {
		// Hook into new comment creation to generate responses
		add_action( 'comment_post', array( __CLASS__, 'maybe_generate_response' ), 10, 2 );
	}

	/**
	 * Check if should generate response and do so
	 *
	 * @param int     $comment_id Comment ID.
	 * @param WP_Comment $comment Comment object.
	 */
	public static function maybe_generate_response( $comment_id, $comment ) {
		// Verify this is a product review
		if ( 'comment' !== $comment->comment_type ) {
			return;
		}

		// Check if plugin is enabled
		if ( ! Settings::get_setting( 'enabled', 1 ) ) {
			return;
		}

		// Check if auto-respond is enabled
		if ( ! Settings::get_setting( 'auto_respond_negative', 1 ) ) {
			return;
		}

		// Get the post (product)
		$post = get_post( $comment->comment_post_ID );
		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		$review_text = wp_kses_post( $comment->comment_content );

		// Don't analyze very short reviews (likely spam)
		if ( strlen( trim( $review_text ) ) < 10 ) {
			return;
		}

		try {
			// Analyze sentiment
			$sentiment_data = Sentiment_Analyzer::analyze( $review_text );

			// Only generate responses for negative reviews
			if ( 'negative' !== $sentiment_data['sentiment'] ) {
				return;
			}

			// Generate AI response
			$ai_response = self::generate_response( $post->ID, $review_text, $sentiment_data, $comment );

			// Store in database
			Database::create_response_record(
				$comment_id,
				$post->ID,
				$comment->comment_author_email,
				$review_text,
				$sentiment_data['sentiment'],
				$sentiment_data['score'],
				$ai_response
			);

			/**
			 * Fires after response is generated
			 *
			 * @param int    $comment_id Comment ID.
			 * @param int    $product_id Product ID.
			 * @param string $ai_response Generated response.
			 * @param array  $sentiment_data Sentiment analysis data.
			 */
			do_action( 'wc_ai_review_manager_response_generated', $comment_id, $post->ID, $ai_response, $sentiment_data );

		} catch ( \Exception $e ) {
			error_log( 'Failed to generate review response: ' . $e->getMessage() );
		}
	}

	/**
	 * Generate AI response for a review with product context
	 *
	 * @param int    $product_id Product ID.
	 * @param string $review_text Original review text.
	 * @param array  $sentiment_data Sentiment analysis data.
	 * @param WP_Comment|null $comment Comment object for additional context.
	 * @return string Generated response.
	 * @throws \Exception If AI call fails.
	 */
	public static function generate_response( $product_id, $review_text, $sentiment_data, $comment = null ) {
		if ( ! function_exists( 'wp_ai_client' ) ) {
			throw new \Exception( __( 'WordPress AI Client is not available', 'wc-ai-review-manager' ) );
		}

		// Get product details for context
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			throw new \Exception( 'Product not found' );
		}

		$product_name = $product->get_name();
		$product_type = $product->get_type();

		// Get reviewer rating if available (1-5 stars from WooCommerce)
		$rating = $comment ? get_comment_meta( $comment->comment_ID, 'rating', true ) : '';

		// Build prompt for response generation
		$prompt = self::build_response_prompt( $product_name, $product_type, $review_text, $sentiment_data, $rating );

		// Call AI Client
		try {
			$response = wp_ai_client()->complete( array(
				'prompt' => $prompt,
			) );

			if ( is_wp_error( $response ) ) {
				throw new \Exception( 'AI request failed: ' . $response->get_error_message() );
			}

			$response_text = $response['content'];

			// Clean up response (remove markdown code blocks if present)
			$response_text = preg_replace( '/^```(?:json|text|markdown)?\s*\n?/', '', $response_text );
			$response_text = preg_replace( '/\n?```$/', '', $response_text );
			$response_text = trim( $response_text );

			return wp_kses_post( $response_text );
		} catch ( \Exception $e ) {
			error_log( 'Review response generation error: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Build response generation prompt with rich context
	 *
	 * @param string $product_name Product name.
	 * @param string $product_type Product type (simple, variable, etc).
	 * @param string $review_text Review text.
	 * @param array  $sentiment_data Sentiment analysis data.
	 * @param string $rating Customer rating (1-5 stars).
	 * @return string Formatted prompt.
	 */
	private static function build_response_prompt( $product_name, $product_type, $review_text, $sentiment_data, $rating = '' ) {
		$sentiment = $sentiment_data['sentiment'];
		$score     = $sentiment_data['score'];

		$rating_context = '';
		if ( $rating ) {
			$rating_context = "Customer Rating: $rating/5 stars\n";
		}

		$tone = $score < 0.2 ? 'empathetic and solution-focused' : 'appreciative and constructive';

		return <<<PROMPT
You are a professional customer service representative for an e-commerce store.

Product: $product_name
Product Type: $product_type
$rating_context
Customer Review: "$review_text"

Sentiment Analysis: $sentiment (confidence: $score)

Your task: Generate a brief, professional response to this $sentiment review.

Guidelines:
1. Tone: Be $tone - match the customer's energy
2. Length: 2-3 sentences maximum (concise and actionable)
3. Focus: 
   - Acknowledge their specific feedback
   - Show genuine concern (if negative)
   - Offer a concrete solution, improvement, or next step
4. Style: Professional but warm, not robotic
5. Avoid: Defensive language, excuses, generic responses
6. Call to action: For negative reviews, offer help (email, support ticket, return/refund)

Generate ONLY the response text, nothing else. Do not include quotes, formatting, or meta-commentary.
PROMPT;
	}

	/**
	 * Manually generate response for a specific review
	 *
	 * @param int $review_id Comment/review ID.
	 * @return string Generated response.
	 * @throws \Exception If review not found.
	 */
	public static function regenerate_response( $review_id ) {
		$comment = get_comment( $review_id );

		if ( ! $comment ) {
			throw new \Exception( 'Review not found' );
		}

		$review_text = wp_kses_post( $comment->comment_content );
		$product     = wc_get_product( $comment->comment_post_ID );

		if ( ! $product ) {
			throw new \Exception( 'Product not found' );
		}

		try {
			$sentiment_data = Sentiment_Analyzer::analyze( $review_text );
			return self::generate_response( $product->get_id(), $review_text, $sentiment_data, $comment );
		} catch ( \Exception $e ) {
			throw new \Exception( 'Failed to generate response: ' . $e->getMessage() );
		}
	}
}
