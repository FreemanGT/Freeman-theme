/**
 * Etucart Variation Swatches — frontend glue.
 *
 * This file does NOT duplicate WooCommerce's variation-matching logic.
 * The visible swatch UI is kept in sync with a hidden <select> per
 * attribute (inside a .variations container, where WC core expects them).
 * Changes flow through WC's own `wc-add-to-cart-variation` script, which
 * handles availability, pricing, stock and variation matching exactly as
 * the default WooCommerce template does.
 *
 * Lifecycle:
 *   1. User clicks a visible swatch.
 *   2. We update the matching hidden <select> and trigger `change`.
 *   3. WC's variations.js runs `check_variations`, disables unavailable
 *      <option>s, fires `woocommerce_update_variation_values`, and
 *      eventually `found_variation` / `reset_data`.
 *   4. We mirror those updates back into the UI (is-selected, is-unavailable,
 *      selected-name label).
 */
(function ($) {
	'use strict';

	function $formOf(el) {
		return $(el).closest('form.etucart-buy-box');
	}

	function findHiddenSelect($form, attributeName) {
		return $form.find('.variations select.etucart-hidden-select').filter(function () {
			return $(this).data('attribute_name') === attributeName || $(this).attr('name') === attributeName;
		}).first();
	}

	function findVisibleVariation($form, attributeName) {
		return $form.find('.etucart-variation').filter(function () {
			return $(this).data('attribute_name') === attributeName;
		}).first();
	}

	function syncSwatchesFromSelect($form, $select) {
		var attributeName = $select.data('attribute_name') || $select.attr('name');
		var $variation    = findVisibleVariation($form, attributeName);
		if (!$variation.length) return;

		var val = $select.val() || '';
		$variation.find('.etucart-swatch').each(function () {
			var $btn = $(this);
			$btn.toggleClass('is-selected', String($btn.data('value')) === String(val) && val !== '');
		});

		var $label = $variation.find('.etucart-variation__selected');
		if (val) {
			var $sel = $variation.find('.etucart-swatch').filter(function () {
				return String($(this).data('value')) === String(val);
			}).first();
			var name = $sel.data('name') || $select.find('option:selected').text();
			$label.text(name);
		} else {
			$label.text('');
		}
	}

	/**
	 * Pull the variations JSON attached by the template (respects WC's AJAX
	 * threshold — if the product has too many variations, WC emits the string
	 * "false" and we fall back to "no in-memory data"). Returns an array or null.
	 */
	function getVariationsData($form) {
		var raw = $form.attr('data-product_variations');
		if (!raw || raw === 'false' || raw === 'null') {
			return null;
		}
		// jQuery's .data() already parses JSON for us, but when the attribute is
		// the string "false" it becomes boolean false. Use .data() when possible.
		var parsed = $form.data('product_variations');
		if (parsed && typeof parsed === 'object') {
			return parsed;
		}
		try {
			return JSON.parse(raw);
		} catch (e) {
			return null;
		}
	}

	/**
	 * Given the current selections across all attributes, compute per-value
	 * availability state on `targetAttribute`. For each swatch value we
	 * return one of:
	 *   - 'unavailable'  → NO matching variation exists (combined with
	 *                      currently-selected other attributes). User cannot
	 *                      pick this — it's invalid.
	 *   - 'out_of_stock' → matching variation(s) exist but ALL of them are
	 *                      out of stock / not purchasable.
	 *   - 'ok'           → at least one matching variation is purchasable.
	 *
	 * Computing "unavailable" here ourselves (instead of relying on WC's
	 * `:disabled` <option> state) means OOS/availability greying works even
	 * before WC's `check_variations` has bound or run — which is important
	 * for quick-view modals, AJAX-injected shop listings, and any other
	 * context where WC's variations.js lifecycle is incomplete.
	 */
	function computeValueStates($form, targetAttribute, variations) {
		if (!variations || !variations.length) return {};

		// Gather current selections from the hidden selects.
		var current = {};
		$form.find('.variations select.etucart-hidden-select').each(function () {
			var $s   = $(this);
			var name = $s.data('attribute_name') || $s.attr('name');
			var val  = $s.val() || '';
			if (name) current[name] = val;
		});

		var $variation = findVisibleVariation($form, targetAttribute);
		if (!$variation.length) return {};

		var result = {};
		$variation.find('.etucart-swatch').each(function () {
			var candidate = String($(this).data('value'));
			var hasAnyMatch         = false;
			var hasPurchasableMatch = false;

			for (var i = 0; i < variations.length; i++) {
				var v = variations[i];
				if (!v || !v.attributes) continue;

				var matches = true;
				// The candidate value must match on targetAttribute.
				var vTarget = v.attributes[targetAttribute];
				if (typeof vTarget === 'undefined') {
					matches = false;
				} else if (vTarget !== '' && vTarget !== candidate) {
					// Empty string on a variation means "any" — matches.
					matches = false;
				}

				// All OTHER currently-selected attributes must match too.
				if (matches) {
					for (var key in current) {
						if (!current.hasOwnProperty(key)) continue;
						if (key === targetAttribute) continue;
						var sel = current[key];
						if (sel === '') continue; // nothing selected on this axis
						var vv = v.attributes[key];
						if (typeof vv === 'undefined') continue;
						if (vv !== '' && vv !== sel) {
							matches = false;
							break;
						}
					}
				}

				if (matches) {
					hasAnyMatch = true;
					var purchasable =
						v.is_purchasable !== false &&
						v.is_in_stock    !== false &&
						v.variation_is_active !== false &&
						v.variation_is_visible !== false;
					if (purchasable) {
						hasPurchasableMatch = true;
						break;
					}
				}
			}

			if (!hasAnyMatch) {
				result[candidate] = 'unavailable';
			} else if (!hasPurchasableMatch) {
				result[candidate] = 'out_of_stock';
			} else {
				result[candidate] = 'ok';
			}
		});

		return result;
	}

	function syncAvailability($form) {
		var variations = getVariationsData($form);

		$form.find('.variations select.etucart-hidden-select').each(function () {
			var $select       = $(this);
			var attributeName = $select.data('attribute_name') || $select.attr('name');
			var $variation    = findVisibleVariation($form, attributeName);
			if (!$variation.length) return;

			var stateMap = computeValueStates($form, attributeName, variations);

			$variation.find('.etucart-swatch').each(function () {
				var $btn = $(this);
				var v    = String($btn.data('value'));
				var $opt = $select.find('option').filter(function () {
					return String($(this).val()) === v;
				});

				// Prefer our own computed state (works even when WC hasn't
				// run). Fall back to WC's :disabled option flag if we don't
				// have variations data (product exceeded threshold in an
				// external install, or data was stripped by something).
				var state = stateMap[v];
				var unavailable, outOfStock;
				if (typeof state === 'string') {
					unavailable = (state === 'unavailable');
					outOfStock  = (state === 'out_of_stock');
				} else {
					unavailable = !!($opt.length && $opt.is(':disabled'));
					outOfStock  = false;
				}

				$btn.toggleClass('is-unavailable', !!unavailable);
				$btn.toggleClass('is-out-of-stock', !!outOfStock);

				if (outOfStock || unavailable) {
					$btn.attr('aria-disabled', 'true');
					if (!$btn.attr('data-etucart-oos-title')) {
						$btn.attr('data-etucart-oos-title', '1');
						var existing = $btn.attr('title') || '';
						var msg = outOfStock ? 'אזל מהמלאי' : 'לא זמין';
						$btn.attr('title', existing ? existing + ' — ' + msg : msg);
					}
				} else {
					$btn.removeAttr('aria-disabled');
					if ($btn.attr('data-etucart-oos-title')) {
						$btn.removeAttr('title');
						$btn.removeAttr('data-etucart-oos-title');
					}
				}
			});
		});
	}

	$(function () {

		var $doc = $(document);

		// ---------- Swatch click → drive the hidden <select> ------------
		$doc.on('click', '.etucart-buy-box .etucart-swatch', function (e) {
			e.preventDefault();
			var $btn = $(this);
			if ($btn.hasClass('is-unavailable') || $btn.hasClass('is-out-of-stock')) {
				return;
			}
			var $form         = $formOf($btn);
			var attributeName = $btn.closest('.etucart-variation').data('attribute_name');
			var $select       = findHiddenSelect($form, attributeName);
			if (!$select.length) return;
			var newValue      = String($btn.data('value'));

			// Toggle off if already selected.
			if ($btn.hasClass('is-selected')) {
				$select.val('').trigger('change');
				return;
			}

			$select.val(newValue).trigger('change');
		});

		// ---------- Quantity stepper -----------------------------------
		// Decorate every .quantity inside a buy box with +/- buttons. This is
		// idempotent (see the early-return on existing buttons) so it's safe
		// to call repeatedly — we re-run it whenever a new buy box appears
		// (quick-view modal, infinite-scroll shop listing, etc.).
		function decorateQuantitySteppers(context) {
			var $scope = context ? $(context) : $(document);
			// Collect .quantity elements:
			//   - if $scope is itself a .etucart-buy-box form, look at its descendants directly
			//   - otherwise look at .quantity nested inside .etucart-buy-box descendants
			//   - also include $scope itself if it happens to be a .quantity inside a buy box
			var $quantities = $()
				.add($scope.filter('form.etucart-buy-box').find('.quantity'))
				.add($scope.find('form.etucart-buy-box .quantity'))
				.add($scope.filter('.quantity').filter(function () {
					return $(this).closest('.etucart-buy-box').length > 0;
				}));
			$quantities.each(function () {
				var $q = $(this);
				if ($q.find('.etucart-qty__btn').length) return;

				var $minus = $('<button type="button" class="etucart-qty__btn etucart-qty__btn--minus" aria-label="−">−</button>');
				var $plus  = $('<button type="button" class="etucart-qty__btn etucart-qty__btn--plus" aria-label="+">+</button>');
				var $input = $q.find('input.qty');

				// RTL visual order: [+] [value] [-]
				$q.prepend($plus);
				$q.append($minus);

				$plus.on('click', function (e) {
					e.preventDefault();
					var max  = parseFloat($input.attr('max'));
					var step = parseFloat($input.attr('step')) || 1;
					var val  = parseFloat($input.val()) || 0;
					var next = val + step;
					if (!isNaN(max) && max > 0 && next > max) {
						next = max;
					}
					$input.val(next).trigger('change');
				});

				$minus.on('click', function (e) {
					e.preventDefault();
					var min  = parseFloat($input.attr('min')) || 1;
					var step = parseFloat($input.attr('step')) || 1;
					var val  = parseFloat($input.val()) || 0;
					var next = val - step;
					if (next < min) {
						next = min;
					}
					$input.val(next).trigger('change');
				});
			});
		}
		decorateQuantitySteppers(document);

		// ---------- Mirror WC availability → swatch UI ------------------
		// WC fires these events during its check_variations cycle.
		$doc.on('woocommerce_update_variation_values check_variations woocommerce_variation_has_changed', 'form.etucart-buy-box', function () {
			syncAvailability($(this));
		});

		// The event is also fired on selects themselves; catch via delegation.
		$doc.on('woocommerce_update_variation_values', 'form.etucart-buy-box .variations select', function () {
			syncAvailability($formOf(this));
		});

		// ---------- Button enable/disable — defensive safety net --------
		// Explicitly toggle our buttons based on WC's variation events, so we
		// do not depend on any theme CSS or the core `.variations_button`
		// hide/show flow (which some themes override).
		function setButtonsEnabled($form, enabled) {
			var $btns = $form.find('.single_add_to_cart_button');
			if (enabled) {
				$btns.removeClass('disabled wc-variation-selection-needed wc-variation-is-unavailable')
					.prop('disabled', false)
					.attr('aria-disabled', 'false');
			} else {
				$btns.addClass('disabled wc-variation-selection-needed')
					.prop('disabled', false) // keep clickable for UX feedback; we preventDefault on click
					.attr('aria-disabled', 'true');
			}
		}

		// Swap the ATC button label + hide Buy Now when the picked variation
		// is OOS / unpurchasable. Stashes the default "הוספה לעגלה" label on
		// first use so we can restore it when the user picks a buyable one.
		// The Hebrew strings are hardcoded to mirror the PHP templates —
		// there's no i18n glossary registered on the client, and duplicating
		// one literal here beats wiring a new localized global.
		var OOS_LABEL     = 'אזל מהמלאי';
		var DEFAULT_LABEL = 'הוספה לעגלה';
		function setOutOfStockState($form, isOOS) {
			var $atcButtons = $form.find('.etucart-add-to-cart, .etucart-sticky-bar__buy--atc');
			$atcButtons.each(function () {
				var $b = $(this);
				if (!$b.data('etucart-default-label')) {
					$b.data('etucart-default-label', $.trim($b.text()) || DEFAULT_LABEL);
				}
				$b.text(isOOS ? OOS_LABEL : $b.data('etucart-default-label'));
			});

			var $buyNow = $form.find('.etucart-buy-now');
			if ($buyNow.length) {
				$buyNow.toggleClass('etucart-buy-now--hidden', isOOS);
				if (isOOS) {
					$buyNow.attr('hidden', 'hidden');
				} else {
					$buyNow.removeAttr('hidden');
				}
			}
		}

		/**
		 * Update the .etucart-pdp-price line that sits ABOVE the buy-box.
		 * - With a variation: swap the value to that variation's price_html
		 *   and hide the "starting from" prefix (a single picked variation
		 *   has one definite price, so the prefix is meaningless).
		 * - Without one (reset_data / no match): restore the saved min-price
		 *   HTML and re-show the prefix if the product had a range to start.
		 */
		function updatePdpPrice($form, variation) {
			var $price = $form.prevAll('.etucart-pdp-price').first();
			if (!$price.length) {
				$price = $form.parent().find('.etucart-pdp-price').first();
			}
			if (!$price.length) return;

			var $value  = $price.find('.etucart-pdp-price__value');
			var $prefix = $price.find('.etucart-pdp-price__prefix');
			var hadRange = $price.attr('data-has-range') === '1';

			if (variation && variation.price_html) {
				$value.html(variation.price_html);
				if ($prefix.length) $prefix.attr('hidden', 'hidden');
			} else {
				var fromHtml = $price.attr('data-from-html') || '';
				if (fromHtml) $value.html(fromHtml);
				if ($prefix.length && hadRange) $prefix.removeAttr('hidden');
			}
		}

		$doc.on('found_variation', 'form.etucart-buy-box', function (event, variation) {
			var $form = $(this);
			var purchasable = variation && (variation.is_purchasable === true || variation.is_in_stock === true) && (variation.variation_is_active !== false);
			setButtonsEnabled($form, !!purchasable);
			// A matched-but-OOS variation is exactly the case where we want
			// the "אזל מהמלאי" wording + no Buy Now. An unmatched selection
			// (reset_data / hide_variation) keeps the default labels so the
			// shopper sees "choose options" UX, not a false OOS state.
			setOutOfStockState($form, !purchasable);
			// Keep the single_variation wrap visible (some themes hide it).
			$form.find('.single_variation_wrap, .etucart-actions').show();
			// Reflect variation_id for safety.
			if (variation && variation.variation_id) {
				$form.find('input.variation_id').val(variation.variation_id);
			}
			// Re-evaluate OOS state against the now-changed selection.
			syncAvailability($form);
			// Update the "starting from" line to the picked variation's price.
			// Form sits next to (not inside) the .etucart-pdp-price element —
			// it's rendered above the form by variation-buy-box.php.
			updatePdpPrice($form, variation);
		});

		$doc.on('reset_data hide_variation', 'form.etucart-buy-box', function () {
			var $form = $(this);
			// Simple products don't have variations — these events shouldn't
			// disable our buttons. A third-party script (or an accidental
			// $form.wc_variation_form()) can still dispatch them at the form;
			// ignoring them here keeps the PHP-rendered enabled state intact.
			if ($form.hasClass('etucart-buy-box--simple')) return;
			setButtonsEnabled($form, false);
			setOutOfStockState($form, false);
			$form.find('input.variation_id').val('0');
			syncAvailability($form);
			updatePdpPrice($form, null);
		});

		// Block form submission when buttons are in "selection needed" state.
		$doc.on('click', '.etucart-buy-box .single_add_to_cart_button', function (e) {
			var $btn = $(this);
			if ($btn.hasClass('disabled') || $btn.attr('aria-disabled') === 'true') {
				e.preventDefault();
				// Give the user a nudge: focus the first empty select so WC's
				// own "please choose options" hint can kick in.
				var $form      = $formOf($btn);
				var $firstEmpty = $form.find('.variations select').filter(function () {
					return $(this).val() === '';
				}).first();
				if ($firstEmpty.length) {
					$firstEmpty.trigger('focusin');
				}
				return false;
			}
		});

		// ---------- Keep swatches + selected-label in sync with selects --
		$doc.on('change', 'form.etucart-buy-box .variations select.etucart-hidden-select', function () {
			syncSwatchesFromSelect($formOf(this), $(this));
		});

		// ---------- reset_data: clear selected state -------------------
		$doc.on('reset_data', 'form.etucart-buy-box', function () {
			var $form = $(this);
			$form.find('.etucart-swatch').removeClass('is-selected');
			$form.find('.etucart-variation__selected').text('');
		});

		// ---------- Unified add-to-cart / buy-now submit handler -------
		//
		// We own the add-to-cart submission everywhere — main product page,
		// quick-view modals, AJAX-filtered shop listings, sticky mobile bar.
		// ONE code path, identical behaviour in every context.
		//
		// Why this handler exists:
		//   - On the main product page, FunnelKit Cart intercepts the form
		//     submit and runs its own AJAX. Inside a quick-view modal (WPC
		//     QuickView, YITH QV, etc.) the modal plugin's OWN click handler
		//     fires first, does its own server-side add, and never
		//     broadcasts `added_to_cart` in a way FunnelKit recognises — so
		//     FunnelKit's slide-in stays stale until the next add or a page
		//     refresh.
		//   - Posting to WC's own `wc-ajax=add_to_cart` endpoint gives us a
		//     proven, cart-plugin-agnostic path: WC processes the variation
		//     (unique cart_item_key per variation_id, no cross-variation
		//     collisions), returns the standard fragments payload, and every
		//     cart UI — FunnelKit, WC mini-cart widgets, theme counters —
		//     refreshes correctly via the `added_to_cart` event we fire.
		//
		// We bind in the CAPTURE phase on document so this handler runs
		// before any quick-view plugin's bubble-phase click interceptor.
		// `stopImmediatePropagation` + `preventDefault` then prevents their
		// out-of-band AJAX from running at all.
		//
		// Disabled buttons intentionally fall through without
		// stopImmediatePropagation so the bubble-phase "selection needed"
		// handler above can still do its focus-the-empty-select nudge.

		function resolveAddToCartAjaxUrl() {
			var src = (window.wc_add_to_cart_params && window.wc_add_to_cart_params.wc_ajax_url)
				|| (window.wc_add_to_cart_variation_params && window.wc_add_to_cart_variation_params.wc_ajax_url)
				|| (window.wc_cart_fragments_params && window.wc_cart_fragments_params.wc_ajax_url);
			if (src) {
				return String(src).replace('%%endpoint%%', 'add_to_cart');
			}
			return '/?wc-ajax=add_to_cart';
		}

		function handleEtucartAdd($form, $btn) {
			// Simple-product form: no hidden variation_id input, no attribute_*
			// inputs. We post product_id directly to WC's add_to_cart AJAX
			// endpoint, which handles simple products natively.
			var isSimple   = $form.hasClass('etucart-buy-box--simple');
			var productId  = parseInt($form.find('input[name="product_id"]').val(), 10) || 0;
			var variationId = isSimple ? 0 : parseInt($form.find('input.variation_id').val(), 10);

			if (!isSimple && !variationId) {
				return false; // variable product without selection — let WC native validation kick in
			}
			if (isSimple && !productId) {
				return false; // no product id somehow — fall back to native submit
			}

			// Determine intent: Buy Now (checkout after) or Add-to-Cart.
			var isBuyNow = $btn.hasClass('etucart-buy-now')
				|| $btn.attr('data-etucart-buy-now') === '1';
			$form.find('.etucart-buy-now-flag').val(isBuyNow ? '1' : '');

			var qty  = parseInt($form.find('input.qty').val(), 10);
			if (isNaN(qty) || qty < 1) qty = 1;

			// WC's add_to_cart AJAX endpoint expects product_id to be the
			// variation_id for variable products, and the actual product_id
			// for simple products (see WC_AJAX::add_to_cart).
			var data;
			var bundleCompat = !!(window.FreemanCoreVSFlags && window.FreemanCoreVSFlags.bundleCompat);
			if (bundleCompat) {
				// Wave 4.5: forward every form field except a small deny-list
				// of WP nonces / referer fields. Bundle/FBT plugins inject
				// hidden fields like woosb_*, wcfbt_*; the legacy
				// product_id/quantity/attribute_* whitelist silently dropped
				// them, so the cart only got the main product. Whitelisting
				// is fragile against new bundle plugins — denylist is more
				// compatible.
				var DENY = { '_wpnonce': 1, '_wp_http_referer': 1, 'woocommerce-process-checkout-nonce': 1 };
				data = {};
				$form.serializeArray().forEach(function (field) {
					if (DENY[field.name]) return;
					data[field.name] = field.value;
				});
			} else {
				data = { product_id: (isSimple ? productId : variationId), quantity: qty };
				if (!isSimple) {
					$form.find('[name^="attribute_"]').each(function () {
						data[this.name] = $(this).val();
					});
				}
			}
			// product_id / quantity overrides apply to both branches: WC's
			// endpoint requires variation_id in the product_id slot for
			// variable products, and the form's quantity field name varies.
			data.product_id = (isSimple ? productId : variationId);
			data.quantity   = qty;

			$btn.addClass('loading');

			$.ajax({
				url:      resolveAddToCartAjaxUrl(),
				method:   'POST',
				data:     data,
				dataType: 'json'
			}).done(function (response) {
				$btn.removeClass('loading');
				if (!response) return;
				if (response.error && response.product_url) {
					// WC asks us to go to the product page (e.g. variation
					// requires selection we didn't send). Follow.
					window.location = response.product_url;
					return;
				}
				// Broadcast the standard WC event. FunnelKit Cart, WC
				// mini-cart widgets, theme counters — every cart UI
				// listens for this and refreshes from the fragments
				// payload. This is the same event WC's own AJAX
				// add-to-cart button emits.
				$(document.body).trigger('wc_fragment_refresh');
				$(document.body).trigger('added_to_cart', [
					response.fragments || {},
					response.cart_hash || '',
					$btn
				]);

				if (isBuyNow) {
					var url = (window.EtucartVS && EtucartVS.checkoutUrl)
						|| (window.location.origin + '/checkout/');
					window.location.href = url;
				}
			}).fail(function () {
				// Server/network failure — fall back to native submit so
				// the user is not stranded. This is a degraded path: it
				// reloads the page but still adds the item.
				$btn.removeClass('loading');
				try { $form[0].submit(); } catch (e) {}
			});

			return true; // handled
		}

		// Wave 4.5: when bundle compatibility is on AND the form carries any
		// known bundle plugin's hidden fields, our capture-phase shortcut
		// must step out so the bundle plugin's bubble-phase click handler
		// runs natively. WC's add_to_cart endpoint can't process a bundle
		// payload — only the plugin's own AJAX handler can.
		function formOwnedByBundlePlugin(formEl) {
			var flags = window.FreemanCoreVSFlags || {};
			if (!flags.bundleCompat) return false;
			var markers = flags.bundleMarkers || ['woobt_'];
			for (var i = 0; i < markers.length; i++) {
				var marker = String(markers[i] || '');
				if (!marker) continue;
				if (formEl.querySelector('[name^="' + marker + '"]')) {
					return true;
				}
			}
			return false;
		}

		// Wave 4.5 (1.11.16): bridge FBT's success event to wc_fragment_refresh.
		// FBT fires `added_to_cart` (good — passes its fragments to WC's
		// fragment update) and `woobt_added_to_cart` (its own event), but
		// NOT `wc_fragment_refresh`. FunnelKit Cart's auto-open and
		// re-fetch logic listen for `wc_fragment_refresh` specifically, so
		// after an FBT add the side cart neither auto-opens nor reflects
		// the freshly-added items until the next fragment-refresh trigger
		// (any other action). Re-firing wc_fragment_refresh here closes the
		// gap. No-op when FBT isn't installed (event never fires) and
		// no-op when our flag is OFF (FBT's handler is intercepted by our
		// capture-phase shortcut, so it never reaches its success path).
		$(document.body).on('woobt_added_to_cart', function () {
			$(document.body).trigger('wc_fragment_refresh');
		});

		// Capture-phase native listener: runs before any other click handler
		// in the document, regardless of what order plugins were loaded.
		document.addEventListener('click', function (nativeEvt) {
			var target = nativeEvt.target;
			if (!target || !target.closest) return;
			var btn = target.closest('.etucart-buy-box .single_add_to_cart_button');
			if (!btn) return;
			var form = btn.closest('form.etucart-buy-box');
			if (!form) return;

			var $btn  = $(btn);
			var $form = $(form);

			// Let the bubble-phase "selection needed" handler keep its
			// focus-the-empty-select nudge for disabled buttons.
			if ($btn.hasClass('disabled') || btn.getAttribute('aria-disabled') === 'true') {
				return;
			}

			if (formOwnedByBundlePlugin(form)) {
				return; // bundle plugin owns this submit
			}

			if (handleEtucartAdd($form, $btn)) {
				// We've taken responsibility — stop every other click
				// handler (FunnelKit's submit interceptor, WPC QuickView's
				// AJAX-add handler, etc.) AND cancel the native submit.
				nativeEvt.stopImmediatePropagation();
				nativeEvt.preventDefault();
			}
		}, true); // true = capture phase

		// Safety net for keyboard submission (Enter key inside a field).
		// This doesn't fire a click event, so we also intercept `submit`.
		document.addEventListener('submit', function (nativeEvt) {
			var form = nativeEvt.target;
			if (!form || !form.matches || !form.matches('form.etucart-buy-box')) return;

			var $form = $(form);
			// Best guess: whichever submit button is focused, else the
			// primary Add-to-Cart button.
			var $btn = $form.find(':submit:focus').first();
			if (!$btn.length) $btn = $form.find('.etucart-add-to-cart').first();
			if (!$btn.length) $btn = $form.find(':submit').first();

			if ($btn.hasClass('disabled') || $btn.attr('aria-disabled') === 'true') {
				return;
			}

			if (formOwnedByBundlePlugin(form)) {
				return; // bundle plugin owns this submit
			}

			if (handleEtucartAdd($form, $btn)) {
				nativeEvt.stopImmediatePropagation();
				nativeEvt.preventDefault();
			}
		}, true);

		// ---------- Ripple animation on primary pill buttons -----------
		// Pure visual feedback — does not interfere with the form submit
		// flow. Runs on pointerdown so the animation is in flight by the
		// time the click event fires.
		function createRipple(e, $btn) {
			var rect  = $btn[0].getBoundingClientRect();
			var size  = Math.max(rect.width, rect.height);
			var x     = (e.clientX || rect.left + rect.width / 2)  - rect.left - size / 2;
			var y     = (e.clientY || rect.top  + rect.height / 2) - rect.top  - size / 2;
			var $rip  = $('<span class="etucart-ripple" aria-hidden="true"></span>').css({
				width: size + 'px',
				height: size + 'px',
				top: y + 'px',
				left: x + 'px'
			});
			$btn.append($rip);
			setTimeout(function () { $rip.remove(); }, 700);
		}

		$doc.on('pointerdown', '.etucart-buy-box .etucart-add-to-cart, .etucart-buy-box .etucart-buy-now, .etucart-sticky-bar__buy', function (e) {
			var $btn = $(this);
			if ($btn.hasClass('disabled') || $btn.attr('aria-disabled') === 'true') return;
			createRipple(e, $btn);
		});

		// ---------- Sticky mobile bottom bar ---------------------------
		// The sticky bar is an Add-to-Cart shortcut on mobile — it mirrors
		// the main Add-to-Cart button but is always reachable. It is part of
		// the form (so submit naturally carries every hidden input, and the
		// WC `name="add-to-cart"` value is present). We mirror three things
		// into it:
		//   1. The current variation price HTML (from WC's .single_variation).
		//   2. The disabled/enabled state of the main Buy Now button
		//      (which tracks the same "variation selected?" condition as
		//      Add-to-Cart, so we piggy-back on it).
		//   3. Visibility — hidden until the main Buy Now scrolls out of view.
		// Enabled clicks on the sticky button submit the form natively
		// (handled by FunnelKit / WC native AJAX / standard submit);
		// disabled clicks are caught below for the "please choose
		// options first" affordance.

		function isMobileViewport() {
			return window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
		}

		function syncStickyPrice($form) {
			var $bar   = $form.find('.etucart-sticky-bar');
			if (!$bar.length) return;
			// Simple products seed the sticky price server-side from
			// $product->get_price_html() — there are no variations, so the
			// price never changes and we'd only overwrite it with an empty
			// string if we tried to pull from `.single_variation .price`.
			if ($form.hasClass('etucart-buy-box--simple')) return;
			var $price = $bar.find('.etucart-sticky-bar__price-value');
			var $src   = $form.find('.single_variation .price').first();
			if ($src.length && $src.html() && $.trim($src.text()) !== '') {
				$price.html($src.html());
			} else {
				$price.empty();
			}
		}

		function syncStickyDisabled($form) {
			var $main = $form.find('.etucart-buy-now').first();
			var $bar  = $form.find('.etucart-sticky-bar__buy');
			if (!$bar.length) return;
			var disabled = $main.hasClass('disabled') || $main.attr('aria-disabled') === 'true';
			if (disabled) {
				$bar.addClass('disabled wc-variation-selection-needed').attr('aria-disabled', 'true');
			} else {
				$bar.removeClass('disabled wc-variation-selection-needed').attr('aria-disabled', 'false');
			}
		}

		// Heuristic: is this form embedded inside a quick-view / modal / popup
		// container? If so, the viewport-fixed sticky bar would either overlap
		// the modal or (worse) show product info that belongs to the underlying
		// page's product. We detect a broad set of common plugin wrappers —
		// WPC Smart Quick View (`.woosq-product`), WPC QuickView
		// (`.wpcqv-product`), YITH (`.yith-wcqv-content`), FiboSearch, Flatsome
		// (`.fl-wcqv-modal`), and any generic `[role="dialog"]` / `<dialog>`.
		function isInsideModal($form) {
			return $form.closest(
				'.woosq-product, .woosq-wrap, ' +
				'.wpcqv-product, .wpcqv, ' +
				'.yith-wcqv-content, .yith-wcqv-main, ' +
				'.fl-wcqv-modal, .mfp-container, ' +
				'[role="dialog"], dialog, ' +
				'[class*="quick-view"], [class*="quickview"]'
			).length > 0;
		}

		function updateStickyVisibility($form) {
			var $bar = $form.find('.etucart-sticky-bar');
			if (!$bar.length) return;

			if (!isMobileViewport() || isInsideModal($form)) {
				$bar.removeClass('is-visible').attr('aria-hidden', 'true');
				$('body').removeClass('etucart-sticky-active');
				return;
			}

			// Visible if the main Buy Now button is NOT currently in the viewport.
			var $anchor = $form.find('.etucart-buy-now').first();
			if (!$anchor.length) return;
			var rect = $anchor[0].getBoundingClientRect();
			var vh   = window.innerHeight || document.documentElement.clientHeight;
			var inView = rect.bottom > 0 && rect.top < vh;

			if (!inView) {
				$bar.addClass('is-visible').attr('aria-hidden', 'false');
				$('body').addClass('etucart-sticky-active');
			} else {
				$bar.removeClass('is-visible').attr('aria-hidden', 'true');
				$('body').removeClass('etucart-sticky-active');
			}
		}

		// Sticky Add-to-Cart disabled-click UX — the enabled path is owned
		// by the Add-to-Cart AJAX handler above; this handler runs only
		// when the button is disabled and provides the "please choose
		// options first" feedback (scroll to the form and focus the first
		// empty select).
		$doc.on('click', '.etucart-buy-box .etucart-sticky-bar__buy', function (e) {
			var $btn = $(this);
			if (!$btn.hasClass('disabled') && $btn.attr('aria-disabled') !== 'true') {
				return; // enabled — handled elsewhere
			}
			e.preventDefault();
			var $form = $formOf($btn);
			$('html, body').animate({
				scrollTop: $form.offset().top - 20
			}, 220);
			var $firstEmpty = $form.find('.variations select').filter(function () {
				return $(this).val() === '';
			}).first();
			if ($firstEmpty.length) {
				setTimeout(function () { $firstEmpty.trigger('focusin'); }, 240);
			}
			return false;
		});

		// Mirror price/state on WC events.
		$doc.on('found_variation reset_data hide_variation', 'form.etucart-buy-box', function () {
			var $form = $(this);
			// Let WC finish writing the price HTML before we read it.
			setTimeout(function () {
				syncStickyPrice($form);
				syncStickyDisabled($form);
			}, 0);
		});

		// Scroll + resize: update visibility.
		var scrollTicking = false;
		function onScrollOrResize() {
			if (scrollTicking) return;
			scrollTicking = true;
			requestAnimationFrame(function () {
				$('form.etucart-buy-box').each(function () {
					updateStickyVisibility($(this));
				});
				scrollTicking = false;
			});
		}
		$(window).on('scroll resize orientationchange', onScrollOrResize);

		// ---------- Form initialization --------------------------------
		// Reflect any pre-selected default attributes into the visible UI,
		// wire up quantity steppers, and kick WC's variations check once so
		// stock/price/availability flow through on first render.
		//
		// This is extracted so we can run it both on DOM ready AND when a
		// buy-box form is inserted later (quick-view modals, infinite-scroll
		// listings, theme tab switches, etc.). A small flag (`data-etucart-init`)
		// makes init idempotent so we don't double-bind or re-fire events on
		// forms that were already initialised.
		// ---------- Quick View preselect bridge (1.6.6) -----------------
		// When the user picks a size/color on the archive picker and then
		// triggers a Quick View (WPC Quick View, WooSQ, YITH, etc.), this
		// buy box should arrive with those attributes already selected so
		// they don't have to pick twice.
		//
		// The archive JS writes the current selection into sessionStorage
		// under `etucart_qv_preselect_<pid>` whenever the user taps an
		// option. We read it here on form init, apply each attribute to
		// its matching hidden <select> (so WC's `check_variations`
		// resolves the variation), then remove the entry so it fires
		// once and never haunts a later pageview.
		//
		// TTL mirrors the archive side (60s): long enough for "pick then
		// click Quick View", short enough that a stale pick from an earlier
		// session never silently pre-selects on a fresh PDP nav.
		var QV_PRESELECT_PREFIX = 'etucart_qv_preselect_';
		var QV_PRESELECT_TTL_MS = 60 * 1000;

		function readPreselectEntry(pid) {
			if (!pid || !window.sessionStorage) return null;
			var key = QV_PRESELECT_PREFIX + pid;
			var raw;
			try { raw = window.sessionStorage.getItem(key); } catch (e) { return null; }
			if (!raw) return null;
			var parsed;
			try { parsed = JSON.parse(raw); } catch (e) { parsed = null; }
			if (!parsed || !parsed.attrs || typeof parsed.attrs !== 'object') {
				try { window.sessionStorage.removeItem(key); } catch (e) {}
				return null;
			}
			if (typeof parsed.ts !== 'number' || (Date.now() - parsed.ts) > QV_PRESELECT_TTL_MS) {
				try { window.sessionStorage.removeItem(key); } catch (e) {}
				return null;
			}
			return parsed;
		}

		function clearPreselectEntry(pid) {
			if (!pid || !window.sessionStorage) return;
			try { window.sessionStorage.removeItem(QV_PRESELECT_PREFIX + pid); } catch (e) {}
		}

		function applyPreselect($form, attrs) {
			var applied = 0;
			for (var name in attrs) {
				if (!attrs.hasOwnProperty(name)) continue;
				var value  = String(attrs[name] || '');
				if (value === '') continue;
				var $select = findHiddenSelect($form, name);
				if (!$select.length) continue;
				// Only apply if the value is a valid option on this select.
				// Guards against stale picks across products that share attr slugs.
				var hasOpt = $select.find('option').filter(function () {
					return String($(this).val()) === value;
				}).length > 0;
				if (!hasOpt) continue;
				if ($select.val() === value) continue; // already set — don't re-fire
				$select.val(value).trigger('change');
				applied++;
			}
			return applied;
		}

		function initForm($form) {
			if (!$form || !$form.length) return;

			// One-time setup (qty stepper, swatch→select sync, sticky bar) is
			// guarded by a flag so repeated calls from the MutationObserver /
			// quick-view events don't redo DOM work.
			if ($form.attr('data-etucart-init') !== '1') {
				$form.attr('data-etucart-init', '1');
				decorateQuantitySteppers($form);
				$form.find('.variations select.etucart-hidden-select').each(function () {
					syncSwatchesFromSelect($form, $(this));
				});

				// One-shot preselect from archive / Quick View hand-off.
				var pid = parseInt($form.attr('data-product_id') || '0', 10);
				var pre = readPreselectEntry(pid);
				if (pre) {
					var applied = applyPreselect($form, pre.attrs);
					if (applied > 0) {
						clearPreselectEntry(pid);
					}
				}
			}

			// Availability sync is DIFFERENT — it must run every time init is
			// called (e.g. when a quick-view injects a fresh form), because
			// WC's `check_variations` is what actually disables unavailable
			// <option>s (our `is-unavailable` state) and populates the
			// variation-level flags our `is-out-of-stock` logic reads. We run
			// it on multiple timers as a belt-and-suspenders against init-order
			// races — WC's variations.js registers its `check_variations`
			// handler on DOM ready; if we fire our trigger too early (e.g.
			// before WC's $(fn) callback has run in an edge case), the event
			// is lost. Retrying guarantees eventual consistency.
			function refresh() {
				// Simple-product forms have no variations to match, no hidden
				// <select>s, no .variations_form class. Calling WC's
				// wc_variation_form() on them would make WC assert missing
				// payload and — worse — eventually fire reset_data at the
				// form, which our handler used to interpret as "disable".
				// Skip all of that for the simple flavour.
				var isSimple = $form.hasClass('etucart-buy-box--simple');
				if (!isSimple) {
					// For forms injected AFTER DOM ready (quick-view modals, AJAX
					// filters, etc.) WC's own $(function) callback never ran for
					// them, so `check_variations` has no handler bound. Calling
					// `$.fn.wc_variation_form()` directly on the form binds WC's
					// full variation-form behavior — option disabling,
					// `found_variation`, price update, etc. Guarded so we don't
					// double-bind on forms WC already owns.
					if (typeof $.fn.wc_variation_form === 'function' && !$form.data('wc_variation_form')) {
						try { $form.wc_variation_form(); } catch (e) {}
					}
					try { $form.trigger('check_variations'); } catch (e) {}
				}
				syncAvailability($form);
				syncStickyPrice($form);
				syncStickyDisabled($form);
				updateStickyVisibility($form);
			}
			setTimeout(refresh, 0);
			setTimeout(refresh, 200);
			setTimeout(refresh, 700);
		}

		function initAllForms(context) {
			var $scope = context ? $(context) : $(document);
			$scope.find('form.etucart-buy-box').addBack('form.etucart-buy-box').each(function () {
				initForm($(this));
			});
		}

		// Initial pass — existing forms printed into the page.
		initAllForms(document);

		// ---------- Quick-view / dynamic injection support -------------
		// Many plugins (WPC Smart Quick View / WPC QuickView / WooSQ, YITH
		// Quick View, FiboSearch's product cards, infinite-scroll / AJAX
		// shop plugins, etc.) inject variable-product forms into the page
		// after DOM ready. We listen for:
		//   - A MutationObserver over document.body that spots any newly
		//     inserted `.etucart-buy-box` form (authoritative fallback).
		//   - Named events fired by common quick-view plugins (fast path
		//     for popular cases).
		// Whichever fires first wins — `initForm` guards against double-init.

		function reinitSoon(root) {
			// A tick of delay lets the inserted DOM settle (some plugins
			// rewrite the content a second time, e.g. to replace placeholders).
			setTimeout(function () { initAllForms(root || document); }, 0);
			// And once more slightly later to catch late rewrites.
			setTimeout(function () { initAllForms(root || document); }, 250);
		}

		if (typeof MutationObserver !== 'undefined' && document.body) {
			var mo = new MutationObserver(function (mutations) {
				for (var i = 0; i < mutations.length; i++) {
					var m = mutations[i];
					if (!m.addedNodes || !m.addedNodes.length) continue;
					for (var j = 0; j < m.addedNodes.length; j++) {
						var node = m.addedNodes[j];
						if (!node || node.nodeType !== 1) continue;
						if (node.matches && node.matches('form.etucart-buy-box')) {
							reinitSoon(node);
						} else if (node.querySelector && node.querySelector('form.etucart-buy-box')) {
							reinitSoon(node);
						}
					}
				}
			});
			mo.observe(document.body, { childList: true, subtree: true });
		}

		// Common quick-view plugin events. Firing reinit on any of these is
		// safe (idempotent). Harmless if the event never fires.
		$doc.on(
			[
				'woosq_loaded',               // WPC Smart Quick View (WooSQ)
				'wpcqv_loaded',               // WPC QuickView (older handle)
				'wpcqv-content-loaded',       // WPC QuickView (content swap)
				'quickview_loaded',           // generic
				'wc_quickview_loaded',        // generic
				'yith_infs_adding_elem',      // YITH Infinite Scroll
				'yith_quick_view_loaded',     // YITH Quick View
				'yith_quick_view_loaded_ajax',// YITH (ajax)
				'qv_loader_stop',             // theme quick-view
				'fibosearch/products_loaded'  // FiboSearch
			].join(' '),
			function () { reinitSoon(document); }
		);

		// Also trigger when WC replaces content somewhere (theme integrations
		// with AJAX shop filtering).
		$doc.on('updated_wc_div wc_fragments_refreshed wc_fragments_loaded', function () {
			reinitSoon(document);
		});

	});
})(jQuery);
