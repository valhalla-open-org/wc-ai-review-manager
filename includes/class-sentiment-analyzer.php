<?php
/**
 * Sentiment Analyzer class - integrates with Google Gemini API
 *
 * @package WC_AI_Review_Manager
 */

namespace WC_AI_Review_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sentiment_Analyzer {

	const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
	const CACHE_PREFIX   = 'wc_ai_rm_sentiment_';
	const CACHE_TTL      = 30 * DAY_IN_SECONDS; // Cache for 30 days

	/**
	 * Analyze sentiment of review text with caching
	 *
	 * @param string $review_text Review text to analyze.
	 * @param bool   $use_cache Whether to use cached results.
	 * @return array Array containing sentiment label and score.
	 * @throws \Exception If API call fails.
	 */
	public static function analyze( $review_text, $use_cache = true ) {
		$api_key = Settings::get_setting( 'gemini_api_key' );

		if ( empty( $api_key ) ) {
			throw new \Exception( __( 'Gemini API key not configured', 'wc-ai-review-manager' ) );
		}

		$review_text = sanitize_text_field( $review_text );

		// Check cache first
		if ( $use_cache ) {
			$cached = self::get_cached_analysis( $review_text );
			if ( $cached ) {
				return $cached;
			}
		}

		// Prepare the prompt for sentiment analysis
		$prompt = self::build_sentiment_prompt( $review_text );

		// Call Gemini API with retry logic
		$response = self::call_gemini_api_with_retry( $prompt, $api_key );

		// Parse the response
		$result = self::parse_sentiment_response( $response );

		// Cache the result
		self::cache_analysis( $review_text, $result );

		return $result;
	}

	/**
	 * Get cached sentiment analysis
	 *
	 * @param string $review_text Review text.
	 * @return array|false Cached result or false if not found.
	 */
	private static function get_cached_analysis( $review_text ) {
		$cache_key = self::CACHE_PREFIX . md5( $review_text );
		return wp_cache_get( $cache_key );
	}

	/**
	 * Cache sentiment analysis result
	 *
	 * @param string $review_text Review text.
	 * @param array  $result Analysis result.
	 */
	private static function cache_analysis( $review_text, $result ) {
		$cache_key = self::CACHE_PREFIX . md5( $review_text );
		wp_cache_set( $cache_key, $result, '', self::CACHE_TTL );
	}

	/**
	 * Build sentiment analysis prompt
	 *
	 * @param string $review_text Review text.
	 * @return string Formatted prompt.
	 */
	private static function build_sentiment_prompt( $review_text ) {
		return <<<PROMPT
You are a professional sentiment analyst. Analyze the sentiment of this product review.

Review: "$review_text"

Respond with ONLY a valid JSON object (no markdown, no backticks, no extra text):

{
  "sentiment": "positive|neutral|negative",
  "score": <decimal between 0.0 and 1.0>,
  "summary": "<one sentence explanation>"
}

Classification guide:
- positive (score 0.7-1.0): Customer is satisfied, happy, or recommends the product
- neutral (score 0.4-0.6): Factual comment without clear satisfaction or dissatisfaction
- negative (score 0.0-0.3): Customer is dissatisfied, disappointed, or warns others

Important: Return ONLY the JSON object, nothing else.
PROMPT;
	}

	/**
	 * Call Google Gemini API with retry logic
	 *
	 * @param string $prompt Prompt text.
	 * @param string $api_key API key.
	 * @param int    $retry_count Current retry count.
	 * @return string API response text.
	 * @throws \Exception If request fails after retries.
	 */
	private static function call_gemini_api_with_retry( $prompt, $api_key, $retry_count = 0 ) {
		$max_retries = 3;

		try {
			return self::call_gemini_api( $prompt, $api_key );
		} catch ( \Exception $e ) {
			if ( $retry_count < $max_retries ) {
				// Exponential backoff: 1s, 2s, 4s
				$wait_seconds = pow( 2, $retry_count );
				sleep( $wait_seconds );
				return self::call_gemini_api_with_retry( $prompt, $api_key, $retry_count + 1 );
			}

			throw $e;
		}
	}

	/**
	 * Call Google Gemini API
	 *
	 * @param string $prompt Prompt text.
	 * @param string $api_key API key.
	 * @return string API response text.
	 * @throws \Exception If request fails.
	 */
	private static function call_gemini_api( $prompt, $api_key ) {
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
			// Add safety settings to improve response quality
			'safety_settings' => array(
				array(
					'category' => 'HARM_CATEGORY_UNSPECIFIED',
					'threshold' => 'BLOCK_NONE',
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
			'user-agent'  => 'WooCommerce-AI-Review-Manager/' . WC_AI_REVIEW_MANAGER_VERSION,
		);

		$url      = self::GEMINI_API_URL . '?key=' . urlencode( $api_key );
		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'API request failed: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			$error_data = json_decode( $body, true );
			$error_msg  = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'Unknown error';

			// Log errors for debugging
			error_log( 'Gemini API Error (' . $status_code . '): ' . $error_msg );

			throw new \Exception( 'Gemini API error: ' . $error_msg );
		}

		return $body;
	}

	/**
	 * Parse sentiment response from Gemini
	 *
	 * @param string $response API response body.
	 * @return array Parsed sentiment data.
	 * @throws \Exception If parsing fails.
	 */
	private static function parse_sentiment_response( $response ) {
		$data = json_decode( $response, true );

		if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			throw new \Exception( 'Invalid API response format' );
		}

		$text   = $data['candidates'][0]['content']['parts'][0]['text'];
		$parsed = json_decode( $text, true );

		if ( ! is_array( $parsed ) || ! isset( $parsed['sentiment'], $parsed['score'] ) ) {
			// Log the raw response for debugging
			error_log( 'Failed to parse sentiment data. Raw: ' . $text );
			throw new \Exception( 'Failed to parse sentiment data' );
		}

		// Validate sentiment value
		$valid_sentiments = array( 'positive', 'neutral', 'negative' );
		if ( ! in_array( $parsed['sentiment'], $valid_sentiments, true ) ) {
			$parsed['sentiment'] = 'neutral';
		}

		// Validate score is between 0 and 1
		$parsed['score'] = max( 0, min( 1, floatval( $parsed['score'] ) ) );

		return array(
			'sentiment' => $parsed['sentiment'],
			'score'     => $parsed['score'],
			'summary'   => isset( $parsed['summary'] ) ? sanitize_text_field( $parsed['summary'] ) : '',
		);
	}

	/**
	 * Classify sentiment based on score and threshold
	 *
	 * @param float $score Score between 0 and 1.
	 * @return string 'positive', 'neutral', or 'negative'.
	 */
	public static function classify_by_score( $score ) {
		$negative_threshold = Settings::get_setting( 'negative_threshold', 0.4 );
		$positive_threshold = 1 - $negative_threshold;

		if ( $score < $negative_threshold ) {
			return 'negative';
		} elseif ( $score > $positive_threshold ) {
			return 'positive';
		}

		return 'neutral';
	}

	/**
	 * Batch analyze multiple reviews
	 *
	 * @param array $reviews Array of review texts.
	 * @return array Array of sentiment analyses.
	 */
	public static function batch_analyze( $reviews ) {
		$results = array();

		foreach ( $reviews as $review ) {
			try {
				$results[] = self::analyze( $review );
			} catch ( \Exception $e ) {
				// Log error but continue processing
				error_log( 'Sentiment analysis error: ' . $e->getMessage() );
				$results[] = array(
					'sentiment' => 'neutral',
					'score'     => 0.5,
					'summary'   => 'Analysis failed',
					'error'     => $e->getMessage(),
				);
			}
		}

		return $results;
	}

	/**
	 * Clear sentiment analysis cache
	 *
	 * @param string $review_text Specific review to clear, or empty for all.
	 */
	public static function clear_cache( $review_text = '' ) {
		if ( ! empty( $review_text ) ) {
			$cache_key = self::CACHE_PREFIX . md5( $review_text );
			wp_cache_delete( $cache_key );
		} else {
			// Clear all sentiment caches
			wp_cache_flush();
		}
	}
}
