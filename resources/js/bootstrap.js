import axios from 'axios';
window.axios = axios;

window.axios.defaults.withCredentials = true;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Read fresh XSRF-TOKEN cookie before every request (prevents stale-token 419s)
window.axios.interceptors.request.use((config) => {
    const token = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
    if (token) {
        config.headers['X-XSRF-TOKEN'] = decodeURIComponent(token);
    }
    return config;
});

// Retry once on 419 (CSRF token mismatch) — covers session-refreshed tokens
window.axios.interceptors.response.use(undefined, (error) => {
    if (error.response?.status === 419 && !error.config.__retried) {
        error.config.__retried = true;
        return window.axios.request(error.config);
    }
    return Promise.reject(error);
});

/**
 * Laravel Echo — real-time WebSocket client via Reverb
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 6001,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 6001,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
