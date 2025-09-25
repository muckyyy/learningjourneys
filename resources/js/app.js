require('./bootstrap');

// Load SortableJS globally
try {
    window.Sortable = require('sortablejs');
} catch (e) {}

// Import Echo and Pusher
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Environment-aware WebSocket configuration
const getWebSocketConfig = () => {
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

// Configure WebSocket settings
const config = getWebSocketConfig();
window.webSocketConfig = config;

// Function to create Echo instance when needed
function createEchoInstance() {
    if (!window.Echo) {
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
        console.log('✅ Echo WebSocket instance created');
    }
    return window.Echo;
}

// Function to create VoiceEcho instance when needed
function createVoiceEchoInstance() {
    if (!window.VoiceEcho) {
        window.VoiceEcho = new Echo({
            broadcaster: 'reverb',
            key: config.app_key,
            wsHost: config.host,
            wsPort: config.port,
            wssPort: config.port,
            forceTLS: config.forceTLS,
            encrypted: config.encrypted,
            enabledTransports: config.enabledTransports,
            disableStats: config.disableStats,
            
        });

        // Add VoiceEcho error handling
        window.VoiceEcho.connector.pusher.connection.bind('error', function(err) {
            console.error('Voice WebSocket connection error:', err);
            if (err.error && err.error.data && err.error.data.code === 4009) {
                console.error('Voice WebSocket authentication failed. Please log in.');
            }
        });

        window.VoiceEcho.connector.pusher.connection.bind('connected', function() {
            console.log('Voice WebSocket connected successfully');
        });

        window.VoiceEcho.connector.pusher.connection.bind('disconnected', function() {
            console.log('Voice WebSocket disconnected');
        });
        
        console.log('✅ VoiceEcho WebSocket instance created');
    }
    return window.VoiceEcho;
}

// Detect which pages need WebSocket connections
function detectWebSocketRequirements() {
    const needsEcho = document.getElementById('journey-data') || document.getElementById('preview-data');
    const needsVoiceEcho = document.getElementById('journey-data-voice');
    
    return {
        needsEcho: !!needsEcho,
        needsVoiceEcho: !!needsVoiceEcho,
        pageName: needsVoiceEcho ? 'voice-journey' : (needsEcho ? 'chat-journey' : 'other')
    };
}

// Only create WebSocket connections if needed for this page
const wsRequirements = detectWebSocketRequirements();
console.log(`🔍 WebSocket requirements for ${wsRequirements.pageName} page:`, wsRequirements);

if (wsRequirements.needsEcho) {
    createEchoInstance();
}

if (wsRequirements.needsVoiceEcho) {
    createVoiceEchoInstance();
}

// Import modules after Echo instances are ready
require('./utili');
require('./journeystep');
// chatmode.js was removed; previewchat.js provides PreviewChat now
try { require('./previewchat'); } catch (e) { /* optional */ }
require('./voicemode');

// Initialize modules when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 DOM loaded, initializing modules...');
    
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
        console.log('✅ VoiceMode module initialized');
    }
    
    // Initialize PreviewChat if we're on the preview chat page
    if (document.getElementById('preview-data') && window.PreviewChat) {
        console.log('💬 Preview chat page detected, initializing PreviewChat module...');
        window.PreviewChat.init();
        console.log('✅ PreviewChat module initialized');
    }
});