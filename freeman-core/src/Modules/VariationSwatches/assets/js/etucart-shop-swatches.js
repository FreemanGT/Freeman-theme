/**
 * Etucart Variation Swatches — shop / archive compact picker (1.6.0).
 *
 * Vanilla JS (no jQuery) — a single document-level event delegate handles
 * every picker on the page, so 24 variable products in a grid cost us one
 * event listener per event type, not 24.
 *
 * Responsibilities:
 *   - Swatch click → update selected value, refresh availability on every
 *     OTHER attribute in the same picker (grey out impossible combos).
 *   - "+N" reveal → show the hidden overflow options; clicking again collapses.
 *   - When every attribute has a selection, resolve the matching variation
 *     from the embedded JSON, enable Add-to-cart, and update the displayed
 *     "החל מ:" price to the selected variation's price_html.
 *   - Add-to-cart → POST to wc-ajax=etucart_shop_add_to_cart with nonce.
 *     On success: show "נוסף לעגלה ✓" for 1.5s, fire `added_to_cart` on
 *     document.body so WC-compatible cart drawers refresh fragments.
 *   - Works on fragments-refreshed / AJAX-reloaded archives — we delegate to
 *     document so new pickers are picked up automatically with no re-bind.
 */
(function () {
	'use strict';

	var i18n = (window.EtucartShopVS && window.EtucartShopVS.i18n) || {};
	var AJAX_URL = (window.EtucartShopVS && window.EtucartShopVS.ajaxUrl) || '';
	var CART_URL = (window.EtucartShopVS && window.EtucartShopVS.cartUrl) || '';

	/* ------------------------------------------------------------------ *
	 * Small helpers
	 * ------------------------------------------------------------------ */

	function qs(sel, root)  { return (root || document).querySelector(sel); }
	function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

	function closest(el, sel) {
		while (el && el !== document) {
			if (el.matches && el.matches(sel)) return el;
			el = el.parentNode;
		}
		return null;
	}

	function getPickerData(picker) {
		if (!picker._etucartData) {
			try {
				var raw = picker.getAttribute('data-variations');
				picker._etucartData = raw ? JSON.parse(raw) : [];
			} catch (e) {
				picker._etucartData = [];
			}
		}
		return picker._etucartData;
	}

	function getSelections(picker) {
		var out = {};
		qsa('.etucart-shop-pick__attr', picker).forEach(function (row) {
			var name = row.getAttribute('data-attribute-name') || '';
			if (!name) return;
			var sel = qs('.etucart-shop-pick__opt.is-selected', row);
			out[name] = sel ? (sel.getAttribute('data-value') || '') : '';
		});
		return out;
	}

	/* ------------------------------------------------------------------ *
	 * Quick View bridge (1.6.6)
	 *
	 * Carry the archive-card attribute selection into the single-product buy
	 * box when a Quick View modal (WPC Quick View, WooSQ, YITH Quick View,
	 * etc.) opens that product. We store the current selection in
	 * sessionStorage keyed by product ID with a short TTL; the PDP JS reads
	 * it on form init and applies it to the hidden selects, then deletes the
	 * key so it only fires once.
	 *
	 * Why sessionStorage (not postMessage / a global): Quick View plugins
	 * often inject the product via AJAX into a detached DOM then mount it,
	 * which makes "reach into another frame" signalling brittle. A
	 * timestamp-gated sessionStorage entry is simple, survives across
	 * injection timings, and is scoped to the tab.
	 *
	 * TTL is deliberately short — 60 seconds is long enough for the user to
	 * click Quick View after picking a size, but short enough that picking a
	 * size and then navigating to the real PDP 5 minutes later doesn't
	 * silently pre-select for them.
	 * ------------------------------------------------------------------ */

	var QV_PRESELECT_PREFIX = 'etucart_qv_preselect_';
	var QV_PRESELECT_TTL_MS = 60 * 1000;

	function persistPreselectForQuickView(picker) {
		var pid = picker.getAttribute('data-product-id') || '';
		if (!pid) return;
		var key = QV_PRESELECT_PREFIX + pid;

		var selections = getSelections(picker);
		// Drop empty axes so the PDP side can check "do we have anything to apply?" cheaply.
		var clean = {};
		var hasAny = false;
		for (var k in selections) {
			if (selections.hasOwnProperty(k) && selections[k] !== '') {
				clean[k] = selections[k];
				hasAny = true;
			}
		}

		try {
			if (!hasAny) {
				window.sessionStorage.removeItem(key);
				return;
			}
			window.sessionStorage.setItem(key, JSON.stringify({
				attrs: clean,
				ts:    Date.now()
			}));
		} catch (e) {
			// sessionStorage may be disabled (private mode in older browsers,
			// cookie/storage blocked, quota exceeded). Silently skip — the
			// feature is a nice-to-have, it shouldn't break the picker.
		}
	}

	/* ------------------------------------------------------------------ *
	 * Variation matching
	 * ------------------------------------------------------------------ */

	/**
	 * For each option of `targetAttr`, decide its state given the current
	 * selections on OTHER attributes:
	 *   - 'unavailable' : no variation matches at all.
	 *   - 'out_of_stock': matching variation(s) exist but all are OOS / not purchasable.
	 *   - 'ok'          : at least one matching variation is purchasable.
	 */
	function computeStates(picker, targetAttr) {
		var variations = getPickerData(picker);
		var current    = getSelections(picker);
		var targetRow  = qs('[data-attribute-name="' + cssEscape(targetAttr) + '"]', picker);
		if (!targetRow) return {};

		var result = {};
		qsa('.etucart-shop-pick__opt', targetRow).forEach(function (btn) {
			var candidate          = String(btn.getAttribute('data-value') || '');
			var hasAny             = false;
			var hasPurchasable     = false;

			for (var i = 0; i < variations.length; i++) {
				var v = variations[i];
				if (!v || !v.attrs) continue;

				var vTarget = v.attrs[targetAttr];
				if (typeof vTarget === 'undefined') continue;
				if (vTarget !== '' && vTarget !== candidate) continue;

				var matches = true;
				for (var key in current) {
					if (!current.hasOwnProperty(key)) continue;
					if (key === targetAttr) continue;
					var sel = current[key];
					if (sel === '') continue;
					var vv = v.attrs[key];
					if (typeof vv === 'undefined') continue;
					if (vv !== '' && vv !== sel) { matches = false; break; }
				}
				if (!matches) continue;

				hasAny = true;
				if (v.is_purchasable !== false && v.in_stock !== false) {
					hasPurchasable = true;
					break;
				}
			}

			if (!hasAny)             result[candidate] = 'unavailable';
			else if (!hasPurchasable) result[candidate] = 'out_of_stock';
			else                      result[candidate] = 'ok';
		});

		return result;
	}

	/**
	 * Once every attribute has a selection, find the variation that matches.
	 * Returns null if no unique variation resolves.
	 */
	function findMatchingVariation(picker) {
		var current    = getSelections(picker);
		var variations = getPickerData(picker);

		// Bail if any axis is still empty.
		for (var k in current) {
			if (current.hasOwnProperty(k) && current[k] === '') return null;
		}

		for (var i = 0; i < variations.length; i++) {
			var v = variations[i];
			if (!v || !v.attrs) continue;
			var ok = true;
			for (var key in current) {
				if (!current.hasOwnProperty(key)) continue;
				var sel = current[key];
				var vv  = v.attrs[key];
				if (typeof vv === 'undefined') { ok = false; break; }
				if (vv !== '' && vv !== sel)   { ok = false; break; }
			}
			if (ok) return v;
		}
		return null;
	}

	/* ------------------------------------------------------------------ *
	 * UI syncing
	 * ------------------------------------------------------------------ */

	function refreshAvailability(picker) {
		qsa('.etucart-shop-pick__attr', picker).forEach(function (row) {
			var attrName = row.getAttribute('data-attribute-name') || '';
			var states   = computeStates(picker, attrName);

			qsa('.etucart-shop-pick__opt', row).forEach(function (btn) {
				var val = String(btn.getAttribute('data-value') || '');
				var st  = states[val] || 'ok';
				btn.classList.toggle('is-unavailable', st === 'unavailable');
				btn.classList.toggle('is-out-of-stock', st === 'out_of_stock');
				if (st === 'unavailable' || st === 'out_of_stock') {
					btn.setAttribute('aria-disabled', 'true');
					btn.setAttribute('title', st === 'out_of_stock'
						? (i18n.oos || 'Out of stock')
						: (i18n.unavailable || 'Unavailable')
					);
				} else {
					btn.removeAttribute('aria-disabled');
					btn.removeAttribute('title');
				}
			});
		});
	}

	function refreshHeadLabels(picker) {
		qsa('.etucart-shop-pick__attr', picker).forEach(function (row) {
			var selBtn   = qs('.etucart-shop-pick__opt.is-selected', row);
			var headSpan = qs('.etucart-shop-pick__attr-selected', row);
			if (!headSpan) return;
			headSpan.textContent = selBtn ? (selBtn.getAttribute('data-name') || '') : '';
		});
	}

	function refreshAddState(picker) {
		var btn = qs('.etucart-shop-pick__add', picker);
		if (!btn) return;

		var variation = findMatchingVariation(picker);

		// Reset any transient state classes.
		btn.classList.remove('is-success', 'is-error');
		btn.textContent = i18n.addToCart || 'Add to cart';

		if (!variation) {
			btn.disabled = true;
			btn.setAttribute('aria-disabled', 'true');
			btn.removeAttribute('data-variation-id');
			return;
		}

		if (variation.is_purchasable === false || variation.in_stock === false) {
			btn.disabled = true;
			btn.setAttribute('aria-disabled', 'true');
			btn.textContent = i18n.oos || 'Out of stock';
			btn.removeAttribute('data-variation-id');
			return;
		}

		btn.disabled = false;
		btn.removeAttribute('aria-disabled');
		btn.setAttribute('data-variation-id', String(variation.id || 0));
	}

	function refreshPrice(picker) {
		var priceEl  = qs('.etucart-shop-pick__price-value', picker);
		var priceRow = qs('.etucart-shop-pick__price', picker);
		var prefix   = qs('.etucart-shop-pick__price-prefix', picker);
		if (!priceEl) return;

		// Record the product-level "this product has a range" flag once.
		// data-has-range is set by PHP based on min vs. max variation price.
		// We keep the prefix visible ONLY while (a) that flag is true AND
		// (b) the user has not yet resolved a single variation.
		var hasRange = priceRow && priceRow.getAttribute('data-has-range') === '1';

		var variation = findMatchingVariation(picker);
		if (variation && variation.price_html) {
			priceEl.innerHTML = variation.price_html;
			if (prefix) prefix.hidden = true;
			if (priceRow) priceRow.classList.remove('has-range');
		} else {
			if (!picker._etucartOriginalPrice) {
				// Stash the server-rendered "from" price so we can restore it
				// when the user changes a selection back to an unresolved state.
				picker._etucartOriginalPrice = priceEl.innerHTML;
			} else {
				priceEl.innerHTML = picker._etucartOriginalPrice;
			}
			// Restore the prefix only if the PRODUCT had a range to begin with;
			// for flat-priced products we never show "from:" at all.
			if (prefix) prefix.hidden = !hasRange;
			if (priceRow) priceRow.classList.toggle('has-range', !!hasRange);
		}
	}

	function refreshAll(picker) {
		// Simple-product pickers have no attributes / variations — the
		// button is already in its final enabled/OOS state from the PHP
		// template, the price is the server-rendered price_html, and
		// there are no swatches to grey out. Running the variable-flavour
		// refresh would clobber the enabled button back to "disabled"
		// because findMatchingVariation() returns null for simple pickers.
		if (picker.classList && picker.classList.contains('etucart-shop-pick--simple')) {
			return;
		}
		refreshAvailability(picker);
		refreshHeadLabels(picker);
		refreshPrice(picker);
		refreshAddState(picker);
		refreshOverflow(picker);
	}

	/* ------------------------------------------------------------------ *
	 * Single-line overflow scanner.
	 *
	 * The PHP template caps options at `max_visible` (default 5), but on
	 * narrow cards even that can wrap to two lines. We force a single
	 * line via CSS (`flex-wrap: nowrap`) and walk the chips here to mark
	 * everything that wouldn't fit as `is-overflow`, hiding it and
	 * routing it through the existing `+N` reveal button. Runs on init
	 * and after each picker mutation (resize, fragment refresh).
	 * ------------------------------------------------------------------ */
	function refreshOverflow(picker) {
		qsa('.etucart-shop-pick__attr', picker).forEach(function (row) {
			var opts = qs('.etucart-shop-pick__opts', row);
			if (!opts) return;

			var moreBtn = qs('.etucart-shop-pick__more', opts);
			var rawChips = qsa('.etucart-shop-pick__opt', opts);
			if (!rawChips.length) return;

			// If the +N is currently expanded, leave the layout alone — the
			// user explicitly opened the overflow drawer and we don't want
			// to fight that by re-hiding chips on a resize tick.
			if (moreBtn && moreBtn.getAttribute('aria-expanded') === 'true') {
				return;
			}

			// In-stock-only filter: `refreshAvailability()` ran just before
			// this and set `.is-unavailable` (no matching variation) and
			// `.is-out-of-stock` (matching variation but sold out) on the
			// chips. The +N badge should ONLY count IN-STOCK overflow:
			// pointing the customer at "+1 more" that turns out to be a
			// sold-out swatch is worse than no badge at all. Likewise for
			// "unavailable" combos (e.g., no XL in red). PHP's
			// OPT_SHOP_HIDE_OOS already prunes whole attribute values whose
			// every matching variation is OOS, but cross-attribute states
			// (e.g., the SIZE row when the user has picked a color that
			// only stocks two sizes) are computed live by the JS — so the
			// filter has to live here too.
			var allChips = rawChips.filter(function (c) {
				return !c.classList.contains('is-unavailable') &&
					   !c.classList.contains('is-out-of-stock');
			});

			// Reset every chip first (including OOS ones) so the
			// measurement starts from a clean state and any chip that
			// was previously marked overflow can re-flow naturally.
			rawChips.forEach(function (c) {
				c.hidden = false;
				c.classList.remove('is-overflow');
			});

			// No in-stock chips → nothing to overflow, hide the +N
			// completely. (User-facing rule: "if there's nothing to show,
			// hide the icon completely.")
			if (!allChips.length) {
				if (moreBtn) {
					moreBtn.hidden = true;
				}
				return;
			}

			// Briefly un-hide the +N to read its width, but always restore
			// to `hidden` before bailing — the final visible state is set
			// at the bottom of this function based on hiddenCount, so any
			// early return must leave the button hidden, not "+0" visible.
			if (moreBtn) {
				moreBtn.hidden = false;
			}

			var available = opts.clientWidth;
			if (available <= 0) {
				// Element not laid out yet (e.g. inside a hidden tab) —
				// retry once on next frame. Re-hide the button first so
				// it doesn't leak "+N" while we wait.
				if (moreBtn) {
					moreBtn.hidden = true;
				}
				if (typeof requestAnimationFrame === 'function') {
					requestAnimationFrame(function () { refreshOverflow(picker); });
				}
				return;
			}

			var gapStr = window.getComputedStyle(opts).columnGap || window.getComputedStyle(opts).gap || '0';
			var gap    = parseFloat(gapStr) || 0;
			var moreW  = moreBtn ? (moreBtn.offsetWidth + gap) : 0;
			var maxVisible = parseInt(row.getAttribute('data-max-visible'), 10) || allChips.length;

			// Width-fit measurement, in-stock chips only. OOS chips still
			// occupy space in the visual row (they render greyed-out) so
			// we account for their width but never assign them overflow.
			var used         = 0;
			var fitOverflow  = -1;
			for (var i = 0; i < allChips.length; i++) {
				var w = allChips[i].offsetWidth + (i === 0 ? 0 : gap);
				var needsMore = (i < allChips.length - 1);
				var budget    = needsMore ? (available - moreW) : available;
				if (used + w > budget) {
					fitOverflow = i;
					break;
				}
				used += w;
			}

			var capOverflow = allChips.length > maxVisible ? maxVisible : -1;
			var overflowIdx = fitOverflow;
			if (capOverflow !== -1) {
				overflowIdx = overflowIdx === -1 ? capOverflow : Math.min(overflowIdx, capOverflow);
			}

			if (overflowIdx === -1) {
				// All in-stock chips fit on one line — drop the +N entirely.
				if (moreBtn) {
					moreBtn.hidden = true;
				}
				return;
			}

			// Mark in-stock chips beyond overflowIdx as overflow.
			var hiddenCount = 0;
			for (var j = overflowIdx; j < allChips.length; j++) {
				allChips[j].hidden = true;
				allChips[j].classList.add('is-overflow');
				hiddenCount++;
			}

			// Belt-and-braces: if for any reason the loop above produced
			// zero overflow chips (e.g., pathological measurement), keep
			// the badge hidden rather than flashing "+0".
			if (hiddenCount === 0) {
				if (moreBtn) {
					moreBtn.hidden = true;
				}
				return;
			}

			if (moreBtn) {
				moreBtn.hidden = false;
				moreBtn.setAttribute('data-count', String(hiddenCount));
				moreBtn.setAttribute('aria-expanded', 'false');
				var lbl = qs('.etucart-shop-pick__more-label', moreBtn);
				if (lbl) {
					lbl.textContent = '+' + hiddenCount;
				}
			}
		});
	}

	/* ------------------------------------------------------------------ *
	 * cssEscape polyfill (data-attribute-name may contain [])
	 * ------------------------------------------------------------------ */
	function cssEscape(str) {
		if (typeof window.CSS !== 'undefined' && typeof window.CSS.escape === 'function') {
			return window.CSS.escape(str);
		}
		return String(str).replace(/([^\w-])/g, '\\$1');
	}

	/* ------------------------------------------------------------------ *
	 * Toast notifications (1.6.4, restyled 1.6.5)
	 *
	 * WooCommerce returns notices like "You cannot add another X to your
	 * cart" when stock is maxed or validation fails. The previous behaviour
	 * dumped that text into an in-card <div> AND into the Add-to-cart
	 * button, which blew the card out of the grid and pushed neighbours
	 * around. We now render every add-to-cart message into a singleton
	 * fixed-position stack appended to document.body, so card geometry is
	 * never affected regardless of message length.
	 *
	 * Visual style: matches the PDP `.etucart-toast` (dark pill, bottom-left,
	 * green circular check on success, red circular "!" on error, slide-up
	 * from below). Visibility is driven by the `is-visible` class so the CSS
	 * `transition` gets a render tick to pick up the "from" state — we add
	 * the element first, then toggle `is-visible` on the next frame.
	 *
	 * Scope: shop picker only. The single-product buy box continues to use
	 * WC's own notice flow.
	 * ------------------------------------------------------------------ */

	var _toastStack   = null;
	var _toastTimers  = null; // WeakMap ( toast -> timeoutId ) so dismiss can cancel pending auto-close.

	function ensureToastStack() {
		if (_toastStack && document.body && document.body.contains(_toastStack)) {
			return _toastStack;
		}
		if (!document.body) return null;
		_toastStack = document.createElement('div');
		_toastStack.className = 'etucart-shop-toast-stack';
		_toastStack.setAttribute('role', 'region');
		_toastStack.setAttribute('aria-label', i18n.notices || 'Shop notices');
		document.body.appendChild(_toastStack);
		if (typeof window.WeakMap === 'function') {
			_toastTimers = new WeakMap();
		}
		return _toastStack;
	}

	/**
	 * Render a toast that visually matches the PDP toast.
	 *
	 * @param {string} text  Plain text shown to the user. HTML is escaped.
	 * @param {string} type  'error' | 'success' | 'info' (default 'info').
	 * @param {number} [ttl] Optional override in ms. Default: 4500 for
	 *                       errors, 2600 otherwise.
	 */
	function showToast(text, type, ttl) {
		if (!text) return;
		var stack = ensureToastStack();
		if (!stack) return;

		type = type || 'info';
		if (typeof ttl !== 'number') {
			ttl = (type === 'error') ? 4500 : 2600;
		}

		var toast = document.createElement('div');
		toast.className = 'etucart-shop-toast etucart-shop-toast--' + type;
		toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
		toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
		toast.setAttribute('aria-atomic', 'true');

		// Icon (matches PDP visual language):
		//   - success → green circle with white checkmark (CSS draws it via ::before)
		//   - error   → red circle with white "!"
		//   - info    → no icon
		if (type === 'success') {
			var check = document.createElement('span');
			check.className = 'etucart-shop-toast__check';
			check.setAttribute('aria-hidden', 'true');
			toast.appendChild(check);
		} else if (type === 'error') {
			var bang = document.createElement('span');
			bang.className = 'etucart-shop-toast__bang';
			bang.setAttribute('aria-hidden', 'true');
			bang.textContent = '!';
			toast.appendChild(bang);
		}

		var textEl = document.createElement('span');
		textEl.className = 'etucart-shop-toast__text';
		textEl.textContent = String(text); // textContent escapes — never innerHTML user messages.
		toast.appendChild(textEl);

		var closeBtn = document.createElement('button');
		closeBtn.type = 'button';
		closeBtn.className = 'etucart-shop-toast__close';
		closeBtn.setAttribute('aria-label', i18n.close || 'Close');
		closeBtn.textContent = '\u00d7'; // ×
		closeBtn.addEventListener('click', function () { dismissToast(toast); });
		toast.appendChild(closeBtn);

		stack.appendChild(toast);

		// Force a reflow so the browser registers the initial transform: translateY(140%)
		// state before we toggle `is-visible`, otherwise the toast pops in without the
		// slide-up transition.
		// eslint-disable-next-line no-unused-expressions
		toast.offsetWidth;

		// Add `is-visible` on the next frame so the CSS `transition` animates in.
		if (typeof window.requestAnimationFrame === 'function') {
			window.requestAnimationFrame(function () {
				toast.classList.add('is-visible');
			});
		} else {
			toast.classList.add('is-visible');
		}

		if (ttl > 0) {
			var tid = setTimeout(function () { dismissToast(toast); }, ttl);
			if (_toastTimers) { _toastTimers.set(toast, tid); }
			else { toast._etucartTimer = tid; }
		}

		return toast;
	}

	function dismissToast(toast) {
		if (!toast || !toast.parentNode) return;
		// Cancel any pending auto-dismiss so we don't double-animate.
		if (_toastTimers && _toastTimers.has(toast)) {
			clearTimeout(_toastTimers.get(toast));
			_toastTimers.delete(toast);
		} else if (toast._etucartTimer) {
			clearTimeout(toast._etucartTimer);
			toast._etucartTimer = null;
		}
		if (toast.classList.contains('is-leaving')) return;
		// The PDP-matching transition runs on transform+opacity, so simply
		// removing `is-visible` and adding `is-leaving` is enough to reverse
		// the slide-up.
		toast.classList.remove('is-visible');
		toast.classList.add('is-leaving');
		setTimeout(function () {
			if (toast.parentNode) toast.parentNode.removeChild(toast);
		}, 340);
	}

	/* ------------------------------------------------------------------ *
	 * Event delegation
	 * ------------------------------------------------------------------ */

	document.addEventListener('click', function (e) {
		var target = e.target;
		if (!target || !(target instanceof Element)) return;

		// --- Swatch click (color or size) -----------------------------
		var optBtn = closest(target, '.etucart-shop-pick__opt');
		if (optBtn && closest(optBtn, '.etucart-shop-pick')) {
			e.preventDefault();
			e.stopPropagation();
			if (optBtn.classList.contains('is-unavailable') ||
				optBtn.classList.contains('is-out-of-stock')) {
				return;
			}
			var row    = closest(optBtn, '.etucart-shop-pick__attr');
			var picker = closest(optBtn, '.etucart-shop-pick');
			if (!row || !picker) return;

			var alreadySelected = optBtn.classList.contains('is-selected');
			qsa('.etucart-shop-pick__opt', row).forEach(function (other) {
				other.classList.remove('is-selected');
				other.setAttribute('aria-pressed', 'false');
			});
			if (!alreadySelected) {
				optBtn.classList.add('is-selected');
				optBtn.setAttribute('aria-pressed', 'true');
			}
			refreshAll(picker);
			// Keep the Quick View preselect in sync with the latest pick — if
			// the user opens Quick View right after tapping a size, the modal's
			// PDP buy box will hydrate from this entry.
			persistPreselectForQuickView(picker);
			return;
		}

		// --- +N reveal -------------------------------------------------
		var moreBtn = closest(target, '.etucart-shop-pick__more');
		if (moreBtn && closest(moreBtn, '.etucart-shop-pick')) {
			e.preventDefault();
			e.stopPropagation();
			var row2   = closest(moreBtn, '.etucart-shop-pick__attr');
			if (!row2) return;

			var expanded = moreBtn.getAttribute('aria-expanded') === 'true';
			// Mark the opts container so CSS can switch from
			// `flex-wrap: nowrap; overflow: hidden` (single line, clipped)
			// to `flex-wrap: wrap; overflow: visible` — revealed chips
			// flow onto a new row directly below the visible row instead
			// of being pushed inline and clipped.
			var optsContainer = qs('.etucart-shop-pick__opts', row2);
			if (optsContainer) {
				optsContainer.classList.toggle('is-expanded', !expanded);
			}
			qsa('.etucart-shop-pick__opt.is-overflow', row2).forEach(function (o) {
				if (expanded) {
					o.hidden = true;
					o.classList.remove('is-revealed');
				} else {
					o.hidden = false;
					o.classList.add('is-revealed');
				}
			});
			moreBtn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
			var lbl = qs('.etucart-shop-pick__more-label', moreBtn);
			if (lbl) {
				if (expanded) {
					var count = moreBtn.getAttribute('data-count') || '';
					lbl.textContent = '+' + count;
				} else {
					lbl.textContent = '−';
				}
			}
			return;
		}

		// --- Quantity +/- (simple-product picker) ---------------------
		// Mirror the PDP buy-box stepper so the archive card matches.
		var qtyBtn = closest(target, '.etucart-shop-pick__qty-btn');
		if (qtyBtn && closest(qtyBtn, '.etucart-shop-pick--simple')) {
			e.preventDefault();
			e.stopPropagation();
			var pickerQ  = closest(qtyBtn, '.etucart-shop-pick');
			if (!pickerQ) return;
			var qtyInput = qs('.etucart-shop-pick__qty', pickerQ);
			if (!qtyInput) return;
			var step = parseFloat(qtyInput.getAttribute('step')) || 1;
			var min  = parseFloat(qtyInput.getAttribute('min')) || 1;
			var maxAttr = qtyInput.getAttribute('max');
			var max  = maxAttr ? parseFloat(maxAttr) : NaN;
			var val  = parseFloat(qtyInput.value);
			if (isNaN(val)) val = min;
			var next = qtyBtn.classList.contains('etucart-shop-pick__qty-btn--plus')
				? val + step
				: val - step;
			if (next < min) next = min;
			if (!isNaN(max) && max > 0 && next > max) next = max;
			qtyInput.value = String(next);
			try {
				qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
			} catch (ev) { /* old browsers */ }
			return;
		}

		// --- Add to cart ----------------------------------------------
		var addBtn = closest(target, '.etucart-shop-pick__add');
		if (addBtn && closest(addBtn, '.etucart-shop-pick')) {
			e.preventDefault();
			e.stopPropagation();
			if (addBtn.disabled || addBtn.classList.contains('is-busy')) return;
			var picker2 = closest(addBtn, '.etucart-shop-pick');
			if (!picker2) return;
			submitAddToCart(picker2, addBtn);
			return;
		}
	}, false);

	/* ------------------------------------------------------------------ *
	 * AJAX add-to-cart
	 * ------------------------------------------------------------------ */

	function submitAddToCart(picker, addBtn) {
		var pid      = picker.getAttribute('data-product-id') || '';
		var nonce    = picker.getAttribute('data-nonce') || '';
		var isSimple = picker.classList.contains('etucart-shop-pick--simple');

		if (!pid) return;

		var body = new URLSearchParams();
		body.set('product_id', pid);
		body.set('nonce',      nonce);

		if (isSimple) {
			// Simple picker: read quantity from the qty input (defaulting to
			// the input's min, then to 1). No variation payload.
			var qtyInput = qs('.etucart-shop-pick__qty', picker);
			var qty      = 1;
			if (qtyInput) {
				var parsed = parseInt(qtyInput.value, 10);
				if (!isNaN(parsed) && parsed > 0) {
					qty = parsed;
				}
			}
			body.set('quantity', String(qty));
		} else {
			var variationId = addBtn.getAttribute('data-variation-id') || '';
			if (!variationId) return;
			var current = getSelections(picker);
			body.set('variation_id', variationId);
			body.set('quantity',     '1');
			for (var k in current) {
				if (current.hasOwnProperty(k) && current[k] !== '') {
					body.set('variation[' + k + ']', current[k]);
				}
			}
		}

		addBtn.classList.add('is-busy');
		addBtn.disabled = true;
		addBtn.setAttribute('aria-disabled', 'true');

		fetch(AJAX_URL, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest'
			},
			body: body.toString()
		}).then(function (res) {
			return res.json().then(function (json) { return { ok: res.ok, json: json }; });
		}).then(function (r) {
			addBtn.classList.remove('is-busy');

			if (r.ok && r.json && (r.json.success !== false)) {
				addBtn.classList.add('is-success');
				addBtn.textContent = (i18n.addedToCart || 'Added to cart') + ' ✓';
				showToast((i18n.addedToCart || 'Added to cart'), 'success');

				// Fire the core WC event so mini-carts / drawers / analytics
				// listeners refresh identically to a native add-to-cart.
				var fragments = (r.json && r.json.fragments) ? r.json.fragments : null;
				var cartHash  = (r.json && r.json.cart_hash) ? r.json.cart_hash : '';

				// Dispatch both a native CustomEvent and (if jQuery is present
				// on the page, which is typical of WooCommerce themes) the
				// jQuery event that mini-cart fragments code listens to.
				try {
					document.body.dispatchEvent(new CustomEvent('added_to_cart', {
						bubbles: true,
						detail: { fragments: fragments, cart_hash: cartHash, button: addBtn }
					}));
				} catch (e) { /* no-op */ }

				if (window.jQuery) {
					try {
						window.jQuery(document.body).trigger('added_to_cart', [
							fragments, cartHash, window.jQuery(addBtn)
						]);
						// Also trigger wc_fragment_refresh to force a fragments
						// pull if our endpoint didn't return them (older WC).
						if (!fragments) {
							window.jQuery(document.body).trigger('wc_fragment_refresh');
						}
					} catch (e) { /* no-op */ }
				}

				// Revert the button back to its default label after 1.5s so
				// the user can add again. We don't clear the selections —
				// tapping a different color + add-to-cart adds a new item.
				setTimeout(function () {
					addBtn.classList.remove('is-success');
					addBtn.textContent = i18n.addToCart || 'Add to cart';
					addBtn.disabled = false;
					addBtn.removeAttribute('aria-disabled');
				}, 1500);
				return;
			}

			// Error path ---------------------------------------------------
			// The WC message (e.g. "You cannot add another X to your cart")
			// is variable-length and can be long. We intentionally DO NOT put
			// it into the button text (button grows, grid breaks) or into an
			// in-card element (card grows, grid breaks). Route it to the
			// toast instead; the button gets only a brief is-error colour
			// flash so the user sees WHERE the error originated.
			addBtn.classList.add('is-error');
			var msg = (r.json && r.json.data && r.json.data.message)
				? r.json.data.message
				: (i18n.errorGeneric || 'Error, please try again');
			showToast(msg, 'error');

			setTimeout(function () {
				addBtn.classList.remove('is-error');
				addBtn.textContent = i18n.addToCart || 'Add to cart';
				addBtn.disabled = false;
				addBtn.removeAttribute('aria-disabled');
			}, 2500);

		}).catch(function () {
			addBtn.classList.remove('is-busy');
			addBtn.classList.add('is-error');
			showToast((i18n.errorGeneric || 'Error, please try again'), 'error');
			setTimeout(function () {
				addBtn.classList.remove('is-error');
				addBtn.textContent = i18n.addToCart || 'Add to cart';
				addBtn.disabled = false;
				addBtn.removeAttribute('aria-disabled');
			}, 2500);
		});
	}

	/* ------------------------------------------------------------------ *
	 * Initial sync on load (OOS greying for any pre-selected defaults)
	 * + re-sync when new pickers appear (fragments / ajax grids)
	 * ------------------------------------------------------------------ */

	function initAllPickers(root) {
		qsa('.etucart-shop-pick', root || document).forEach(function (p) {
			refreshAll(p);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { initAllPickers(); });
	} else {
		initAllPickers();
	}

	// Re-init after WC fragment refreshes (cart drawer updates can re-render
	// archive strips in some themes), after Elementor's lazy-loaded loops,
	// and after generic ajax page-navigation events.
	if (window.jQuery) {
		try {
			window.jQuery(document.body).on('wc_fragments_refreshed updated_wc_div', function () {
				initAllPickers();
			});
		} catch (e) { /* no-op */ }
	}

	// Re-run the single-line overflow scan on viewport resize so chips
	// re-flow when the card width changes (e.g. responsive grid breakpoint).
	// Debounced via rAF to avoid thrashing during continuous resize.
	var resizeRaf = 0;
	window.addEventListener('resize', function () {
		if (resizeRaf) return;
		resizeRaf = requestAnimationFrame(function () {
			resizeRaf = 0;
			qsa('.etucart-shop-pick').forEach(function (p) { refreshOverflow(p); });
		});
	});

	// MutationObserver: pick up any picker inserted after page load (infinite
	// scroll, AJAX category switch, etc.) without re-binding events.
	if (typeof window.MutationObserver === 'function') {
		var mo = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				var m = mutations[i];
				if (!m.addedNodes) continue;
				for (var j = 0; j < m.addedNodes.length; j++) {
					var n = m.addedNodes[j];
					if (n.nodeType !== 1) continue;
					if (n.classList && n.classList.contains('etucart-shop-pick')) {
						refreshAll(n);
					} else {
						qsa('.etucart-shop-pick', n).forEach(function (p) { refreshAll(p); });
					}
				}
			}
		});
		mo.observe(document.body, { childList: true, subtree: true });
	}
})();
