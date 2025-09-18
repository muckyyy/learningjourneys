window._ = require('lodash');

try {
    require('bootstrap');
} catch (e) {}

// Load SortableJS globally
try {
    window.Sortable = require('sortablejs');
} catch (e) {}

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = require('axios');

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: process.env.MIX_VITE_REVERB_APP_KEY || 'ez8fmlurx5ekx7vdiocj',
    wsHost: process.env.MIX_VITE_REVERB_HOST || 'the-thinking-course.com',
    wsPort: process.env.MIX_VITE_REVERB_PORT || 443,
    wssPort: process.env.MIX_VITE_REVERB_PORT || 443,
    forceTLS: (process.env.MIX_VITE_REVERB_SCHEME === 'https') || true,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    }
});
