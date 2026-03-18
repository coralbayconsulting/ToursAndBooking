<?php
/**
 * Shared bulk email modal + JS (dashboard tile + tour bookings list).
 *
 * @var array $bst_bulk_finalization_args {
 *     @type bool $require_tour_date_id             True = dashboard (tour date required). False = bookings list.
 *     @type bool $attach_bookings_list_send_handler When true, binds #send-email-btn to open modal with pageBookingsData.
 * }
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$args                               = isset( $bst_bulk_finalization_args ) && is_array( $bst_bulk_finalization_args ) ? $bst_bulk_finalization_args : array();
$bst_bulk_require_tour_date_id      = ! empty( $args['require_tour_date_id'] );
$bst_bulk_attach_list_send_handler  = ! empty( $args['attach_bookings_list_send_handler'] );
$bst_bulk_nonce                     = wp_create_nonce( 'bst_tour_bookings_nonce' );
$bst_bulk_test_email                = wp_get_current_user()->user_email;
$bst_bulk_from_line                 = get_option( 'bst_from_email_name', 'Blue Strada Tours' ) . ' <' . get_option( 'bst_from_email_address', 'info@bluestradatours.com' ) . '>';
?>
<script>
(function() {
var bstBulkFinalizationNonce = <?php echo wp_json_encode( $bst_bulk_nonce ); ?>;
var bstBulkFinalizationTestEmail = <?php echo wp_json_encode( $bst_bulk_test_email ); ?>;
var bstBulkFinalizationRequireTourDateId = <?php echo $bst_bulk_require_tour_date_id ? 'true' : 'false'; ?>;

function bstNormalizeBulkBooking(b) {
	var id = parseInt(b.id, 10);
	var g1 = '';
	if (b.guest1_first_name || b.guest1_last_name) {
		g1 = [b.guest1_first_name, b.guest1_last_name].filter(Boolean).join(' ').trim();
	}
	if (!g1 && b.guest1_name) { g1 = String(b.guest1_name).trim(); }
	if (!g1 && b.name) { g1 = String(b.name).trim(); }
	var g2 = '';
	if (b.guest2_first_name || b.guest2_last_name) {
		g2 = [b.guest2_first_name, b.guest2_last_name].filter(Boolean).join(' ').trim();
	} else if (b.guest2_name) {
		g2 = String(b.guest2_name).trim();
	}
	var email = (b.guest1_email || b.email || '').trim();
	return { id: id, guest1_name: g1 || 'Guest', guest1_email: email, guest2_name: g2 };
}

window.bstBulkEmailState = {
	bookings: [],
	selectedBookings: [],
	currentPreviewIndex: 0,
	tourDateId: null,
	tourName: '',
	templateContent: ''
};

window.openDashboardFinalizationEmailModal = function(tourDateId, tourName, bookings) {
	if (bstBulkFinalizationRequireTourDateId && !tourDateId) {
		alert('Invalid tour date');
		return;
	}
	if (!bookings || bookings.length === 0) {
		alert('No bookings found for this tour date');
		return;
	}
	var normalized = bookings.map(bstNormalizeBulkBooking);
	window.bstBulkEmailState.tourDateId = tourDateId || null;
	window.bstBulkEmailState.tourName = tourName || 'Tour';
	window.bstBulkEmailState.bookings = normalized;
	window.bstBulkEmailState.selectedBookings = normalized.map(function(b) { return parseInt(b.id, 10); });
	window.bstBulkEmailState.currentPreviewIndex = 0;

	jQuery('#bulk-email-modal-title').text('Send Email: ' + window.bstBulkEmailState.tourName + ' (' + normalized.length + ' booking' + (normalized.length !== 1 ? 's' : '') + ')');
	window.updateRecipientDisplay();
	jQuery('#bulk-email-modal').fadeIn(200);
	jQuery('body').css('overflow', 'hidden');
	window.loadBulkEmailTemplates();
	jQuery('#bulk-email-subject').off('input.bstBulk').on('input.bstBulk', window.updateBulkSubjectCounter);
};

window.closeBulkEmailModal = function() {
	jQuery('#bulk-email-modal').fadeOut(200);
	jQuery('body').css('overflow', '');
	jQuery('#bulk-template-select').val('');
	jQuery('#bulk-email-subject').val('');
	jQuery('#bulk-email-cc').val('');
	jQuery('#bulk-content-preview').html('<em style="color: #999;">Select a template to preview content...</em>');
	window.bstBulkEmailState.bookings = [];
	window.bstBulkEmailState.selectedBookings = [];
	window.bstBulkEmailState.currentPreviewIndex = 0;
	window.bstBulkEmailState.templateContent = '';
};

window.updateRecipientDisplay = function() {
	var st = window.bstBulkEmailState;
	var total = st.bookings.length;
	var selected = st.selectedBookings.length;
	if (selected === total) {
		jQuery('#bulk-recipient-display').text(total + ' booking(s) selected (all)');
	} else {
		jQuery('#bulk-recipient-display').text(selected + ' of ' + total + ' booking(s) selected');
	}
	var btnText = selected === total ? 'Send to All' : 'Send to Selected';
	jQuery('#bulk-send-btn-text').text(btnText);
};

window.openRecipientSelector = function() {
	var st = window.bstBulkEmailState;
	var html = '';
	st.bookings.forEach(function(booking) {
		var bookingId = parseInt(booking.id, 10);
		var isChecked = st.selectedBookings.indexOf(bookingId) !== -1 ? 'checked' : '';
		var guestName = booking.guest1_name + (booking.guest2_name ? ' & ' + booking.guest2_name : '');
		var guestEmail = booking.guest1_email;
		html += '<label style="display: flex; align-items: center; gap: 8px; padding: 10px; cursor: pointer; border: 1px solid #ddd; border-radius: 4px; background: white; transition: background 0.2s;" onmouseover="this.style.background=\'#f9f9f9\'" onmouseout="this.style.background=\'white\'">';
		html += '<input type="checkbox" class="recipient-checkbox" value="' + bookingId + '" ' + isChecked + ' onchange="window.updateRecipientSelectionCount()" style="cursor: pointer;" />';
		html += '<div style="flex: 1;"><div style="font-weight: 600;">' + jQuery('<div/>').text(guestName).html() + '</div>';
		html += '<div style="font-size: 12px; color: #666;">' + jQuery('<div/>').text(guestEmail).html() + '</div></div></label>';
	});
	jQuery('#recipient-list').html(html);
	window.updateRecipientSelectionCount();
	jQuery('#recipient-selector-modal').fadeIn(200);
	jQuery('body').css('overflow', 'hidden');
};

window.closeRecipientSelector = function() {
	jQuery('#recipient-selector-modal').fadeOut(200);
};

window.toggleSelectAllRecipients = function() {
	var isChecked = jQuery('#recipient-select-all').is(':checked');
	jQuery('.recipient-checkbox').prop('checked', isChecked);
	window.updateRecipientSelectionCount();
};

window.updateRecipientSelectionCount = function() {
	var st = window.bstBulkEmailState;
	var total = st.bookings.length;
	var selected = jQuery('.recipient-checkbox:checked').length;
	jQuery('#recipient-selection-count').text(selected + ' of ' + total + ' selected');
	jQuery('#recipient-select-all').prop('checked', selected === total);
};

window.applyRecipientSelection = function() {
	var st = window.bstBulkEmailState;
	var selectedIds = [];
	jQuery('.recipient-checkbox:checked').each(function() {
		selectedIds.push(parseInt(jQuery(this).val(), 10));
	});
	if (selectedIds.length === 0) {
		alert('Please select at least one recipient.');
		return;
	}
	st.selectedBookings = selectedIds;
	window.updateRecipientDisplay();
	window.closeRecipientSelector();
	var currentBooking = st.bookings[st.currentPreviewIndex];
	if (currentBooking && st.selectedBookings.indexOf(parseInt(currentBooking.id, 10)) === -1) {
		for (var i = 0; i < st.bookings.length; i++) {
			if (st.selectedBookings.indexOf(parseInt(st.bookings[i].id, 10)) !== -1) {
				st.currentPreviewIndex = i;
				window.updateBulkPreview();
				break;
			}
		}
	}
};

window.getSelectedBookings = function() {
	var st = window.bstBulkEmailState;
	return st.bookings.filter(function(b) {
		return st.selectedBookings.indexOf(parseInt(b.id, 10)) !== -1;
	});
};

window.loadBulkEmailTemplates = function() {
	jQuery('#bulk-template-select').html('<option value="">Loading templates...</option>');
	jQuery.ajax({
		url: typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
		method: 'POST',
		data: { action: 'bst_get_manual_email_templates', nonce: bstBulkFinalizationNonce },
		success: function(response) {
			if (response.success && response.data) {
				var html = '<option value="">Select a template...</option>';
				response.data.forEach(function(template) {
					html += '<option value="' + template.id + '" data-type="' + (template.type || '') + '">' + template.title + '</option>';
				});
				jQuery('#bulk-template-select').html(html);
				jQuery('#bulk-template-select').off('change').on('change', function() {
					var templateId = jQuery(this).val();
					if (templateId) { window.loadBulkTemplateContent(templateId); }
				});
			}
		},
		error: function() {
			jQuery('#bulk-template-select').html('<option value="">Error loading templates</option>');
		}
	});
};

window.loadBulkTemplateContent = function(templateId) {
	jQuery.ajax({
		url: typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
		method: 'POST',
		data: { action: 'bst_get_email_template_content', template_id: templateId, nonce: bstBulkFinalizationNonce },
		success: function(response) {
			if (response.success && response.data) {
				jQuery('#bulk-email-subject').val(response.data.subject || '');
				window.updateBulkSubjectCounter();
				var content = response.data.content || '';
				jQuery('#bulk-content-preview').html(content);
				jQuery('#bulk-content-preview a').attr('target', '_blank').attr('rel', 'noopener noreferrer');
				window.bstBulkEmailState.templateContent = content;
			} else {
				alert('Error loading template: ' + (response.data || 'Unknown error'));
			}
		},
		error: function() { alert('Error loading template content'); }
	});
};

window.switchBulkEmailTab = function(tab) {
	jQuery('.bst-modal-tab').removeClass('bst-modal-tab-active');
	jQuery('.bst-modal-tab').css({ background: '#f5f5f5', 'font-weight': 'normal' });
	jQuery('.bulk-email-tab-content').hide();
	if (tab === 'compose') {
		jQuery('.bst-modal-tab').eq(0).addClass('bst-modal-tab-active').css({ background: 'white', 'font-weight': '600' });
		jQuery('#bulk-compose-tab').show();
	} else if (tab === 'preview') {
		jQuery('.bst-modal-tab').eq(1).addClass('bst-modal-tab-active').css({ background: 'white', 'font-weight': '600' });
		jQuery('#bulk-preview-tab').show();
		window.updateBulkPreview();
	}
};

window.updateBulkPreview = function() {
	var st = window.bstBulkEmailState;
	if (st.bookings.length === 0) {
		jQuery('#bulk-preview-content').html('<p style="text-align: center; color: #d63638;">No bookings to preview</p>');
		return;
	}
	var currentBooking = st.bookings[st.currentPreviewIndex];
	var subject = jQuery('#bulk-email-subject').val();
	var content = st.templateContent || '';
	var selectedBookings = window.getSelectedBookings();
	if (selectedBookings.length === 0) {
		jQuery('#bulk-preview-counter').text('No bookings selected');
		jQuery('#bulk-preview-content').html('<p style="text-align: center; color: #d63638;">No recipients selected. Please use "Select Recipients..." button.</p>');
		return;
	}
	var currentPosInSelected = -1;
	for (var i = 0; i < selectedBookings.length; i++) {
		if (parseInt(selectedBookings[i].id, 10) === parseInt(currentBooking.id, 10)) {
			currentPosInSelected = i + 1;
			break;
		}
	}
	if (currentPosInSelected === -1) { currentPosInSelected = 1; }
	jQuery('#bulk-preview-counter').text('Booking ' + currentPosInSelected + ' of ' + selectedBookings.length);
	jQuery('#bulk-preview-booking-name').text(currentBooking.guest1_name);
	jQuery('button.bst-bulk-preview-prev').prop('disabled', currentPosInSelected <= 1).css('opacity', currentPosInSelected <= 1 ? '0.5' : '1');
	jQuery('button.bst-bulk-preview-next').prop('disabled', currentPosInSelected >= selectedBookings.length).css('opacity', currentPosInSelected >= selectedBookings.length ? '0.5' : '1');
	jQuery('#bulk-preview-content').html('<p style="text-align: center;"><i class="fas fa-spinner fa-spin"></i> Generating preview...</p>');
	if (!content) {
		jQuery('#bulk-preview-content').html('<p style="color: #d63638;">No template content. Please select a template in the Compose tab.</p>');
		jQuery('#bulk-preview-subject').text(subject || '(No subject)');
		jQuery('#bulk-preview-to').text(currentBooking.guest1_email);
		return;
	}
	var cc = jQuery('#bulk-email-cc').val();
	jQuery.ajax({
		url: typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
		method: 'POST',
		data: {
			action: 'bst_preview_email_content',
			booking_id: currentBooking.id,
			content: content,
			subject: subject,
			cc: cc,
			nonce: bstBulkFinalizationNonce
		},
		success: function(response) {
			if (response.success && response.data) {
				jQuery('#bulk-preview-content').html(response.data.content);
				jQuery('#bulk-preview-subject').text(response.data.subject || subject);
				jQuery('#bulk-preview-to').text(currentBooking.guest1_email);
				if (response.data.cc && response.data.cc.trim() !== '') {
					if (!jQuery('#bulk-preview-cc-wrapper').length) {
						jQuery('#bulk-preview-to').parent().after('<div id="bulk-preview-cc-wrapper" style="margin-bottom: 8px;"><strong>CC:</strong> <span id="bulk-preview-cc"></span></div>');
					}
					jQuery('#bulk-preview-cc').text(response.data.cc);
				} else {
					jQuery('#bulk-preview-cc-wrapper').remove();
				}
				jQuery('#bulk-preview-content a').attr('target', '_blank').attr('rel', 'noopener noreferrer');
			} else {
				jQuery('#bulk-preview-content').html('<p style="color: #d63638;">Preview failed</p>');
			}
		},
		error: function() {
			jQuery('#bulk-preview-content').html('<p style="color: #d63638;">Error generating preview</p>');
		}
	});
};

window.previousPreviewBooking = function() {
	var st = window.bstBulkEmailState;
	var selectedBookings = window.getSelectedBookings();
	var currentBooking = st.bookings[st.currentPreviewIndex];
	var currentPosInSelected = -1;
	for (var i = 0; i < selectedBookings.length; i++) {
		if (parseInt(selectedBookings[i].id, 10) === parseInt(currentBooking.id, 10)) {
			currentPosInSelected = i;
			break;
		}
	}
	if (currentPosInSelected > 0) {
		var prevSelectedBooking = selectedBookings[currentPosInSelected - 1];
		for (var j = 0; j < st.bookings.length; j++) {
			if (parseInt(st.bookings[j].id, 10) === parseInt(prevSelectedBooking.id, 10)) {
				st.currentPreviewIndex = j;
				window.updateBulkPreview();
				break;
			}
		}
	}
};

window.nextPreviewBooking = function() {
	var st = window.bstBulkEmailState;
	var selectedBookings = window.getSelectedBookings();
	var currentBooking = st.bookings[st.currentPreviewIndex];
	var currentPosInSelected = -1;
	for (var i = 0; i < selectedBookings.length; i++) {
		if (parseInt(selectedBookings[i].id, 10) === parseInt(currentBooking.id, 10)) {
			currentPosInSelected = i;
			break;
		}
	}
	if (currentPosInSelected < selectedBookings.length - 1) {
		var nextSelectedBooking = selectedBookings[currentPosInSelected + 1];
		for (var j = 0; j < st.bookings.length; j++) {
			if (parseInt(st.bookings[j].id, 10) === parseInt(nextSelectedBooking.id, 10)) {
				st.currentPreviewIndex = j;
				window.updateBulkPreview();
				break;
			}
		}
	}
};

window.sendBulkTestEmail = function() {
	var st = window.bstBulkEmailState;
	if (st.bookings.length === 0) {
		alert('No bookings loaded');
		return;
	}
	var currentBooking = st.bookings[st.currentPreviewIndex];
	var subject = jQuery('#bulk-email-subject').val();
	var cc = jQuery('#bulk-email-cc').val();
	var content = st.templateContent || '';
	if (!subject || !content) {
		alert('Please enter a subject and message');
		return;
	}
	if (!confirm('Send test email to ' + bstBulkFinalizationTestEmail + ' using data from ' + currentBooking.guest1_name + '?\n\nSubject: [TEST] ' + subject)) {
		return;
	}
	var emailType = jQuery('#bulk-template-select option:selected').data('type') || 'Ad Hoc';
	jQuery('#bulk-send-test-btn, #bulk-send-all-btn').prop('disabled', true);
	jQuery('#bulk-send-status').text('Sending test email...').css('color', '#666');
	jQuery.ajax({
		url: typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
		method: 'POST',
		data: {
			action: 'bst_send_adhoc_email_compose',
			booking_id: currentBooking.id,
			email_to: bstBulkFinalizationTestEmail,
			email_cc: cc,
			subject: '[TEST] ' + subject,
			message: content,
			email_type: 'Test',
			nonce: bstBulkFinalizationNonce
		},
		success: function(response) {
			if (response.success) {
				jQuery('#bulk-send-status').text('Test email sent!').css('color', '#00a32a');
				if (window.showMessage) { window.showMessage('Test email sent successfully', 'success'); }
			} else {
				jQuery('#bulk-send-status').text('Error: ' + (response.data || 'Failed to send')).css('color', '#d63638');
			}
			jQuery('#bulk-send-test-btn, #bulk-send-all-btn').prop('disabled', false);
		},
		error: function() {
			jQuery('#bulk-send-status').text('Error sending test email').css('color', '#d63638');
			jQuery('#bulk-send-test-btn, #bulk-send-all-btn').prop('disabled', false);
		}
	});
};

window.sendBulkEmailToAll = function() {
	var st = window.bstBulkEmailState;
	if (st.bookings.length === 0) {
		alert('No bookings loaded');
		return;
	}
	var subject = jQuery('#bulk-email-subject').val();
	var cc = jQuery('#bulk-email-cc').val();
	var content = st.templateContent || '';
	if (!subject || !content) {
		alert('Please select a template and enter a subject');
		return;
	}
	var selectedBookings = window.getSelectedBookings();
	if (selectedBookings.length === 0) {
		alert('Please select at least one recipient.');
		return;
	}
	var confirmMsg = selectedBookings.length === st.bookings.length
		? 'Send email to all ' + selectedBookings.length + ' booking(s)?\n\nSubject: ' + subject
		: 'Send email to ' + selectedBookings.length + ' of ' + st.bookings.length + ' booking(s)?\n\nSubject: ' + subject;
	if (!confirm(confirmMsg)) { return; }
	var selectedTemplate = jQuery('#bulk-template-select').find('option:selected');
	var emailType = selectedTemplate.data('type') || 'Ad Hoc';
	var templateId = selectedTemplate.val() || null;
	jQuery('#bulk-send-test-btn, #bulk-send-all-btn').prop('disabled', true);
	jQuery('#bulk-send-status').text('Preparing batch...').css('color', '#666');
	jQuery.ajax({
		url: typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
		method: 'POST',
		data: {
			action: 'bst_create_email_batch',
			email_type: emailType,
			template_id: templateId,
			email_subject: subject,
			cc_emails: cc,
			tour_date_id: st.tourDateId || '',
			total_emails: selectedBookings.length,
			is_test: 0,
			notes: '',
			nonce: bstBulkFinalizationNonce
		},
		success: function(response) {
			if (response.success && response.data.batch_id) {
				window.sendBulkEmailsWithBatch(response.data.batch_id, selectedBookings, subject, cc, content, emailType);
			} else {
				alert('Failed to create email batch');
				jQuery('#bulk-send-test-btn, #bulk-send-all-btn').prop('disabled', false);
				jQuery('#bulk-send-status').text('');
			}
		},
		error: function() {
			alert('Failed to create email batch');
			jQuery('#bulk-send-test-btn, #bulk-send-all-btn').prop('disabled', false);
			jQuery('#bulk-send-status').text('');
		}
	});
};

window.sendBulkEmailsWithBatch = function(batchId, bookings, subject, cc, content, emailType) {
	jQuery('#bulk-send-status').text('Sending 0 of ' + bookings.length + '...').css('color', '#666');
	var successCount = 0;
	var errorCount = 0;
	var failedBookings = [];
	var currentIndex = 0;
	function guestLabel(b) {
		return b.guest1_name + (b.guest2_name ? ' & ' + b.guest2_name : '');
	}
	function sendNext() {
		if (currentIndex >= bookings.length) {
			jQuery.ajax({
				url: typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
				method: 'POST',
				data: {
					action: 'bst_update_email_batch',
					batch_id: batchId,
					successful_emails: successCount,
					failed_emails: errorCount,
					nonce: bstBulkFinalizationNonce
				},
				complete: function() {
					var alertMsg = 'Email sending complete!\n\n' + successCount + ' email(s) sent successfully';
					if (errorCount > 0) {
						alertMsg += '\n' + errorCount + ' email(s) failed\n\nFailed bookings:';
						failedBookings.forEach(function(name) { alertMsg += '\n• ' + name; });
					}
					alert(alertMsg);
					window.closeBulkEmailModal();
					window.location.reload();
				}
			});
			return;
		}
		var booking = bookings[currentIndex];
		jQuery('#bulk-send-status').text('Sending ' + (currentIndex + 1) + ' of ' + bookings.length + '...').css('color', '#666');
		jQuery.ajax({
			url: typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
			method: 'POST',
			data: {
				action: 'bst_send_adhoc_email_compose',
				booking_id: booking.id,
				email_to: booking.guest1_email,
				email_cc: cc,
				subject: subject,
				message: content,
				email_type: emailType,
				batch_id: batchId,
				nonce: bstBulkFinalizationNonce
			},
			success: function(response) {
				if (response.success) {
					successCount++;
				} else {
					errorCount++;
					failedBookings.push(guestLabel(booking));
				}
				currentIndex++;
				sendNext();
			},
			error: function() {
				errorCount++;
				failedBookings.push(guestLabel(booking));
				currentIndex++;
				sendNext();
			}
		});
	}
	sendNext();
};

window.updateBulkSubjectCounter = function() {
	var length = jQuery('#bulk-email-subject').val().length;
	var counterEl = jQuery('#bulk-subject-counter');
	counterEl.text(length + '/70');
	if (length > 70) {
		counterEl.css('color', '#d63638');
	} else if (length > 50) {
		counterEl.css('color', '#dba617');
	} else {
		counterEl.css('color', '#666');
	}
};
})();
</script>

<div id="bulk-email-modal" class="bst-modal bst-bulk-finalization-modal" style="display: none;">
	<div class="bst-modal-content" style="max-width: 1400px; width: 95%; height: 85vh; display: flex; flex-direction: column;">
		<div class="bst-modal-header">
			<h2 id="bulk-email-modal-title"><?php esc_html_e( 'Send Email to Tour Group', 'bst-plugin' ); ?></h2>
			<button type="button" class="bst-modal-close" onclick="closeBulkEmailModal()">&times;</button>
		</div>
		<div class="bst-modal-body" style="flex: 1; overflow-y: auto; padding: 20px; min-height: 0;">
			<div class="bst-modal-tabs" style="display: flex; border-bottom: 2px solid #ddd; margin-bottom: 20px;">
				<div class="bst-modal-tab bst-modal-tab-active" onclick="switchBulkEmailTab('compose')" style="padding: 10px 20px; cursor: pointer; border: 1px solid #ddd; border-bottom: none; background: white; font-weight: 600; margin-right: 4px; border-radius: 4px 4px 0 0;">Compose</div>
				<div class="bst-modal-tab" onclick="switchBulkEmailTab('preview')" style="padding: 10px 20px; cursor: pointer; border: 1px solid #ddd; border-bottom: none; background: #f5f5f5; margin-right: 4px; border-radius: 4px 4px 0 0;">Preview</div>
			</div>
			<div id="bulk-compose-tab" class="bulk-email-tab-content">
				<div style="max-width: 900px; margin: 0 auto;">
					<div style="display: grid; grid-template-columns: 100px 1fr; gap: 15px; align-items: start; margin-bottom: 15px;">
						<label style="text-align: right; padding-top: 8px; font-weight: 600;">Template:</label>
						<select id="bulk-template-select" style="width: 100%; max-width: 500px; padding: 8px;">
							<option value=""><?php esc_html_e( 'Select a template...', 'bst-plugin' ); ?></option>
						</select>
						<label style="text-align: right; padding-top: 8px; font-weight: 600;">To:</label>
						<div style="display: flex; gap: 10px; align-items: center; max-width: 700px;">
							<div style="padding: 8px; background: #f0f0f0; border: 1px solid #ddd; border-radius:4px; flex: 1;" id="bulk-recipient-display">Loading...</div>
							<button type="button" onclick="openRecipientSelector()" class="button" style="white-space: nowrap;">
								<i class="fas fa-user-check"></i> <?php esc_html_e( 'Select Recipients...', 'bst-plugin' ); ?>
							</button>
						</div>
						<label style="text-align: right; padding-top: 8px;">CC:</label>
						<input type="email" id="bulk-email-cc" placeholder="<?php esc_attr_e( 'Optional', 'bst-plugin' ); ?>" style="width: 100%; max-width: 500px; padding: 8px;" />
						<label style="text-align: right; padding-top: 8px; font-weight: 600;">Subject:</label>
						<div style="display: flex; flex-direction: column; gap: 5px; max-width: 700px;">
							<input type="text" id="bulk-email-subject" placeholder="<?php esc_attr_e( 'Email subject', 'bst-plugin' ); ?>" style="width: 100%; padding: 8px;" />
							<div id="bulk-subject-counter" style="font-size: 11px; color: #666; text-align: right;">0/70</div>
						</div>
					</div>
					<div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
							<strong style="font-size: 13px; color: #666;"><?php esc_html_e( 'Template Content Preview', 'bst-plugin' ); ?></strong>
							<span style="font-size: 12px; color: #999; font-style: italic;"><?php esc_html_e( 'Edit templates in Email Templates screen', 'bst-plugin' ); ?></span>
						</div>
						<div id="bulk-content-preview" style="background: white; padding: 15px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; line-height: 1.6;">
							<em style="color: #999;"><?php esc_html_e( 'Select a template to preview content...', 'bst-plugin' ); ?></em>
						</div>
					</div>
				</div>
			</div>
			<div id="bulk-preview-tab" class="bulk-email-tab-content" style="display: none;">
				<div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
					<div>
						<button type="button" onclick="previousPreviewBooking()" class="button bst-bulk-preview-prev">← Previous</button>
						<span id="bulk-preview-counter" style="margin: 0 15px; font-weight: 600;">Booking 1 of 1</span>
						<button type="button" onclick="nextPreviewBooking()" class="button bst-bulk-preview-next">Next →</button>
					</div>
					<div style="font-size: 13px; color: #666;">
						<strong><?php esc_html_e( 'Preview for:', 'bst-plugin' ); ?></strong> <span id="bulk-preview-booking-name"></span>
					</div>
				</div>
				<div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
					<div style="background: white; padding: 20px; border-radius: 4px;">
						<div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #eee;">
							<div style="margin-bottom: 8px;"><strong>From:</strong> <?php echo esc_html( $bst_bulk_from_line ); ?></div>
							<div style="margin-bottom: 8px;"><strong>To:</strong> <span id="bulk-preview-to"></span></div>
							<div><strong>Subject:</strong> <span id="bulk-preview-subject"></span></div>
						</div>
						<div id="bulk-preview-content" style="min-height: 200px;"></div>
					</div>
				</div>
			</div>
		</div>
		<div class="bst-modal-footer" style="border-top: 1px solid #ddd; padding: 15px 20px; background: #f9f9f9; flex-shrink: 0;">
			<div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
				<div><span id="bulk-send-status" style="font-style: italic; color: #666; font-size: 13px; font-weight: 600;"></span></div>
				<div>
					<button type="button" onclick="closeBulkEmailModal()" class="button"><?php esc_html_e( 'Cancel', 'bst-plugin' ); ?></button>
					<button type="button" id="bulk-send-test-btn" onclick="sendBulkTestEmail()" class="button" style="margin-left: 8px;">
						<i class="fas fa-flask"></i> <?php esc_html_e( 'Send Test to Me', 'bst-plugin' ); ?>
					</button>
					<button type="button" id="bulk-send-all-btn" onclick="sendBulkEmailToAll()" class="button button-primary" style="margin-left: 8px;">
						<i class="fas fa-paper-plane"></i> <span id="bulk-send-btn-text"><?php esc_html_e( 'Send to All', 'bst-plugin' ); ?></span>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

<div id="recipient-selector-modal" class="bst-modal bst-bulk-finalization-modal" style="display: none;">
	<div class="bst-modal-content" style="max-width: 600px; height: auto; max-height: 80vh; display: flex; flex-direction: column;">
		<div class="bst-modal-header" style="border-bottom: 1px solid #ddd; padding: 15px 20px; background: #f9f9f9;">
			<h3 style="margin: 0; font-size: 18px;"><?php esc_html_e( 'Select Recipients', 'bst-plugin' ); ?></h3>
			<button type="button" class="bst-modal-close" onclick="closeRecipientSelector()">&times;</button>
		</div>
		<div class="bst-modal-body" style="flex: 1; overflow-y: auto; padding: 20px; min-height: 0;">
			<div style="margin-bottom: 15px; padding: 10px; background: #f0f8ff; border: 1px solid #d0e8ff; border-radius: 4px; font-size: 13px;">
				<strong><?php esc_html_e( 'Select which bookings to send to:', 'bst-plugin' ); ?></strong>
			</div>
			<div style="margin-bottom: 15px;">
				<label style="display: flex; align-items: center; gap: 8px; padding: 8px; cursor: pointer; font-weight: 600; border-bottom: 1px solid #ddd;">
					<input type="checkbox" id="recipient-select-all" onchange="toggleSelectAllRecipients()" style="cursor: pointer;" />
					<?php esc_html_e( 'Select All', 'bst-plugin' ); ?>
				</label>
			</div>
			<div id="recipient-list" style="display: flex; flex-direction: column; gap: 8px;"></div>
		</div>
		<div class="bst-modal-footer" style="border-top: 1px solid #ddd; padding: 15px 20px; background: #f9f9f9; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center;">
			<div style="font-size: 13px; color: #666;"><span id="recipient-selection-count">0 of 0 selected</span></div>
			<div>
				<button type="button" onclick="closeRecipientSelector()" class="button"><?php esc_html_e( 'Cancel', 'bst-plugin' ); ?></button>
				<button type="button" onclick="applyRecipientSelection()" class="button button-primary" style="margin-left: 8px;"><?php esc_html_e( 'Apply Selection', 'bst-plugin' ); ?></button>
			</div>
		</div>
	</div>
</div>

<style>
.bst-bulk-finalization-modal.bst-modal {
	position: fixed;
	z-index: 100000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0,0,0,0.5);
}
.bst-bulk-finalization-modal .bst-modal-content {
	background-color: #fff;
	margin: 2% auto;
	padding: 0;
	border-radius: 5px;
	box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.bst-bulk-finalization-modal .bst-modal-header {
	background: #f1f1f1;
	padding: 15px 20px;
	border-bottom: 1px solid #ddd;
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.bst-bulk-finalization-modal .bst-modal-header h2,
.bst-bulk-finalization-modal .bst-modal-header h3 {
	margin: 0;
	font-size: 18px;
}
.bst-bulk-finalization-modal .bst-modal-close {
	background: none;
	border: none;
	font-size: 28px;
	cursor: pointer;
	padding: 0;
	width: 30px;
	height: 30px;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #666;
}
.bst-bulk-finalization-modal .bst-modal-close:hover { color: #000; }
#bulk-content-preview a,
#bulk-preview-content a {
	color: #0073aa;
	text-decoration: underline;
}
#bulk-content-preview a:hover,
#bulk-preview-content a:hover {
	color: #005177;
}
</style>
<?php if ( $bst_bulk_attach_list_send_handler ) : ?>
<script>
jQuery(document).ready(function() {
	jQuery('#send-email-btn').on('click', function() {
		if (typeof pageBookingsData === 'undefined' || !pageBookingsData || pageBookingsData.length === 0) {
			alert('No bookings available for the selected tour date');
			return;
		}
		openDashboardFinalizationEmailModal(null, 'Tour Date Bookings', pageBookingsData);
	});
});
</script>
<?php endif; ?>

