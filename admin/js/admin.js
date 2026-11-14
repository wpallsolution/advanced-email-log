/**
 * Advanced Email Log - Admin JavaScript
 *
 * Handles modal details, sandboxed iframe loading, live polling,
 * CSV exports, CSV imports, date range picker, and column visibility.
 */

(function ($) {
	'use strict';

	$(function () {
		// Cache DOM elements.
		var $modalOverlay        = $('#ael-modal-overlay');
		var $modalLoading        = $('#ael-modal-loading');
		var $iframe              = $('#ael-details-iframe');
		var $importModal         = $('#ael-import-modal');
		var $datePickerWrap      = $('#ael-date-picker-wrap');
		var $dateDropdownCard    = $('#ael-date-dropdown-card');
		var $columnsWrap         = $('#ael-columns-wrap');
		var $columnsDropdown     = $('#ael-columns-dropdown');
		var $newLogsBanner       = $('#ael-new-logs-banner');
		var pollTimer            = null;

		// -------------------------------------------------------------
		// Universal Clipboard Copy Helper (Works on both HTTP and HTTPS)
		// -------------------------------------------------------------
		function copyToClipboard(text, $btn, successText) {
			var origHtml = $btn.html();

			function showSuccess() {
				$btn.addClass('button-primary').html('<span class="dashicons dashicons-yes"></span> ' + (successText || aelData.i18n.copied));
				setTimeout(function () {
					$btn.removeClass('button-primary').html(origHtml);
				}, 2200);
			}

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text).then(showSuccess).catch(function () {
					fallbackCopy(text);
				});
			} else {
				fallbackCopy(text);
			}

			function fallbackCopy(str) {
				var textArea = document.createElement('textarea');
				textArea.value = str;
				textArea.style.position = 'fixed';
				textArea.style.left = '-999999px';
				textArea.style.top = '-999999px';
				document.body.appendChild(textArea);
				textArea.focus();
				textArea.select();
				try {
					document.execCommand('copy');
					showSuccess();
				} catch (err) {
					alert('Copy failed. Please manually select and copy.');
				}
				document.body.removeChild(textArea);
			}
		}

		// -------------------------------------------------------------
		// 1. Log Details Modal Dialog
		// -------------------------------------------------------------

		function openModal() {
			$modalOverlay.fadeIn(150);
			$('body').addClass('ael-modal-open');
		}

		function closeModal() {
			$modalOverlay.fadeOut(150, function () {
				$iframe.attr('src', 'about:blank');
				$('.ael-modal-tab-btn').removeClass('active');
				$('.ael-modal-tab-btn[data-tab="overview"]').addClass('active');
				$('.ael-modal-tab-pane').removeClass('active');
				$('#ael-pane-overview').addClass('active');
			});
			$('body').removeClass('ael-modal-open');
		}

		// Trigger modal from button or subject click.
		$(document).on('click', '.ael-view-log-btn, .ael-view-log-trigger', function (e) {
			e.preventDefault();
			var logId = $(this).data('id');
			if (!logId) {
				return;
			}

			openModal();
			$modalLoading.show();
			$('.ael-modal-tab-pane').hide();

			$.ajax({
				url: aelData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ael_get_log_details',
					id: logId,
					nonce: aelData.nonce
				},
				success: function (response) {
					$modalLoading.hide();
					$('.ael-modal-tab-pane.active').show();

					if (!response.success || !response.data) {
						alert(aelData.i18n.errorLoading);
						closeModal();
						return;
					}

					var data = response.data;

					// Populate Title & Status Badge.
					$('.ael-modal-subject-text').text(data.subject);
					$('#ael-modal-status-badge')
						.attr('class', 'ael-badge ael-status-' + data.status)
						.text(data.status_label);

					// Populate Overview Fields.
					$('#ael-modal-id').text('#' + data.id);
					$('#ael-modal-time-local').text(data.created_at_local);
					$('#ael-modal-time-utc').text('(UTC: ' + data.created_at_utc + ')');
					$('#ael-modal-to').text(data.to);
					$('#ael-modal-from').text(data.from || '—');
					$('#ael-modal-subject').text(data.subject);
					$('#ael-modal-content-type').text(data.content_type);
					$('#ael-modal-ip').text(data.ip_address || '—');

					// CC, BCC, Reply-To.
					if (data.cc) {
						$('#ael-modal-cc').text(data.cc);
						$('#ael-row-cc').show();
					} else {
						$('#ael-row-cc').hide();
					}

					if (data.bcc) {
						$('#ael-modal-bcc').text(data.bcc);
						$('#ael-row-bcc').show();
					} else {
						$('#ael-row-bcc').hide();
					}

					if (data.reply_to) {
						$('#ael-modal-reply-to').text(data.reply_to);
						$('#ael-row-reply-to').show();
					} else {
						$('#ael-row-reply-to').hide();
					}

					// Diagnostic detailed error banner with troubleshooting tip.
					if ('failed' === data.status) {
						$('#ael-modal-error-text').text(data.error_message || 'wp_mail() returned false without a specific error code.');
						if (data.troubleshooting_tip) {
							$('#ael-modal-error-tip').html('<strong>Tip:</strong> ' + data.troubleshooting_tip);
						} else {
							$('#ael-modal-error-tip').empty();
						}
						$('#ael-modal-error-banner').show();
					} else {
						$('#ael-modal-error-banner').hide();
					}

					// Attachments list with download button.
					var $attList = $('#ael-modal-attachments-list').empty();
					if (data.attachments && data.attachments.length > 0) {
						$.each(data.attachments, function (idx, att) {
							var sizeKb = att.size ? Math.round(att.size / 1024) + ' KB' : '0 KB';
							var $li = $('<li>')
								.append('<span class="dashicons dashicons-paperclip" style="color:#646970;"></span> ')
								.append($('<strong>').text(att.filename))
								.append(' <span class="ael-dim">(' + sizeKb + ' — ' + (att.mime || 'file') + ')</span>');

							if (att.download_url) {
								$li.append(' <a href="' + att.download_url + '" class="button button-small" style="margin-left:8px;" target="_blank"><span class="dashicons dashicons-download" style="vertical-align:middle; margin-top:-2px;"></span> Download</a>');
							}

							$attList.append($li);
						});
						$('#ael-row-attachments').show();
					} else {
						$('#ael-row-attachments').hide();
					}

					// Sandboxed Iframe Preview.
					var previewSrc = aelData.previewUrl + '&id=' + data.id + '&token=' + data.preview_token;
					$iframe.attr('src', previewSrc);

					// Raw Body.
					$('#ael-modal-raw-body').text(data.body_raw);

					// Full Headers.
					var $rawHeaders = $('#ael-modal-raw-headers').empty();
					if (data.headers && data.headers.length > 0) {
						$rawHeaders.text(data.headers.join("\n"));
						$('#ael-no-headers-msg').hide();
						$rawHeaders.show();
					} else {
						$('#ael-no-headers-msg').show();
						$rawHeaders.hide();
					}
				},
				error: function () {
					$modalLoading.hide();
					alert(aelData.i18n.errorLoading);
					closeModal();
				}
			});
		});

		// Close modal handlers.
		$('#ael-modal-close-btn, #ael-modal-footer-close-btn').on('click', closeModal);

		$modalOverlay.on('click', function (e) {
			if ($(e.target).is('#ael-modal-overlay')) {
				closeModal();
			}
		});

		$(document).on('keydown', function (e) {
			if ('Escape' === e.key && $modalOverlay.is(':visible')) {
				closeModal();
			}
		});

		// Modal inner tab switching.
		$('.ael-modal-tab-btn').on('click', function () {
			var tab = $(this).data('tab');
			$('.ael-modal-tab-btn').removeClass('active');
			$(this).addClass('active');

			$('.ael-modal-tab-pane').removeClass('active').hide();
			$('#ael-pane-' + tab).addClass('active').show();
		});

		// Copy raw body button with reliable clipboard fallback.
		$('#ael-copy-body-btn').on('click', function () {
			var bodyText = $('#ael-modal-raw-body').text();
			copyToClipboard(bodyText, $(this), aelData.i18n.copied);
		});

		// Copy full headers button.
		$('#ael-copy-headers-btn').on('click', function () {
			var headersText = $('#ael-modal-raw-headers').text();
			copyToClipboard(headersText, $(this), aelData.i18n.copied);
		});

		// Preview mode switcher (Desktop vs Mobile).
		$('.ael-preview-mode-btn').on('click', function () {
			var mode = $(this).data('mode');
			$('.ael-preview-mode-btn').removeClass('active');
			$(this).addClass('active');

			if ('mobile' === mode) {
				$('#ael-iframe-holder').removeClass('desktop-mode').addClass('mobile-mode');
			} else {
				$('#ael-iframe-holder').removeClass('mobile-mode').addClass('desktop-mode');
			}
		});

		// -------------------------------------------------------------
		// 2. Easy-to-Use Date Range Picker Dropdown
		// -------------------------------------------------------------

		$('#ael-date-picker-btn').on('click', function (e) {
			e.stopPropagation();
			$dateDropdownCard.fadeToggle(150);
			$columnsDropdown.fadeOut(100);
		});

		// Presets selection.
		$('.ael-preset-btn').on('click', function () {
			var preset = $(this).data('preset');
			$('#ael-hidden-date-range').val(preset);
			$('#ael-hidden-date-from').val('');
			$('#ael-hidden-date-to').val('');
			$('#ael-logs-filter-form').trigger('submit');
		});

		// Apply custom dates.
		$('#ael-apply-date-btn').on('click', function () {
			var from = $('#ael-dp-from').val();
			var to   = $('#ael-dp-to').val();
			$('#ael-hidden-date-range').val('');
			$('#ael-hidden-date-from').val(from);
			$('#ael-hidden-date-to').val(to);
			$('#ael-logs-filter-form').trigger('submit');
		});

		// Clear dates.
		$('#ael-reset-date-btn').on('click', function () {
			$('#ael-hidden-date-range').val('');
			$('#ael-hidden-date-from').val('');
			$('#ael-hidden-date-to').val('');
			$('#ael-logs-filter-form').trigger('submit');
		});

		// -------------------------------------------------------------
		// 3. Columns Visibility Toggle with Persistent Storage
		// -------------------------------------------------------------

		$('#ael-columns-btn').on('click', function (e) {
			e.stopPropagation();
			$columnsDropdown.fadeToggle(150);
			$dateDropdownCard.fadeOut(100);
		});

		// Load saved hidden columns from localStorage.
		var savedHiddenCols = [];
		try {
			var stored = localStorage.getItem('ael_hidden_columns');
			if (stored) {
				savedHiddenCols = JSON.parse(stored);
			}
		} catch (err) {}

		// Apply saved visibility on load.
		if (savedHiddenCols && savedHiddenCols.length > 0) {
			$.each(savedHiddenCols, function (idx, colKey) {
				$('.ael-col-toggle[data-col="' + colKey + '"]').prop('checked', false);
				$('table.wp-list-table th.column-' + colKey + ', table.wp-list-table td.column-' + colKey).hide();
			});
		} else {
			// By default on fresh install, hide CC and BCC to keep table uncluttered.
			$('.ael-col-toggle[data-col="cc"], .ael-col-toggle[data-col="bcc"]').prop('checked', false);
			$('table.wp-list-table th.column-cc, table.wp-list-table td.column-cc').hide();
			$('table.wp-list-table th.column-bcc, table.wp-list-table td.column-bcc').hide();
		}

		// Checkbox toggle event.
		$('.ael-col-toggle').on('change', function () {
			var colKey    = $(this).data('col');
			var isChecked = $(this).is(':checked');

			if (isChecked) {
				$('table.wp-list-table th.column-' + colKey + ', table.wp-list-table td.column-' + colKey).fadeIn(100);
			} else {
				$('table.wp-list-table th.column-' + colKey + ', table.wp-list-table td.column-' + colKey).fadeOut(100);
			}

			// Update saved columns in localStorage.
			var hidden = [];
			$('.ael-col-toggle:not(:checked)').each(function () {
				hidden.push($(this).data('col'));
			});
			try {
				localStorage.setItem('ael_hidden_columns', JSON.stringify(hidden));
			} catch (err) {}
		});

		// Close floating popups when clicking outside.
		$(document).on('click', function (e) {
			if (!$(e.target).closest('#ael-date-picker-wrap').length) {
				$dateDropdownCard.fadeOut(100);
			}
			if (!$(e.target).closest('#ael-columns-wrap').length) {
				$columnsDropdown.fadeOut(100);
			}
		});

		// -------------------------------------------------------------
		// 4. Trash, Restore & Permanent Delete Actions
		// -------------------------------------------------------------

		// Single Move to Trash.
		$(document).on('click', '.ael-action-trash', function (e) {
			e.preventDefault();
			var logId = $(this).data('id');
			if (!confirm(aelData.i18n.confirmTrash)) {
				return;
			}

			$.post(aelData.ajaxUrl, {
				action: 'ael_trash_log',
				ids: [logId],
				nonce: aelData.nonce
			}, function (res) {
				if (res.success) {
					window.location.reload();
				} else {
					alert(res.data.message || 'Operation failed.');
				}
			});
		});

		// Single Restore.
		$(document).on('click', '.ael-action-restore', function (e) {
			e.preventDefault();
			var logId = $(this).data('id');

			$.post(aelData.ajaxUrl, {
				action: 'ael_restore_log',
				ids: [logId],
				nonce: aelData.nonce
			}, function (res) {
				if (res.success) {
					window.location.reload();
				} else {
					alert(res.data.message || 'Operation failed.');
				}
			});
		});

		// Single Permanent Delete.
		$(document).on('click', '.ael-action-delete', function (e) {
			e.preventDefault();
			var logId = $(this).data('id');
			if (!confirm(aelData.i18n.confirmDelete)) {
				return;
			}

			$.post(aelData.ajaxUrl, {
				action: 'ael_delete_log',
				ids: [logId],
				nonce: aelData.nonce
			}, function (res) {
				if (res.success) {
					window.location.reload();
				} else {
					alert(res.data.message || 'Operation failed.');
				}
			});
		});

		// Empty Trash Button.
		$('#ael-empty-trash-btn').on('click', function (e) {
			e.preventDefault();
			if (!confirm(aelData.i18n.confirmEmptyTrash)) {
				return;
			}

			$.post(aelData.ajaxUrl, {
				action: 'ael_empty_trash',
				nonce: aelData.nonce
			}, function (res) {
				if (res.success) {
					window.location.reload();
				} else {
					alert(res.data.message || 'Operation failed.');
				}
			});
		});

		// Clear All Logs Button.
		$('#ael-clear-all-btn').on('click', function (e) {
			e.preventDefault();
			if (!confirm(aelData.i18n.confirmClearAll)) {
				return;
			}

			$.post(aelData.ajaxUrl, {
				action: 'ael_clear_all_logs',
				nonce: aelData.nonce
			}, function (res) {
				if (res.success) {
					window.location.reload();
				} else {
					alert(res.data.message || 'Operation failed.');
				}
			});
		});

		// -------------------------------------------------------------
		// 5. Bulk Export & Filtered CSV Export
		// -------------------------------------------------------------

		function handleBulkExport(e) {
			var action = $(this).siblings('select[name^="action"]').val();
			if ('export' !== action) {
				return;
			}

			e.preventDefault();

			var selectedIds = [];
			$('.ael-log-cb:checked').each(function () {
				selectedIds.push($(this).val());
			});

			if (selectedIds.length === 0) {
				alert(aelData.i18n.noLogsSelected);
				return;
			}

			$('#ael-export-selected-ids').val(selectedIds.join(','));
			$('#ael-bulk-export-form').trigger('submit');
		}

		$('#doaction, #doaction2').on('click', handleBulkExport);

		// Export Filtered Logs button.
		$('#ael-export-filtered-btn').on('click', function (e) {
			e.preventDefault();
			$('#ael-export-selected-ids').val(''); // Clear selected IDs to trigger filtered query export.
			$('#ael-bulk-export-form').trigger('submit');
		});

		// -------------------------------------------------------------
		// 6. CSV Importer Modal & Handling
		// -------------------------------------------------------------

		$('#ael-import-btn').on('click', function (e) {
			e.preventDefault();
			$importModal.fadeIn(150);
		});

		$('#ael-import-modal-close, #ael-import-cancel-btn').on('click', function () {
			$importModal.fadeOut(150);
		});

		// Import source tabs (Computer vs Media Library).
		$('.ael-import-tab-btn').on('click', function () {
			var tab = $(this).data('tab');
			$('.ael-import-tab-btn').removeClass('active');
			$(this).addClass('active');

			$('.ael-import-pane').removeClass('active').hide();
			$('#ael-import-pane-' + tab).addClass('active').show();
		});

		// File drag & drop highlight.
		var $dropzone = $('#ael-upload-dropzone');
		$dropzone.on('dragover dragenter', function (e) {
			e.preventDefault();
			$dropzone.addClass('dragover');
		}).on('dragleave dragend drop', function (e) {
			e.preventDefault();
			$dropzone.removeClass('dragover');
		});

		$('#ael-csv-file-input').on('change', function () {
			if (this.files && this.files[0]) {
				$('#ael-selected-filename').text('Selected file: ' + this.files[0].name);
			}
		});

		// Open WP Media Library.
		var mediaUploader;
		$('#ael-open-media-btn').on('click', function (e) {
			e.preventDefault();
			if (mediaUploader) {
				mediaUploader.open();
				return;
			}

			mediaUploader = wp.media({
				title: 'Select CSV File to Import',
				button: { text: 'Use this CSV' },
				multiple: false,
				library: { type: 'text/csv' }
			});

			mediaUploader.on('select', function () {
				var attachment = mediaUploader.state().get('selection').first().toJSON();
				$('#ael-selected-media-id').val(attachment.id);
				$('#ael-selected-media-name').text('Selected from Media: ' + attachment.filename);
			});

			mediaUploader.open();
		});

		// Start CSV Import AJAX.
		$('#ael-start-import-btn').on('click', function () {
			var $btn            = $(this);
			var $result         = $('#ael-import-result');
			var activeTab       = $('.ael-import-tab-btn.active').data('tab');
			var skipDuplicates  = $('input[name="ael_skip_duplicates"]:checked').val();

			var formData = new FormData();
			formData.append('action', 'ael_import_csv');
			formData.append('nonce', aelData.nonce);
			formData.append('skip_duplicates', skipDuplicates);

			if ('upload' === activeTab) {
				var fileInput = document.getElementById('ael-csv-file-input');
				if (!fileInput.files || !fileInput.files[0]) {
					alert('Please choose a CSV file to upload.');
					return;
				}
				formData.append('csv_file', fileInput.files[0]);
			} else {
				var mediaId = $('#ael-selected-media-id').val();
				if (!mediaId) {
					alert('Please select a CSV file from the Media Library.');
					return;
				}
				formData.append('media_id', mediaId);
			}

			$btn.prop('disabled', true).text(aelData.i18n.importing);
			$result.slideUp(150);

			$.ajax({
				url: aelData.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json',
				success: function (res) {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Import Logs Now');
					if (res.success && res.data) {
						$result.attr('class', 'ael-import-result success')
							.html('<strong>✔ ' + res.data.message + '</strong><br><small>Refreshing table...</small>')
							.slideDown(200);

						setTimeout(function () {
							window.location.reload();
						}, 1500);
					} else {
						$result.attr('class', 'ael-import-result error')
							.html('<strong>⚠ ' + (res.data ? res.data.message : 'Import failed.') + '</strong>')
							.slideDown(200);
					}
				},
				error: function (xhr) {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Import Logs Now');
					var msg = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Server error during import.';
					$result.attr('class', 'ael-import-result error')
						.html('<strong>⚠ ' + msg + '</strong>')
						.slideDown(200);
				}
			});
		});

		// -------------------------------------------------------------
		// 7. Manual Refresh & Live Updates Polling
		// -------------------------------------------------------------

		$('#ael-manual-refresh-btn, .ael-banner-refresh-btn').on('click', function () {
			window.location.reload();
		});

		if (aelData.liveUpdatesEnabled && $('#ael-logs-filter-form').length) {
			var currentLatestId = parseInt(aelData.latestId, 10) || 0;

			function pollNewLogs() {
				if (document.hidden || (document.visibilityState && document.visibilityState === 'hidden')) {
					return;
				}

				$.ajax({
					url: aelData.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'ael_poll_new_logs',
						since_id: currentLatestId,
						nonce: aelData.nonce
					},
					success: function (response) {
						if (response.success && response.data) {
							if (response.data.has_new && response.data.count > 0) {
								var msg = aelData.i18n.newLogsNotice.replace('%d', response.data.count);
								$('.ael-banner-message').text(msg);
								$newLogsBanner.slideDown(200);
							}
						}
					}
				});
			}

			pollTimer = setInterval(pollNewLogs, 20000);
		}

		// -------------------------------------------------------------
		// 8. Test Email Submission
		// -------------------------------------------------------------

		var $testForm = $('#ael-test-email-form');
		if ($testForm.length) {
			$testForm.on('submit', function (e) {
				e.preventDefault();

				var $btn     = $('#ael-send-test-btn');
				var $spinner = $('#ael-test-spinner');
				var $result  = $('#ael-test-result-box');
				var toEmail  = $('#ael_test_to').val();
				var subject  = $('#ael_test_subject').val();

				$btn.prop('disabled', true);
				$spinner.addClass('is-active');
				$result.slideUp(150);

				$.ajax({
					url: aelData.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'ael_send_test_email',
						to: toEmail,
						subject: subject,
						nonce: aelData.nonce
					},
					success: function (response) {
						$btn.prop('disabled', false);
						$spinner.removeClass('is-active');

						var isSuccess = response.success;
						var data      = response.data || {};
						var cssClass  = isSuccess ? 'success' : 'error';

						var outputHtml = '<p><strong>' + (isSuccess ? '&#10003; ' : '&#9888; ') + data.message + '</strong></p>';

						if (data.log_id && data.log_id > 0) {
							outputHtml += '<p style="margin-top:8px;">' +
								'<button type="button" class="button button-small ael-view-log-btn" data-id="' + data.log_id + '">' +
								'<span class="dashicons dashicons-visibility"></span> View Log Record #' + data.log_id +
								'</button>' +
								'</p>';
						}

						if (data.error) {
							outputHtml += '<p style="margin-top:6px; font-family:monospace; font-size:12px;">' + data.error + '</p>';
						}

						$result.attr('class', 'ael-test-result-box ' + cssClass).html(outputHtml).slideDown(200);
					},
					error: function () {
						$btn.prop('disabled', false);
						$spinner.removeClass('is-active');
						$result.attr('class', 'ael-test-result-box error')
							.html('<p>&#9888; Server error occurred while processing test email dispatch.</p>')
							.slideDown(200);
					}
				});
			});
		}

		// -------------------------------------------------------------
		// 8. Analytics Dashboard & Dynamic Charts (Chart.js)
		// -------------------------------------------------------------
		if ($('#ael-timeline-chart').length && typeof Chart !== 'undefined') {
			var timelineChart = null;
			var donutChart    = null;

			// Helper to format numbers with commas.
			function formatNumber(num) {
				return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
			}

			// Initialize Timeline Bar Chart.
			var timelineCtx = document.getElementById('ael-timeline-chart').getContext('2d');
			var initialTimeline = (typeof aelAnalyticsInitialData !== 'undefined' && aelAnalyticsInitialData.timeline) ? aelAnalyticsInitialData.timeline : { labels: [], accepted: [], failed: [] };

			timelineChart = new Chart(timelineCtx, {
				type: 'bar',
				data: {
					labels: initialTimeline.labels || [],
					datasets: [
						{
							label: 'Accepted / Sent',
							data: initialTimeline.accepted || [],
							backgroundColor: '#008a20',
							borderRadius: 4,
							barPercentage: 0.6
						},
						{
							label: 'Failed',
							data: initialTimeline.failed || [],
							backgroundColor: '#d63638',
							borderRadius: 4,
							barPercentage: 0.6
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: {
						mode: 'index',
						intersect: false
					},
					plugins: {
						legend: {
							position: 'top',
							align: 'end',
							labels: {
								boxWidth: 12,
								usePointStyle: true
							}
						},
						tooltip: {
							padding: 10,
							cornerRadius: 6
						}
					},
					scales: {
						x: {
							grid: {
								display: false
							}
						},
						y: {
							beginAtZero: true,
							ticks: {
								precision: 0
							}
						}
					}
				}
			});

			// Initialize Status Share Donut Chart.
			var donutCtx = document.getElementById('ael-donut-chart').getContext('2d');
			var initialSummary = (typeof aelAnalyticsInitialData !== 'undefined' && aelAnalyticsInitialData.summary) ? aelAnalyticsInitialData.summary : { accepted_count: 0, failed_count: 0, pending_count: 0, unknown_count: 0 };

			donutChart = new Chart(donutCtx, {
				type: 'doughnut',
				data: {
					labels: ['Accepted', 'Failed', 'Pending', 'Unknown'],
					datasets: [
						{
							data: [
								initialSummary.accepted_count || 0,
								initialSummary.failed_count || 0,
								initialSummary.pending_count || 0,
								initialSummary.unknown_count || 0
							],
							backgroundColor: ['#008a20', '#d63638', '#dba617', '#787c82'],
							borderWidth: 2,
							borderColor: '#ffffff'
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					cutout: '68%',
					plugins: {
						legend: {
							position: 'bottom',
							labels: {
								boxWidth: 12,
								padding: 14,
								usePointStyle: true
							}
						}
					}
				}
			});

			// Toggle Custom Date Range fields on dropdown change.
			$('#ael-analytics-range').on('change', function () {
				if ($(this).val() === 'custom') {
					$('#ael-analytics-custom-dates').slideDown(150);
				} else {
					$('#ael-analytics-custom-dates').slideUp(150);
					fetchAnalyticsData();
				}
			});

			// Apply Filter Button Click.
			$('#ael-analytics-apply-btn').on('click', function (e) {
				e.preventDefault();
				fetchAnalyticsData();
			});

			// Reset Filter Button Click.
			$('#ael-analytics-reset-btn').on('click', function (e) {
				e.preventDefault();
				$('#ael-analytics-range').val('last_7_days');
				$('#ael-analytics-ip').val('');
				$('#ael-analytics-date-from').val('');
				$('#ael-analytics-date-to').val('');
				$('#ael-analytics-custom-dates').hide();
				fetchAnalyticsData();
			});

			// Quick filter by IP click from table row.
			$(document).on('click', '.ael-filter-by-ip-btn', function (e) {
				e.preventDefault();
				var ip = $(this).data('ip');
				if (ip) {
					$('#ael-analytics-ip').val(ip);
					$('html, body').animate({ scrollTop: $('#ael-analytics-ip').offset().top - 80 }, 300);
					fetchAnalyticsData();
				}
			});

			// AJAX function to fetch updated analytics data dynamically.
			function fetchAnalyticsData() {
				var $spinner = $('#ael-analytics-spinner');
				var dateRange = $('#ael-analytics-range').val();
				var dateFrom  = $('#ael-analytics-date-from').val();
				var dateTo    = $('#ael-analytics-date-to').val();
				var ipAddress = $.trim($('#ael-analytics-ip').val());

				$spinner.show();
				$('#ael-analytics-apply-btn').prop('disabled', true);

				$.ajax({
					url: aelData.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'ael_get_analytics_data',
						nonce: aelData.nonce,
						date_range: dateRange,
						date_from: dateFrom,
						date_to: dateTo,
						ip_address: ipAddress
					},
					success: function (res) {
						$spinner.hide();
						$('#ael-analytics-apply-btn').prop('disabled', false);

						if (!res.success || !res.data) {
							return;
						}

						var data = res.data;
						var sum  = data.summary || {};

						// 1. Update KPI Summary Cards.
						$('#ael-kpi-total').text(formatNumber(sum.total || 0));
						$('#ael-kpi-accepted').text(formatNumber(sum.accepted_count || 0));
						$('#ael-kpi-accepted-pct').text((sum.accepted_pct || 0) + '%');
						$('#ael-kpi-failed').text(formatNumber(sum.failed_count || 0));
						$('#ael-kpi-failed-pct').text((sum.failed_pct || 0) + '%');
						$('#ael-kpi-pending').text(formatNumber(sum.pending_count || 0));
						$('#ael-kpi-pending-pct').text((sum.pending_pct || 0) + '%');
						$('#ael-kpi-unknown').text(formatNumber(sum.unknown_count || 0));
						$('#ael-kpi-unknown-pct').text((sum.unknown_pct || 0) + '%');

						// 2. Update Timeline Chart.
						if (timelineChart && data.timeline) {
							timelineChart.data.labels = data.timeline.labels || [];
							timelineChart.data.datasets[0].data = data.timeline.accepted || [];
							timelineChart.data.datasets[1].data = data.timeline.failed || [];
							timelineChart.update();
						}

						// 3. Update Donut Share Chart.
						if (donutChart) {
							donutChart.data.datasets[0].data = [
								sum.accepted_count || 0,
								sum.failed_count || 0,
								sum.pending_count || 0,
								sum.unknown_count || 0
							];
							donutChart.update();
						}

						// 4. Update IP Stats Table.
						var $ipTbody = $('#ael-ip-stats-tbody');
						$ipTbody.empty();

						if (!data.top_ips || !data.top_ips.length) {
							$ipTbody.html('<tr><td colspan="6" style="text-align:center; color:#646970; padding:18px;">No IP activity recorded in this time range.</td></tr>');
						} else {
							$.each(data.top_ips, function (i, item) {
								var badgeClass = item.failure_rate > 40.0 ? 'ael-badge-danger' : 'ael-badge-neutral';
								var riskPill   = item.is_suspicious
									? '<span class="ael-risk-pill ael-risk-danger" title="High failure rate detected. Possible spam bot or brute-force form submission."><span class="dashicons dashicons-warning"></span> Suspicious Bot</span>'
									: '<span class="ael-risk-pill ael-risk-safe"><span class="dashicons dashicons-yes"></span> Normal</span>';

								var rowHtml = '<tr>' +
									'<td><code>' + $('<div>').text(item.ip_address).html() + '</code></td>' +
									'<td><strong>' + formatNumber(item.total) + '</strong></td>' +
									'<td><span style="color:' + (item.failed_count > 0 ? '#d63638' : '#2c3338') + '; font-weight:600;">' + formatNumber(item.failed_count) + '</span></td>' +
									'<td><span class="ael-badge ' + badgeClass + '">' + item.failure_rate + '%</span></td>' +
									'<td>' + riskPill + '</td>' +
									'<td><button type="button" class="button button-small ael-filter-by-ip-btn" data-ip="' + $('<div>').text(item.ip_address).html() + '"><span class="dashicons dashicons-filter"></span> Filter</button></td>' +
									'</tr>';
								$ipTbody.append(rowHtml);
							});
						}

						// 5. Update Error Diagnostics.
						var $errContainer = $('#ael-error-stats-container');
						$errContainer.empty();

						if (!data.errors || !data.errors.length) {
							$errContainer.html('<div class="ael-empty-state-notice">' +
								'<span class="dashicons dashicons-yes-alt" style="color:#00a32a; font-size:32px; width:32px; height:32px; margin-bottom:8px;"></span>' +
								'<p style="font-size:14px; color:#2c3338; margin:0; font-weight:500;">No delivery errors recorded in this time range!</p>' +
								'<p style="color:#646970; font-size:12px; margin-top:4px;">Your WordPress email pipeline is operating smoothly without failures.</p>' +
								'</div>');
						} else {
							var $ul = $('<ul class="ael-error-list" id="ael-error-list-ul"></ul>');
							$.each(data.errors, function (i, err) {
								var shortMsg = err.error_message.length > 80 ? err.error_message.substring(0, 80) + '...' : err.error_message;
								var liHtml = '<li class="ael-error-item">' +
									'<div class="ael-error-item-header">' +
									'<span class="ael-error-message-text" title="' + $('<div>').text(err.error_message).html() + '">' + $('<div>').text(shortMsg).html() + '</span>' +
									'<span class="ael-badge ael-badge-danger">' + formatNumber(err.count) + ' events</span>' +
									'</div>' +
									'</li>';
								$ul.append(liHtml);
							});
							$errContainer.append($ul);
						}
					},
					error: function () {
						$spinner.hide();
						$('#ael-analytics-apply-btn').prop('disabled', false);
					}
				});
			}
		}

		// Manual Auto-Delete Cleanup Trigger in Settings.
		$('#ael-run-cleanup-btn').on('click', function () {
			var confirmMsg = aelData.i18n.confirmRunCleanup || 'Are you sure you want to run auto-delete now? Logs older than the chosen threshold will be permanently deleted.';
			if (!confirm(confirmMsg)) {
				return;
			}

			var $btn     = $(this);
			var $spinner = $('#ael-cleanup-spinner');
			var $notice  = $('#ael-cleanup-notice');

			var value        = $('#ael_auto_delete_value').val() || 30;
			var unit         = $('#ael_auto_delete_unit').val() || 'days';
			var includeTrash = $('#ael_auto_delete_include_trash').is(':checked') ? '1' : '0';

			$btn.prop('disabled', true);
			$spinner.addClass('is-active').show();
			$notice.hide().empty();

			$.ajax({
				url: aelData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ael_run_manual_cleanup',
					nonce: aelData.nonce,
					value: value,
					unit: unit,
					include_trash: includeTrash
				},
				success: function (res) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active').hide();

					if (res.success && res.data) {
						$notice.html('<div class="notice notice-success inline" style="padding:8px 12px; margin:4px 0;"><p>' + $('<div>').text(res.data.message).html() + '</p></div>').slideDown(200);
					} else {
						var err = (res.data && res.data.message) ? res.data.message : 'Failed to execute cleanup.';
						$notice.html('<div class="notice notice-error inline" style="padding:8px 12px; margin:4px 0;"><p>' + $('<div>').text(err).html() + '</p></div>').slideDown(200);
					}
				},
				error: function () {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active').hide();
					$notice.html('<div class="notice notice-error inline" style="padding:8px 12px; margin:4px 0;"><p>An unexpected network error occurred while running cleanup.</p></div>').slideDown(200);
				}
			});
		});
	});

})(jQuery);
