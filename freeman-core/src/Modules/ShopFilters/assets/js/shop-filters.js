/**
 * Freeman Shop Filters — front-end controller (Phase 6.3a, checkbox facets).
 *
 * On a debounced facet change it does two things in parallel:
 *   1. POSTs the selection to the admin-AJAX endpoint and gets back the
 *      recomputed facets + count + the canonical filtered URL (JSON);
 *   2. GETs that same filtered front-end URL and swaps the WooCommerce product
 *      grid's contents with the filtered page-1 markup — reusing the page's own
 *      Elementor/Woo loop output so card markup stays identical (no re-render).
 *
 * It then pushes the filtered URL. The existing Infinite Scroll module is left
 * untouched: its MutationObserver sees the grid change and re-syncs onto the
 * filtered set (the swapped DOM carries the filtered pagination "next" link).
 *
 * Vanilla, no jQuery — mirrors infinite-scroll.js conventions.
 */
(function () {
	'use strict';

	var CFG = window.FreemanShopFilters || {};
	if (!CFG.ajaxUrl || !CFG.action || !CFG.nonce) { return; }

	var FILTER_PREFIX = 'filter_';
	var DEBOUNCE_MS = 250;

	// Grid container selectors — a trimmed mirror of Infinite Scroll's list so
	// we target the same element it manages (Elementor widgets + stock Woo).
	var CONTAINER_SELECTORS = [
		'.elementor-widget-woocommerce-products ul.products',
		'.elementor-widget-wc-archive-products ul.products',
		'.elementor-products-grid ul.products',
		'ul.products',
		'.products'
	];
	var PAGINATION_SELECTORS = [
		'nav.woocommerce-pagination',
		'.woocommerce-pagination',
		'.elementor-pagination',
		'nav.elementor-pagination',
		'ul.page-numbers'
	];

	var panel = document.querySelector('[data-freeman-sf]');
	if (!panel) { return; }

	var facetsForm = panel.querySelector('[data-freeman-sf-facets]');
	var countEl = panel.querySelector('[data-freeman-sf-count]');
	var chipsEl = panel.querySelector('[data-freeman-sf-chips]');

	var abortController = null;

	function debounce(fn, ms) {
		var t;
		return function () {
			var args = arguments, self = this;
			clearTimeout(t);
			t = setTimeout(function () { fn.apply(self, args); }, ms);
		};
	}

	function firstMatch(root, selectors) {
		for (var i = 0; i < selectors.length; i++) {
			var el = root.querySelector(selectors[i]);
			if (el) { return el; }
		}
		return null;
	}

	/** Collect the current selection as { taxonomy: [slug, ...] }. */
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

	/** Build the filter query params (filter_<tax>=csv) for a selection. */
	function selectionParams(selection) {
		var params = new URLSearchParams();
		Object.keys(selection).forEach(function (tax) {
			var slugs = selection[tax];
			if (slugs && slugs.length) { params.set(FILTER_PREFIX + tax, slugs.join(',')); }
		});
		return params;
	}

	/** Filtered front-end URL = current path + filter params (page reset). */
	function buildFrontendUrl(selection) {
		var url = new URL(location.href);
		url.search = '';
		var params = selectionParams(selection);
		params.forEach(function (value, key) { url.searchParams.set(key, value); });
		return url.href;
	}

	function fetchJson(selection) {
		var body = selectionParams(selection);
		body.set('action', CFG.action);
		body.set('_ajax_nonce', CFG.nonce);
		body.set('context_id', String(CFG.contextId || 0));
		return fetch(CFG.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
			signal: abortController ? abortController.signal : undefined
		}).then(function (r) { return r.json(); });
	}

	function fetchGrid(frontendUrl) {
		return fetch(frontendUrl, {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			signal: abortController ? abortController.signal : undefined
		}).then(function (r) {
			if (!r.ok) { throw new Error('HTTP ' + r.status); }
			return r.text();
		});
	}

	/** Replace the live grid (and pagination) with the filtered markup. */
	function swapGrid(html) {
		var doc = new DOMParser().parseFromString(html, 'text/html');
		var liveGrid = firstMatch(document, CONTAINER_SELECTORS);
		var newGrid = firstMatch(doc, CONTAINER_SELECTORS);
		if (!liveGrid || !newGrid) { return; }

		// Swap product cards. Keeping the same container node lets Infinite
		// Scroll's MutationObserver re-sync on the filtered set in place.
		liveGrid.innerHTML = newGrid.innerHTML;

		// Bring the filtered pagination across so the "next" link Infinite
		// Scroll reads reflects the filtered set.
		var livePager = firstMatch(document, PAGINATION_SELECTORS);
		var newPager = firstMatch(doc, PAGINATION_SELECTORS);
		if (livePager && newPager) {
			livePager.innerHTML = newPager.innerHTML;
		} else if (!livePager && newPager) {
			liveGrid.parentNode.insertBefore(newPager.cloneNode(true), liveGrid.nextSibling);
		} else if (livePager && !newPager) {
			livePager.innerHTML = '';
		}
	}

	function renderCount(count) {
		if (!countEl) { return; }
		var n = parseInt(count, 10) || 0;
		countEl.textContent = n + (n === 1 ? ' product' : ' products');
	}

	/** Rebuild the facet checkboxes from the AJAX response. */
	function renderFacets(facets) {
		if (!Array.isArray(facets)) { return; }
		var frag = document.createDocumentFragment();
		facets.forEach(function (facet) {
			if (!facet || !facet.taxonomy || !Array.isArray(facet.terms) || !facet.terms.length) { return; }
			var fs = document.createElement('fieldset');
			fs.className = 'freeman-sf__facet';
			fs.setAttribute('data-freeman-sf-facet', facet.taxonomy);

			var legend = document.createElement('legend');
			legend.className = 'freeman-sf__facet-title';
			legend.textContent = facet.label || facet.taxonomy;
			fs.appendChild(legend);

			var ul = document.createElement('ul');
			ul.className = 'freeman-sf__terms';
			facet.terms.forEach(function (term) {
				var li = document.createElement('li');
				li.className = 'freeman-sf__term';
				var label = document.createElement('label');

				var input = document.createElement('input');
				input.type = 'checkbox';
				input.className = 'freeman-sf__checkbox';
				input.setAttribute('data-freeman-sf-taxonomy', facet.taxonomy);
				input.value = term.slug;
				input.checked = !!term.selected;

				var name = document.createElement('span');
				name.className = 'freeman-sf__term-label';
				name.textContent = term.label || term.slug;

				var cnt = document.createElement('span');
				cnt.className = 'freeman-sf__term-count';
				cnt.textContent = '(' + (parseInt(term.count, 10) || 0) + ')';

				label.appendChild(input);
				label.appendChild(name);
				label.appendChild(cnt);
				li.appendChild(label);
				ul.appendChild(li);
			});
			fs.appendChild(ul);
			frag.appendChild(fs);
		});
		facetsForm.innerHTML = '';
		facetsForm.appendChild(frag);
	}

	/** Active-filter chips with remove buttons + a clear-all. */
	function renderChips(facets) {
		if (!chipsEl) { return; }
		chipsEl.innerHTML = '';
		var any = false;
		(facets || []).forEach(function (facet) {
			(facet.terms || []).forEach(function (term) {
				if (!term.selected) { return; }
				any = true;
				var chip = document.createElement('button');
				chip.type = 'button';
				chip.className = 'freeman-sf__chip';
				chip.setAttribute('data-freeman-sf-taxonomy', facet.taxonomy);
				chip.setAttribute('data-freeman-sf-slug', term.slug);
				chip.textContent = (term.label || term.slug) + ' ✕';
				chipsEl.appendChild(chip);
			});
		});
		if (any) {
			var clear = document.createElement('button');
			clear.type = 'button';
			clear.className = 'freeman-sf__clear';
			clear.setAttribute('data-freeman-sf-clear', '');
			clear.textContent = 'Clear all';
			chipsEl.appendChild(clear);
		}
	}

	function apply(pushUrl) {
		if (abortController) { try { abortController.abort(); } catch (e) {} }
		abortController = ('AbortController' in window) ? new AbortController() : null;

		var selection = readSelection();
		var frontendUrl = buildFrontendUrl(selection);
		panel.classList.add('freeman-sf--loading');

		Promise.all([fetchJson(selection), fetchGrid(frontendUrl)])
			.then(function (results) {
				var json = results[0];
				var gridHtml = results[1];
				if (!json || !json.success || !json.data) { return; }
				var data = json.data;

				swapGrid(gridHtml);
				renderCount(data.count);
				renderFacets(data.facets);
				renderChips(data.facets);

				if (pushUrl !== false) {
					var target = data.url || frontendUrl;
					window.history.pushState({ freemanShopFilters: true }, '', target);
				}
			})
			.catch(function (err) {
				if (err && err.name === 'AbortError') { return; }
				if (window.console) { console.error('[Freeman SF]', err); }
			})
			.then(function () { panel.classList.remove('freeman-sf--loading'); });
	}

	var debouncedApply = debounce(function () { apply(true); }, DEBOUNCE_MS);

	// Delegated change handler — survives facet re-renders.
	facetsForm.addEventListener('change', function (e) {
		if (e.target && e.target.classList && e.target.classList.contains('freeman-sf__checkbox')) {
			debouncedApply();
		}
	});

	// Chip remove + clear-all.
	if (chipsEl) {
		chipsEl.addEventListener('click', function (e) {
			var clear = e.target.closest && e.target.closest('[data-freeman-sf-clear]');
			if (clear) {
				facetsForm.querySelectorAll('.freeman-sf__checkbox:checked').forEach(function (b) { b.checked = false; });
				apply(true);
				return;
			}
			var chip = e.target.closest && e.target.closest('.freeman-sf__chip');
			if (chip) {
				var tax = chip.getAttribute('data-freeman-sf-taxonomy');
				var slug = chip.getAttribute('data-freeman-sf-slug');
				var box = facetsForm.querySelector('.freeman-sf__checkbox[data-freeman-sf-taxonomy="' + tax + '"][value="' + slug + '"]');
				if (box) { box.checked = false; }
				apply(true);
			}
		});
	}

	// Back/forward: re-read the URL selection and re-apply without re-pushing.
	window.addEventListener('popstate', function () {
		var url = new URL(location.href);
		var boxes = facetsForm.querySelectorAll('.freeman-sf__checkbox');
		var wanted = {};
		url.searchParams.forEach(function (value, key) {
			if (key.indexOf(FILTER_PREFIX) !== 0) { return; }
			var tax = key.slice(FILTER_PREFIX.length);
			value.split(',').forEach(function (slug) { wanted[tax + '|' + slug] = true; });
		});
		for (var i = 0; i < boxes.length; i++) {
			var tax2 = boxes[i].getAttribute('data-freeman-sf-taxonomy');
			boxes[i].checked = !!wanted[tax2 + '|' + boxes[i].value];
		}
		apply(false);
	});
})();
