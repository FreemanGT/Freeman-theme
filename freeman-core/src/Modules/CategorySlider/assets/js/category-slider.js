/*
 * Freeman Category Slider — vanilla JS runtime.
 *
 * Drag-scroll with momentum, optional snap, arrow buttons, and a progress
 * bar / "current/total" label. Idempotent: init() can be called multiple
 * times on the same DOM (Elementor editor re-renders) and each .cs element
 * is bound at most once.
 *
 * Mouse drag on the *track* is ON by default in 1.7.14 (was opt-in
 * before). Cards remain clickable — drag only engages after a *deliberate*
 * horizontal motion that passes three gates simultaneously:
 *
 *   1. Distance > DRAG_DIST px from press point
 *   2. Elapsed > DRAG_TIME_MS since pointerdown
 *   3. |dx| > |dy| × DRAG_AXIS_RATIO (horizontal-dominant)
 *
 * Implementation uses Pointer Events with setPointerCapture so a drag
 * continues even if the cursor leaves the slider's bounds.
 *
 * Touch is handled NATIVELY — the JS drag is desktop-mouse only. The
 * track's `touch-action: pan-x pan-y` lets the browser own horizontal
 * swipe scrolling (with OS-level momentum) and vertical swipe page
 * scrolling. Trying to drive touch through Pointer Events here raced the
 * browser's own gesture arbitration: gates waiting on 10px / 80ms /
 * horizontal-dominance left the early frames of a flick uncommitted, and
 * `touch-action: pan-y` blocked the native horizontal pan in the
 * meantime — the slider felt frozen on phones. The progress bar still
 * updates from the track's `scroll` event, which fires for any scroll
 * source (native or JS).
 *
 * Click suppression: capture-phase `preventDefault + stopPropagation`
 * runs only when state.dragged === true, i.e. only when all three gates
 * have passed. Sub-threshold presses leave the click intact and the
 * underlying anchor's navigation fires normally.
 *
 * Admins can disable mouse drag entirely via the widget toggle — the
 * `data-cs-mouse-drag="0"` attribute then bypasses Pointer Event binding
 * altogether, falling back to the browser's native overflow-x scroll.
 *
 * RTL: modern browsers (Chrome 85+, Firefox, Safari 16+) normalize
 * scrollLeft to "negative" mode in RTL — 0 at start (right edge), negative
 * as you scroll towards the end (left). The drag formula
 * `scrollLeft = startScroll - dx` is direction-agnostic in this mode
 * (content always follows the finger physically), so the drag handler is
 * shared. The arrow scrollBy direction IS flipped because Previous/Next
 * are *semantic* directions: in RTL the start is on the right, so
 * "Previous" needs to scrollBy positive (towards 0). The progress bar
 * uses `Math.abs(scrollLeft) / max` for ratio so it's robust against any
 * scrollLeft mode, and the bar's transform sign is flipped in RTL because
 * it's anchored to the right edge via CSS.
 */
(function () {
	'use strict';

	var INIT_FLAG = '__fcsInit';

	// --- Multi-gate drag detection (1.7.14) ----------------------------
	// All three gates must pass on the same pointermove for a press to
	// become a drag. This mirrors how Swiper / Embla / Asos / Amazon
	// category rails behave — clicks navigate, drags scroll, the two
	// never fight.
	var DRAG_DIST       = 10;  // px from press point
	var DRAG_TIME_MS    = 80;  // ms since pointerdown
	var DRAG_AXIS_RATIO = 1.2; // |dx| must exceed |dy| × this to count

	function clamp(n, lo, hi) { return Math.max(lo, Math.min(hi, n)); }

	function attachDragScroll(track, opts) {
		opts = opts || {};
		if (opts.enabled === false) return;

		var state = {
			active: false,    // a press is in flight
			pointerId: null,  // ignore secondary pointers while one is active
			startX: 0,
			startY: 0,
			startT: 0,
			startScroll: 0,
			lastX: 0,
			lastT: 0,
			vel: 0,
			raf: 0,
			dragged: false,   // gates have passed at least once this gesture
			captured: false,  // setPointerCapture has been called
		};

		function cancelMomentum() {
			if (state.raf) cancelAnimationFrame(state.raf);
			state.raf = 0;
		}

		function momentum() {
			if (Math.abs(state.vel) < 0.4) {
				state.raf = 0;
				return;
			}
			var max = Math.max(0, track.scrollWidth - track.clientWidth);
			if (max <= 0) {
				state.raf = 0;
				return;
			}
			// vel is finger px/frame (positive = finger moving right). To make
			// content follow finger, scrollLeft moves opposite — same as the
			// drag formula `scrollLeft = startScroll - dx`. Direction-agnostic
			// thanks to scrollLeft normalization in modern browsers.
			var before = track.scrollLeft;
			track.scrollLeft -= state.vel;
			var after = track.scrollLeft;
			var pos = Math.abs(after);
			if (Math.abs(after - before) < 0.5 || pos <= 0.5 || pos >= max - 0.5) {
				state.raf = 0;
				return;
			}
			state.vel *= 0.94;
			state.raf = requestAnimationFrame(momentum);
		}

		function resetState() {
			state.active = false;
			state.pointerId = null;
			state.dragged = false;
			state.captured = false;
		}

		function onPointerDown(e) {
			// Mouse: primary button only. Touch / pen always have button 0.
			if (e.pointerType === 'mouse' && e.button !== 0) return;
			// Touch / pen: hand off to the browser's native overflow-x scroll
			// (with its built-in momentum). Driving touch through Pointer
			// Events here was racing the browser's own gesture arbitration —
			// `touch-action: pan-y` blocked horizontal native scroll while
			// our gates waited for 10px / 80ms / horizontal-dominance, so a
			// quick horizontal flick produced no scroll at all on phones.
			// The progress bar still updates because it listens to the
			// track's `scroll` event, which fires for native scrolls too.
			if (e.pointerType !== 'mouse') return;
			// Re-entrancy guard: ignore secondary pointers (e.g. pinch-zoom
			// second finger) while one gesture is already being tracked.
			if (state.active) return;

			cancelMomentum();
			state.active      = true;
			state.pointerId   = e.pointerId;
			state.dragged     = false;
			state.captured    = false;
			state.startX      = e.clientX;
			state.startY      = e.clientY;
			state.startT      = performance.now();
			state.startScroll = track.scrollLeft;
			state.lastX       = e.clientX;
			state.lastT       = state.startT;
			state.vel         = 0;
			// Don't setPointerCapture or add cs-dragging yet — wait until
			// gates pass. Touch needs its native vertical-scroll affordance
			// preserved until we know this gesture is a horizontal drag.
		}

		function onPointerMove(e) {
			if (!state.active || e.pointerId !== state.pointerId) return;

			var dx = e.clientX - state.startX;
			var dy = e.clientY - state.startY;

			if (!state.dragged) {
				var dt = performance.now() - state.startT;
				var distOk = Math.abs(dx) > DRAG_DIST;
				var timeOk = dt > DRAG_TIME_MS;
				var axisOk = Math.abs(dx) > Math.abs(dy) * DRAG_AXIS_RATIO;
				if (!(distOk && timeOk && axisOk)) return;

				// Gates passed — commit to drag mode.
				state.dragged = true;
				track.classList.add('cs-dragging');
				try {
					track.setPointerCapture(e.pointerId);
					state.captured = true;
				} catch (err) { /* older browsers */ }
				// On touch only: prevent the native horizontal pan so we
				// can drive scrollLeft directly. Vertical pans were already
				// claimed by the browser via `touch-action: pan-y`.
				if (e.pointerType !== 'mouse' && e.cancelable) {
					e.preventDefault();
				}
			}

			// Drag is live — follow the pointer. Clamp to the track's real
			// scroll bounds so the user can't pull past the last card on
			// either edge; modern browsers normalize scrollLeft to 0..-max
			// in RTL, so the bounds are [-max, 0] in RTL and [0, max] in
			// LTR — same `clamp` shape with the bounds swapped.
			var dragMax = Math.max(0, track.scrollWidth - track.clientWidth);
			var dragNext = state.startScroll - dx;
			if (opts.isRtl) {
				track.scrollLeft = clamp(dragNext, -dragMax, 0);
			} else {
				track.scrollLeft = clamp(dragNext, 0, dragMax);
			}
			var now = performance.now();
			var moveDt = Math.max(1, now - state.lastT);
			state.vel = (e.clientX - state.lastX) / moveDt * 16;
			state.lastX = e.clientX;
			state.lastT = now;
			if (e.pointerType !== 'mouse' && e.cancelable) e.preventDefault();
		}

		function onPointerEnd(e) {
			if (!state.active || e.pointerId !== state.pointerId) return;

			var wasDragged = state.dragged;
			var vel        = state.vel;

			if (state.captured) {
				try { track.releasePointerCapture(state.pointerId); } catch (err) {}
			}
			track.classList.remove('cs-dragging');
			resetState();

			if (wasDragged && Math.abs(vel) > 1) {
				state.vel = vel; // momentum reads state.vel
				momentum();
			}

			// state.dragged is now false. The browser fires `click` AFTER
			// pointerup; we need to remember "the gesture that just ended
			// was a drag" so onClickCapture can suppress the next click.
			// Stash it on the function for the click handler to read.
			onClickCapture._lastDragged = wasDragged;
		}

		function onClickCapture(e) {
			// Block the click only when the gesture that just ended was
			// confirmed as a drag (all three gates passed). Sub-threshold
			// presses pass through and the anchor navigates normally.
			if (onClickCapture._lastDragged) {
				onClickCapture._lastDragged = false;
				e.preventDefault();
				e.stopPropagation();
			}
		}
		onClickCapture._lastDragged = false;

		// Suppress the browser's native HTML5 drag on anchors and images
		// inside the track. Without this, mousedown-and-move on a card
		// triggers the browser's "drag this link to your bookmarks bar"
		// preview ghost AND swallows our pointermove events — the slider
		// stops following the cursor. Single delegated listener at the
		// track level catches every descendant.
		track.addEventListener('dragstart', function (e) { e.preventDefault(); });

		track.addEventListener('pointerdown',   onPointerDown);
		track.addEventListener('pointermove',   onPointerMove, { passive: false });
		track.addEventListener('pointerup',     onPointerEnd);
		track.addEventListener('pointercancel', onPointerEnd);
		track.addEventListener('click', onClickCapture, true);

		// If the tab loses focus mid-drag, the browser may swallow the
		// pointerup that would otherwise end the gesture. Synthesize an
		// end-event from window blur so the slider doesn't get stuck in
		// the dragging state.
		window.addEventListener('blur', function () {
			if (!state.active) return;
			if (state.captured) {
				try { track.releasePointerCapture(state.pointerId); } catch (err) {}
			}
			track.classList.remove('cs-dragging');
			resetState();
		});
	}

	/**
	 * Make the progress bar a real horizontal scrubber. Mousedown anywhere
	 * on it jumps the track to that position; mousedown + drag moves the
	 * thumb continuously. This is the desktop "middle ground" — cards stay
	 * click-only, but the user has a clear visual affordance (the bar
	 * grows on hover) for scrolling with the mouse.
	 *
	 * RTL: scrollLeft in normalized RTL is 0 at the start (right edge) and
	 * -max at the end (left edge). The progress bar is anchored to the right
	 * edge in RTL via CSS, so visual ratio 0 corresponds to scrollLeft 0
	 * regardless of direction. The sign flip for scrollLeft is the only
	 * direction-dependent piece.
	 */
	function attachProgressDrag(progress, track, isRtl) {
		if (!progress || !track) return;

		function maxScroll() {
			return Math.max(0, track.scrollWidth - track.clientWidth);
		}

		function ratioFromEvent(e) {
			var rect = progress.getBoundingClientRect();
			var w = rect.width;
			if (w <= 0) return 0;
			// "Visual ratio" — 0 at the start of the bar, 1 at the end —
			// independent of LTR/RTL. In LTR the start is the left edge of
			// the bar; in RTL the start is the right edge.
			var localX = e.clientX - rect.left;
			var visual = clamp(localX / w, 0, 1);
			return isRtl ? (1 - visual) : visual;
		}

		function applyRatio(r) {
			var max = maxScroll();
			if (max <= 0) return;
			track.scrollLeft = (isRtl ? -1 : 1) * r * max;
		}

		var dragging = false;

		function onDown(e) {
			// Left mouse button only.
			if (e.button !== undefined && e.button !== 0) return;
			dragging = true;
			progress.classList.add('cs-progress-dragging');
			applyRatio(ratioFromEvent(e));
			e.preventDefault();
		}

		function onMove(e) {
			if (!dragging) return;
			applyRatio(ratioFromEvent(e));
			e.preventDefault();
		}

		function onUp() {
			if (!dragging) return;
			dragging = false;
			progress.classList.remove('cs-progress-dragging');
		}

		progress.addEventListener('mousedown', onDown);
		window.addEventListener('mousemove', onMove);
		window.addEventListener('mouseup', onUp);
	}

	function initOne(root) {
		if (!root || root[INIT_FLAG]) return;
		root[INIT_FLAG] = true;

		var track = root.querySelector('[data-cs-track]');
		if (!track) return;

		var isRtl = (root.getAttribute('dir') || '').toLowerCase() === 'rtl';
		var arrowSign = isRtl ? -1 : 1;
		// data-cs-mouse-drag attribute name preserved for back-compat with
		// already-saved widget instances. "1" = enabled (now the default),
		// "0" = explicitly disabled by admin → fall back to native overflow
		// scroll for touch and to the progress scrubber for desktop.
		var dragEnabled = root.getAttribute('data-cs-mouse-drag') !== '0';

		attachDragScroll(track, { enabled: dragEnabled, isRtl: isRtl });

		var arrows = root.querySelectorAll('[data-cs-dir]');
		arrows.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var dir = parseInt(btn.getAttribute('data-cs-dir'), 10) || 1;
				// In RTL, "Next" (dir=+1) means scroll towards the end (left),
				// which is scrollBy negative in normalized RTL scrollLeft.
				track.scrollBy({ left: arrowSign * dir * track.clientWidth * 0.85, behavior: 'smooth' });
			});
		});

		var bar = root.querySelector('[data-cs-progress-bar]');
		var progress = root.querySelector('.cs-progress');
		var footCurrent = root.querySelector('[data-cs-foot-current]');
		var totalCards = track.querySelectorAll('.cs-card').length;

		attachProgressDrag(progress, track, isRtl);
		var per = parseInt(getComputedStyle(root).getPropertyValue('--cs-per'), 10) || 5;

		function updateProgress() {
			var max = track.scrollWidth - track.clientWidth;
			// Math.abs handles both LTR (scrollLeft 0..max) and RTL normalized
			// mode (0..-max), and is safe against the legacy positive-RTL mode.
			var ratio = max > 0 ? clamp(Math.abs(track.scrollLeft) / max, 0, 1) : 0;

			if (bar && progress) {
				// Pixel-based math so the bar reaches the parent's far edge
				// exactly at ratio=1. Translating in % of the bar's own width
				// (the previous approach) under-shoots whenever bar.width
				// fraction differs from the visible-content fraction.
				var parentW = progress.clientWidth;
				var visibleRatio = track.scrollWidth > 0 ? track.clientWidth / track.scrollWidth : 1;
				var barW = Math.max(parentW * 0.12, parentW * Math.min(1, visibleRatio));
				var travelPx = Math.max(0, parentW - barW);
				var dx = (isRtl ? -1 : 1) * ratio * travelPx;
				bar.style.width = barW.toFixed(2) + 'px';
				bar.style.transform = 'translate3d(' + dx.toFixed(2) + 'px, 0, 0)';
			}
			if (progress) {
				progress.setAttribute('aria-valuenow', String(Math.round(ratio * 100)));
			}
			if (footCurrent && totalCards > 0) {
				var current = clamp(Math.round(ratio * (totalCards - per)) + per, per, totalCards);
				footCurrent.textContent = String(current).padStart(2, '0');
			}

			arrows.forEach(function (btn) {
				var dir = parseInt(btn.getAttribute('data-cs-dir'), 10) || 1;
				// "Previous" (dir=-1) is disabled when we're at the start (ratio=0);
				// "Next" (dir=+1) at the end (ratio=1). Same in both directions
				// because we normalized via Math.abs above.
				if (dir < 0) btn.disabled = ratio <= 0.001;
				else btn.disabled = ratio >= 0.999;
			});
		}

		updateProgress();
		track.addEventListener('scroll', updateProgress, { passive: true });

		if (typeof ResizeObserver !== 'undefined') {
			var ro = new ResizeObserver(updateProgress);
			ro.observe(track);
			// Also observe the progress element since its width drives the bar's
			// pixel calculations and changes independently from the track on
			// some Elementor column layouts.
			if (progress) ro.observe(progress);
		} else {
			window.addEventListener('resize', updateProgress);
		}

		// Bridge to dependent measurers that read child widths (notably the
		// VariationSwatches archive picker, which scans each card's `+N`
		// overflow on DOMContentLoaded — sometimes before the slider's flex
		// layout has fully settled). After the slider's own layout is
		// committed (next animation frame), dispatch a window `resize` so
		// any module that listens to it re-measures against final widths.
		if (typeof window.requestAnimationFrame === 'function') {
			window.requestAnimationFrame(function () {
				window.dispatchEvent(new Event('resize'));
			});
		}
	}

	function initAll(scope) {
		var root = scope || document;
		var nodes = root.querySelectorAll ? root.querySelectorAll('.cs[data-cs-snap]') : [];
		Array.prototype.forEach.call(nodes, initOne);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { initAll(); });
	} else {
		initAll();
	}

	// Elementor editor: re-init when widgets are added or re-rendered.
	// Both the Category Slider and the Product Slider widgets emit the same
	// `.cs[data-cs-snap]` skeleton, so a single init covers both — we just
	// need to subscribe to each widget's `element_ready` action.
	if (window.jQuery) {
		window.jQuery(window).on('elementor/frontend/init', function () {
			if (!window.elementorFrontend || !window.elementorFrontend.hooks) return;
			['freeman_category_slider.default', 'freeman_product_slider.default'].forEach(function (hook) {
				window.elementorFrontend.hooks.addAction(
					'frontend/element_ready/' + hook,
					function ($scope) { initAll($scope[0]); }
				);
			});
		});
	}

	// Public hook for callers that swap markup at runtime.
	window.FreemanCategorySlider = { init: initAll };
})();
