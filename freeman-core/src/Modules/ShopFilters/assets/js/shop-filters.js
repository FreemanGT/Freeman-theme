/**
 * Freeman Shop Filters — front-end controller (reload transport + mobile drawer).
 *
 * On desktop, a debounced facet change navigates to the filtered URL (existing
 * non-filter params preserved, filter_<taxonomy>=slug,slug rewritten, paged
 * reset). On mobile the same panel becomes an off-canvas drawer: ticking defers
 * — the selection is collected and only "Apply" navigates once, the standard
 * drawer pattern that avoids a reload per tick. The server-side query bridge
 * (Query.php) applies the selection to the main product query, so the reloaded
 * grid is genuinely filtered and the selection persists in the URL. Active-filter
 * chips are server-rendered; category-tree links are plain navigation.
 *
 * Vanilla, no jQuery. Infinite Scroll is untouched and works on the reloaded page.
 */
(function () {
	'use strict';

	var FILTER_PREFIX = 'filter_';
	var DEBOUNCE_MS = 400;
	var MOBILE_QUERY = '(max-width: 768px)';

	var panel = document.querySelector('[data-freeman-sf]');
	if (!panel) { return; }

	var facetsForm = panel.querySelector('[data-freeman-sf-facets]');
	if (!facetsForm) { return; }

	var chipsEl = panel.querySelector('[data-freeman-sf-chips]');
	var drawer = panel.querySelector('[data-freeman-sf-panel]');
	var toggle = panel.querySelector('[data-freeman-sf-toggle]');
	var overlay = panel.querySelector('[data-freeman-sf-overlay]');
	var closeBtn = panel.querySelector('[data-freeman-sf-close]');
	var applyBtn = panel.querySelector('[data-freeman-sf-apply]');
	var clearMobileBtn = panel.querySelector('[data-freeman-sf-clear-mobile]');
	var mq = window.matchMedia ? window.matchMedia(MOBILE_QUERY) : { matches: false };

	function isMobile() { return !!mq.matches; }

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

	/* ---- mobile drawer: open / close + focus trap ---- */

	var lastFocus = null;

	function focusableIn(el) {
		var nodes = el.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),[tabindex]:not([tabindex="-1"])');
		return Array.prototype.slice.call(nodes).filter(function (n) { return n.offsetParent !== null; });
	}

	function openDrawer() {
		if (!drawer) { return; }
		lastFocus = document.activeElement;
		panel.classList.add('freeman-sf--open');
		if (toggle) { toggle.setAttribute('aria-expanded', 'true'); }
		document.addEventListener('keydown', onKeydown);
		var f = focusableIn(drawer);
		if (f.length) { f[0].focus(); }
	}

	function closeDrawer() {
		panel.classList.remove('freeman-sf--open');
		if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
		document.removeEventListener('keydown', onKeydown);
		if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
	}

	function onKeydown(e) {
		if (e.key === 'Escape' || e.key === 'Esc') { closeDrawer(); return; }
		if (e.key !== 'Tab' || !drawer) { return; }
		var f = focusableIn(drawer);
		if (!f.length) { return; }
		var first = f[0], last = f[f.length - 1];
		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	if (toggle) { toggle.addEventListener('click', openDrawer); }
	if (closeBtn) { closeBtn.addEventListener('click', closeDrawer); }
	if (overlay) { overlay.addEventListener('click', closeDrawer); }
	if (applyBtn) { applyBtn.addEventListener('click', navigate); }
	if (clearMobileBtn) {
		clearMobileBtn.addEventListener('click', function () {
			facetsForm.querySelectorAll('.freeman-sf__checkbox:checked').forEach(function (b) { b.checked = false; });
			navigate();
		});
	}

	/* ---- facet change: desktop auto-navigates; mobile defers to Apply ---- */
	facetsForm.addEventListener('change', function (e) {
		if (e.target && e.target.classList && e.target.classList.contains('freeman-sf__checkbox')) {
			if (isMobile()) { return; }
			debouncedNavigate();
		}
	});

	/* ---- chip remove + clear-all → uncheck then navigate immediately ---- */
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
