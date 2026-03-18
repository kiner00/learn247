/**
 * Google Analytics 4 (GA4) composable.
 *
 * Uses gtag.js with the creator's G-XXXXXXXXXX Measurement ID.
 *
 * Standard events:
 *   page_view     — every page visit
 *   view_item     — landing page view (ViewContent equivalent)
 *   begin_checkout — join form submitted (Lead equivalent)
 *   purchase      — payment confirmed
 */
export function useGoogleAnalytics(measurementId) {
    function init() {
        if (!measurementId || typeof window === 'undefined') return;
        if (window[`ga-disabled-${measurementId}`] !== undefined) return; // already loaded

        const script = document.createElement('script');
        script.async = true;
        script.src   = `https://www.googletagmanager.com/gtag/js?id=${measurementId}`;
        document.head.appendChild(script);

        window.dataLayer = window.dataLayer || [];
        window.gtag      = function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', measurementId, { send_page_view: false });
    }

    function gtag(...args) {
        if (typeof window === 'undefined' || !window.gtag) return;
        window.gtag(...args);
    }

    function pageView(path) {
        gtag('event', 'page_view', {
            page_location: path ?? window.location.href,
            send_to:       measurementId,
        });
    }

    function viewContent(params = {}) {
        gtag('event', 'view_item', { send_to: measurementId, ...params });
    }

    function lead(params = {}) {
        gtag('event', 'begin_checkout', { send_to: measurementId, ...params });
    }

    function purchase(params = {}) {
        // GA4 purchase expects: transaction_id, value, currency, items[]
        gtag('event', 'purchase', { send_to: measurementId, ...params });
    }

    return { init, pageView, viewContent, lead, purchase };
}
