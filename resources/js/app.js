require('./bootstrap');

import Alpine from 'alpinejs';

// Load SortableJS globally (handles default/CommonJS builds)
try {
    const sortableModule = require('sortablejs');
    window.Sortable = sortableModule?.default || sortableModule?.Sortable || sortableModule;
} catch (e) {}

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.store('sound', {
        enabled: JSON.parse(window.localStorage.getItem('lj:sound-enabled') ?? 'true'),
        toggle() {
            this.enabled = !this.enabled;
            window.localStorage.setItem('lj:sound-enabled', JSON.stringify(this.enabled));
            window.dispatchEvent(new CustomEvent('lj:sound-changed', { detail: { enabled: this.enabled } }));
        },
        set(value) {
            this.enabled = !!value;
            window.localStorage.setItem('lj:sound-enabled', JSON.stringify(this.enabled));
        }
    });

    Alpine.data('soundToggle', () => ({
        get soundEnabled() {
            return Alpine.store('sound').enabled;
        },
        toggleSound() {
            Alpine.store('sound').toggle();
        }
    }));
});

Alpine.start();

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
            if (err.error && err.error.data && err.error.data.code === 4009) {
            }
        });

        window.VoiceEcho.connector.pusher.connection.bind('connected', function() {
        });

        window.VoiceEcho.connector.pusher.connection.bind('disconnected', function() {
        });
    }
    return window.VoiceEcho;
}

// Function to create VoiceEcho instance when needed
function createChatEchoInstance() {
    if (!window.ChatEcho) {
        window.ChatEcho = new Echo({
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

        // Add ChatEcho error handling
        window.ChatEcho.connector.pusher.connection.bind('error', function(err) {
            if (err.error && err.error.data && err.error.data.code === 4009) {
            }
        });

        window.ChatEcho.connector.pusher.connection.bind('connected', function() {
        });

        window.ChatEcho.connector.pusher.connection.bind('disconnected', function() {
        });
    }
    return window.VoiceEcho;
}

// Detect which pages need WebSocket connections
function detectWebSocketRequirements() {
    const needsEcho = document.getElementById('journey-data') || document.getElementById('preview-data');
    const needsVoiceEcho = document.getElementById('journey-data-voice');
    const needsChatEcho = document.getElementById('journey-data-chat');
    
    return {
        needsEcho: !!needsEcho,
        needsVoiceEcho: !!needsVoiceEcho,
        needsChatEcho: !!needsChatEcho,
        pageName: needsVoiceEcho ? 'voice-journey' : (needsEcho ? 'chat-journey' : 'other')
    };
}

// Only create WebSocket connections if needed for this page
const wsRequirements = detectWebSocketRequirements();
if (wsRequirements.needsEcho) {
    createEchoInstance();
}

if (wsRequirements.needsVoiceEcho) {
    createVoiceEchoInstance();
}
if (wsRequirements.needsChatEcho) {
    createChatEchoInstance();
}

// Import modules after Echo instances are ready
require('./utili');
try { require('./previewchat'); } catch (e) { /* optional */ }
require('./voice');

// Initialize modules when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Expose Laravel user data from body data attributes (replaces inline script)
    try {
        const body = document.body;
        if (body && body.dataset && body.dataset.userId) {
            window.Laravel = {
                user: {
                    id: parseInt(body.dataset.userId, 10),
                    name: body.dataset.userName || null,
                    email: body.dataset.userEmail || null,
                }
            };
        }
    } catch (e) { /* noop */ }

    // Sidebar toggle and click-away handling
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleButtons = document.querySelectorAll('.js-sidebar-toggle');
    const mobileQuery = window.matchMedia('(max-width: 991.98px)');

    if (sidebar) {
        sidebar.classList.remove('is-open');
        if (mobileQuery.matches) {
            sidebar.style.transform = 'translateX(-100%)';
        }
    }
    document.body.classList.remove('sidebar-open');

    const applySidebarTransform = () => {
        if (!sidebar) return;
        if (mobileQuery.matches) {
            if (sidebar.classList.contains('is-open')) {
                sidebar.style.transform = 'translateX(0)';
            } else {
                sidebar.style.transform = 'translateX(-100%)';
            }
        } else {
            sidebar.style.transform = '';
        }
    };

    const setToggleVisibility = (visible) => {
        if (!toggleButtons || !toggleButtons.length) return;
        toggleButtons.forEach(btn => {
            if (visible) {
                btn.classList.remove('is-hidden');
            } else {
                btn.classList.add('is-hidden');
            }
            try { btn.setAttribute('aria-expanded', (!visible).toString()); } catch (e) {}
        });
    };

    const openSidebar = () => {
        if (!sidebar) return;
        document.body.classList.add('sidebar-open');
        sidebar.classList.add('is-open');
        applySidebarTransform();
        setToggleVisibility(false);
    };

    const closeSidebar = () => {
        if (!sidebar) return;
        document.body.classList.remove('sidebar-open');
        sidebar.classList.remove('is-open');
        applySidebarTransform();
        setToggleVisibility(true);
    };

    const toggleSidebar = () => {
        if (!sidebar) return;
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    };

    const ensureResponsiveSidebarState = () => {
        if (!sidebar) return;
        if (mobileQuery.matches) {
            closeSidebar();
        } else {
            document.body.classList.remove('sidebar-open');
            sidebar.classList.remove('is-open');
            applySidebarTransform();
            setToggleVisibility(true);
        }
    };

    if (mobileQuery.addEventListener) {
        mobileQuery.addEventListener('change', ensureResponsiveSidebarState);
    } else if (mobileQuery.addListener) {
        mobileQuery.addListener(ensureResponsiveSidebarState);
    }

    const scheduleResponsiveCheck = () => {
        ensureResponsiveSidebarState();
        window.setTimeout(ensureResponsiveSidebarState, 100);
        window.setTimeout(ensureResponsiveSidebarState, 300);
    };

    if (toggleButtons && toggleButtons.length) {
        toggleButtons.forEach(btn => btn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleSidebar();
        }));
        // Ensure initial visibility reflects current state per viewport
        scheduleResponsiveCheck();
    } else {
        scheduleResponsiveCheck();
    }

    if (overlay) {
        overlay.addEventListener('click', (e) => {
            e.preventDefault();
            closeSidebar();
        });
    }

    window.addEventListener('load', ensureResponsiveSidebarState);
    window.addEventListener('resize', ensureResponsiveSidebarState);

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });

    // Click-away: click outside sidebar when open closes it
    document.addEventListener('click', (e) => {
        if (!sidebar || !sidebar.classList.contains('is-open')) return;
        const target = e.target;
        const clickedInsideSidebar = sidebar.contains(target);
        const clickedToggle = target.closest && target.closest('.js-sidebar-toggle');
        if (!clickedInsideSidebar && !clickedToggle) {
            closeSidebar();
        }
    });
    
    // Initialize JourneyStartModal on all pages
    if (window.JourneyStartModal) {
        window.JourneyStartModal.init();
    }
    
    // Only initialize JourneyStep if we're on the journey step page
    if (document.getElementById('journey-data') && window.JourneyStep) {
        window.JourneyStep.init();
        // Check if we need to start the journey chat (no messages case)
        const chatContainer = document.getElementById('chatContainer');
        const messages = chatContainer ? chatContainer.querySelectorAll('.message') : [];
        if (messages.length === 0) {
            window.JourneyStep.startJourneyChat();
        }
    }
    
    // Initialize Voice page functionality if we're on the voice journey page
    if (document.getElementById('journey-data-voice') && window.VoiceMode) {
        window.VoiceMode.init();
    }
    
    // Initialize PreviewChat if we're on the preview chat page
    if (document.getElementById('preview-data') && window.PreviewChat) {
        window.PreviewChat.init();
    }
});