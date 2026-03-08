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

	/**
	 * Analyze sentiment of review text
	 *
	 * @param string $review_text Review text to analyze.
	 * @return array Array containing sentiment label and score.
	 * @throws \Exception If API call fails.
	 */
	public static function analyze( $review_text ) {
		$api_key = Settings::get_setting( 'gemini_api_key' );

		if ( empty( $api_key ) ) {
			throw new \Exception( __( 'Gemini API key not configured', 'wc-ai-review-manager' ) );
		}

		$review_text = sanitize_text_field( $review_text );

		// Prepare the prompt for sentiment analysis
		$prompt = self::build_sentiment_prompt( $review_text );

		// Call Gemini API
		$response = self::call_gemini_api( $prompt, $api_key );

		// Parse the response
		return self::parse_sentiment_response( $response );
	}

	/**
	 * Build sentiment analysis prompt
	 *
	 * @param string $review_text Review text.
	 * @return string Formatted prompt.
	 */
	private static function build_sentiment_prompt( $review_text ) {
		return <<<PROMPT
Analyze the sentiment of the following product review. Respond with ONLY a JSON object (no markdown, no extra text).

Review: "$review_text"

Respond with this exact JSON format:
{
  "sentiment": "positive|neutral|negative",
  "score": <number between 0 and 1>,
  "summary": "<brief explanation>"
}

Where:
- sentiment: positive (happy customer), neutral (factual), negative (unhappy customer)
- score: 1.0 is most positive, 0.0 is most negative
- summary: One sentence explaining the sentiment
PROMPT;
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
		);

		$args = array(
			'body'        => wp_json_encode( $request_body ),
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'timeout'     => 30,
			'sslverify'   => true,
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
}
