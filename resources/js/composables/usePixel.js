/**
 * Facebook Pixel composable.
 *
 * Usage:
 *   const pixel = usePixel('1234567890');
 *   pixel.pageView();
 *   pixel.viewContent({ content_name: 'My Community' });
 *   pixel.lead();
 *   pixel.purchase({ value: 1000, currency: 'PHP' });
 */
export function usePixel(pixelId) {
    function init() {
        if (!pixelId || typeof window === 'undefined') return;
        if (window.fbq) return; // already bootstrapped

        /* eslint-disable */
        !function(f,b,e,v,n,t,s){
            if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)
        }(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
        /* eslint-enable */

        window.fbq('init', pixelId);
    }

    function track(event, params = {}) {
        if (typeof window === 'undefined' || !window.fbq) return;
        window.fbq('track', event, params);
    }

    function pageView() {
        track('PageView');
    }

    function viewContent(params = {}) {
        track('ViewContent', params);
    }

    function lead(params = {}) {
        track('Lead', params);
    }

    function purchase(params = {}) {
        track('Purchase', params);
    }

    return { init, pageView, viewContent, lead, purchase };
}
