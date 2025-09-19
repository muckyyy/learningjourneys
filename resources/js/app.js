require('./bootstrap');

// Load SortableJS globally
try {
    window.Sortable = require('sortablejs');
} catch (e) {}

// Import Echo and Pusher for WebSocket functionality
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher class available globally for Blade templates
window.Pusher = Pusher;

// WebSocket Application Logic
// Environment-aware WebSocket configuration
const getWebSocketConfig = () => {
    // For local development
    const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    
    if (isLocal) {
        return {
            app_key: process.env.MIX_VITE_REVERB_APP_KEY || 'ez8fmlurx5ekx7vdiocj',
            host: '127.0.0.1',
            port: 8080,
            scheme: 'http',
            forceTLS: false,
            encrypted: false,
            disableStats: true,
            enabledTransports: ['ws', 'wss']
        };
    }
    
    // For production - use compiled environment variables
    return {
        app_key: process.env.MIX_VITE_REVERB_APP_KEY || 'ez8fmlurx5ekx7vdiocj',
        host: process.env.MIX_VITE_REVERB_HOST || 'the-thinking-course.com',
        port: parseInt(process.env.MIX_VITE_REVERB_PORT) || 443,
        scheme: process.env.MIX_VITE_REVERB_SCHEME || 'https',
        forceTLS: (process.env.MIX_VITE_REVERB_SCHEME || 'https') === 'https',
        encrypted: true,
        disableStats: true,
        enabledTransports: ['ws', 'wss']
    };
};

// Create and configure WebSocket settings
const config = getWebSocketConfig();
window.webSocketConfig = config;

// Create Echo instance with environment-aware configuration
const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: config.app_key,
    wsHost: config.host,
    wsPort: config.port,
    wssPort: config.port,
    forceTLS: config.forceTLS,
    encrypted: config.encrypted,
    enabledTransports: config.enabledTransports,
    disableStats: config.disableStats,
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    }
});

// Add global error handling for authentication failures
window.Echo.connector.pusher.connection.bind('error', function(err) {
    if (err.error && err.error.data && err.error.data.code === 4009) {
        console.error('WebSocket authentication failed. Please log in.');
    }
});

