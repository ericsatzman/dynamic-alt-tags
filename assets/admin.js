(function () {
	'use strict';

	function setAltFieldValue(scope, value, attachmentId) {
		var selectors = [
			'input[data-setting="alt"]',
			'textarea[data-setting="alt"]',
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
		if (customInput instanceof HTMLInputElement) {
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

				var altText = payload.data && typeof payload.data.alt_text !== 'undefined' ? String(payload.data.alt_text) : '';
				var container = select.closest('.attachment-details, .media-sidebar, .compat-item, .setting, tr, table, tbody');
				if (container instanceof HTMLElement) {
					setAltFieldValue(container, altText, attachmentId);
				} else {
					setAltFieldValue(document, altText, attachmentId);
				}

				select.value = '';
				if (customInput instanceof HTMLInputElement) {
					customInput.value = '';
				}
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

	document.addEventListener('click', function (event) {
		var target = event.target;
		if (!(target instanceof HTMLElement)) {
			return;
		}

		var trigger = target.closest('button, input[type="submit"], input[type="button"]');
		if (!(trigger instanceof HTMLButtonElement) && !(trigger instanceof HTMLInputElement)) {
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

			if (isReject || isSkip) {
				var message = isSkip
					? 'Skip this image and move it to History?'
					: 'Reject this generated alt text?';

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
				if (!(customInput instanceof HTMLInputElement)) {
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
			if (!actionValue) {
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
			if (!(customInput instanceof HTMLInputElement)) {
				customInput = document.querySelector('input.ai-alt-upload-custom-alt[name="attachments[' + String(target.getAttribute('data-attachment-id') || '') + '][ai_alt_custom_alt]"]');
			}
			if (!(resultNode instanceof HTMLElement)) {
				resultNode = document.querySelector('.ai-alt-upload-action-result');
			}
			applyUploadAction(target, target, customInput, resultNode);
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
				window.clearInterval(timer);
				progressBar.style.width = '100%';
				progressBar.setAttribute('aria-valuenow', '100');

				if (!payload || payload.success !== true) {
					var errorMessage = i18n.error || 'Queue processing failed. Please try again.';
					if (payload && payload.data && payload.data.message) {
						errorMessage = String(payload.data.message);
					}
					progressMessage.textContent = errorMessage;
					progressMessage.classList.add('ai-alt-message-error');
					return;
				}

				var processed = 0;
				if (payload.data && typeof payload.data.processed !== 'undefined') {
					processed = Number(payload.data.processed) || 0;
				}

				var successMessage = i18n.success || 'Manual processing finished. %d items processed.';
				progressMessage.textContent = successMessage.replace('%d', String(processed));
				progressMessage.classList.add('ai-alt-message-success');
				window.setTimeout(function () {
					var url = new URL(window.location.href);
					url.searchParams.set('page', 'ai-alt-text-settings');
					url.searchParams.set('notice', 'process_done');
					url.searchParams.set('processed', String(processed));
					window.location.href = url.toString();
				}, 300);
			})
			.catch(function () {
				window.clearInterval(timer);
				progressMessage.textContent = i18n.error || 'Queue processing failed. Please try again.';
				progressMessage.classList.add('ai-alt-message-error');
			})
			.finally(function () {
				submitButton.disabled = false;
			});
	});
})();
