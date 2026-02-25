(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var target = event.target;
		if (!(target instanceof HTMLButtonElement)) {
			return;
		}

		if (target.classList.contains('ai-alt-toggle-token')) {
			var inputId = target.getAttribute('data-target');
			if (!inputId) {
				return;
			}

			var input = document.getElementById(inputId);
			if (!(input instanceof HTMLInputElement)) {
				return;
			}

			var showLabel = target.getAttribute('data-show-label') || 'Show';
			var hideLabel = target.getAttribute('data-hide-label') || 'Hide';
			var showing = input.type === 'text';

			input.type = showing ? 'password' : 'text';
			target.textContent = showing ? showLabel : hideLabel;
			target.setAttribute('aria-pressed', showing ? 'false' : 'true');
			return;
		}

		var actionValue = String(target.value || '');
		var isReject = actionValue === 'reject' || actionValue.indexOf('reject|') === 0;
		var isSkip = actionValue === 'skip' || actionValue.indexOf('skip|') === 0;

		if (isReject || isSkip) {
			var message = isSkip
				? 'Mark this as decorative and store empty alt text?'
				: 'Reject this generated alt text?';

			if (!window.confirm(message)) {
				event.preventDefault();
			}
		}
	});

	document.addEventListener('change', function (event) {
		var target = event.target;
		if (!(target instanceof HTMLInputElement)) {
			return;
		}

		if (!target.classList.contains('ai-alt-select-all')) {
			return;
		}

		var checked = target.checked;
		var checkboxes = document.querySelectorAll('.ai-alt-row-checkbox');
		checkboxes.forEach(function (checkbox) {
			if (checkbox instanceof HTMLInputElement) {
				checkbox.checked = checked;
			}
		});
	});
})();
