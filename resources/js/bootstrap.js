import axios from 'axios';
window.axios = axios;

window.axios.defaults.withCredentials = true;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Pick up the XSRF-TOKEN cookie that Laravel sets and send it as a header
const token = document.cookie
    .split('; ')
    .find((row) => row.startsWith('XSRF-TOKEN='))
    ?.split('=')[1];

if (token) {
    window.axios.defaults.headers.common['X-XSRF-TOKEN'] = decodeURIComponent(token);
}

/**
 * Laravel Echo — real-time WebSocket client via Reverb
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 6001,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 6001,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
