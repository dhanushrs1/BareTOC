(function () {
	'use strict';

	function setState(button, content, expanded) {
		var openIcon = button.querySelector('.baretoc-toggle-icon--open');
		var closeIcon = button.querySelector('.baretoc-toggle-icon--close');

		button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
		button.setAttribute('aria-label', expanded ? button.getAttribute('data-close-label') : button.getAttribute('data-open-label'));
		content.hidden = !expanded;

		if (openIcon) {
			openIcon.hidden = expanded;
		}

		if (closeIcon) {
			closeIcon.hidden = !expanded;
		}
	}

	function contentFor(button) {
		var contentId = button.getAttribute('aria-controls');

		return contentId ? document.getElementById(contentId) : null;
	}

	function initialize(button) {
		var content = contentFor(button);

		if (!content) {
			return;
		}

		setState(button, content, button.getAttribute('data-baretoc-initial') !== 'closed');
		button.hidden = false;
	}

	function initializeAll() {
		document.querySelectorAll('.baretoc-toggle').forEach(initialize);
	}

	document.addEventListener('click', function (event) {
		var source = event.target;
		var button;
		var content;

		if (!(source instanceof Element)) {
			return;
		}

		button = source.closest('.baretoc-toggle');

		if (!button) {
			return;
		}

		content = contentFor(button);

		if (!content) {
			return;
		}

		setState(button, content, button.getAttribute('aria-expanded') !== 'true');
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeAll);
	} else {
		initializeAll();
	}
}());
