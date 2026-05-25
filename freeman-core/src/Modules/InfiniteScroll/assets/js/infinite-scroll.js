/**
 * Freeman Infinite Scroll — product-grid auto-loader.
 *
 * Ported from bookomers-infinite-scroll v1.0.5 with localization and
 * pluggable option values via window.FreemanInfiniteScroll.
 *
 * Debug with ?freeman_is_debug=1 (legacy ?bookomers_debug=1 also accepted).
 */
(function () {
    'use strict';

    var CFG = window.FreemanInfiniteScroll || {};
    var DEBUG = /[?&](freeman_is_debug|bookomers_debug)=1/.test(location.search);

    var MAIN_SCOPE_SELECTORS = [
        'main.site-main',
        '#main',
        'main',
        '[role="main"]',
        '.site-main',
        '.content-area',
        '#content',
        '.elementor-main-content',
        '.elementor-location-archive',
        '.elementor-location-single'
    ];

    // Wave 3.1b: container selectors come from the localized
    // CFG.containerSelector when present (resolved server-side via the
    // setting + freeman_core/infinite_scroll/selector filter). When
    // absent or empty, fall back to the hardcoded 11-entry list.
    var CONTAINER_SELECTORS = (function () {
        var FALLBACK = [
            '.elementor-widget-woocommerce-products ul.products',
            '.elementor-widget-wc-archive-products ul.products',
            '.elementor-widget-woocommerce-archive-products ul.products',
            '.elementor-widget-woocommerce-product-archive ul.products',
            '.elementor-products-grid ul.products',
            '.elementor-products-grid',
            'ul.wc-block-product-template',
            '.wc-block-grid__products',
            '.wp-block-woocommerce-product-template',
            'ul.products',
            '.products'
        ];
        var override = CFG.containerSelector;
        if (typeof override === 'string' && override.trim() !== '') {
            return [override];
        }
        if (Array.isArray(override) && override.length > 0) {
            return override.slice();
        }
        return FALLBACK;
    })();

    var ITEM_SELECTORS = [
        'li.product',
        'li.wc-block-grid__product',
        '.wc-block-product-template .product',
        '.wc-block-grid__product',
        '.type-product',
        '.product'
    ];

    var PAGINATION_SELECTORS = [
        'nav.woocommerce-pagination',
        '.woocommerce-pagination',
        '.elementor-pagination',
        'nav.elementor-pagination',
        '.wc-block-pagination',
        '.wp-block-query-pagination',
        'ul.page-numbers'
    ];

    var NEXT_LINK_SELECTORS = [
        'nav.woocommerce-pagination a.next',
        '.woocommerce-pagination a.next',
        '.elementor-pagination a.next',
        'nav.elementor-pagination a.next',
        '.wc-block-pagination a.next',
        'a.wp-block-query-pagination-next',
        'a.next.page-numbers',
        '.page-numbers a.next'
    ];

    var NO_PRODUCTS_SELECTORS = [
        '.woocommerce-no-products-found',
        '.wc-no-products-found',
        '.wc-block-product-template__no-results',
        '.wc-block-no-products',
        '.woocommerce-info.no_products_found',
        '.no-products-found'
    ];

    var EXCLUDE_ANCESTORS = [
        '.sidebar',
        '.widget-area',
        '.widget',
        '.elementor-widget-wp-widget-woocommerce_products',
        '.elementor-widget-wp-widget-woocommerce_recent_products',
        '.elementor-widget-woocommerce-products-recent',
        '.related',
        '.upsells',
        '.cross-sells'
    ];

    var OPTS = {
        skeletonCount: (CFG.skeletonCount | 0) || 6,
        rootMargin: '800px 0px',
        fadeStaggerMs: 40,
        mutationDebounceMs: 200,
        maxPages: (CFG.maxPages | 0) || 50,
        scrollTriggerPx: 900,
        iosPollMs: 1500,
        endMessage: CFG.endMessage || 'You have reached the end.',
        errorMessage: CFG.errorMessage || 'Could not load more.',
        loadMoreLabel: CFG.loadMoreLabel || 'Load more',
        triggerModesEnabled: !!CFG.triggerModesEnabled,
        triggerMode: CFG.triggerMode || 'auto',
        historyMode: CFG.historyMode || 'pushState',
        hybridThreshold: (CFG.hybridThreshold | 0) || 2
    };

    var IS_IOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

    var state = {
        isLoading: false, nextUrl: null, observer: null, sentinel: null,
        container: null, itemSelector: null, stopped: false,
        mainObserver: null, abortController: null,
        seenIds: Object.create(null), fetchedUrls: Object.create(null),
        pagesLoaded: 0
    };

    function log() {
        if (!DEBUG) return;
        console.log.apply(console, ['[Freeman IS]'].concat([].slice.call(arguments)));
    }

    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments, self = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(self, args); }, ms);
        };
    }

    function resolveScope(root) {
        root = root || document;
        for (var i = 0; i < MAIN_SCOPE_SELECTORS.length; i++) {
            var el = root.querySelector(MAIN_SCOPE_SELECTORS[i]);
            if (el) return el;
        }
        return root.body || root.documentElement || root;
    }

    function isInsideExcluded(el) {
        for (var i = 0; i < EXCLUDE_ANCESTORS.length; i++) {
            if (el.closest && el.closest(EXCLUDE_ANCESTORS[i])) return true;
        }
        return false;
    }

    function firstMatchIn(scope, selectors) {
        for (var i = 0; i < selectors.length; i++) {
            var candidates;
            try {
                candidates = scope.querySelectorAll(selectors[i]);
            } catch (e) {
                continue;
            }
            for (var j = 0; j < candidates.length; j++) {
                if (!isInsideExcluded(candidates[j])) {
                    return { el: candidates[j], selector: selectors[i] };
                }
            }
        }
        return null;
    }

    function hasNoProductsNotice(scope) {
        for (var i = 0; i < NO_PRODUCTS_SELECTORS.length; i++) {
            if (scope.querySelector(NO_PRODUCTS_SELECTORS[i])) return true;
        }
        var info = scope.querySelector('.woocommerce-info');
        if (info && /no products were found/i.test(info.textContent || '')) return true;
        return false;
    }

    function resolveItemSelector(container) {
        for (var i = 0; i < ITEM_SELECTORS.length; i++) {
            if (container.querySelector(ITEM_SELECTORS[i])) return ITEM_SELECTORS[i];
        }
        return null;
    }

    function getProductId(el) {
        if (!el || !el.getAttribute) return null;
        var attrId = el.getAttribute('data-product-id') || el.getAttribute('data-product_id') || el.getAttribute('data-id');
        if (attrId) return 'p:' + attrId;
        var cls = (el.className || '').toString().split(/\s+/);
        for (var i = 0; i < cls.length; i++) {
            var m = cls[i].match(/^(?:post|product)[-_](\d+)$/);
            if (m) return 'p:' + m[1];
        }
        var a = el.querySelector && el.querySelector('a[href]');
        if (a && a.href) return 'u:' + a.href.replace(/[?#].*$/, '');
        return null;
    }

    function seedSeenIds() {
        state.seenIds = Object.create(null);
        if (!state.container || !state.itemSelector) return;
        var items = state.container.querySelectorAll(state.itemSelector);
        for (var i = 0; i < items.length; i++) {
            var id = getProductId(items[i]);
            if (id) state.seenIds[id] = true;
        }
    }

    function normalizeUrl(u) {
        try {
            var x = new URL(u, location.href);
            x.hash = '';
            var s = x.pathname.replace(/\/+$/, '/') + x.search;
            return x.origin + s;
        } catch (e) { return u; }
    }

    function buildFetchUrl(rawHref) {
        try {
            var nextU = new URL(rawHref, location.href);
            var curU  = new URL(location.href);
            curU.searchParams.forEach(function (value, key) {
                if (key === 'freeman_is_debug' || key === 'bookomers_debug') return;
                if (!nextU.searchParams.has(key)) {
                    nextU.searchParams.set(key, value);
                }
            });
            return nextU.href;
        } catch (e) { return rawHref; }
    }

    function init() {
        var scope = resolveScope(document);
        if (hasNoProductsNotice(scope)) { watchMain(); return; }
        var containerMatch = firstMatchIn(scope, CONTAINER_SELECTORS);
        if (!containerMatch) { watchMain(); return; }
        state.container = containerMatch.el;

        var itemSel = resolveItemSelector(state.container);
        if (!itemSel || state.container.querySelectorAll(itemSel).length === 0) {
            state.container = null; watchMain(); return;
        }
        state.itemSelector = itemSel;

        seedSeenIds();
        state.fetchedUrls = Object.create(null);
        state.fetchedUrls[normalizeUrl(location.href)] = true;
        state.pagesLoaded = 0;

        var nextMatch = firstMatchIn(scope, NEXT_LINK_SELECTORS);
        state.nextUrl = nextMatch ? buildFetchUrl(nextMatch.el.href) : null;

        hidePagination(scope);
        insertSentinelAfter(state.container);
        attachObserver();
        watchMain();
    }

    function hidePagination(scope) {
        PAGINATION_SELECTORS.forEach(function (sel) {
            scope.querySelectorAll(sel).forEach(function (el) {
                if (!isInsideExcluded(el)) el.style.display = 'none';
            });
        });
    }

    function insertSentinelAfter(container) {
        if (state.sentinel && state.sentinel.parentNode) {
            state.sentinel.parentNode.removeChild(state.sentinel);
        }
        state.sentinel = document.createElement('div');
        state.sentinel.className = 'bookomers-infinite-sentinel';
        state.sentinel.setAttribute('aria-hidden', 'true');
        state.sentinel.style.cssText = 'width:100%;min-height:4px;height:20px;margin:24px 0;pointer-events:none;';
        container.parentNode.insertBefore(state.sentinel, container.nextSibling);
    }

    // Wave 3.1a JS-side dispatcher per master plan §4-D8.
    //
    // Single entry point that consolidates the two trigger-mode gates:
    //   'skip-auto-attach' — 'button' mode; attachObserver uses this to
    //                       suppress IO + scroll fallback + iOS poll so
    //                       they don't do wasted work / skeleton-flash.
    //                       The user-visible 'Load more' button ships
    //                       in 3.1b; in 3.1a 'button' mode just halts
    //                       auto-loading until 3.1b lands.
    //   'stop'             — 'hybrid' mode once state.pagesLoaded crosses
    //                       OPTS.hybridThreshold; loadNext uses this to
    //                       halt further fetches. The user-facing button
    //                       takes over in 3.1b.
    //   null               — caller proceeds with auto-trigger behavior.
    //
    // Flag-OFF returns null unconditionally — callers route to the legacy
    // triple-stack path verbatim. Flag-ON-with-mode='auto' is null too.
    function applyTriggerMode(mode) {
        if (!OPTS.triggerModesEnabled) return null;
        if (mode === 'button') return 'skip-auto-attach';
        if (mode === 'hybrid' && state.pagesLoaded >= OPTS.hybridThreshold) return 'stop';
        return null;
    }

    function attachObserver() {
        if (applyTriggerMode(OPTS.triggerMode || 'auto') === 'skip-auto-attach') return;
        attachScrollFallback();
        if (IS_IOS) attachIosPoll();
        if (!('IntersectionObserver' in window)) return;
        if (state.observer) state.observer.disconnect();
        state.observer = new IntersectionObserver(onIntersect, { root: null, rootMargin: OPTS.rootMargin, threshold: 0 });
        state.observer.observe(state.sentinel);
    }

    function maybeTriggerFromScroll() {
        if (state.stopped || state.isLoading) return;
        if (!state.nextUrl || !state.sentinel) return;
        var rect = state.sentinel.getBoundingClientRect();
        var vh = window.innerHeight || document.documentElement.clientHeight;
        if (rect.top <= vh + OPTS.scrollTriggerPx) loadNext();
    }

    var _scrollFallbackAttached = false;
    function attachScrollFallback() {
        if (_scrollFallbackAttached) return;
        _scrollFallbackAttached = true;
        var ticking = false;
        function onAny() {
            if (ticking) return;
            ticking = true;
            (window.requestAnimationFrame || function (f) { return setTimeout(f, 16); })(function () {
                ticking = false; maybeTriggerFromScroll();
            });
        }
        var opts = { passive: true };
        ['scroll','touchmove','touchend','resize','orientationchange'].forEach(function(ev){
            window.addEventListener(ev, onAny, opts);
        });
        document.addEventListener('scroll', onAny, opts);
    }

    var _iosPollTimer = null;
    function attachIosPoll() {
        if (_iosPollTimer) return;
        _iosPollTimer = setInterval(function () {
            if (state.stopped || state.isLoading || !state.nextUrl) return;
            maybeTriggerFromScroll();
        }, OPTS.iosPollMs);
    }

    function onIntersect(entries) {
        if (state.stopped || state.isLoading || !state.nextUrl) return;
        if (entries[0].isIntersecting) loadNext();
    }

    function watchMain() {
        if (state.mainObserver) return;
        var scope = resolveScope(document);
        var debouncedResync = debounce(resync, OPTS.mutationDebounceMs);
        state.mainObserver = new MutationObserver(function (mutations) {
            if (!state.container) { debouncedResync(); return; }
            for (var i = 0; i < mutations.length; i++) {
                var m = mutations[i];
                for (var j = 0; j < m.removedNodes.length; j++) {
                    var n = m.removedNodes[j];
                    if (n === state.container) { debouncedResync(); return; }
                    if (n.nodeType !== 1) continue;
                    if (n.classList && (n.classList.contains('bookomers-skeleton') || n.classList.contains('bookomers-end-message') || n.classList.contains('bookomers-error-message'))) continue;
                    if (n.classList && (n.classList.contains('product') || n.classList.contains('wc-block-grid__product') || n.classList.contains('type-product'))) {
                        debouncedResync(); return;
                    }
                }
            }
        });
        state.mainObserver.observe(scope, { childList: true, subtree: true });
    }

    function resync() {
        var scope = resolveScope(document);
        if (hasNoProductsNotice(scope)) { abortInFlight(); teardownUi(); return; }
        var match = firstMatchIn(scope, CONTAINER_SELECTORS);
        if (!match) { abortInFlight(); teardownUi(); return; }
        var gridChanged = match.el !== state.container || !document.body.contains(state.container);
        if (gridChanged) {
            abortInFlight(); removeSkeletons(); removeAuxMessages();
            state.container = match.el;
            var itemSel = resolveItemSelector(state.container);
            if (!itemSel || state.container.querySelectorAll(itemSel).length === 0) {
                state.container = null; state.nextUrl = null; state.stopped = false; return;
            }
            state.itemSelector = itemSel;
            state.stopped = false; state.pagesLoaded = 0;
            state.fetchedUrls = Object.create(null);
            state.fetchedUrls[normalizeUrl(location.href)] = true;
            seedSeenIds(); insertSentinelAfter(state.container); attachObserver();
        } else {
            abortInFlight(); removeSkeletons(); removeAuxMessages();
            state.pagesLoaded = 0;
            state.fetchedUrls = Object.create(null);
            state.fetchedUrls[normalizeUrl(location.href)] = true;
            state.stopped = false; seedSeenIds();
        }
        hidePagination(scope);
        var nextMatch = firstMatchIn(scope, NEXT_LINK_SELECTORS);
        if (nextMatch) { state.nextUrl = buildFetchUrl(nextMatch.el.href); }
        else { state.nextUrl = null; state.stopped = true; }
    }

    function loadNext() {
        if (!state.nextUrl) return;
        if (state.pagesLoaded >= OPTS.maxPages) { stop('max pages reached'); showEndMessage(); return; }
        var normalizedNext = normalizeUrl(state.nextUrl);
        if (state.fetchedUrls[normalizedNext]) { stop('already fetched'); showEndMessage(); return; }

        state.isLoading = true;
        showSkeletons();
        var urlBeingFetched = state.nextUrl;
        state.fetchedUrls[normalizedNext] = true;

        state.abortController = ('AbortController' in window) ? new AbortController() : null;

        fetch(urlBeingFetched, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: state.abortController ? state.abortController.signal : undefined
        })
            .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.text(); })
            .then(function (html) {
                if (urlBeingFetched !== state.nextUrl) { log('stale response'); return; }
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var docScope = resolveScope(doc);

                if (hasNoProductsNotice(docScope)) { removeSkeletons(); stop('no products'); showEndMessage(); return; }

                var newContainerMatch = firstMatchIn(docScope, CONTAINER_SELECTORS);
                var newProducts = newContainerMatch ? [].slice.call(newContainerMatch.el.querySelectorAll(state.itemSelector)) : [];
                removeSkeletons();
                if (!newProducts.length) { stop('zero products'); showEndMessage(); return; }

                var unique = [], duplicates = 0;
                for (var i = 0; i < newProducts.length; i++) {
                    var id = getProductId(newProducts[i]);
                    if (id && state.seenIds[id]) { duplicates++; continue; }
                    if (id) state.seenIds[id] = true;
                    unique.push(newProducts[i]);
                }
                if (unique.length === 0) { stop('all duplicates'); showEndMessage(); return; }

                applyHistoryMode(urlBeingFetched);

                for (var k = 0; k < unique.length; k++) {
                    unique[k].classList.add('bookomers-new-product');
                    unique[k].style.animationDelay = (k * OPTS.fadeStaggerMs) + 'ms';
                    state.container.appendChild(unique[k]);
                }

                // A11y: announce how many products were just appended so
                // screen readers know new content arrived. Announced once
                // per fetch (aggregated count across `unique`).
                announceLoaded(unique.length);

                state.pagesLoaded++;

                if (applyTriggerMode(OPTS.triggerMode || 'auto') === 'stop') {
                    stop('hybrid threshold reached');
                }

                var nextMatch = firstMatchIn(docScope, NEXT_LINK_SELECTORS);
                if (!nextMatch) { stop('no next link'); showEndMessage(); return; }
                var candidate = buildFetchUrl(nextMatch.el.href);
                var candidateNorm = normalizeUrl(candidate);
                if (candidateNorm === normalizedNext || state.fetchedUrls[candidateNorm]) {
                    stop('next URL did not advance'); showEndMessage(); return;
                }
                state.nextUrl = candidate;
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') { removeSkeletons(); return; }
                console.error('[Freeman IS] fetch error:', err);
                removeSkeletons();
                showErrorMessage();
            })
            .then(function () { state.isLoading = false; });
    }

    function applyHistoryMode(url) {
        // Wave 3.1a: wrapper around the History API call site that used to
        // be inline. Flag-OFF preserves today's pushState behavior verbatim;
        // flag-ON branches on historyMode setting (pushState | replaceState
        // | disabled). Default is pushState — flag-ON-with-defaults is
        // byte-identical to flag-OFF.
        if (!OPTS.triggerModesEnabled) {
            try {
                var u0 = new URL(url);
                window.history.pushState({ freemanPage: u0.pathname }, '', u0.pathname + u0.search);
            } catch (e) { /* noop */ }
            return;
        }
        var mode = OPTS.historyMode || 'pushState';
        if (mode === 'disabled') return;
        try {
            var u = new URL(url);
            var pathSearch = u.pathname + u.search;
            if (mode === 'replaceState') {
                window.history.replaceState({ freemanPage: u.pathname }, '', pathSearch);
            } else {
                window.history.pushState({ freemanPage: u.pathname }, '', pathSearch);
            }
        } catch (e) { /* noop */ }
    }

    function abortInFlight() {
        if (state.abortController) { try { state.abortController.abort(); } catch (e) {} state.abortController = null; }
        state.isLoading = false;
    }

    function stop(reason) {
        state.stopped = true; state.nextUrl = null;
        if (state.observer) state.observer.disconnect();
        if (_iosPollTimer) { clearInterval(_iosPollTimer); _iosPollTimer = null; }
        log('stopped:', reason);
    }

    function teardownUi() {
        removeSkeletons(); removeAuxMessages();
        if (state.observer) { state.observer.disconnect(); state.observer = null; }
        if (state.sentinel && state.sentinel.parentNode) state.sentinel.parentNode.removeChild(state.sentinel);
        state.sentinel = null; state.container = null; state.itemSelector = null;
        state.nextUrl = null; state.stopped = false; state.isLoading = false;
    }

    function makeSkeletonCard() {
        var tag = state.container.tagName === 'UL' ? 'li' : 'div';
        var skel = document.createElement(tag);
        skel.className = 'product bookomers-skeleton wc-block-grid__product';
        skel.setAttribute('aria-hidden', 'true');
        skel.innerHTML = '<div class="bookomers-skel-image"></div><div class="bookomers-skel-line short"></div><div class="bookomers-skel-line"></div><div class="bookomers-skel-line price"></div>';
        return skel;
    }

    function showSkeletons() {
        if (!state.container) return;
        for (var i = 0; i < OPTS.skeletonCount; i++) state.container.appendChild(makeSkeletonCard());
    }

    function removeSkeletons() {
        if (!state.container) return;
        state.container.querySelectorAll('.bookomers-skeleton').forEach(function (s) { s.remove(); });
    }

    function removeAuxMessages() {
        document.querySelectorAll('.bookomers-end-message, .bookomers-error-message').forEach(function (el) { el.remove(); });
    }

    // A11y: aria-live region for announcing newly loaded products to screen
    // readers. A single visually-hidden node is lazily created and reused.
    var _liveRegion = null;
    function getLiveRegion() {
        if (_liveRegion && _liveRegion.parentNode) return _liveRegion;
        _liveRegion = document.createElement('div');
        _liveRegion.setAttribute('role', 'status');
        _liveRegion.setAttribute('aria-live', 'polite');
        _liveRegion.setAttribute('aria-atomic', 'true');
        _liveRegion.style.cssText = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;';
        document.body.appendChild(_liveRegion);
        return _liveRegion;
    }
    function announceLoaded(count) {
        if (!count) return;
        var region = getLiveRegion();
        var tmpl   = (OPTS.announceTemplate || 'Loaded %d more products.');
        region.textContent = tmpl.replace('%d', String(count));
    }

    function showEndMessage() {
        if (!state.sentinel) return;
        var prev = state.sentinel.previousElementSibling;
        if (prev && prev.classList && prev.classList.contains('bookomers-end-message')) return;
        var msg = document.createElement('div');
        msg.className = 'bookomers-end-message';
        msg.textContent = OPTS.endMessage;
        state.sentinel.parentNode.insertBefore(msg, state.sentinel);
    }

    function showErrorMessage() {
        if (!state.sentinel) return;
        var wrap = document.createElement('div');
        wrap.className = 'bookomers-error-message';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = OPTS.loadMoreLabel;
        btn.addEventListener('click', function () { wrap.remove(); loadNext(); });
        wrap.appendChild(document.createTextNode(OPTS.errorMessage + ' '));
        wrap.appendChild(btn);
        state.sentinel.parentNode.insertBefore(wrap, state.sentinel);
    }

    function boot() {
        if (state.mainObserver && state.container) return;
        init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else { boot(); }
    window.addEventListener('load', boot);

    // Elementor widgets + some page-builder grids can mount long after
    // DOMContentLoaded/load. Instead of polling with escalating timeouts,
    // watch the document for product-grid nodes and boot exactly once they
    // appear. Stops observing after a successful boot or after 10 s — at
    // that point the grid really isn't coming.
    if (typeof MutationObserver === 'function' && !state.container) {
        var lateMount = new MutationObserver(function () {
            if (state.container) { lateMount.disconnect(); return; }
            boot();
            if (state.container) { lateMount.disconnect(); }
        });
        lateMount.observe(document.body || document.documentElement, { childList: true, subtree: true });
        setTimeout(function () { lateMount.disconnect(); }, 10000);
    }

    window.addEventListener('popstate', function () { setTimeout(resync, 50); });
    window.addEventListener('pageshow', function (e) { if (e.persisted) setTimeout(boot, 100); });
})();
