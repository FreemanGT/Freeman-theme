(function ($) {
    'use strict';

    /* =============================================================
       INIT — run on ready AND observe late-added forms (Elementor).
       ============================================================= */
    function init() {
        bindEvents();
        initVariations();

        // Re-check after a short delay for builders that render async.
        setTimeout(function () {
            initVariations();
        }, 1000);
    }

    function bindEvents() {
        // Use delegated events so they work on any form, even injected late.
        $(document).on('click', '.rsn-submit-btn', handleSubmit);
        $(document).on('keypress', '.rsn-input', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                $(this).closest('.rsn-form-card').find('.rsn-submit-btn').trigger('click');
            }
        });
    }

    /* =============================================================
       FORM SUBMISSION
       ============================================================= */
    // Translatable strings — provided by rsn_ajax.i18n via wp_localize_script.
    // Hebrew fallbacks are embedded so the form still works if the localize
    // payload is somehow stripped (e.g. an aggressive minifier on a custom build).
    function t(key, fallback) {
        return (typeof rsn_ajax !== 'undefined' && rsn_ajax.i18n && rsn_ajax.i18n[key]) || fallback;
    }

    function handleSubmit() {
        var $btn  = $(this);
        var $card = $btn.closest('.rsn-form-card');
        var $wrap = $btn.closest('.rsn-form-wrap');

        var name  = $card.find('.rsn-name').val().trim();
        var email = $card.find('.rsn-email').val().trim();

        if (!email || !isValidEmail(email)) {
            showError($card, t('invalidEmail', 'יש להזין כתובת אימייל תקינה.'));
            $card.find('.rsn-email').focus();
            return;
        }

        var $gdpr = $card.find('.rsn-gdpr-check');
        if ($gdpr.length && !$gdpr.is(':checked')) {
            showError($card, t('consentMissing', 'יש לאשר את תיבת ההסכמה.'));
            return;
        }

        // Read IDs from the data attributes.
        var productId   = parseInt($wrap.attr('data-product-id'), 10) || 0;
        var variationId = parseInt($wrap.attr('data-variation-id'), 10) || 0;

        if (!productId) {
            showError($card, t('productMissing', 'שגיאה: מזהה מוצר חסר.'));
            return;
        }

        $btn.addClass('rsn-loading');
        hideError($card);

        // Ensure rsn_ajax is available.
        if (typeof rsn_ajax === 'undefined') {
            showError($card, 'שגיאה: הסקריפט לא נטען כראוי. רענן את הדף.');
            $btn.removeClass('rsn-loading');
            return;
        }

        $.ajax({
            url: rsn_ajax.url,
            type: 'POST',
            data: {
                action:       'rsn_subscribe',
                nonce:        rsn_ajax.nonce,
                product_id:   productId,
                variation_id: variationId,
                name:         name,
                email:        email,
                gdpr:         $gdpr.length && $gdpr.is(':checked') ? 'yes' : 'no',
                _hp:          $card.find('.rsn-hp').val() || ''
            },
            success: function (response) {
                $btn.removeClass('rsn-loading');

                if (response.success) {
                    $card.find('.rsn-form-fields').slideUp(250, function () {
                        $card.find('.rsn-form-desc').slideUp(150);
                        $card.find('.rsn-form-success').removeClass('rsn-hidden').hide().fadeIn(300);
                    });
                } else {
                    var msg = (response.data && response.data.message)
                        ? response.data.message
                        : t('genericError', 'משהו השתבש. נסו שוב.');

                    if (response.data && response.data.duplicate) {
                        $card.find('.rsn-form-fields').slideUp(250, function () {
                            $card.find('.rsn-form-desc').slideUp(150);
                            $card.find('.rsn-success-text').text(msg);
                            $card.find('.rsn-form-success').removeClass('rsn-hidden').hide().fadeIn(300);
                        });
                    } else {
                        showError($card, msg);
                    }
                }
            },
            error: function () {
                $btn.removeClass('rsn-loading');
                showError($card, t('networkError', 'שגיאת רשת. נסו שוב.'));
            }
        });
    }

    /* =============================================================
       VARIATION HANDLING
       Uses OUR oos_variation_ids list — does NOT trust WooCommerce's
       variation.is_in_stock which can be wrong when parent manages stock.
       ============================================================= */
    function initVariations() {
        if (typeof rsn_variations === 'undefined') return;

        var $form = $('form.variations_form');
        var $wrap = $('.rsn-form-wrap.rsn-variable-product');

        if (!$wrap.length) return;

        // Build a fast lookup set of OOS variation IDs (as integers).
        var oosSet = {};
        for (var i = 0; i < rsn_variations.oos_variation_ids.length; i++) {
            oosSet[parseInt(rsn_variations.oos_variation_ids[i], 10)] = true;
        }

        // If ALL variations are out of stock, show immediately.
        if (rsn_variations.all_oos) {
            $wrap.removeClass('rsn-hidden');
        }

        // If there's no WC variation form (Elementor may not have it),
        // and all are OOS, the form is already visible.
        if (!$form.length) return;

        // Already bound? Don't re-bind.
        if ($form.data('rsn-bound')) return;
        $form.data('rsn-bound', true);

        $form.on('found_variation', function (e, variation) {
            var vid = parseInt(variation.variation_id, 10);

            // ── KEY FIX: Check OUR list, NOT variation.is_in_stock ──
            // WooCommerce's is_in_stock can be true even when the variation
            // is actually OOS (when parent manages stock with qty > 0).
            var isOurOOS = oosSet.hasOwnProperty(vid);

            if (isOurOOS) {
                // Variation is out of stock — show the notification form.
                $wrap.attr('data-variation-id', vid);
                resetForm($wrap);
                $wrap.removeClass('rsn-hidden').hide().slideDown(300);
            } else {
                // Variation is in stock — hide the form.
                $wrap.slideUp(200, function () {
                    $wrap.addClass('rsn-hidden');
                });
            }
        });

        $form.on('reset_data', function () {
            // No variation selected — hide unless all are OOS.
            if (!rsn_variations.all_oos) {
                $wrap.slideUp(200, function () {
                    $wrap.addClass('rsn-hidden');
                });
            }
        });
    }

    /* =============================================================
       HELPERS
       ============================================================= */
    function resetForm($wrap) {
        var $card = $wrap.find('.rsn-form-card');
        $card.find('.rsn-form-fields').show();
        $card.find('.rsn-form-desc').show();
        $card.find('.rsn-form-success').addClass('rsn-hidden');
        $card.find('.rsn-form-error').addClass('rsn-hidden');
        $card.find('.rsn-submit-btn').removeClass('rsn-loading');
    }

    function showError($card, msg) {
        var $error = $card.find('.rsn-form-error');
        $error.find('.rsn-error-text').text(msg);
        $error.removeClass('rsn-hidden').hide().fadeIn(200);
    }

    function hideError($card) {
        $card.find('.rsn-form-error').addClass('rsn-hidden');
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    /* =============================================================
       BOOT
       ============================================================= */
    $(document).ready(init);

    // Also run when the window fully loads (images, iframes, etc.)
    // This catches cases where Elementor finishes rendering after DOMContentLoaded.
    $(window).on('load', function () {
        initVariations();
    });

})(jQuery);
