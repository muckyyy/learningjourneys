require('./bootstrap');

// Load SortableJS globally
try {
    window.Sortable = require('sortablejs');
} catch (e) {}

// Import our separated modules
require('./utili');
require('./chatmode');
require('./voicemode');

// Import Echo and Pusher for WebSocket functionality
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher class available globally for Blade templates
window.Pusher = Pusher;

// WebSocket Application Logic
// Environment-aware WebSocket configuration
const getWebSocketConfig = () => {
    // For local development - expanded local hostname detection
    const isLocal = window.location.hostname === 'localhost' || 
                   window.location.hostname === '127.0.0.1' ||
                   window.location.hostname.endsWith('.local') ||
                   window.location.port === '8000' ||
                   window.location.hostname === 'learningjourneys.test';
    
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

// Initialize modules when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize JourneyStartModal on all pages
    if (window.JourneyStartModal) {
        window.JourneyStartModal.init();
        console.log('✅ JourneyStartModal module initialized');
    }
    
    // Only initialize JourneyStep if we're on the journey step page
    if (document.getElementById('journey-data') && window.JourneyStep) {
        console.log('🎯 Journey step page detected, initializing JourneyStep module...');
        
        window.JourneyStep.init();
        console.log('✅ JourneyStep module initialized');
        
        // Check if we need to start the journey chat (no messages case)
        const chatContainer = document.getElementById('chatContainer');
        const messages = chatContainer ? chatContainer.querySelectorAll('.message') : [];
        
        console.log('🔍 Found', messages.length, 'existing messages');
        
        if (messages.length === 0) {
            console.log('🚀 No messages found, starting journey chat automatically...');
            window.JourneyStep.startJourneyChat();
        }
    }
    
    // Initialize Voice page functionality if we're on the voice journey page
    if (document.getElementById('journey-data-voice') && window.VoiceMode) {
        console.log('🎤 Voice journey page detected, initializing voice functionality...');
        window.VoiceMode.init();
    }
});