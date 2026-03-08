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

		try {
			// Analyze sentiment
			$sentiment_data = Sentiment_Analyzer::analyze( $review_text );

			// Only generate responses for negative reviews
			if ( 'negative' !== $sentiment_data['sentiment'] ) {
				return;
			}

			// Generate AI response
			$ai_response = self::generate_response( $post->ID, $review_text, $sentiment_data );

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

		} catch ( \Exception $e ) {
			error_log( 'Failed to generate review response: ' . $e->getMessage() );
		}
	}

	/**
	 * Generate AI response for a review
	 *
	 * @param int    $product_id Product ID.
	 * @param string $review_text Original review text.
	 * @param array  $sentiment_data Sentiment analysis data.
	 * @return string Generated response.
	 * @throws \Exception If API call fails.
	 */
	public static function generate_response( $product_id, $review_text, $sentiment_data ) {
		$api_key = Settings::get_setting( 'gemini_api_key' );

		if ( empty( $api_key ) ) {
			throw new \Exception( 'Gemini API key not configured' );
		}

		// Get product details for context
		$product = wc_get_product( $product_id );
		$product_name = $product ? $product->get_name() : '';

		// Build prompt for response generation
		$prompt = self::build_response_prompt( $product_name, $review_text, $sentiment_data );

		// Call API
		$response_body = self::call_gemini_api( $prompt, $api_key );

		// Extract text
		$data = json_decode( $response_body, true );

		if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			throw new \Exception( 'Invalid API response' );
		}

		$response_text = $data['candidates'][0]['content']['parts'][0]['text'];

		// Clean up response (remove markdown code blocks if present)
		$response_text = preg_replace( '/^```(?:json|text)?\s*\n?/', '', $response_text );
		$response_text = preg_replace( '/\n?```$/', '', $response_text );
		$response_text = trim( $response_text );

		return wp_kses_post( $response_text );
	}

	/**
	 * Build response generation prompt
	 *
	 * @param string $product_name Product name.
	 * @param string $review_text Review text.
	 * @param array  $sentiment_data Sentiment analysis data.
	 * @return string Formatted prompt.
	 */
	private static function build_response_prompt( $product_name, $review_text, $sentiment_data ) {
		$sentiment = $sentiment_data['sentiment'];

		return <<<PROMPT
You are a professional customer service representative for an e-commerce store. A customer left a $sentiment review.

Product: $product_name
Customer Review: "$review_text"

Generate a professional, empathetic response to this review. The response should:
1. Acknowledge the customer's feedback
2. Show genuine concern (for negative reviews)
3. Offer a concrete solution or next step
4. Be 2-3 sentences maximum
5. Be warm and human, not robotic

Generate ONLY the response text, no quotes or extra formatting.
PROMPT;
	}

	/**
	 * Call Google Gemini API
	 *
	 * @param string $prompt Prompt text.
	 * @param string $api_key API key.
	 * @return string API response.
	 * @throws \Exception If request fails.
	 */
	private static function call_gemini_api( $prompt, $api_key ) {
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . urlencode( $api_key );

		$request_body = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => $prompt,
						),
					),
				),
			),
		);

		$args = array(
			'body'        => wp_json_encode( $request_body ),
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'timeout'     => 30,
			'sslverify'   => true,
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'API request failed: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			$error_data = json_decode( $body, true );
			$error_msg  = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'Unknown error';
			throw new \Exception( 'Gemini API error: ' . $error_msg );
		}

		return $body;
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
			return self::generate_response( $product->get_id(), $review_text, $sentiment_data );
		} catch ( \Exception $e ) {
			throw new \Exception( 'Failed to generate response: ' . $e->getMessage() );
		}
	}
}
