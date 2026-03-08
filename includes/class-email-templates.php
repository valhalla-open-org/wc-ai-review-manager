<?php
/**
 * Email Templates class - handles email template customization and management
 *
 * @package WC_AI_Review_Manager
 */

namespace WC_AI_Review_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email_Templates {

	const POST_TYPE = 'wc_ai_email_template';
	const META_KEY_DEFAULT = '_wc_ai_default_template';
	const META_KEY_HTML_CONTENT = '_wc_ai_html_content';

	/**
	 * Initialize email templates
	 */
	public static function init() {
		// Register custom post type
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );

		// Add admin menu
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );

		// Add meta boxes for template editing
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );

		// Save template meta
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_template_meta' ), 10, 2 );

		// Add template column to admin list
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'add_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_columns' ), 10, 2 );

		// Add admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );

		// AJAX handlers for preview
		add_action( 'wp_ajax_wc_ai_preview_email', array( __CLASS__, 'ajax_preview_email' ) );
		// AJAX handlers for duplication
		add_action( 'wp_ajax_wc_ai_duplicate_template', array( __CLASS__, 'ajax_duplicate_template' ) );
	}

	/**
	 * Register custom post type for email templates
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Email Templates', 'wc-ai-review-manager' ),
			'singular_name'      => __( 'Email Template', 'wc-ai-review-manager' ),
			'menu_name'          => __( 'Email Templates', 'wc-ai-review-manager' ),
			'name_admin_bar'     => __( 'Email Template', 'wc-ai-review-manager' ),
			'add_new'            => __( 'Add New Template', 'wc-ai-review-manager' ),
			'add_new_item'       => __( 'Add New Email Template', 'wc-ai-review-manager' ),
			'new_item'           => __( 'New Email Template', 'wc-ai-review-manager' ),
			'edit_item'          => __( 'Edit Email Template', 'wc-ai-review-manager' ),
			'view_item'          => __( 'View Email Template', 'wc-ai-review-manager' ),
			'all_items'          => __( 'All Email Templates', 'wc-ai-review-manager' ),
			'search_items'       => __( 'Search Email Templates', 'wc-ai-review-manager' ),
			'parent_item_colon'  => __( 'Parent Email Template:', 'wc-ai-review-manager' ),
			'not_found'          => __( 'No email templates found.', 'wc-ai-review-manager' ),
			'not_found_in_trash' => __( 'No email templates found in Trash.', 'wc-ai-review-manager' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => false, // We'll add our own menu item
			'query_var'           => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => null,
			'supports'            => array( 'title', 'revisions' ),
			'show_in_rest'        => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Add admin menu for email templates
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Email Templates', 'wc-ai-review-manager' ),
			__( 'Email Templates', 'wc-ai-review-manager' ),
			'manage_woocommerce',
			'edit.php?post_type=' . self::POST_TYPE
		);
	}

	/**
	 * Add meta boxes for template editing
	 */
	public static function add_meta_boxes() {
		add_meta_box(
			'wc_ai_template_editor',
			__( 'Template Editor', 'wc-ai-review-manager' ),
			array( __CLASS__, 'render_template_editor' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'wc_ai_template_preview',
			__( 'Live Preview', 'wc-ai-review-manager' ),
			array( __CLASS__, 'render_template_preview' ),
			self::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'wc_ai_template_placeholders',
			__( 'Available Placeholders', 'wc-ai-review-manager' ),
			array( __CLASS__, 'render_placeholders_info' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render template editor meta box
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function render_template_editor( $post ) {
		wp_nonce_field( 'wc_ai_save_template', 'wc_ai_template_nonce' );

		$html_content = get_post_meta( $post->ID, self::META_KEY_HTML_CONTENT, true );
		$is_default = get_post_meta( $post->ID, self::META_KEY_DEFAULT, true ) === '1';

		if ( empty( $html_content ) ) {
			$html_content = self::get_default_template_html();
		}

		?>
		<div class="wc-ai-template-editor">
			<div class="editor-options">
				<label>
					<input type="checkbox" name="wc_ai_default_template" value="1" <?php checked( $is_default ); ?> />
					<?php esc_html_e( 'Set as default template', 'wc-ai-review-manager' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'This template will be used for all review invitations.', 'wc-ai-review-manager' ); ?>
				</p>
			</div>

			<div class="template-editor-wrapper">
				<textarea id="wc_ai_html_content" name="wc_ai_html_content" rows="20" style="width: 100%; font-family: monospace;"><?php echo esc_textarea( $html_content ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'Use HTML and CSS to design your email template. Available placeholders are listed on the right.', 'wc-ai-review-manager' ); ?>
				</p>
			</div>

			<div class="template-variables">
				<h4><?php esc_html_e( 'Quick Insert Placeholders:', 'wc-ai-review-manager' ); ?></h4>
				<div class="variable-buttons">
					<button type="button" class="button button-small insert-placeholder" data-placeholder="{customer_name}"><?php esc_html_e( 'Customer Name', 'wc-ai-review-manager' ); ?></button>
					<button type="button" class="button button-small insert-placeholder" data-placeholder="{store_name}"><?php esc_html_e( 'Store Name', 'wc-ai-review-manager' ); ?></button>
					<button type="button" class="button button-small insert-placeholder" data-placeholder="{product_list}"><?php esc_html_e( 'Product List', 'wc-ai-review-manager' ); ?></button>
					<button type="button" class="button button-small insert-placeholder" data-placeholder="{review_link}"><?php esc_html_e( 'Review Link', 'wc-ai-review-manager' ); ?></button>
					<button type="button" class="button button-small insert-placeholder" data-placeholder="{expiry_date}"><?php esc_html_e( 'Expiry Date', 'wc-ai-review-manager' ); ?></button>
				</div>
			</div>
		</div>

		<style>
			.wc-ai-template-editor .editor-options {
				margin-bottom: 20px;
				padding: 10px;
				background: #f5f5f5;
				border-radius: 4px;
			}
			.wc-ai-template-editor .template-variables {
				margin-top: 20px;
				padding: 15px;
				background: #f9f9f9;
				border: 1px solid #ddd;
				border-radius: 4px;
			}
			.wc-ai-template-editor .variable-buttons {
				display: flex;
				gap: 8px;
				flex-wrap: wrap;
				margin-top: 10px;
			}
		</style>

		<script>
		jQuery(document).ready(function($) {
			// Insert placeholder into textarea
			$('.insert-placeholder').on('click', function() {
				var textarea = $('#wc_ai_html_content');
				var placeholder = $(this).data('placeholder');
				var current = textarea.val();
				var cursorPos = textarea.prop('selectionStart');
				var textBefore = current.substring(0, cursorPos);
				var textAfter = current.substring(cursorPos, current.length);
				
				textarea.val(textBefore + placeholder + textAfter);
				textarea.focus();
				
				// Update cursor position
				var newCursorPos = cursorPos + placeholder.length;
				textarea.prop('selectionStart', newCursorPos);
				textarea.prop('selectionEnd', newCursorPos);
				
				// Trigger preview update
				updatePreview();
			});

			// Auto-update preview on content change
			var previewTimeout;
			$('#wc_ai_html_content').on('input', function() {
				clearTimeout(previewTimeout);
				previewTimeout = setTimeout(updatePreview, 500);
			});

			function updatePreview() {
				var content = $('#wc_ai_html_content').val();
				var templateId = <?php echo esc_js( $post->ID ); ?>;
				
				// Show loading
				$('#wc_ai_preview_container').html('<p>Loading preview...</p>');
				
				$.post(ajaxurl, {
					action: 'wc_ai_preview_email',
					template_id: templateId,
					content: content,
					nonce: '<?php echo esc_js( wp_create_nonce( 'wc_ai_preview' ) ); ?>'
				}, function(response) {
					if (response.success) {
						$('#wc_ai_preview_container').html(response.data.preview);
					} else {
						$('#wc_ai_preview_container').html('<p class="error">' + response.data.message + '</p>');
					}
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Render template preview meta box
	 */
	public static function render_template_preview() {
		?>
		<div id="wc_ai_preview_container" style="background: white; padding: 15px; border: 1px solid #ddd; border-radius: 4px; max-height: 400px; overflow-y: auto;">
			<p><?php esc_html_e( 'Preview will appear here as you edit the template.', 'wc-ai-review-manager' ); ?></p>
		</div>
		<p class="description" style="margin-top: 10px;">
			<?php esc_html_e( 'Note: Preview uses sample data. Actual emails will use real customer and product information.', 'wc-ai-review-manager' ); ?>
		</p>
		<?php
	}

	/**
	 * Render placeholders info meta box
	 */
	public static function render_placeholders_info() {
		?>
		<div class="placeholder-list">
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Placeholder', 'wc-ai-review-manager' ); ?></th>
						<th><?php esc_html_e( 'Description', 'wc-ai-review-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>{customer_name}</code></td>
						<td><?php esc_html_e( 'Customer\'s first name', 'wc-ai-review-manager' ); ?></td>
					</tr>
					<tr>
						<td><code>{store_name}</code></td>
						<td><?php esc_html_e( 'Your store name', 'wc-ai-review-manager' ); ?></td>
					</tr>
					<tr>
						<td><code>{product_list}</code></td>
						<td><?php esc_html_e( 'List of purchased products with links', 'wc-ai-review-manager' ); ?></td>
					</tr>
					<tr>
						<td><code>{review_link}</code></td>
						<td><?php esc_html_e( 'Direct link to leave a review', 'wc-ai-review-manager' ); ?></td>
					</tr>
					<tr>
						<td><code>{expiry_date}</code></td>
						<td><?php esc_html_e( 'Date when review link expires (30 days from now)', 'wc-ai-review-manager' ); ?></td>
					</tr>
					<tr>
						<td><code>{order_date}</code></td>
						<td><?php esc_html_e( 'Date when order was placed', 'wc-ai-review-manager' ); ?></td>
					</tr>
					<tr>
						<td><code>{order_number}</code></td>
						<td><?php esc_html_e( 'Order ID/number', 'wc-ai-review-manager' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<style>
			.placeholder-list table {
				font-size: 13px;
			}
			.placeholder-list code {
				background: #f0f0f0;
				padding: 2px 4px;
				border-radius: 3px;
				font-family: monospace;
			}
		</style>
		<?php
	}

	/**
	 * Save template meta data
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 */
	public static function save_template_meta( $post_id, $post ) {
		// Check nonce
		if ( ! isset( $_POST['wc_ai_template_nonce'] ) || ! wp_verify_nonce( $_POST['wc_ai_template_nonce'], 'wc_ai_save_template' ) ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save HTML content
		if ( isset( $_POST['wc_ai_html_content'] ) ) {
			$html_content = wp_kses_post( $_POST['wc_ai_html_content'] );
			update_post_meta( $post_id, self::META_KEY_HTML_CONTENT, $html_content );
		}

		// Handle default template setting
		if ( isset( $_POST['wc_ai_default_template'] ) && '1' === $_POST['wc_ai_default_template'] ) {
			// Remove default flag from all other templates
			$existing_defaults = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'posts_per_page' => -1,
					'meta_key'       => self::META_KEY_DEFAULT,
					'meta_value'     => '1',
					'fields'         => 'ids',
					'post__not_in'   => array( $post_id ),
				)
			);

			foreach ( $existing_defaults as $existing_id ) {
				delete_post_meta( $existing_id, self::META_KEY_DEFAULT );
			}

			// Set this template as default
			update_post_meta( $post_id, self::META_KEY_DEFAULT, '1' );
		} else {
			delete_post_meta( $post_id, self::META_KEY_DEFAULT );
		}
	}

	/**
	 * Add admin columns to templates list
	 *
	 * @param array $columns Columns array.
	 * @return array Modified columns.
	 */
	public static function add_admin_columns( $columns ) {
		$new_columns = array();
		
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			
			if ( 'title' === $key ) {
				$new_columns['is_default'] = __( 'Default', 'wc-ai-review-manager' );
				$new_columns['shortcode'] = __( 'Shortcode', 'wc-ai-review-manager' );
			}
		}
		
		return $new_columns;
	}

	/**
	 * Render admin columns
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_admin_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'is_default':
				$is_default = get_post_meta( $post_id, self::META_KEY_DEFAULT, true ) === '1';
				if ( $is_default ) {
					echo '<span class="dashicons dashicons-star-filled" style="color: #ffb900;" title="' . esc_attr__( 'Default Template', 'wc-ai-review-manager' ) . '"></span>';
				}
				break;

			case 'shortcode':
				echo '<code>[wc_ai_email_template id="' . intval( $post_id ) . '"]</code>';
				break;
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public static function enqueue_admin_scripts( $hook ) {
		global $post_type;

		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		wp_enqueue_script(
			'wc-ai-email-templates',
			WC_AI_REVIEW_MANAGER_PLUGIN_URL . 'assets/js/email-templates.js',
			array( 'jquery' ),
			WC_AI_REVIEW_MANAGER_VERSION,
			true
		);

		wp_localize_script(
			'wc-ai-email-templates',
			'wcAiEmailTemplates',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wc_ai_preview' ),
				'loading_text' => __( 'Loading preview...', 'wc-ai-review-manager' ),
				'error_text' => __( 'Error', 'wc-ai-review-manager' ),
				'empty_warning' => __( 'The template is empty. Are you sure you want to save?', 'wc-ai-review-manager' ),
				'html_warning' => __( 'The template doesn\'t appear to have proper HTML structure. Are you sure you want to save?', 'wc-ai-review-manager' ),
				'duplicating_text' => __( 'Duplicating...', 'wc-ai-review-manager' ),
				'duplicate_text' => __( 'Duplicate', 'wc-ai-review-manager' ),
				'duplicate_error' => __( 'Failed to duplicate template.', 'wc-ai-review-manager' ),
				'preview_title' => __( 'Preview: %s', 'wc-ai-review-manager' ),
				'stats_title' => __( 'Template Statistics', 'wc-ai-review-manager' ),
				'characters_text' => __( 'characters', 'wc-ai-review-manager' ),
				'lines_text' => __( 'lines', 'wc-ai-review-manager' ),
				'placeholders_text' => __( 'placeholders', 'wc-ai-review-manager' ),
			)
		);

		wp_enqueue_style(
			'wc-ai-email-templates',
			WC_AI_REVIEW_MANAGER_PLUGIN_URL . 'assets/css/email-templates.css',
			array(),
			WC_AI_REVIEW_MANAGER_VERSION
		);
	}

	/**
	 * AJAX handler for email preview
	 */
	public static function ajax_preview_email() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wc_ai_preview' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'wc-ai-review-manager' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'wc-ai-review-manager' ) ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0;
		$content = isset( $_POST['content'] ) ? wp_kses_post( $_POST['content'] ) : '';

		// Use provided content or get from template
		if ( empty( $content ) && $template_id > 0 ) {
			$content = get_post_meta( $template_id, self::META_KEY_HTML_CONTENT, true );
		}

		if ( empty( $content ) ) {
			$content = self::get_default_template_html();
		}

		// Generate preview with sample data
		$preview_html = self::render_template( $content, array(
			'customer_name' => __( 'John Smith', 'wc-ai-review-manager' ),
			'store_name'    => get_bloginfo( 'name' ),
			'product_list'  => '<ul><li><a href="#">' . __( 'Sample Product 1', 'wc-ai-review-manager' ) . '</a></li><li><a href="#">' . __( 'Sample Product 2', 'wc-ai-review-manager' ) . '</a></li></ul>',
			'review_link'   => '<a href="' . esc_url( site_url( '/product/sample-product/#review-form' ) ) . '">' . __( 'Leave a Review', 'wc-ai-review-manager' ) . '</a>',
			'expiry_date'   => date_i18n( get_option( 'date_format' ), strtotime( '+30 days' ) ),
			'order_date'    => date_i18n( get_option( 'date_format' ) ),
			'order_number'  => '12345',
		) );

		wp_send_json_success( array( 'preview' => $preview_html ) );
	}

	/**
	 * Get default HTML template
	 */
	public static function get_default_template_html() {
		return <<<HTML
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Review Invitation</title>
	<style>
		body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
		.header { background: #f8f8f8; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
		.content { background: white; padding: 30px; border: 1px solid #eee; border-top: none; }
		.footer { background: #f8f8f8; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 12px; color: #666; }
		.button { display: inline-block; background: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 15px 0; }
		.product-list { background: #f9f9f9; padding: 15px; border-radius: 4px; margin: 15px 0; }
	</style>
</head>
<body>
	<div class="header">
		<h2>{store_name}</h2>
	</div>
	
	<div class="content">
		<h3>Hi {customer_name},</h3>
		
		<p>We hope you're enjoying your recent purchase from {store_name}!</p>
		
		<div class="product-list">
			<h4>Your Purchased Products:</h4>
			{product_list}
		</div>
		
		<p>Your feedback is incredibly valuable to us and helps other customers make informed decisions.</p>
		
		<p>Would you take a moment to share your experience? It only takes 2-3 minutes!</p>
		
		<p style="text-align: center;">
			<a href="{review_link}" class="button">Leave Your Review</a>
		</p>
		
		<p><strong>Review link expires:</strong> {expiry_date}</p>
		
		<p>Thank you for supporting our store!</p>
		
		<p>Best regards,<br>
		The {store_name} Team</p>
	</div>
	
	<div class="footer">
		<p>This email was sent by {store_name}. If you have any questions, please contact us.</p>
	</div>
</body>
</html>
HTML;
	}

	/**
	 * Render template with placeholders replaced
	 *
	 * @param string $template Template HTML.
	 * @param array  $data Placeholder data.
	 * @return string Rendered template.
	 */
	public static function render_template( $template, $data ) {
		$placeholders = array(
			'{customer_name}' => isset( $data['customer_name'] ) ? esc_html( $data['customer_name'] ) : '',
			'{store_name}'    => isset( $data['store_name'] ) ? esc_html( $data['store_name'] ) : '',
			'{product_list}'  => isset( $data['product_list'] ) ? $data['product_list'] : '',
			'{review_link}'   => isset( $data['review_link'] ) ? $data['review_link'] : '',
			'{expiry_date}'   => isset( $data['expiry_date'] ) ? esc_html( $data['expiry_date'] ) : '',
			'{order_date}'    => isset( $data['order_date'] ) ? esc_html( $data['order_date'] ) : '',
			'{order_number}'  => isset( $data['order_number'] ) ? esc_html( $data['order_number'] ) : '',
		);

		return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );
	}

	/**
	 * AJAX handler for template duplication
	 */
	public static function ajax_duplicate_template() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wc_ai_preview' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'wc-ai-review-manager' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'wc-ai-review-manager' ) ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0;
		
		if ( ! $template_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template ID', 'wc-ai-review-manager' ) ) );
		}

		// Get the original template
		$original = get_post( $template_id );
		
		if ( ! $original || $original->post_type !== self::POST_TYPE ) {
			wp_send_json_error( array( 'message' => __( 'Template not found', 'wc-ai-review-manager' ) ) );
		}

		// Prepare duplicate post data
		$post_data = array(
			'post_title'   => sprintf( __( 'Copy of %s', 'wc-ai-review-manager' ), $original->post_title ),
			'post_content' => '',
			'post_status'  => 'draft',
			'post_type'    => self::POST_TYPE,
			'post_author'  => get_current_user_id(),
		);

		// Insert the duplicate
		$new_template_id = wp_insert_post( $post_data );
		
		if ( is_wp_error( $new_template_id ) ) {
			wp_send_json_error( array( 'message' => $new_template_id->get_error_message() ) );
		}

		// Copy meta data
		$meta_keys = array(
			self::META_KEY_HTML_CONTENT,
			self::META_KEY_DEFAULT,
		);

		foreach ( $meta_keys as $meta_key ) {
			$meta_value = get_post_meta( $template_id, $meta_key, true );
			if ( $meta_value !== '' ) {
				// Don't copy the default flag
				if ( $meta_key === self::META_KEY_DEFAULT ) {
					update_post_meta( $new_template_id, $meta_key, '0' );
				} else {
					update_post_meta( $new_template_id, $meta_key, $meta_value );
				}
			}
		}

		wp_send_json_success( array(
			'message' => __( 'Template duplicated successfully', 'wc-ai-review-manager' ),
			'template_id' => $new_template_id,
			'edit_url' => get_edit_post_link( $new_template_id, 'raw' ),
		) );
	}

	/**
	 * Get the default email template
	 *
	 * @return WP_Post|null Default template post or null.
	 */
	public static function get_default_template() {
		$default_templates = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 1,
				'meta_key'       => self::META_KEY_DEFAULT,
				'meta_value'     => '1',
				'post_status'    => 'publish',
			)
		);

		if ( ! empty( $default_templates ) ) {
			return $default_templates[0];
		}

		// Fallback to the first published template
		$templates = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return ! empty( $templates ) ? $templates[0] : null;
	}

	/**
	 * Get template HTML content
	 *
	 * @param int $template_id Template post ID.
	 * @return string Template HTML.
	 */
	public static function get_template_html( $template_id ) {
		$html = get_post_meta( $template_id, self::META_KEY_HTML_CONTENT, true );
		
		if ( empty( $html ) ) {
			$html = self::get_default_template_html();
		}
		
		return $html;
	}
}