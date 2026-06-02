/**
 * Etechflow_AbandonedCart - Storefront popup handler (vanilla JS, IIFE).
 *
 * Shared across Luma and Hyvä:
 *   - Luma loads via RequireJS: `require(['Etechflow_AbandonedCart/js/popup-handler'])`
 *   - Hyvä loads via direct <script src> in the container template
 *
 * Both entry points hit the same IIFE — module self-bootstraps on
 * DOMContentLoaded and never exposes anything to global scope beyond
 * window.etechflowPopupHandlerLoaded (used as a duplicate-load guard).
 *
 * Pipeline on every page:
 *   1. Read JSON config from #etechflow-popup-config
 *   2. GET /etechflow_abandonedcart/popup/get?page_scope=...
 *   3. For the first matching rule, attach the trigger listener
 *      (exit-intent / time-on-page / scroll-depth / cart-subtotal-immediate)
 *   4. When trigger fires: render markup into #etechflow-popup-container,
 *      POST /popup/track to log the impression
 *   5. On CTA click (only if has_discount): POST /popup/apply, show coupon
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
(function () {
    'use strict';

    if (window.etechflowPopupHandlerLoaded) {
        return;
    }
    window.etechflowPopupHandlerLoaded = true;

    var config = null;
    var shown = false;
    var currentRule = null;
    var currentImpressionId = null;
    var cleanupFns = [];

    function readConfig() {
        var el = document.getElementById('etechflow-popup-config');
        if (!el) {
            return null;
        }
        try {
            return JSON.parse(el.textContent || el.innerText || '{}');
        } catch (e) {
            return null;
        }
    }

    function fetchRules() {
        var url = config.urls.get + '?page_scope=' + encodeURIComponent(config.page_scope);
        return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { return (data && data.rules) ? data.rules : []; })
            .catch(function () { return []; });
    }

    function attachTrigger(rule) {
        switch (rule.trigger_type) {
            case 'exit_intent':
                attachExitIntent(rule);
                break;
            case 'time_on_page':
                attachTimeOnPage(rule);
                break;
            case 'scroll_depth':
                attachScrollDepth(rule);
                break;
            case 'cart_subtotal_threshold':
                // Server's matcher already verified subtotal hit — show now.
                showPopup(rule);
                break;
            default:
                // Unknown trigger — silently skip.
                break;
        }
    }

    function attachExitIntent(rule) {
        // Device-aware dispatch: mouseout works only on desktop. Touch
        // devices need a different signal (tab switch + idle fallback).
        if (config.device_type === 'desktop') {
            attachDesktopExitIntent(rule);
        } else {
            attachMobileExitIntent(rule);
        }
    }

    function attachDesktopExitIntent(rule) {
        var handler = function (e) {
            // Fire when mouse leaves the top of the viewport.
            if (e.clientY <= 0 && !shown) {
                showPopup(rule);
            }
        };
        document.addEventListener('mouseout', handler);
        cleanupFns.push(function () { document.removeEventListener('mouseout', handler); });
    }

    function attachMobileExitIntent(rule) {
        // Primary signal: visibilitychange (tab/app switch, screen lock).
        // This is the most reliable "leaving" indicator on mobile browsers.
        var visHandler = function () {
            if (document.visibilityState === 'hidden' && !shown) {
                showPopup(rule);
            }
        };
        document.addEventListener('visibilitychange', visHandler);
        cleanupFns.push(function () {
            document.removeEventListener('visibilitychange', visHandler);
        });

        // Fallback: idle timer. Admin-configurable per rule (default 15s).
        // Set to 0 in the rule to disable the fallback entirely.
        var fallbackSec = parseInt(rule.mobile_fallback_seconds, 10);
        if (isNaN(fallbackSec)) {
            fallbackSec = 15;
        }
        if (fallbackSec > 0) {
            var timer = setTimeout(function () {
                if (!shown) { showPopup(rule); }
            }, fallbackSec * 1000);
            cleanupFns.push(function () { clearTimeout(timer); });
        }
    }

    function attachTimeOnPage(rule) {
        var seconds = parseInt(rule.trigger_value, 10);
        if (isNaN(seconds) || seconds <= 0) {
            seconds = 30;
        }
        var timer = setTimeout(function () {
            if (!shown) { showPopup(rule); }
        }, seconds * 1000);
        cleanupFns.push(function () { clearTimeout(timer); });
    }

    function attachScrollDepth(rule) {
        var target = parseInt(rule.trigger_value, 10);
        if (isNaN(target) || target <= 0) {
            target = 50;
        }
        var handler = function () {
            var doc = document.documentElement;
            var scrollable = doc.scrollHeight - window.innerHeight;
            if (scrollable <= 0) { return; }
            var pct = (window.scrollY / scrollable) * 100;
            if (pct >= target && !shown) {
                showPopup(rule);
            }
        };
        window.addEventListener('scroll', handler, { passive: true });
        cleanupFns.push(function () { window.removeEventListener('scroll', handler); });
    }

    function showPopup(rule) {
        if (shown) { return; }
        shown = true;
        currentRule = rule;

        // Stop other triggers — we have our winner.
        cleanupFns.forEach(function (fn) { try { fn(); } catch (e) {} });
        cleanupFns = [];

        var container = document.getElementById('etechflow-popup-container');
        if (!container) { return; }
        container.innerHTML = renderHtml(rule);
        applyRuleStyles(container, rule);
        container.classList.add('is-open');
        container.classList.add('etechflow-popup--' + (rule.template_layout || 'modal'));
        container.classList.add('etechflow-popup--anim-' + (rule.animation_type || 'zoom_in'));
        document.body.classList.add('etechflow-popup-open');

        var ctaBtn = container.querySelector('.etechflow-popup__cta');
        if (ctaBtn) {
            ctaBtn.addEventListener('click', onCtaClick);
        }
        var closeEls = container.querySelectorAll('.etechflow-popup__close, .etechflow-popup__backdrop');
        for (var i = 0; i < closeEls.length; i++) {
            closeEls[i].addEventListener('click', onClose);
        }

        var keyHandler = function (e) {
            if (e.key === 'Escape') { onClose(); }
        };
        document.addEventListener('keydown', keyHandler);
        cleanupFns.push(function () { document.removeEventListener('keydown', keyHandler); });

        trackImpression(rule.rule_id);
    }

    function trackImpression(ruleId) {
        var body = new URLSearchParams();
        body.append('rule_id', String(ruleId));
        body.append('device_type', config.device_type || 'desktop');

        fetch(config.urls.track, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success && data.impression_id) {
                    currentImpressionId = data.impression_id;
                }
            })
            .catch(function () { /* non-fatal */ });
    }

    function onCtaClick() {
        if (!currentRule) { return; }

        // No discount linked — just close gracefully.
        if (!currentRule.has_discount) {
            onClose();
            return;
        }

        if (!currentImpressionId) {
            // Track hasn't completed yet — disable button and retry briefly.
            setTimeout(onCtaClick, 300);
            return;
        }

        var ctaBtn = document.querySelector('.etechflow-popup__cta');
        if (ctaBtn) {
            ctaBtn.disabled = true;
            ctaBtn.textContent = 'Applying...';
        }

        var body = new URLSearchParams();
        body.append('rule_id', String(currentRule.rule_id));
        body.append('impression_id', String(currentImpressionId));

        fetch(config.urls.apply, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) { renderApplyResult(data); })
            .catch(function () {
                renderApplyResult({ success: false, message: 'network_error' });
            });
    }

    function renderApplyResult(data) {
        var resultEl = document.querySelector('.etechflow-popup__result');
        var ctaBtn = document.querySelector('.etechflow-popup__cta');
        if (!resultEl) { return; }

        resultEl.hidden = false;
        if (data && data.success && data.coupon_code) {
            resultEl.innerHTML =
                '<p>Your discount code <strong>' + escapeHtml(data.coupon_code) + '</strong> has been applied to your cart!</p>';
            if (ctaBtn) { ctaBtn.style.display = 'none'; }
        } else {
            resultEl.textContent = 'Sorry, we could not apply the discount. Please try again later.';
            if (ctaBtn) {
                ctaBtn.disabled = false;
                ctaBtn.textContent = currentRule.cta_text;
            }
        }
    }

    function onClose() {
        var container = document.getElementById('etechflow-popup-container');
        if (container) {
            container.classList.remove('is-open');
            // Strip layout + animation classes so the next show starts clean.
            for (var i = container.classList.length - 1; i >= 0; i--) {
                var cls = container.classList[i];
                if (cls.indexOf('etechflow-popup--') === 0) {
                    container.classList.remove(cls);
                }
            }
            // Defer DOM clear until the close animation has space to run.
            setTimeout(function () {
                if (!container.classList.contains('is-open')) {
                    container.innerHTML = '';
                }
            }, 300);
        }
        document.body.classList.remove('etechflow-popup-open');
        cleanupFns.forEach(function (fn) { try { fn(); } catch (e) {} });
        cleanupFns = [];
    }

    /**
     * Push admin-configured colors + dimensions onto the container as
     * CSS custom properties. The stylesheet reads them via var() so
     * the same rules work across all 4 layouts without inline styling
     * on individual elements.
     */
    function applyRuleStyles(container, rule) {
        var s = container.style;
        s.setProperty('--etechflow-popup-bg',           rule.bg_color           || '#ffffff');
        s.setProperty('--etechflow-popup-headline',     rule.headline_color     || '#0f172a');
        s.setProperty('--etechflow-popup-body',         rule.body_color         || '#374151');
        s.setProperty('--etechflow-popup-cta-bg',       rule.cta_bg_color       || '#0f172a');
        s.setProperty('--etechflow-popup-cta-text',     rule.cta_text_color     || '#ffffff');
        s.setProperty('--etechflow-popup-radius',       (parseInt(rule.border_radius, 10) || 12) + 'px');
        s.setProperty('--etechflow-popup-width',        (parseInt(rule.dialog_width, 10) || 480) + 'px');
    }

    function renderHtml(rule) {
        var img = rule.image_url
            ? '<img src="' + escapeAttr(rule.image_url) + '" alt="" class="etechflow-popup__image">'
            : '';
        // Body allows HTML per the admin-form notice; admin is trusted.
        var bodyHtml = rule.body
            ? '<div class="etechflow-popup__body">' + rule.body + '</div>'
            : '';
        return ''
            + '<div class="etechflow-popup__backdrop" aria-hidden="true"></div>'
            + '<div class="etechflow-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="etechflow-popup-headline">'
            +   '<button type="button" class="etechflow-popup__close" aria-label="Close">&times;</button>'
            +   img
            +   '<h2 id="etechflow-popup-headline" class="etechflow-popup__headline">' + escapeHtml(rule.headline || '') + '</h2>'
            +   bodyHtml
            +   '<button type="button" class="etechflow-popup__cta">' + escapeHtml(rule.cta_text || 'Continue') + '</button>'
            +   '<div class="etechflow-popup__result" hidden></div>'
            + '</div>';
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function escapeAttr(s) {
        return escapeHtml(s);
    }

    function bootstrap() {
        config = readConfig();
        if (!config || !config.enabled || !config.urls) {
            return;
        }
        fetchRules().then(function (rules) {
            if (!rules || rules.length === 0) { return; }
            // Repo already returns priority-sorted; first wins.
            attachTrigger(rules[0]);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
})();
