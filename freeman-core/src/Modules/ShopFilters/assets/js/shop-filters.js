/**
 * Freeman Shop Filters — front-end controller (reload transport).
 *
 * On a debounced facet change it navigates to the filtered URL: the existing
 * non-filter query params (?s=, post_type, orderby, …) are preserved, the
 * filter_<taxonomy>=slug,slug params are rewritten, paged is reset, and the
 * page reloads. The server-side query bridge (Query.php) applies the selection
 * to the main product query, so the reloaded grid is genuinely filtered and the
 * selection persists in the URL. Active-filter chips are server-rendered; the
 * remove / clear-all controls just adjust the boxes and navigate.
 *
 * Vanilla, no jQuery. Infinite Scroll is untouched and works normally on the
 * reloaded page.
 */
(function () {
	'use strict';

	var FILTER_PREFIX = 'filter_';
	var DEBOUNCE_MS = 400;

	var panel = document.querySelector('[data-freeman-sf]');
	if (!panel) { return; }

	var facetsForm = panel.querySelector('[data-freeman-sf-facets]');
	var chipsEl = panel.querySelector('[data-freeman-sf-chips]');
	if (!facetsForm) { return; }

	function debounce(fn, ms) {
		var t;
		return function () {
			var args = arguments, self = this;
			clearTimeout(t);
			t = setTimeout(function () { fn.apply(self, args); }, ms);
		};
	}

	/** Current selection as { taxonomy: [slug, ...] }. */
	function readSelection() {
		var selection = {};
		var boxes = facetsForm.querySelectorAll('.freeman-sf__checkbox');
		for (var i = 0; i < boxes.length; i++) {
			var box = boxes[i];
			if (!box.checked) { continue; }
			var tax = box.getAttribute('data-freeman-sf-taxonomy');
			if (!tax) { continue; }
			(selection[tax] = selection[tax] || []).push(box.value);
		}
		return selection;
	}

	/** Filtered URL: keep non-filter params, rewrite filter_*, reset to page 1. */
	function buildUrl(selection) {
		var url = new URL(location.href);
		var stale = [];
		url.searchParams.forEach(function (value, key) {
			if (key.indexOf(FILTER_PREFIX) === 0 || key === 'paged') { stale.push(key); }
		});
		stale.forEach(function (key) { url.searchParams.delete(key); });

		// Reset pretty pagination (/page/N/) too — filtering can shrink the
		// result set below the current page, which would 404.
		url.pathname = url.pathname.replace(/\/page\/\d+\/?$/, '/');

		Object.keys(selection).forEach(function (tax) {
			var slugs = selection[tax];
			if (slugs && slugs.length) { url.searchParams.set(FILTER_PREFIX + tax, slugs.join(',')); }
		});
		return url.href;
	}

	function navigate() {
		panel.classList.add('freeman-sf--loading');
		location.assign(buildUrl(readSelection()));
	}

	var debouncedNavigate = debounce(navigate, DEBOUNCE_MS);

	// Delegated change handler.
	facetsForm.addEventListener('change', function (e) {
		if (e.target && e.target.classList && e.target.classList.contains('freeman-sf__checkbox')) {
			debouncedNavigate();
		}
	});

	// Chip remove + clear-all → uncheck then navigate immediately.
	if (chipsEl) {
		chipsEl.addEventListener('click', function (e) {
			var clear = e.target.closest && e.target.closest('[data-freeman-sf-clear]');
			if (clear) {
				facetsForm.querySelectorAll('.freeman-sf__checkbox:checked').forEach(function (b) { b.checked = false; });
				navigate();
				return;
			}
			var chip = e.target.closest && e.target.closest('.freeman-sf__chip');
			if (chip) {
				e.preventDefault();
				var tax = chip.getAttribute('data-freeman-sf-taxonomy');
				var slug = chip.getAttribute('data-freeman-sf-slug');
				var box = facetsForm.querySelector('.freeman-sf__checkbox[data-freeman-sf-taxonomy="' + tax + '"][value="' + slug + '"]');
				if (box) { box.checked = false; }
				navigate();
			}
		});
	}
})();
