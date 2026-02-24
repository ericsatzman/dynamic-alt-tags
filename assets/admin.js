(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var target = event.target;
		if (!(target instanceof HTMLButtonElement)) {
			return;
		}

		if (target.value === 'reject' || target.value === 'skip') {
			var message = target.value === 'skip'
				? 'Mark this as decorative and store empty alt text?'
				: 'Reject this generated alt text?';

			if (!window.confirm(message)) {
				event.preventDefault();
			}
		}
	});
})();
