(function () {
	'use strict';

	function setAltFieldValue(scope, value, attachmentId) {
		var selectors = [
			'input[data-setting="alt"]',
			'textarea[data-setting="alt"]',
			'[data-setting="alt"]',
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

	function setTitleFieldValue(scope, value, attachmentId) {
		var selectors = [
			'input[data-setting="title"]',
			'[data-setting="title"]',
			'#attachment-details-two-column-title',
			'input#title',
			'input[name="attachments[' + attachmentId + '][post_title]"]'
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

	function setMediaModelFields(attachmentId, altText, titleText, syncTitle) {
		if (!window.wp || !window.wp.media) {
			return;
		}

		var numericId = Number(attachmentId);
		if (!numericId) {
			return;
		}

		var model = null;
		var frame = window.wp.media.frame;

		try {
			if (frame && typeof frame.state === 'function') {
				var state = frame.state();
				if (state && typeof state.get === 'function') {
					var selection = state.get('selection');
					if (selection && typeof selection.get === 'function') {
						model = selection.get(numericId) || selection.get(String(numericId));
					}
				}
			}
		} catch (e) {
			model = null;
		}

		if (!model && typeof window.wp.media.attachment === 'function') {
			model = window.wp.media.attachment(numericId);
		}

		if (!model || typeof model.set !== 'function') {
			return;
		}

		if (typeof altText === 'string' && altText.trim()) {
			model.set('alt', altText);
		}
		if (syncTitle && typeof titleText === 'string' && titleText.trim()) {
			model.set('title', titleText);
		}
		if (typeof model.trigger === 'function') {
			model.trigger('change');
		}
	}

	function setActiveSelectionModelFields(altText, titleText, syncTitle) {
		if (!window.wp || !window.wp.media || !window.wp.media.frame || typeof window.wp.media.frame.state !== 'function') {
			return;
		}

		try {
			var state = window.wp.media.frame.state();
			if (!state || typeof state.get !== 'function') {
				return;
			}
			var selection = state.get('selection');
			if (!selection || typeof selection.first !== 'function') {
				return;
			}
			var model = selection.first();
			if (!model || typeof model.set !== 'function') {
				return;
			}

			if (typeof altText === 'string' && altText.trim()) {
				model.set('alt', altText);
			}
			if (syncTitle && typeof titleText === 'string' && titleText.trim()) {
				model.set('title', titleText);
			}
			if (typeof model.trigger === 'function') {
				model.trigger('change');
			}
		} catch (e) {
			// Ignore media-frame state access errors.
		}
	}

	function applyAltAndTitleAcrossUi(attachmentId, altText, syncTitle, container) {
		var shouldSyncTitle = Boolean(syncTitle);
		var updateOnce = function () {
			try {
				if (container instanceof HTMLElement) {
					setAltFieldValue(container, altText, attachmentId);
					if (shouldSyncTitle) {
						setTitleFieldValue(container, altText, attachmentId);
					}
				}
				setAltFieldValue(document, altText, attachmentId);
				if (shouldSyncTitle) {
					setTitleFieldValue(document, altText, attachmentId);
				}
				setMediaModelFields(attachmentId, altText, altText, shouldSyncTitle);
				setActiveSelectionModelFields(altText, altText, shouldSyncTitle);
			} catch (e) {
				// Never turn a successful server response into a UI error due to local binding issues.
			}
		};

		// Re-apply after short delays because the grid sidebar can re-render asynchronously.
		updateOnce();
		window.setTimeout(updateOnce, 120);
		window.setTimeout(updateOnce, 360);
		window.setTimeout(updateOnce, 800);
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

	function hideUploadActionHint(select) {
		if (!(select instanceof HTMLSelectElement)) {
			return;
		}

		var container = select.closest('tr, .compat-field, .setting, .attachment-details');
		var hintNode = container ? container.querySelector('.ai-alt-upload-action-hint') : null;
		if (!(hintNode instanceof HTMLElement)) {
			return;
		}

		hintNode.style.display = 'none';
	}

	function clearPluginPageNotices() {
		// Intentionally no-op: preserve core/plugin notices in admin screens.
		return;
	}

	function placeRetrieveButtons() {
		var buttons = document.querySelectorAll('.ai-alt-upload-retrieve');
		buttons.forEach(function (button) {
			if (!(button instanceof HTMLButtonElement || button instanceof HTMLInputElement)) {
				return;
			}

			var sourceRow = button.closest('tr, .setting, .compat-field');
			if (!(sourceRow instanceof HTMLElement)) {
				return;
			}

			var wrap = sourceRow.querySelector('.ai-alt-upload-retrieve-wrap');
			if (!(wrap instanceof HTMLElement)) {
				return;
			}

			sourceRow.classList.add('ai-alt-upload-row');
			sourceRow.style.removeProperty('display');
			wrap.style.removeProperty('margin-top');
			wrap.style.removeProperty('margin-left');
			wrap.style.removeProperty('clear');
		});
	}

	function initSettingsTabs() {
		var container = document.getElementById('ai-alt-settings-tabs');
		if (!(container instanceof HTMLElement)) {
			return;
		}

		var tabButtons = container.querySelectorAll('.ai-alt-settings-tab');
		var tabPanels = container.querySelectorAll('.ai-alt-settings-tab-panel');
		if (!tabButtons.length || !tabPanels.length) {
			return;
		}

		container.classList.add('ai-alt-settings-tabs-ready');

		function activateTab(tabKey) {
			tabButtons.forEach(function (button) {
				if (!(button instanceof HTMLButtonElement)) {
					return;
				}
				var buttonTab = String(button.getAttribute('data-tab-target') || '');
				var isActive = buttonTab === tabKey;
				button.classList.toggle('nav-tab-active', isActive);
				button.setAttribute('aria-selected', isActive ? 'true' : 'false');
				button.setAttribute('tabindex', isActive ? '0' : '-1');
			});

			tabPanels.forEach(function (panel) {
				if (!(panel instanceof HTMLElement)) {
					return;
				}
				var panelTab = String(panel.getAttribute('data-tab-panel') || '');
				var isActive = panelTab === tabKey;
				panel.hidden = !isActive;
			});

			try {
				window.sessionStorage.setItem('aiAltSettingsTab', tabKey);
			} catch (e) {
				// Ignore sessionStorage availability errors.
			}
		}

		var availableTabs = [];
		tabButtons.forEach(function (button) {
			if (!(button instanceof HTMLButtonElement)) {
				return;
			}
			var key = String(button.getAttribute('data-tab-target') || '');
			if (key) {
				availableTabs.push(key);
			}
			button.addEventListener('click', function () {
				activateTab(key);
			});
		});

		if (!availableTabs.length) {
			return;
		}

		var initialTab = String(container.getAttribute('data-default-tab') || availableTabs[0]);
		try {
			var storedTab = window.sessionStorage.getItem('aiAltSettingsTab');
			if (storedTab && availableTabs.indexOf(storedTab) !== -1) {
				initialTab = storedTab;
			}
		} catch (e) {
			// Ignore sessionStorage availability errors.
		}

		if (availableTabs.indexOf(initialTab) === -1) {
			initialTab = availableTabs[0];
		}

		activateTab(initialTab);
	}

	function initSettingsMetricsRefresh() {
		var metricsPanel = document.getElementById('ai-alt-settings-panel-metrics');
		if (!(metricsPanel instanceof HTMLElement)) {
			return;
		}

		var adminData = window.aiAltAdmin || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' && adminData.ajaxUrl ? adminData.ajaxUrl : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
		var nonce = typeof adminData.settingsMetricsNonce === 'string' ? adminData.settingsMetricsNonce : '';
		if (!ajaxUrl || !nonce || typeof window.fetch !== 'function') {
			return;
		}

		var isRequestInFlight = false;

		function applyMetricFields(fields) {
			if (!fields || typeof fields !== 'object') {
				return;
			}

			Object.keys(fields).forEach(function (fieldId) {
				var node = document.getElementById(fieldId);
				if (!(node instanceof HTMLElement)) {
					return;
				}
				node.textContent = String(fields[fieldId]);
			});
		}

		function refreshMetrics() {
			if (isRequestInFlight || metricsPanel.hidden) {
				return;
			}

			isRequestInFlight = true;
			var body = new URLSearchParams();
			body.append('action', 'ai_alt_settings_metrics_ajax');
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
					if (!payload || payload.success !== true || !payload.data || typeof payload.data.fields !== 'object') {
						return;
					}
					applyMetricFields(payload.data.fields);
				})
				.catch(function () {
					return;
				})
				.finally(function () {
					isRequestInFlight = false;
				});
		}

		var metricsTabButton = document.querySelector('.ai-alt-settings-tab[data-tab-target="metrics"]');
		if (metricsTabButton instanceof HTMLButtonElement) {
			metricsTabButton.addEventListener('click', function () {
				window.setTimeout(refreshMetrics, 75);
			});
		}

		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) {
				refreshMetrics();
			}
		});

		window.setInterval(refreshMetrics, 15000);
		refreshMetrics();
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
						applyAltAndTitleAcrossUi(attachmentId, altText, Boolean(adminData && adminData.syncTitleFromAlt), container);
					}

					if (customInput instanceof HTMLInputElement || customInput instanceof HTMLTextAreaElement) {
						customInput.value = '';
					}
					setUploadApplyVisibility(select);
					hideUploadActionHint(select);
				})
			.catch(function () {
				if (resultNode.classList.contains('ai-alt-message-success')) {
					return;
				}
				resultNode.textContent = i18n.uploadActionFailed || 'Unable to apply upload action. Please try again.';
				resultNode.classList.add('ai-alt-message-error');
			})
			.finally(function () {
				if (trigger instanceof HTMLInputElement || trigger instanceof HTMLButtonElement) {
					trigger.disabled = false;
				}
			});
	}

	function retrieveUploadAltText(trigger, resultNode) {
		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' && adminData.ajaxUrl ? adminData.ajaxUrl : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');
		var attachmentId = trigger && trigger.getAttribute ? String(trigger.getAttribute('data-attachment-id') || '') : '';
		var nonce = typeof adminData.uploadActionNonce === 'string' ? adminData.uploadActionNonce : '';
		if (!nonce && trigger && trigger.getAttribute) {
			nonce = String(trigger.getAttribute('data-nonce') || '');
		}

		if (!(resultNode instanceof HTMLElement) || !attachmentId || !ajaxUrl || !nonce) {
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
		body.append('review_action', 'generate');
		body.append('custom_alt', '');

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

				if (payload.data && typeof payload.data.alt_text !== 'undefined') {
					var altText = String(payload.data.alt_text || '');
					if (!altText.trim()) {
						return;
					}
					var container = trigger.closest('.attachment-details, .media-sidebar, .compat-item, .setting, tr, table, tbody');
					applyAltAndTitleAcrossUi(attachmentId, altText, Boolean(adminData && adminData.syncTitleFromAlt), container);
				}
			})
			.catch(function () {
				if (resultNode.classList.contains('ai-alt-message-success')) {
					return;
				}
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

				if (trigger.classList.contains('ai-alt-upload-retrieve')) {
					event.preventDefault();
					var retrieveRow = target.closest('tr, .compat-field, .setting, .attachment-details');
					var retrieveResultNode = retrieveRow ? retrieveRow.querySelector('.ai-alt-upload-action-result') : null;
					if (!(retrieveResultNode instanceof HTMLElement)) {
						retrieveResultNode = document.querySelector('.ai-alt-upload-action-result');
					}
					retrieveUploadAltText(trigger, retrieveResultNode);
					return;
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
		if (target instanceof HTMLInputElement && target.classList.contains('ai-alt-admin-role-lock')) {
			target.checked = true;
			return;
		}
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
			clearPluginPageNotices();
			placeRetrieveButtons();
			initSettingsTabs();
			initSettingsMetricsRefresh();
			var lockedAdminRoleCheckboxes = document.querySelectorAll('input.ai-alt-admin-role-lock');
			lockedAdminRoleCheckboxes.forEach(function (checkbox) {
				if (checkbox instanceof HTMLInputElement) {
					checkbox.checked = true;
				}
			});

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
				'process_msg',
				'settings-updated',
				'_wp_http_referer'
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

	var attachmentObserver = new MutationObserver(function () {
		placeRetrieveButtons();
	});

	attachmentObserver.observe(document.documentElement, {
		childList: true,
		subtree: true
	});

	window.addEventListener('resize', function () {
		placeRetrieveButtons();
	});

	function getQueueProgressNodes() {
		return {
			wrap: document.getElementById('ai-alt-queue-progress-wrap'),
			bar: document.getElementById('ai-alt-queue-progress-bar'),
			message: document.getElementById('ai-alt-queue-progress-message')
		};
	}

	function getQueueBulkAction(form) {
		if (!(form instanceof HTMLFormElement)) {
			return '';
		}
		var topSelect = form.querySelector('#bulk-action-selector-top');
		var bottomSelect = form.querySelector('#bulk-action-selector-bottom');
		var topValue = topSelect instanceof HTMLSelectElement ? String(topSelect.value || '') : '';
		if (topValue && topValue !== '-1') {
			return topValue;
		}
		var bottomValue = bottomSelect instanceof HTMLSelectElement ? String(bottomSelect.value || '') : '';
		return (bottomValue && bottomValue !== '-1') ? bottomValue : '';
	}

	function getSelectedQueueRowIds(form) {
		if (!(form instanceof HTMLFormElement)) {
			return [];
		}
		var ids = [];
		var checkboxes = form.querySelectorAll('input.ai-alt-row-checkbox:checked');
		checkboxes.forEach(function (checkbox) {
			if (checkbox instanceof HTMLInputElement) {
				var value = String(checkbox.value || '').trim();
				if (value) {
					ids.push(value);
				}
			}
		});
		return ids;
	}

	function redirectQueueNotice(notice, params) {
		var url = new URL(window.location.href);
		url.searchParams.set('page', 'ai-alt-text-queue');
		url.searchParams.set('notice', notice);
		if (params) {
			Object.keys(params).forEach(function (key) {
				if (typeof params[key] !== 'undefined' && params[key] !== null) {
					url.searchParams.set(key, String(params[key]));
				}
			});
		}
		window.location.href = url.toString();
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		if (form.id !== 'ai-alt-process-form' && form.id !== 'ai-alt-queue-process-form' && !form.classList.contains('ai-alt-queue-form')) {
			return;
		}

		var adminData = window.aiAltAdmin || {};
		var i18n = adminData.i18n || {};
		var ajaxUrl = typeof adminData.ajaxUrl === 'string' ? adminData.ajaxUrl : '';
		if (form.id === 'ai-alt-process-form') {
			event.preventDefault();

			var settingsSubmitButton = form.querySelector('button[type="submit"], input[type="submit"]');
			var settingsProgressWrap = document.getElementById('ai-alt-progress-wrap');
			var settingsProgressBar = document.getElementById('ai-alt-progress-bar');
			var settingsProgressMessage = document.getElementById('ai-alt-progress-message');
			var canSubmitControl = settingsSubmitButton instanceof HTMLButtonElement || settingsSubmitButton instanceof HTMLInputElement;
			var processNowNonce = typeof adminData.processNowNonce === 'string' ? adminData.processNowNonce : '';

			if (!canSubmitControl || !(settingsProgressBar instanceof HTMLDivElement) || !(settingsProgressWrap instanceof HTMLDivElement) || !(settingsProgressMessage instanceof HTMLElement) || !ajaxUrl || !processNowNonce) {
				form.submit();
				return;
			}

			settingsSubmitButton.disabled = true;
			settingsProgressWrap.hidden = false;
			settingsProgressBar.style.width = '0%';
			settingsProgressBar.setAttribute('aria-valuenow', '0');
			settingsProgressMessage.textContent = i18n.processing || 'Processing queue...';
			settingsProgressMessage.classList.remove('ai-alt-message-error');
			settingsProgressMessage.classList.remove('ai-alt-message-success');

			var settingsProgress = 0;
			var settingsTimer = window.setInterval(function () {
				settingsProgress = Math.min(settingsProgress + 5, 90);
				settingsProgressBar.style.width = settingsProgress + '%';
				settingsProgressBar.setAttribute('aria-valuenow', String(settingsProgress));
			}, 180);

			var settingsTotalProcessed = 0;
			var settingsIterations = 0;
			var settingsMaxIterations = 25;
			var settingsLastDetailMessage = '';

			function finishSettingsSuccess(processedCount, noticeType, messageText) {
				window.clearInterval(settingsTimer);
				settingsProgressBar.style.width = '100%';
				settingsProgressBar.setAttribute('aria-valuenow', '100');
				settingsProgressMessage.textContent = messageText;
				settingsProgressMessage.classList.add('ai-alt-message-success');
				window.setTimeout(function () {
					var url = new URL(window.location.href);
					url.searchParams.set('page', 'ai-alt-text-settings');
					url.searchParams.set('notice', noticeType);
					url.searchParams.set('processed', String(processedCount));
					window.location.href = url.toString();
				}, 300);
			}

			function finishSettingsError(messageText) {
				window.clearInterval(settingsTimer);
				settingsProgressBar.style.width = '100%';
				settingsProgressBar.setAttribute('aria-valuenow', '100');
				settingsProgressMessage.textContent = messageText;
				settingsProgressMessage.classList.add('ai-alt-message-error');
				window.setTimeout(function () {
					var errorUrl = new URL(window.location.href);
					errorUrl.searchParams.set('page', 'ai-alt-text-settings');
					errorUrl.searchParams.set('notice', 'process_error');
					errorUrl.searchParams.set('process_msg', messageText);
					window.location.href = errorUrl.toString();
				}, 300);
			}

			function runSettingsChunk() {
				settingsIterations += 1;
				var body = new URLSearchParams();
				body.append('action', 'ai_alt_process_now_ajax');
				body.append('_ajax_nonce', processNowNonce);

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
						settingsTotalProcessed += chunkProcessed;

						var hasMore = false;
						if (payload.data && typeof payload.data.has_more !== 'undefined') {
							hasMore = Boolean(payload.data.has_more);
						}

						settingsLastDetailMessage = '';
						if (payload.data && payload.data.message) {
							settingsLastDetailMessage = String(payload.data.message);
						}

						if (chunkProcessed > 0 && hasMore && settingsIterations < settingsMaxIterations) {
							var processingMessage = i18n.processing || 'Processing queue...';
							settingsProgressMessage.textContent = processingMessage + ' ' + settingsTotalProcessed + ' processed so far.';
							return runSettingsChunk();
						}

						if (settingsTotalProcessed > 0) {
							var doneMessage = i18n.success || 'Manual processing finished. %d items processed.';
							var partialMessage = i18n.partial || 'Processing stopped early after %d items. You can run it again to continue.';
							if (hasMore || settingsIterations >= settingsMaxIterations) {
								finishSettingsSuccess(settingsTotalProcessed, 'process_partial', partialMessage.replace('%d', String(settingsTotalProcessed)));
								return;
							}
							finishSettingsSuccess(settingsTotalProcessed, 'process_done', doneMessage.replace('%d', String(settingsTotalProcessed)));
							return;
						}

						finishSettingsError(settingsLastDetailMessage || (i18n.error || 'Queue processing failed. Please try again.'));
					})
					.catch(function (err) {
						if (settingsTotalProcessed > 0) {
							var partialMessage = i18n.partial || 'Processing stopped early after %d items. You can run it again to continue.';
							finishSettingsSuccess(settingsTotalProcessed, 'process_partial', partialMessage.replace('%d', String(settingsTotalProcessed)));
							return;
						}

						var fallbackError = i18n.error || 'Queue processing failed. Please try again.';
						finishSettingsError((err && err.message) ? String(err.message) : fallbackError);
					})
					.finally(function () {
						if (settingsProgressMessage.classList.contains('ai-alt-message-success') || settingsProgressMessage.classList.contains('ai-alt-message-error')) {
							settingsSubmitButton.disabled = false;
						}
					});
			}

			runSettingsChunk();
			return;
		}

		var queueNodes = getQueueProgressNodes();
		var queueWrap = queueNodes.wrap;
		var queueBar = queueNodes.bar;
		var queueMessage = queueNodes.message;
		if (!(queueWrap instanceof HTMLDivElement) || !(queueBar instanceof HTMLDivElement) || !(queueMessage instanceof HTMLElement)) {
			return;
		}

		function setQueueProgress(percent, text, state) {
			var safePercent = Math.max(0, Math.min(100, Number(percent) || 0));
			queueWrap.hidden = false;
			queueBar.style.width = safePercent + '%';
			queueBar.setAttribute('aria-valuenow', String(safePercent));
			queueMessage.textContent = text || '';
			queueMessage.classList.remove('ai-alt-message-error');
			queueMessage.classList.remove('ai-alt-message-success');
			if (state === 'error') {
				queueMessage.classList.add('ai-alt-message-error');
			} else if (state === 'success') {
				queueMessage.classList.add('ai-alt-message-success');
			}
		}

		if (form.id === 'ai-alt-queue-process-form') {
			event.preventDefault();
			var queueSubmitButton = form.querySelector('button[type="submit"], input[type="submit"]');
			var queueNonce = typeof adminData.processNowNonce === 'string' ? adminData.processNowNonce : '';
			if (!(queueSubmitButton instanceof HTMLButtonElement || queueSubmitButton instanceof HTMLInputElement) || !ajaxUrl || !queueNonce) {
				form.submit();
				return;
			}

			queueSubmitButton.disabled = true;
			setQueueProgress(0, i18n.processing || 'Processing queue...', '');

			var totalProcessed = 0;
			var iterations = 0;
			var maxIterations = 25;
			var lastDetailMessage = '';
			var timerProgress = 0;
			var queueTimer = window.setInterval(function () {
				timerProgress = Math.min(timerProgress + 5, 90);
				setQueueProgress(timerProgress, (i18n.processing || 'Processing queue...') + ' ' + totalProcessed + ' processed so far.', '');
			}, 180);

			function finishQueueTopError(messageText) {
				window.clearInterval(queueTimer);
				setQueueProgress(100, '', '');
				window.setTimeout(function () {
					redirectQueueNotice('queue_error', { queue_msg: messageText });
				}, 250);
			}

			function runQueueTopChunk() {
				iterations += 1;
				var body = new URLSearchParams();
				body.append('action', 'ai_alt_process_now_ajax');
				body.append('_ajax_nonce', queueNonce);

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
						lastDetailMessage = (payload && payload.data && payload.data.message) ? String(payload.data.message) : '';

						if (chunkProcessed > 0 && hasMore && iterations < maxIterations) {
							setQueueProgress(Math.min(timerProgress, 95), (i18n.processing || 'Processing queue...') + ' ' + totalProcessed + ' processed so far.', '');
							return runQueueTopChunk();
						}

						window.clearInterval(queueTimer);
						if (totalProcessed > 0 && !hasMore) {
							setQueueProgress(100, (i18n.success || 'Manual processing finished. %d items processed.').replace('%d', String(totalProcessed)), 'success');
							window.setTimeout(function () {
								redirectQueueNotice('queue_batch_done', { processed: totalProcessed });
							}, 250);
							return;
						}

						if (totalProcessed > 0 && hasMore) {
							var partialMessage = (i18n.partial || 'Processing stopped early after %d items. You can run it again to continue.').replace('%d', String(totalProcessed));
							finishQueueTopError(partialMessage);
							return;
						}

						finishQueueTopError(lastDetailMessage || (i18n.error || 'Queue processing failed. Please try again.'));
					})
					.catch(function (err) {
						var fallbackMessage = (err && err.message) ? String(err.message) : (i18n.error || 'Queue processing failed. Please try again.');
						if (totalProcessed > 0) {
							fallbackMessage = (i18n.partial || 'Processing stopped early after %d items. You can run it again to continue.').replace('%d', String(totalProcessed));
						}
						finishQueueTopError(fallbackMessage);
					})
					.finally(function () {
						if (queueMessage.classList.contains('ai-alt-message-error') || queueMessage.classList.contains('ai-alt-message-success')) {
							queueSubmitButton.disabled = false;
						}
					});
			}

			runQueueTopChunk();
			return;
		}

		var bulkAction = getQueueBulkAction(form);
		if (bulkAction !== 'process') {
			return;
		}

		var rowIds = getSelectedQueueRowIds(form);
		if (rowIds.length < 1) {
			return;
		}

		event.preventDefault();
		var bulkNonce = typeof adminData.queueProcessNonce === 'string' ? adminData.queueProcessNonce : '';
		var bulkSubmitButton = form.querySelector('.tablenav.top .button.action');
		if (!(bulkSubmitButton instanceof HTMLButtonElement || bulkSubmitButton instanceof HTMLInputElement) || !ajaxUrl || !bulkNonce) {
			form.submit();
			return;
		}

		bulkSubmitButton.disabled = true;
		var processedCount = 0;
		var failureCount = 0;
		var currentIndex = 0;

		function finishBulkProcess() {
			var percent = 100;
			if (failureCount > 0) {
				var mixedMessage = processedCount > 0
					? 'Processed ' + processedCount + ' images, ' + failureCount + ' failed.'
					: (i18n.rowError || 'Image processing failed. Please try again.');
				setQueueProgress(percent, '', '');
				window.setTimeout(function () {
					redirectQueueNotice('queue_error', { queue_msg: mixedMessage });
				}, 250);
				return;
			}

			var successMessage = (i18n.success || 'Manual processing finished. %d items processed.').replace('%d', String(processedCount));
			setQueueProgress(percent, successMessage, 'success');
			window.setTimeout(function () {
				redirectQueueNotice('queue_batch_done', { processed: processedCount });
			}, 250);
		}

		function processNextBulkRow() {
			if (currentIndex >= rowIds.length) {
				finishBulkProcess();
				return;
			}

			var rowId = rowIds[currentIndex];
			var percent = Math.round((currentIndex / rowIds.length) * 100);
			setQueueProgress(percent, 'Processing images... ' + (currentIndex + 1) + ' of ' + rowIds.length, '');

			var body = new URLSearchParams();
			body.append('action', 'ai_alt_queue_process_ajax');
			body.append('_ajax_nonce', bulkNonce);
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
					if (payload && payload.success === true) {
						processedCount += 1;
					} else {
						failureCount += 1;
					}
				})
				.catch(function () {
					failureCount += 1;
				})
				.finally(function () {
					currentIndex += 1;
					processNextBulkRow();
				});
		}

		processNextBulkRow();
	});
})();
