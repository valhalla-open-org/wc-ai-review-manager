/**
 * Email Templates JavaScript
 */

jQuery(document).ready(function($) {
	// Only run on email template admin pages
	if (!$('body').hasClass('post-type-wc_ai_email_template')) {
		return;
	}

	// Insert placeholder into textarea
	$('.insert-placeholder').on('click', function(e) {
		e.preventDefault();
		
		var textarea = $('#wc_ai_html_content');
		var placeholder = $(this).data('placeholder');
		
		if (!textarea.length) {
			return;
		}
		
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

	// Update preview when template is loaded or changed
	function updatePreview() {
		var content = $('#wc_ai_html_content').val();
		var templateId = $('#post_ID').val() || 0;
		
		if (!content) {
			return;
		}
		
		// Show loading
		$('#wc_ai_preview_container').html('<div class="notice notice-info inline"><p>' + wcAiEmailTemplates.loading_text + '</p></div>');
		
		$.ajax({
			url: wcAiEmailTemplates.ajax_url,
			method: 'POST',
			data: {
				action: 'wc_ai_preview_email',
				template_id: templateId,
				content: content,
				nonce: wcAiEmailTemplates.nonce
			},
			success: function(response) {
				if (response.success) {
					$('#wc_ai_preview_container').html(response.data.preview);
				} else {
					$('#wc_ai_preview_container').html(
						'<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>'
					);
				}
			},
			error: function(xhr, status, error) {
				$('#wc_ai_preview_container').html(
					'<div class="notice notice-error inline"><p>' + wcAiEmailTemplates.error_text + ': ' + error + '</p></div>'
				);
			}
		});
	}

	// Initialize preview on page load
	if ($('#wc_ai_html_content').val()) {
		setTimeout(updatePreview, 1000);
	}

	// Add template validation before save
	$('#post').on('submit', function(e) {
		var content = $('#wc_ai_html_content').val();
		
		if (!content || content.trim() === '') {
			if (!confirm(wcAiEmailTemplates.empty_warning)) {
				e.preventDefault();
				$('#wc_ai_html_content').focus();
				return false;
			}
		}
		
		// Check for basic HTML structure
		if (content.indexOf('<!DOCTYPE') === -1 && content.indexOf('<html') === -1) {
			if (!confirm(wcAiEmailTemplates.html_warning)) {
				e.preventDefault();
				$('#wc_ai_html_content').focus();
				return false;
			}
		}
	});

	// Add template duplication functionality
	$('.wp-list-table').on('click', '.duplicate-template', function(e) {
		e.preventDefault();
		
		var $button = $(this);
		var templateId = $button.data('template-id');
		var templateTitle = $button.data('template-title');
		
		if (!templateId || !templateTitle) {
			return;
		}
		
		$button.prop('disabled', true).text(wcAiEmailTemplates.duplicating_text);
		
		$.ajax({
			url: wcAiEmailTemplates.ajax_url,
			method: 'POST',
			data: {
				action: 'wc_ai_duplicate_template',
				template_id: templateId,
				nonce: wcAiEmailTemplates.nonce
			},
			success: function(response) {
				if (response.success) {
					// Reload page to show new template
					window.location.reload();
				} else {
					alert(response.data.message);
					$button.prop('disabled', false).text(wcAiEmailTemplates.duplicate_text);
				}
			},
			error: function() {
				alert(wcAiEmailTemplates.duplicate_error);
				$button.prop('disabled', false).text(wcAiEmailTemplates.duplicate_text);
			}
		});
	});

	// Add template preview in list table
	$('.wp-list-table').on('click', '.preview-template', function(e) {
		e.preventDefault();
		
		var templateId = $(this).data('template-id');
		var templateTitle = $(this).data('template-title');
		
		// Open preview in thickbox
		tb_show(
			wcAiEmailTemplates.preview_title.replace('%s', templateTitle),
			wcAiEmailTemplates.ajax_url + '?action=wc_ai_preview_email&template_id=' + templateId + '&nonce=' + wcAiEmailTemplates.nonce + '&TB_iframe=true&width=800&height=600'
		);
	});

	// Add keyboard shortcuts for template editor
	$('#wc_ai_html_content').on('keydown', function(e) {
		// Ctrl/Cmd + S to save
		if ((e.ctrlKey || e.metaKey) && e.key === 's') {
			e.preventDefault();
			$('#publish').click();
		}
		
		// Ctrl/Cmd + P to update preview
		if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
			e.preventDefault();
			updatePreview();
		}
	});

	// Add template status indicator
	function updateTemplateStatus() {
		var content = $('#wc_ai_html_content').val();
		var charCount = content.length;
		var lineCount = content.split('\n').length;
		var placeholderCount = (content.match(/\{[^}]+\}/g) || []).length;
		
		$('#template-stats').remove();
		var statsHtml = '<div id="template-stats" class="misc-pub-section">' +
			'<strong>' + wcAiEmailTemplates.stats_title + ':</strong><br>' +
			'<span class="dashicons dashicons-editor-textcolor"></span> ' + charCount + ' ' + wcAiEmailTemplates.characters_text + '<br>' +
			'<span class="dashicons dashicons-editor-justify"></span> ' + lineCount + ' ' + wcAiEmailTemplates.lines_text + '<br>' +
			'<span class="dashicons dashicons-shortcode"></span> ' + placeholderCount + ' ' + wcAiEmailTemplates.placeholders_text +
			'</div>';
		
		$('#minor-publishing-actions').append(statsHtml);
	}
	
	// Update stats on content change
	$('#wc_ai_html_content').on('input', updateTemplateStatus);
	
	// Initialize stats
	setTimeout(updateTemplateStatus, 500);
});

// Localize script with translations and settings
if (typeof wcAiEmailTemplates === 'undefined') {
	var wcAiEmailTemplates = {
		ajax_url: ajaxurl,
		nonce: '',
		loading_text: 'Loading preview...',
		error_text: 'Error',
		empty_warning: 'The template is empty. Are you sure you want to save?',
		html_warning: 'The template doesn\'t appear to have proper HTML structure. Are you sure you want to save?',
		duplicating_text: 'Duplicating...',
		duplicate_text: 'Duplicate',
		duplicate_error: 'Failed to duplicate template.',
		preview_title: 'Preview: %s',
		stats_title: 'Template Statistics',
		characters_text: 'characters',
		lines_text: 'lines',
		placeholders_text: 'placeholders'
	};
}