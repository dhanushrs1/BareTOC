(function () {
	'use strict';

	var selector = '.baretoc-runtime[data-baretoc-config]';
	var runtimeId = 0;
	var toggleIcon = '<svg class="baretoc-toggle-icon" aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"></rect><rect x="40" y="40" width="176" height="176" rx="8" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></rect><line x1="88" y1="128" x2="168" y2="128" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></line><line class="baretoc-toggle-icon__vertical" x1="128" y1="88" x2="128" y2="168" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></line></svg>';

	function parseConfig(placeholder) {
		try {
			return JSON.parse(placeholder.getAttribute('data-baretoc-config') || '{}');
		} catch (error) {
			return null;
		}
	}

	function findScope(placeholder, config) {
		var scope = null;

		if (config.container) {
			try {
				scope = document.querySelector(config.container);
			} catch (error) {
				scope = null;
			}
		}

		return scope || placeholder.closest('article, main, [role="main"]') || document.querySelector('main, [role="main"], article') || document.body;
	}

	function slugify(value) {
		var normalized = value.toLowerCase();

		if (typeof normalized.normalize === 'function') {
			normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
		}

		normalized = normalized.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

		return normalized || 'section';
	}

	function uniqueId(base, usedIds) {
		var candidate = base;
		var suffix = 2;

		while (usedIds.has(candidate)) {
			candidate = base + '-' + suffix;
			suffix += 1;
		}

		usedIds.add(candidate);

		return candidate;
	}

	function cleanTitle(title) {
		var pattern = /^\s*(?:\(\s*(?:\d{1,3}|[A-Za-z]|[IVXLCDM]+)\s*\)|\d{1,3}(?:\.\d+)+(?:[.):])?|\d{1,3}(?:[.):]|\s*[-\u2013\u2014:])|(?:[IVXLCDM]+|[A-Za-z])(?:[.):]|\s*[-\u2013\u2014:]))(?:\s*[-\u2013\u2014:]\s*|\s+)/u;
		var cleaned = title.replace(pattern, '').trim();

		return cleaned || title;
	}

	function collectHeadings(scope, config) {
		var levels = Array.isArray(config.headings) ? config.headings : [2, 3, 4];
		var headingSelector = levels.map(function (level) {
			return 'h' + parseInt(level, 10);
		}).join(',');
		var usedIds = new Set();

		document.querySelectorAll('[id]').forEach(function (element) {
			if (element.id) {
				usedIds.add(element.id);
			}
		});

		if (!headingSelector) {
			return [];
		}

		return Array.prototype.slice.call(scope.querySelectorAll(headingSelector)).filter(function (heading) {
			return !heading.closest('.baretoc') && !heading.classList.contains('baretoc-ignore') && !heading.classList.contains('no-toc');
		}).map(function (heading) {
			var title = (heading.textContent || '').replace(/\s+/g, ' ').trim();
			var hasIdAttribute = heading.hasAttribute('id');

			if (!title || (hasIdAttribute && !heading.id)) {
				return null;
			}

			if (!heading.id) {
				if (!config.generateIds) {
					return null;
				}

				heading.id = uniqueId(slugify(title), usedIds);
			}

			return {
				level: parseInt(heading.tagName.slice(1), 10),
				id: heading.id,
				title: config.cleanNumbering ? cleanTitle(title) : title,
				children: []
			};
		}).filter(Boolean);
	}

	function buildTree(headings) {
		var roots = [];
		var stack = [];

		headings.forEach(function (heading) {
			while (stack.length && heading.level <= stack[stack.length - 1].level) {
				stack.pop();
			}

			if (stack.length) {
				stack[stack.length - 1].children.push(heading);
			} else {
				roots.push(heading);
			}

			stack.push(heading);
		});

		return roots;
	}

	function renderList(nodes, config, isRoot) {
		var list = document.createElement(config.listStyle === 'numbered' ? 'ol' : 'ul');

		list.className = (isRoot ? 'baretoc-list ' : 'baretoc-sublist ') + 'baretoc-list--' + config.listStyle;

		nodes.forEach(function (node) {
			var item = document.createElement('li');
			var link = document.createElement('a');

			item.className = 'baretoc-item baretoc-level-' + node.level;
			link.className = 'baretoc-link';
			link.href = '#' + encodeURIComponent(node.id);
			link.textContent = node.title;
			item.appendChild(link);

			if (node.children.length) {
				item.appendChild(renderList(node.children, config, false));
			}

			list.appendChild(item);
		});

		return list;
	}

	function appendSchema(nav, headings, config) {
		var baseUrl = window.location.href.split('#')[0];
		var schema = {
			'@context': 'https://schema.org',
			'@type': 'ItemList',
			name: config.title || config.ariaLabel || 'Table of contents',
			itemListOrder: 'https://schema.org/ItemListOrderAscending',
			numberOfItems: headings.length,
			itemListElement: headings.map(function (heading, index) {
				return {
					'@type': 'ListItem',
					position: index + 1,
					name: heading.title,
					url: baseUrl + '#' + encodeURIComponent(heading.id)
				};
			})
		};
		var script = document.createElement('script');

		script.className = 'baretoc-schema';
		script.type = 'application/ld+json';
		script.textContent = JSON.stringify(schema);
		nav.appendChild(script);
	}

	function appendTitle(parent, config) {
		var title;

		if (!config.title) {
			return;
		}

		title = document.createElement(['div', 'p', 'h2', 'h3'].indexOf(config.titleElement) !== -1 ? config.titleElement : 'div');
		title.className = 'baretoc-title';
		title.textContent = config.title;
		parent.appendChild(title);
	}

	function appendHeader(nav, config) {
		var header = document.createElement('div');

		header.className = 'baretoc-header';
		appendTitle(header, config);
		nav.appendChild(header);

		return header;
	}

	function appendCollapsibleContent(nav, list, config) {
		var header = appendHeader(nav, config);
		var content = document.createElement('div');
		var expanded = config.initiallyOpen !== false;

		runtimeId += 1;
		content.className = 'baretoc-content';
		content.id = 'baretoc-content-runtime-' + runtimeId;
		header.classList.add('baretoc-toggle');
		header.setAttribute('role', 'button');
		header.setAttribute('tabindex', '0');
		header.setAttribute('aria-controls', content.id);
		header.setAttribute('aria-expanded', expanded ? 'true' : 'false');
		header.setAttribute('aria-label', expanded ? config.closeLabel : config.openLabel);
		header.setAttribute('data-baretoc-initial', expanded ? 'open' : 'closed');
		header.setAttribute('data-baretoc-smooth', config.smoothToggle ? 'yes' : 'no');
		header.setAttribute('data-open-label', config.openLabel || 'Open table of contents');
		header.setAttribute('data-close-label', config.closeLabel || 'Close table of contents');
		header.insertAdjacentHTML('beforeend', toggleIcon);
		content.hidden = !expanded;
		content.appendChild(list);
		nav.appendChild(content);
	}

	function buildToc(placeholder, finalAttempt) {
		var config = parseConfig(placeholder);
		var headings;
		var nav;
		var list;

		if (!config) {
			placeholder.remove();
			return;
		}

		headings = collectHeadings(findScope(placeholder, config), config);

		if (headings.length < Math.max(1, parseInt(config.minimumHeadings, 10) || 3)) {
			if (finalAttempt) {
				placeholder.remove();
			}

			return;
		}

		nav = document.createElement('nav');
		nav.className = config.collapsible ? 'baretoc baretoc--collapsible' : 'baretoc';
		nav.setAttribute('aria-label', config.ariaLabel || 'Table of contents');
		list = renderList(buildTree(headings), config, true);

		if (config.collapsible) {
			appendCollapsibleContent(nav, list, config);
		} else {
			appendHeader(nav, config);
			nav.appendChild(list);
		}

		appendSchema(nav, headings, config);

		placeholder.replaceWith(nav);
	}

	function processPlaceholders(finalAttempt) {
		document.querySelectorAll(selector).forEach(function (placeholder) {
			buildToc(placeholder, finalAttempt);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			processPlaceholders(false);
		});
		window.addEventListener('load', function () {
			processPlaceholders(true);
		}, { once: true });
	} else {
		processPlaceholders(document.readyState === 'complete');

		if (document.readyState !== 'complete') {
			window.addEventListener('load', function () {
				processPlaceholders(true);
			}, { once: true });
		}
	}
}());
