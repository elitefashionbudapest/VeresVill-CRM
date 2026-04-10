/**
 * Veresvill - Cookie Consent + Google Consent Mode v2
 * Lightweight, GDPR-compliant cookie banner
 */
(function() {
    'use strict';

    var CONSENT_KEY = 'veresvill_cookie_consent';
    var CONSENT_VERSION = 1;

    // =============================================
    // Google Consent Mode v2 - default (deny all)
    // This runs immediately, before any Google tags
    // =============================================
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    window.gtag = gtag;

    var saved = getSavedConsent();

    if (saved) {
        // Visszaallitjuk a mentett consent allapotot
        gtag('consent', 'default', {
            'ad_storage': saved.marketing ? 'granted' : 'denied',
            'ad_user_data': saved.marketing ? 'granted' : 'denied',
            'ad_personalization': saved.marketing ? 'granted' : 'denied',
            'analytics_storage': saved.analytics ? 'granted' : 'denied'
        });
    } else {
        // Alapertelmezett: minden tiltva
        gtag('consent', 'default', {
            'ad_storage': 'denied',
            'ad_user_data': 'denied',
            'ad_personalization': 'denied',
            'analytics_storage': 'denied',
            'wait_for_update': 500
        });
    }

    // =============================================
    // Consent management functions
    // =============================================
    function getSavedConsent() {
        try {
            var data = localStorage.getItem(CONSENT_KEY);
            if (!data) return null;
            var parsed = JSON.parse(data);
            if (parsed.version !== CONSENT_VERSION) return null;
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function saveConsent(analytics, marketing) {
        var data = {
            version: CONSENT_VERSION,
            analytics: analytics,
            marketing: marketing,
            timestamp: new Date().toISOString()
        };
        try {
            localStorage.setItem(CONSENT_KEY, JSON.stringify(data));
        } catch (e) {}

        // Cookie-t is mentunk (backup, server-side olvashatosaghoz)
        var expires = new Date();
        expires.setFullYear(expires.getFullYear() + 1);
        document.cookie = 'veresvill_consent=' + (analytics ? 'a' : '') + (marketing ? 'm' : 'n') +
            '; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
    }

    function updateGoogleConsent(analytics, marketing) {
        gtag('consent', 'update', {
            'ad_storage': marketing ? 'granted' : 'denied',
            'ad_user_data': marketing ? 'granted' : 'denied',
            'ad_personalization': marketing ? 'granted' : 'denied',
            'analytics_storage': analytics ? 'granted' : 'denied'
        });
    }

    function deleteCookies() {
        // Toroljuk a nem szukseges cookie-kat ha a user visszavon
        var cookies = document.cookie.split(';');
        for (var i = 0; i < cookies.length; i++) {
            var name = cookies[i].split('=')[0].trim();
            // Ne toroljuk a sajat consent cookie-nkat
            if (name === 'veresvill_consent') continue;
            // GA / Google Ads cookie-k torlese
            if (name.match(/^(_ga|_gid|_gat|_gcl|_gac|__gads|__gpi|IDE|DSID|FLC|AID|TAID|__Secure)/)) {
                document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/;';
                document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=.' + location.hostname;
            }
        }
    }

    function applyConsent(analytics, marketing) {
        saveConsent(analytics, marketing);
        updateGoogleConsent(analytics, marketing);
        if (!analytics && !marketing) {
            deleteCookies();
        }
        hideBanner();
    }

    // =============================================
    // UI - Banner & Settings
    // =============================================
    function createBanner() {
        var banner = document.createElement('div');
        banner.id = 'cc-banner';
        banner.innerHTML =
            '<div id="cc-inner">' +
                '<div id="cc-text">' +
                    '<strong>Cookie beállítások</strong>' +
                    '<p>Weboldalunk cookie-kat használ a jobb felhasználói élmény és a hirdetések optimalizálása érdekében. ' +
                    'Válassza ki, mely cookie-kat engedélyezi.</p>' +
                '</div>' +
                '<div id="cc-buttons">' +
                    '<button id="cc-settings-btn" type="button">Beállítások</button>' +
                    '<button id="cc-reject-btn" type="button">Csak szükségesek</button>' +
                    '<button id="cc-accept-btn" type="button">Elfogadom mind</button>' +
                '</div>' +
            '</div>' +
            // Settings panel
            '<div id="cc-settings" style="display:none;">' +
                '<div id="cc-settings-inner">' +
                    '<div class="cc-cat">' +
                        '<div class="cc-cat-head">' +
                            '<span class="cc-cat-title">Szükséges cookie-k</span>' +
                            '<span class="cc-always">Mindig aktív</span>' +
                        '</div>' +
                        '<p class="cc-cat-desc">Az oldal működéséhez elengedhetetlen cookie-k. Ezek nélkül az oldal nem tudna megfelelően működni.</p>' +
                    '</div>' +
                    '<div class="cc-cat">' +
                        '<div class="cc-cat-head">' +
                            '<span class="cc-cat-title">Analitikai cookie-k</span>' +
                            '<label class="cc-toggle"><input type="checkbox" id="cc-analytics" checked><span class="cc-slider"></span></label>' +
                        '</div>' +
                        '<p class="cc-cat-desc">Segítenek megérteni, hogyan használják a látogatók az oldalt. Anonimizált adatgyűjtés a webhely fejlesztéséhez.</p>' +
                    '</div>' +
                    '<div class="cc-cat">' +
                        '<div class="cc-cat-head">' +
                            '<span class="cc-cat-title">Marketing cookie-k</span>' +
                            '<label class="cc-toggle"><input type="checkbox" id="cc-marketing" checked><span class="cc-slider"></span></label>' +
                        '</div>' +
                        '<p class="cc-cat-desc">Személyre szabott hirdetések megjelenítésére használjuk (pl. Google Ads). Segítik a releváns hirdetések megjelenítését.</p>' +
                    '</div>' +
                    '<div id="cc-settings-buttons">' +
                        '<button id="cc-save-btn" type="button">Kiválasztottak mentése</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        document.body.appendChild(banner);

        // Event listeners
        document.getElementById('cc-accept-btn').addEventListener('click', function() {
            applyConsent(true, true);
        });

        document.getElementById('cc-reject-btn').addEventListener('click', function() {
            applyConsent(false, false);
        });

        document.getElementById('cc-settings-btn').addEventListener('click', function() {
            var panel = document.getElementById('cc-settings');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        });

        document.getElementById('cc-save-btn').addEventListener('click', function() {
            var analytics = document.getElementById('cc-analytics').checked;
            var marketing = document.getElementById('cc-marketing').checked;
            applyConsent(analytics, marketing);
        });
    }

    function hideBanner() {
        var banner = document.getElementById('cc-banner');
        if (banner) {
            banner.classList.add('cc-hidden');
            setTimeout(function() {
                banner.remove();
            }, 400);
        }
    }

    // =============================================
    // Footer link to re-open settings
    // =============================================
    function addFooterLink() {
        var link = document.createElement('a');
        link.href = '#';
        link.id = 'cc-reopen';
        link.textContent = 'Cookie beállítások';
        link.style.cssText = 'position:fixed;bottom:12px;left:12px;background:#2C3E50;color:#fff;padding:8px 14px;border-radius:20px;font-size:12px;text-decoration:none;z-index:9998;opacity:0.7;transition:opacity 0.2s;';
        link.addEventListener('mouseenter', function() { link.style.opacity = '1'; });
        link.addEventListener('mouseleave', function() { link.style.opacity = '0.7'; });
        link.addEventListener('click', function(e) {
            e.preventDefault();
            localStorage.removeItem(CONSENT_KEY);
            link.remove();
            createBanner();
        });
        document.body.appendChild(link);
    }

    // =============================================
    // Init
    // =============================================
    function init() {
        if (!saved) {
            createBanner();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
