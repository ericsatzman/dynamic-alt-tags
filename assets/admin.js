(function () {
	'use strict';

	function setAltFieldValue(scope, value, attachmentId) {
		var selectors = [
			'input[data-setting="alt"]',
			'textarea[data-setting="alt"]',
			'#attachment-details-two-column-alt-text',
			'input#attachment_alt',
			'textarea#attachment_alt',
			'input[name="attachments[' + attachmentId + '][image_alt]"]',
			'textarea[name="attachments[' + attachmentId + '][image_alt]"]'
		];

		selectors.forEach(function (selector) {
			var nodes = (scope || document).querySelectorAll(selector);
			nodes.forEach(function (node) {
				if (node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement) {
					node.value = value;
					node.dispatchEvent(new Event('input', { bubbles: true }));
					node.dispatchEvent(new Event('change', { bubbles: true }));
				}
			});
		});
	}

	function setUploadApplyVisibility(select) {
		if (!(select instanceof HTMLSelectElement)) {
			return;
		}

		var container = select.closest('tr, .compat-field, .setting, .attachment-details');
		var applyButton = container ? container.querySelector('.ai-alt-upload-apply') : null;
		if (!(applyButton instanceof HTMLButtonElement) && !(applyButton instanceof HTMLInputElement)) {
			return;
		}

		var isCustom = String(select.value || '') === 'custom';
		applyButton.style.display = isCustom ? 'inline-block' : 'none';
	}

	function setUploadCustomVisibility(select) {
		if (!(select instanceof HTMLSelectElement)) {
			return;
		}

		var container = select.closest('tr, .compat-field, .setting, .attachment-details');
		var customWrap = container ? container.querySelector('.ai-alt-upload-custom-wrap') : null;

		if (!(customWrap instanceof HTMLElement)) {
			var customInput = container ? container.querySelector('.ai-alt-upload-custom-alt') : null;
			customWrap = customInput instanceof HTMLElement ? customInput.closest('p') : null;
		}

		if (!(customWrap instanceof HTMLElement)) {
			return;
		}

		var isCustom = String(select.value || '') === 'custom';
		customWrap.style.display = isCustom ? 'block' : 'none';
	}

	function applyUploadAction(trigger, select, customInput, resultNode) {
		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' && adminData.ajaxUrl ? adminData.ajaxUrl : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
		var attachmentId = '';
		if (trigger && trigger.getAttribute) {
			attachmentId = String(trigger.getAttribute('data-attachment-id') || '');
		}
		if (!attachmentId && select && select.getAttribute) {
			attachmentId = String(select.getAttribute('data-attachment-id') || '');
		}
		var nonce = typeof adminData.uploadActionNonce === 'string' ? adminData.uploadActionNonce : '';
		if (!nonce && trigger && trigger.getAttribute) {
			nonce = String(trigger.getAttribute('data-nonce') || '');
		}
		if (!nonce && select && select.getAttribute) {
			nonce = String(select.getAttribute('data-nonce') || '');
		}

		if (!(select instanceof HTMLSelectElement) || !(resultNode instanceof HTMLElement) || !attachmentId || !ajaxUrl || !nonce) {
			return;
		}

		var reviewAction = String(select.value || '');
		if (!reviewAction) {
			resultNode.textContent = i18n.selectUploadAction || 'Please choose an action first.';
			resultNode.classList.add('ai-alt-message-error');
			return;
		}

		var customAlt = '';
		if (customInput instanceof HTMLInputElement || customInput instanceof HTMLTextAreaElement) {
			customAlt = String(customInput.value || '');
		}
		if (reviewAction === 'custom' && !customAlt.trim()) {
			resultNode.textContent = i18n.customAltRequired || 'Enter custom alt text before applying.';
			resultNode.classList.add('ai-alt-message-error');
			return;
		}

		if (trigger instanceof HTMLInputElement || trigger instanceof HTMLButtonElement) {
			trigger.disabled = true;
		}
		resultNode.textContent = '';
		resultNode.classList.remove('ai-alt-message-error');
		resultNode.classList.remove('ai-alt-message-success');

		var body = new URLSearchParams();
		body.append('action', 'ai_alt_upload_action_ajax');
		body.append('_ajax_nonce', nonce);
		body.append('attachment_id', attachmentId);
		body.append('review_action', reviewAction);
		body.append('custom_alt', customAlt);

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || payload.success !== true) {
					var errorMessage = i18n.uploadActionFailed || 'Unable to apply upload action. Please try again.';
					if (payload && payload.data && payload.data.message) {
						errorMessage = String(payload.data.message);
					}
					resultNode.textContent = errorMessage;
					resultNode.classList.add('ai-alt-message-error');
					return;
				}

				var message = payload.data && payload.data.message ? String(payload.data.message) : 'Action applied.';
				resultNode.textContent = message;
				resultNode.classList.add('ai-alt-message-success');

					// Generate creates/refreshes a suggestion only; do not overwrite the current alt field value.
					var shouldUpdateAltField = reviewAction !== 'generate' && payload.data && typeof payload.data.alt_text !== 'undefined';
					if (shouldUpdateAltField) {
						var altText = String(payload.data.alt_text);
						var container = select.closest('.attachment-details, .media-sidebar, .compat-item, .setting, tr, table, tbody');
						if (container instanceof HTMLElement) {
							setAltFieldValue(container, altText, attachmentId);
						}
						setAltFieldValue(document, altText, attachmentId);
					}

				if (customInput instanceof HTMLInputElement || customInput instanceof HTMLTextAreaElement) {
					customInput.value = '';
				}
				setUploadApplyVisibility(select);
			})
			.catch(function () {
				resultNode.textContent = i18n.uploadActionFailed || 'Unable to apply upload action. Please try again.';
				resultNode.classList.add('ai-alt-message-error');
			})
			.finally(function () {
				if (trigger instanceof HTMLInputElement || trigger instanceof HTMLButtonElement) {
					trigger.disabled = false;
				}
			});
	}

	function processQueueRow(trigger) {
		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' && adminData.ajaxUrl ? adminData.ajaxUrl : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
		var nonce = typeof adminData.queueProcessNonce === 'string' ? adminData.queueProcessNonce : '';
		if (!nonce && trigger && trigger.getAttribute) {
			nonce = String(trigger.getAttribute('data-nonce') || '');
		}
		var rowId = trigger && trigger.getAttribute ? String(trigger.getAttribute('data-row-id') || '') : '';
		if (!ajaxUrl || !nonce || !rowId) {
			return;
		}

		var row = trigger.closest('tr');
		if (!(row instanceof HTMLTableRowElement)) {
			return;
		}

		var progressWrap = row.querySelector('.ai-alt-row-progress-wrap');
		var progressBar = row.querySelector('.ai-alt-row-progress-bar');
		var messageNode = row.querySelector('.ai-alt-row-process-message');
		var statusNode = row.querySelector('.ai-alt-row-status');
		var confidenceNode = row.querySelector('.ai-alt-row-confidence');
		var suggestedInput = row.querySelector('.ai-alt-row-suggested');

		if (!(progressWrap instanceof HTMLDivElement) || !(progressBar instanceof HTMLDivElement) || !(messageNode instanceof HTMLElement)) {
			return;
		}

		function clearRowProcessFeedback() {
			progressWrap.hidden = true;
			messageNode.textContent = '';
			messageNode.classList.remove('ai-alt-message-success');
			messageNode.classList.remove('ai-alt-message-error');
		}

		function scheduleClearRowProcessFeedback() {
			window.setTimeout(function () {
				clearRowProcessFeedback();
			}, 1800);
		}

		trigger.disabled = true;
		progressWrap.hidden = false;
		progressBar.style.width = '0%';
		progressBar.setAttribute('aria-valuenow', '0');
		messageNode.textContent = i18n.rowProcessing || 'Processing image...';
		messageNode.classList.remove('ai-alt-message-success');
		messageNode.classList.remove('ai-alt-message-error');

		var progress = 0;
		var timer = window.setInterval(function () {
			progress = Math.min(progress + 8, 90);
			progressBar.style.width = progress + '%';
			progressBar.setAttribute('aria-valuenow', String(progress));
		}, 160);

		var body = new URLSearchParams();
		body.append('action', 'ai_alt_queue_process_ajax');
		body.append('_ajax_nonce', nonce);
		body.append('row_id', rowId);

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				window.clearInterval(timer);
				progressBar.style.width = '100%';
				progressBar.setAttribute('aria-valuenow', '100');

				if (!payload || payload.success !== true) {
					var errorMessage = i18n.rowError || 'Image processing failed. Please try again.';
					if (payload && payload.data && payload.data.message) {
						errorMessage = String(payload.data.message);
					}
					messageNode.textContent = errorMessage;
					messageNode.classList.add('ai-alt-message-error');
					scheduleClearRowProcessFeedback();
					return;
				}

				messageNode.textContent = (payload.data && payload.data.message) ? String(payload.data.message) : (i18n.rowSuccess || 'Image successfully processed');
				messageNode.classList.add('ai-alt-message-success');

				if (statusNode instanceof HTMLElement && payload.data && payload.data.status) {
					statusNode.textContent = String(payload.data.status);
				}
				if (confidenceNode instanceof HTMLElement && payload.data && typeof payload.data.confidence !== 'undefined') {
					var conf = Number(payload.data.confidence) || 0;
					confidenceNode.textContent = conf.toFixed(2);
				}
				if ((suggestedInput instanceof HTMLInputElement || suggestedInput instanceof HTMLTextAreaElement) && payload.data && typeof payload.data.suggested_alt !== 'undefined') {
					suggestedInput.value = String(payload.data.suggested_alt || '');
				}
				scheduleClearRowProcessFeedback();
			})
			.catch(function () {
				window.clearInterval(timer);
				progressBar.style.width = '100%';
				progressBar.setAttribute('aria-valuenow', '100');
				messageNode.textContent = i18n.rowError || 'Image processing failed. Please try again.';
				messageNode.classList.add('ai-alt-message-error');
				scheduleClearRowProcessFeedback();
			})
			.finally(function () {
				trigger.disabled = false;
			});
	}

	function loadMoreQueueRows(trigger) {
		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' && adminData.ajaxUrl ? adminData.ajaxUrl : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
		var nonce = typeof adminData.queueLoadMoreNonce === 'string' ? adminData.queueLoadMoreNonce : '';
		if (!ajaxUrl || !nonce || !(trigger instanceof HTMLButtonElement)) {
			return;
		}

		var view = String(trigger.getAttribute('data-view') || 'active');
		var status = String(trigger.getAttribute('data-status') || '');
		var nextPage = Number(trigger.getAttribute('data-next-page') || '1') || 1;
		var perPage = Number(trigger.getAttribute('data-per-page') || '20') || 20;
		var tbody = document.getElementById('ai-alt-queue-tbody');
		if (!(tbody instanceof HTMLTableSectionElement)) {
			return;
		}

		trigger.disabled = true;
		var originalLabel = trigger.textContent || '';
		trigger.textContent = i18n.loadingMore || 'Loading more...';

		var body = new URLSearchParams();
		body.append('action', 'ai_alt_queue_load_more_ajax');
		body.append('_ajax_nonce', nonce);
		body.append('view', view);
		body.append('status', status);
		body.append('page', String(nextPage));
		body.append('per_page', String(perPage));

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || payload.success !== true || !payload.data || typeof payload.data.html !== 'string') {
					throw new Error(i18n.loadMoreError || 'Unable to load more items. Please try again.');
				}

				var emptyRow = tbody.querySelector('tr td[colspan]');
				if (emptyRow && emptyRow.parentElement === tbody) {
					tbody.removeChild(emptyRow.parentElement);
				}

				tbody.insertAdjacentHTML('beforeend', payload.data.html);

				var hasMore = Boolean(payload.data.has_more);
				var newNextPage = Number(payload.data.next_page || (nextPage + 1));
				if (hasMore) {
					trigger.setAttribute('data-next-page', String(newNextPage));
					trigger.disabled = false;
					trigger.textContent = originalLabel;
					return;
				}

				var wrap = trigger.closest('.ai-alt-load-more-wrap');
				if (wrap instanceof HTMLElement) {
					wrap.remove();
				}
			})
			.catch(function () {
				trigger.disabled = false;
				trigger.textContent = originalLabel;
				window.alert(i18n.loadMoreError || 'Unable to load more items. Please try again.');
			});
	}

	function addNoAltImageToQueue(trigger) {
		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' && adminData.ajaxUrl ? adminData.ajaxUrl : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
		var nonce = typeof adminData.queueAddNoAltNonce === 'string' ? adminData.queueAddNoAltNonce : '';
		if (!ajaxUrl || !nonce || !(trigger instanceof HTMLButtonElement)) {
			return;
		}

		var attachmentId = String(trigger.getAttribute('data-attachment-id') || '');
		if (!attachmentId) {
			return;
		}

		var row = trigger.closest('tr');
		var messageNode = row ? row.querySelector('.ai-alt-no-alt-message') : null;
		var statusNode = row ? row.querySelector('.ai-alt-no-alt-queue-status') : null;

		trigger.disabled = true;
		if (messageNode instanceof HTMLElement) {
			messageNode.textContent = '';
			messageNode.classList.remove('ai-alt-message-success');
			messageNode.classList.remove('ai-alt-message-error');
		}

		var body = new URLSearchParams();
		body.append('action', 'ai_alt_queue_add_no_alt_ajax');
		body.append('_ajax_nonce', nonce);
		body.append('attachment_id', attachmentId);

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || payload.success !== true) {
					var err = i18n.queueAddError || 'Unable to add image to queue.';
					if (payload && payload.data && payload.data.message) {
						err = String(payload.data.message);
					}
					if (messageNode instanceof HTMLElement) {
						messageNode.textContent = err;
						messageNode.classList.add('ai-alt-message-error');
					}
					trigger.disabled = false;
					return;
				}

				trigger.textContent = i18n.queueAddSuccess || 'Added to queue';
				if (statusNode instanceof HTMLElement) {
					statusNode.textContent = 'queued';
				}
				if (messageNode instanceof HTMLElement) {
					messageNode.textContent = i18n.queueAddSuccess || 'Added to queue';
					messageNode.classList.add('ai-alt-message-success');
				}
			})
			.catch(function () {
				if (messageNode instanceof HTMLElement) {
					messageNode.textContent = i18n.queueAddError || 'Unable to add image to queue.';
					messageNode.classList.add('ai-alt-message-error');
				}
				trigger.disabled = false;
			});
	}

	document.addEventListener('click', function (event) {
		var target = event.target;
		if (!(target instanceof HTMLElement)) {
			return;
		}

		var trigger = target.closest('button, input[type="submit"], input[type="button"]');
		if (!(trigger instanceof HTMLButtonElement) && !(trigger instanceof HTMLInputElement)) {
			return;
		}

		if (trigger.classList.contains('ai-alt-load-more')) {
			event.preventDefault();
			loadMoreQueueRows(trigger);
			return;
		}

		if (trigger.classList.contains('ai-alt-add-no-alt')) {
			event.preventDefault();
			addNoAltImageToQueue(trigger);
			return;
		}

		if (trigger.classList.contains('ai-alt-row-process')) {
			event.preventDefault();
			processQueueRow(trigger);
			return;
		}

		if (trigger.classList.contains('ai-alt-toggle-token')) {
			var inputId = trigger.getAttribute('data-target');
			if (!inputId) {
				return;
			}

			var input = document.getElementById(inputId);
			if (!(input instanceof HTMLInputElement)) {
				return;
			}

			var showLabel = trigger.getAttribute('data-show-label') || 'Show';
			var hideLabel = trigger.getAttribute('data-hide-label') || 'Hide';
			var showing = input.type === 'text';

			input.type = showing ? 'password' : 'text';
			trigger.textContent = showing ? showLabel : hideLabel;
			trigger.setAttribute('aria-pressed', showing ? 'false' : 'true');
			return;
		}

			var actionValue = String(trigger.value || '');
			var isReject = actionValue === 'reject' || actionValue.indexOf('reject|') === 0;
			var isSkip = actionValue === 'skip' || actionValue.indexOf('skip|') === 0;
			var adminData = window.aiAltAdmin || {};
			var i18n = adminData.i18n || {};

				if (isReject || isSkip) {
					var message = isSkip
						? (i18n.confirmSkip || 'Skip this image and move it to History?')
						: (i18n.confirmReject || 'Reject this generated alt text?');

				if (!window.confirm(message)) {
					event.preventDefault();
				}
			}

				if (trigger.classList.contains('ai-alt-upload-apply')) {
					event.preventDefault();
					var row = target.closest('tr, .compat-field, .setting, .attachment-details');
					var attachmentId = String(trigger.getAttribute('data-attachment-id') || '');
					var select = row ? row.querySelector('.ai-alt-upload-action') : null;
					var customInput = row ? row.querySelector('.ai-alt-upload-custom-alt') : null;
					var resultNode = row ? row.querySelector('.ai-alt-upload-action-result') : null;
					if (!(select instanceof HTMLSelectElement)) {
						select = document.querySelector('select.ai-alt-upload-action[name="attachments[' + attachmentId + '][ai_alt_action]"]');
				}
					if (!(customInput instanceof HTMLInputElement) && !(customInput instanceof HTMLTextAreaElement)) {
						customInput = document.querySelector('input.ai-alt-upload-custom-alt[name="attachments[' + attachmentId + '][ai_alt_custom_alt]"]');
					}
				if (!(resultNode instanceof HTMLElement)) {
					resultNode = document.querySelector('.ai-alt-upload-action-result');
				}
				applyUploadAction(trigger, select, customInput, resultNode);
			}
		});

	document.addEventListener('change', function (event) {
		var target = event.target;
		if (target instanceof HTMLInputElement && target.classList.contains('ai-alt-select-all')) {
			var checked = target.checked;
			var checkboxes = document.querySelectorAll('.ai-alt-row-checkbox');
			checkboxes.forEach(function (checkbox) {
				if (checkbox instanceof HTMLInputElement) {
					checkbox.checked = checked;
				}
			});
			return;
		}

				var isUploadActionSelect = target instanceof HTMLSelectElement && (target.classList.contains('ai-alt-upload-action') || /\[ai_alt_action\]$/.test(String(target.name || '')));
				if (isUploadActionSelect) {
					var actionValue = String(target.value || '');
					setUploadApplyVisibility(target);
					setUploadCustomVisibility(target);
					if (!actionValue) {
						return;
					}
				if (actionValue === 'custom') {
					return;
				}

			var container = target.closest('tr, .compat-field, .setting, .attachment-details');
			var applyButton = container ? container.querySelector('.ai-alt-upload-apply') : null;
			if (applyButton instanceof HTMLButtonElement || applyButton instanceof HTMLInputElement) {
				applyButton.click();
				return;
			}

			var customInput = container ? container.querySelector('.ai-alt-upload-custom-alt') : null;
			var resultNode = container ? container.querySelector('.ai-alt-upload-action-result') : null;
			if (!(customInput instanceof HTMLInputElement) && !(customInput instanceof HTMLTextAreaElement)) {
				customInput = document.querySelector('input.ai-alt-upload-custom-alt[name="attachments[' + String(target.getAttribute('data-attachment-id') || '') + '][ai_alt_custom_alt]"]');
			}
			if (!(resultNode instanceof HTMLElement)) {
				resultNode = document.querySelector('.ai-alt-upload-action-result');
			}
				applyUploadAction(target, target, customInput, resultNode);
			}
		});

		document.addEventListener('DOMContentLoaded', function () {
			var selects = document.querySelectorAll('select.ai-alt-upload-action');
			selects.forEach(function (select) {
				if (select instanceof HTMLSelectElement) {
					setUploadApplyVisibility(select);
					setUploadCustomVisibility(select);
				}
			});

		// Make admin notices one-time by removing notice query args after render.
		if (window.history && typeof window.history.replaceState === 'function') {
			var url = new URL(window.location.href);
			var noticeParams = [
				'notice',
				'processed',
				'enqueued',
				'updated',
				'test_status',
				'test_msg',
				'queue_msg',
				'process_msg'
			];
			var changed = false;
			noticeParams.forEach(function (key) {
				if (url.searchParams.has(key)) {
					url.searchParams.delete(key);
					changed = true;
				}
			});
			if (changed) {
				window.history.replaceState({}, document.title, url.toString());
			}
		}
	});

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!(form instanceof HTMLFormElement) || form.id !== 'ai-alt-process-form') {
			return;
		}

		event.preventDefault();

		var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
		var progressWrap = document.getElementById('ai-alt-progress-wrap');
		var progressBar = document.getElementById('ai-alt-progress-bar');
		var progressMessage = document.getElementById('ai-alt-progress-message');
		var canSubmitControl = submitButton instanceof HTMLButtonElement || submitButton instanceof HTMLInputElement;

		if (!canSubmitControl || !(progressBar instanceof HTMLDivElement) || !(progressWrap instanceof HTMLDivElement) || !(progressMessage instanceof HTMLElement)) {
			form.submit();
			return;
		}

		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' ? adminData.ajaxUrl : '';
		var nonce = typeof adminData.processNowNonce === 'string' ? adminData.processNowNonce : '';
		if (!ajaxUrl || !nonce) {
			form.submit();
			return;
		}

		submitButton.disabled = true;
		progressWrap.hidden = false;
		progressBar.style.width = '0%';
		progressBar.setAttribute('aria-valuenow', '0');
		progressMessage.textContent = i18n.processing || 'Processing queue...';
		progressMessage.classList.remove('ai-alt-message-error');
		progressMessage.classList.remove('ai-alt-message-success');

		var progress = 0;
		var timer = window.setInterval(function () {
			progress = Math.min(progress + 5, 90);
			progressBar.style.width = progress + '%';
			progressBar.setAttribute('aria-valuenow', String(progress));
		}, 180);

		var totalProcessed = 0;
		var iterations = 0;
		var maxIterations = 25;
		var lastDetailMessage = '';

		function finishSuccess(processedCount, noticeType, messageText) {
			window.clearInterval(timer);
			progressBar.style.width = '100%';
			progressBar.setAttribute('aria-valuenow', '100');
			progressMessage.textContent = messageText;
			progressMessage.classList.add('ai-alt-message-success');
			window.setTimeout(function () {
				var url = new URL(window.location.href);
				url.searchParams.set('page', 'ai-alt-text-settings');
				url.searchParams.set('notice', noticeType);
				url.searchParams.set('processed', String(processedCount));
				window.location.href = url.toString();
			}, 300);
		}

		function finishError(messageText) {
			window.clearInterval(timer);
			progressBar.style.width = '100%';
			progressBar.setAttribute('aria-valuenow', '100');
			progressMessage.textContent = messageText;
			progressMessage.classList.add('ai-alt-message-error');
			window.setTimeout(function () {
				var errorUrl = new URL(window.location.href);
				errorUrl.searchParams.set('page', 'ai-alt-text-settings');
				errorUrl.searchParams.set('notice', 'process_error');
				errorUrl.searchParams.set('process_msg', messageText);
				window.location.href = errorUrl.toString();
			}, 300);
		}

		function runChunk() {
			iterations += 1;
			var body = new URLSearchParams();
			body.append('action', 'ai_alt_process_now_ajax');
			body.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: body.toString()
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (payload) {
					if (!payload || payload.success !== true) {
						var errorMessage = i18n.error || 'Queue processing failed. Please try again.';
						if (payload && payload.data && payload.data.message) {
							errorMessage = String(payload.data.message);
						}
						throw new Error(errorMessage);
					}

					var chunkProcessed = 0;
					if (payload.data && typeof payload.data.processed !== 'undefined') {
						chunkProcessed = Number(payload.data.processed) || 0;
					}
					totalProcessed += chunkProcessed;

					var hasMore = false;
					if (payload.data && typeof payload.data.has_more !== 'undefined') {
						hasMore = Boolean(payload.data.has_more);
					}

					lastDetailMessage = '';
					if (payload.data && payload.data.message) {
						lastDetailMessage = String(payload.data.message);
					}

					if (chunkProcessed > 0 && hasMore && iterations < maxIterations) {
						var processingMessage = i18n.processing || 'Processing queue...';
						progressMessage.textContent = processingMessage + ' ' + totalProcessed + ' processed so far.';
						return runChunk();
					}

					if (totalProcessed > 0) {
						var doneMessage = i18n.success || 'Manual processing finished. %d items processed.';
						var partialMessage = i18n.partial || 'Processing stopped early after %d items. You can run it again to continue.';
						if (hasMore || iterations >= maxIterations) {
							finishSuccess(totalProcessed, 'process_partial', partialMessage.replace('%d', String(totalProcessed)));
							return;
						}
						finishSuccess(totalProcessed, 'process_done', doneMessage.replace('%d', String(totalProcessed)));
						return;
					}

					finishError(lastDetailMessage || (i18n.error || 'Queue processing failed. Please try again.'));
				})
				.catch(function (err) {
					if (totalProcessed > 0) {
						var partialMessage = i18n.partial || 'Processing stopped early after %d items. You can run it again to continue.';
						finishSuccess(totalProcessed, 'process_partial', partialMessage.replace('%d', String(totalProcessed)));
						return;
					}

					var fallbackError = i18n.error || 'Queue processing failed. Please try again.';
					finishError((err && err.message) ? String(err.message) : fallbackError);
				})
				.finally(function () {
					if (progressMessage.classList.contains('ai-alt-message-success') || progressMessage.classList.contains('ai-alt-message-error')) {
						submitButton.disabled = false;
					}
				});
		}

		runChunk();
	});
})();
