(function () {
	'use strict';

	var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');

	document.addEventListener('click', function (event) {
		var source = event.target;
		var link;
		var targetId;
		var target;

		if (!(source instanceof Element) || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
			return;
		}

		link = source.closest('.baretoc-link');

		if (!link || !link.hash || (reducedMotion && reducedMotion.matches)) {
			return;
		}

		if (link.origin !== window.location.origin || link.pathname !== window.location.pathname || link.search !== window.location.search) {
			return;
		}

		try {
			targetId = decodeURIComponent(link.hash.slice(1));
		} catch (error) {
			return;
		}

		target = document.getElementById(targetId);

		if (!target) {
			return;
		}

		event.preventDefault();
		target.scrollIntoView({ behavior: 'smooth', block: 'start' });

		if (window.history && window.history.pushState) {
			window.history.pushState(null, '', link.hash);
		} else {
			window.location.hash = link.hash;
		}
	});
}());
