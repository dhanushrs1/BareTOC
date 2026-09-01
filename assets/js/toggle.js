(function () {
	'use strict';

	var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');

	function updateControl(button, expanded) {
		var openIcon = button.querySelector('.baretoc-toggle-icon--open');
		var closeIcon = button.querySelector('.baretoc-toggle-icon--close');
		var openLabel = button.getAttribute('data-open-label') || 'Open table of contents';
		var closeLabel = button.getAttribute('data-close-label') || 'Close table of contents';

		button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
		button.setAttribute('aria-label', expanded ? closeLabel : openLabel);

		if (openIcon) {
			openIcon.hidden = expanded;
		}

		if (closeIcon) {
			closeIcon.hidden = !expanded;
		}
	}

	function setState(button, content, expanded) {
		updateControl(button, expanded);
		content.hidden = !expanded;
	}

	function animateState(button, content, expanded) {
		var startHeight;
		var endHeight;
		var animation;

		if (button.getAttribute('data-baretoc-smooth') !== 'yes' || typeof content.animate !== 'function' || (reducedMotion && reducedMotion.matches)) {
			setState(button, content, expanded);
			return;
		}

		if (expanded) {
			content.hidden = false;
			startHeight = 0;
			endHeight = content.scrollHeight;
		} else {
			startHeight = content.getBoundingClientRect().height;
			endHeight = 0;
		}

		updateControl(button, expanded);
		button.disabled = true;
		content.style.overflow = 'hidden';
		animation = content.animate(
			[
				{ height: startHeight + 'px', opacity: expanded ? 0 : 1 },
				{ height: endHeight + 'px', opacity: expanded ? 1 : 0 }
			],
			{ duration: 220, easing: 'ease' }
		);
		animation.onfinish = function () {
			content.hidden = !expanded;
			content.style.removeProperty('overflow');
			button.disabled = false;
		};
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

		animateState(button, content, button.getAttribute('aria-expanded') !== 'true');
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeAll);
	} else {
		initializeAll();
	}
}());
