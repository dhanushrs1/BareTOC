(function () {
	'use strict';

	var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');

	function updateControl(trigger, expanded) {
		var openLabel = trigger.getAttribute('data-open-label') || 'Open table of contents';
		var closeLabel = trigger.getAttribute('data-close-label') || 'Close table of contents';

		trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
		trigger.setAttribute('aria-label', expanded ? closeLabel : openLabel);
	}

	function setState(trigger, content, expanded) {
		updateControl(trigger, expanded);
		content.hidden = !expanded;
	}

	function animateState(trigger, content, expanded) {
		var startHeight;
		var endHeight;
		var animation;

		if (trigger.getAttribute('data-baretoc-smooth') !== 'yes' || typeof content.animate !== 'function' || (reducedMotion && reducedMotion.matches)) {
			setState(trigger, content, expanded);
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

		updateControl(trigger, expanded);
		trigger.setAttribute('aria-disabled', 'true');
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
			trigger.removeAttribute('aria-disabled');
		};
	}

	function contentFor(trigger) {
		var contentId = trigger.getAttribute('aria-controls');

		return contentId ? document.getElementById(contentId) : null;
	}

	function initialize(trigger) {
		var content = contentFor(trigger);

		if (!content) {
			return;
		}

		setState(trigger, content, trigger.getAttribute('data-baretoc-initial') !== 'closed');
	}

	function initializeAll() {
		document.querySelectorAll('.baretoc-toggle').forEach(initialize);
	}

	function activate(source) {
		var trigger;
		var content;

		if (!(source instanceof Element)) {
			return;
		}

		trigger = source.closest('.baretoc-toggle');

		if (!trigger || trigger.getAttribute('aria-disabled') === 'true') {
			return;
		}

		content = contentFor(trigger);

		if (!content) {
			return;
		}

		animateState(trigger, content, trigger.getAttribute('aria-expanded') !== 'true');
	}

	document.addEventListener('click', function (event) {
		activate(event.target);
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Enter' && event.key !== ' ') {
			return;
		}

		if (event.target instanceof Element && event.target.closest('.baretoc-toggle')) {
			event.preventDefault();
			activate(event.target);
		}
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeAll);
	} else {
		initializeAll();
	}
}());
