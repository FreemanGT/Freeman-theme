/*
 * Freeman Theme — tiny front-end enhancer.
 * Kept intentionally small: module JS belongs in Freeman Core.
 */
(function () {
    'use strict';

    // Flag JS availability so CSS can style enhanced vs. basic states.
    document.documentElement.classList.add('freeman-js');

    // Expose a minimal theme event bus for modules / cart drawers to hook into.
    if (!window.Freeman) {
        window.Freeman = {
            version: '1.0.0',
            on: function (event, cb) {
                document.addEventListener('freeman:' + event, cb);
            },
            emit: function (event, detail) {
                document.dispatchEvent(new CustomEvent('freeman:' + event, { detail: detail }));
            }
        };
    }
})();
