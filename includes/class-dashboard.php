<?php
/**
 * Dashboard class - displays sentiment analytics and pending responses
 *
 * @package WC_AI_Review_Manager
 */

namespace WC_AI_Review_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dashboard {

	/**
	 * Initialize dashboard
	 */
	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_dashboard_widgets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Add dashboard widgets
	 */
	public static function add_dashboard_widgets() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'wc_ai_review_manager_sentiment',
			__( 'Review Sentiment Analysis', 'wc-ai-review-manager' ),
			array( __CLASS__, 'render_sentiment_widget' )
		);

		wp_add_dashboard_widget(
			'wc_ai_review_manager_pending',
			__( 'Pending AI Responses', 'wc-ai-review-manager' ),
			array( __CLASS__, 'render_pending_widget' )
		);
	}

	/**
	 * Render sentiment dashboard widget
	 */
	public static function render_sentiment_widget() {
		$stats = Database::get_sentiment_stats( 30 );

		$positive = isset( $stats['positive'] ) ? intval( $stats['positive']->count ) : 0;
		$neutral  = isset( $stats['neutral'] ) ? intval( $stats['neutral']->count ) : 0;
		$negative = isset( $stats['negative'] ) ? intval( $stats['negative']->count ) : 0;
		$total    = $positive + $neutral + $negative;

		if ( 0 === $total ) {
			echo '<p>' . esc_html__( 'No reviews analyzed in the last 30 days.', 'wc-ai-review-manager' ) . '</p>';
			return;
		}

		?>
		<div class="wc-ai-sentiment-stats">
			<div class="sentiment-item positive">
				<div class="sentiment-label">
					<span class="emoji">😊</span>
					<span class="label"><?php esc_html_e( 'Positive', 'wc-ai-review-manager' ); ?></span>
				</div>
				<div class="sentiment-count"><?php echo intval( $positive ); ?></div>
				<div class="sentiment-percent"><?php echo intval( ( $positive / $total ) * 100 ); ?>%</div>
			</div>

			<div class="sentiment-item neutral">
				<div class="sentiment-label">
					<span class="emoji">😐</span>
					<span class="label"><?php esc_html_e( 'Neutral', 'wc-ai-review-manager' ); ?></span>
				</div>
				<div class="sentiment-count"><?php echo intval( $neutral ); ?></div>
				<div class="sentiment-percent"><?php echo intval( ( $neutral / $total ) * 100 ); ?>%</div>
			</div>

			<div class="sentiment-item negative">
				<div class="sentiment-label">
					<span class="emoji">😞</span>
					<span class="label"><?php esc_html_e( 'Negative', 'wc-ai-review-manager' ); ?></span>
				</div>
				<div class="sentiment-count"><?php echo intval( $negative ); ?></div>
				<div class="sentiment-percent"><?php echo intval( ( $negative / $total ) * 100 ); ?>%</div>
			</div>
		</div>

		<p class="description">
			<?php echo wp_kses_post( sprintf( __( 'Total reviews analyzed: <strong>%d</strong> in the last 30 days', 'wc-ai-review-manager' ), $total ) ); ?>
		</p>

		<style>
			.wc-ai-sentiment-stats {
				display: flex;
				gap: 20px;
				margin-bottom: 20px;
				flex-wrap: wrap;
			}

			.sentiment-item {
				flex: 1;
				min-width: 150px;
				padding: 15px;
				border-radius: 8px;
				text-align: center;
				background-color: #f5f5f5;
			}

			.sentiment-item.positive {
				background-color: #d4edda;
				border-left: 4px solid #28a745;
			}

			.sentiment-item.neutral {
				background-color: #fff3cd;
				border-left: 4px solid #ffc107;
			}

			.sentiment-item.negative {
				background-color: #f8d7da;
				border-left: 4px solid #dc3545;
			}

			.sentiment-label {
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 8px;
				margin-bottom: 10px;
				font-weight: 600;
			}

			.sentiment-label .emoji {
				font-size: 1.5em;
			}

			.sentiment-count {
				font-size: 2em;
				font-weight: bold;
				margin-bottom: 5px;
			}

			.sentiment-percent {
				font-size: 0.9em;
				opacity: 0.8;
			}
		</style>
		<?php
	}

	/**
	 * Render pending responses widget
	 */
	public static function render_pending_widget() {
		$pending = Database::get_pending_responses( 5 );

		if ( empty( $pending ) ) {
			echo '<p>' . esc_html__( 'No pending responses.', 'wc-ai-review-manager' ) . '</p>';
			return;
		}

		echo '<ul style="list-style: none; padding: 0;">';

		foreach ( $pending as $response ) {
			$product = wc_get_product( $response->product_id );
			$product_name = $product ? $product->get_name() : 'Product #' . $response->product_id;
			?>
			<li style="padding: 10px; border-bottom: 1px solid #eee; margin-bottom: 10px;">
				<div>
					<strong><?php echo esc_html( $product_name ); ?></strong>
				</div>
				<div style="font-size: 0.9em; color: #666; margin: 5px 0;">
					From: <code><?php echo esc_html( $response->customer_email ); ?></code>
				</div>
				<div style="font-size: 0.85em; margin-bottom: 10px; background: #f5f5f5; padding: 8px; border-radius: 4px;">
					<strong><?php esc_html_e( 'Review:', 'wc-ai-review-manager' ); ?></strong>
					<p style="margin: 5px 0;"><?php echo wp_kses_post( substr( $response->review_content, 0, 150 ) ) . '...'; ?></p>
				</div>
				<div style="font-size: 0.85em; margin-bottom: 10px; background: #fff9e6; padding: 8px; border-radius: 4px; border-left: 3px solid #ffc107;">
					<strong><?php esc_html_e( 'AI Suggested Response:', 'wc-ai-review-manager' ); ?></strong>
					<p style="margin: 5px 0;"><?php echo wp_kses_post( $response->ai_response_generated ); ?></p>
				</div>
				<div>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wc-ai-review-manager', 'tab' => 'pending_responses' ) ) ); ?>" class="button button-small">
						<?php esc_html_e( 'Review & Respond', 'wc-ai-review-manager' ); ?>
					</a>
				</div>
			</li>
			<?php
		}

		echo '</ul>';

		$total_pending = count( Database::get_pending_responses( 999 ) );
		if ( $total_pending > 5 ) {
			echo '<p><a href="' . esc_url( add_query_arg( array( 'page' => 'wc-ai-review-manager', 'tab' => 'pending_responses' ) ) ) . '">';
			printf( esc_html__( 'View all %d pending responses', 'wc-ai-review-manager' ), $total_pending );
			echo '</a></p>';
		}
	}

	/**
	 * Enqueue dashboard scripts
	 */
	public static function enqueue_scripts( $hook ) {
		wp_enqueue_style(
			'wc-ai-review-manager-dashboard',
			WC_AI_REVIEW_MANAGER_PLUGIN_URL . 'assets/css/dashboard.css',
			array(),
			WC_AI_REVIEW_MANAGER_VERSION
		);
	}

	/**
	 * Render sentiment page in admin
	 */
	public static function render_sentiment_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'wc-ai-review-manager' ) );
		}

		// Get stats for different time periods
		$stats_7d  = Database::get_sentiment_stats( 7 );
		$stats_30d = Database::get_sentiment_stats( 30 );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sentiment Analytics', 'wc-ai-review-manager' ); ?></h1>

			<div class="tablenav top">
				<div class="alignleft">
					<h3><?php esc_html_e( 'Last 7 Days', 'wc-ai-review-manager' ); ?></h3>
				</div>
			</div>

			<?php self::render_sentiment_chart( $stats_7d ); ?>

			<div class="tablenav top">
				<div class="alignleft">
					<h3><?php esc_html_e( 'Last 30 Days', 'wc-ai-review-manager' ); ?></h3>
				</div>
			</div>

			<?php self::render_sentiment_chart( $stats_30d ); ?>
		</div>
		<?php
	}

	/**
	 * Render sentiment chart
	 *
	 * @param array $stats Stats array.
	 */
	private static function render_sentiment_chart( $stats ) {
		$positive = isset( $stats['positive'] ) ? intval( $stats['positive']->count ) : 0;
		$neutral  = isset( $stats['neutral'] ) ? intval( $stats['neutral']->count ) : 0;
		$negative = isset( $stats['negative'] ) ? intval( $stats['negative']->count ) : 0;
		$total    = $positive + $neutral + $negative;

		?>
		<table class="wp-list-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Sentiment', 'wc-ai-review-manager' ); ?></th>
					<th><?php esc_html_e( 'Count', 'wc-ai-review-manager' ); ?></th>
					<th><?php esc_html_e( 'Percentage', 'wc-ai-review-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Positive 😊', 'wc-ai-review-manager' ); ?></td>
					<td><?php echo intval( $positive ); ?></td>
					<td><strong><?php echo $total > 0 ? intval( ( $positive / $total ) * 100 ) : 0; ?>%</strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Neutral 😐', 'wc-ai-review-manager' ); ?></td>
					<td><?php echo intval( $neutral ); ?></td>
					<td><strong><?php echo $total > 0 ? intval( ( $neutral / $total ) * 100 ) : 0; ?>%</strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Negative 😞', 'wc-ai-review-manager' ); ?></td>
					<td><?php echo intval( $negative ); ?></td>
					<td><strong><?php echo $total > 0 ? intval( ( $negative / $total ) * 100 ) : 0; ?>%</strong></td>
				</tr>
				<tr style="background-color: #f5f5f5; font-weight: bold;">
					<td><?php esc_html_e( 'Total', 'wc-ai-review-manager' ); ?></td>
					<td><?php echo intval( $total ); ?></td>
					<td>100%</td>
				</tr>
			</tbody>
		</table>
		<?php
	}
}
