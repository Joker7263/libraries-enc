<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Shaka Player - DRM TV Streams</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0a;
            color: #fff;
            height: 100vh;
            overflow: hidden;
        }

        .app {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #2a2a3e;
            z-index: 10;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo-icon {
            font-size: 28px;
        }

        .logo-text {
            font-size: 20px;
            font-weight: bold;
            background: linear-gradient(45deg, #e65c00, #ff9a44);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .now-playing {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(0,0,0,0.5);
            padding: 6px 14px;
            border-radius: 30px;
        }

        .live-badge {
            background: #e74c3c;
            color: white;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 4px;
            letter-spacing: 1px;
        }

        .channel-name {
            font-size: 14px;
            font-weight: 500;
        }

        .menu-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .menu-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        /* Video Container */
        .video-container {
            flex: 1;
            position: relative;
            background: #000;
            min-height: 0;
        }

        #videoPlayer {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
        }

        /* Loading Overlay */
        .video-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 16px;
            z-index: 5;
            transition: opacity 0.3s;
        }

        .video-overlay.hide {
            opacity: 0;
            pointer-events: none;
        }

        .loading-spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #333;
            border-top-color: #e65c00;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Error Message */
        .error-message {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(231, 76, 60, 0.95);
            border-left: 4px solid #ff0000;
            padding: 12px 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 10;
            font-size: 13px;
            backdrop-filter: blur(10px);
        }

        .error-message button {
            background: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            color: #e74c3c;
            font-weight: bold;
            cursor: pointer;
            margin-left: auto;
        }

        /* Sidebar - Televizo Style */
        .sidebar {
            position: fixed;
            top: 0;
            right: -100%;
            width: 85%;
            max-width: 350px;
            height: 100%;
            background: #111216;
            z-index: 100;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
            box-shadow: -5px 0 25px rgba(0,0,0,0.5);
        }

        .sidebar.open {
            right: 0;
        }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 20px;
            border-bottom: 1px solid #2a2a2a;
            background: #0d0d12;
        }

        .sidebar-header h3 {
            font-size: 18px;
        }

        .close-sidebar {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
        }

        .channel-search {
            padding: 12px 16px;
            border-bottom: 1px solid #2a2a2a;
        }

        .channel-search input {
            width: 100%;
            padding: 10px 14px;
            background: #1e1e24;
            border: 1px solid #333;
            border-radius: 10px;
            color: white;
            font-size: 14px;
        }

        .channel-search input:focus {
            outline: none;
            border-color: #e65c00;
        }

        .channels-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px 0;
        }

        .channel-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.2s;
            border-left: 3px solid transparent;
        }

        .channel-item:hover {
            background: #1a1a22;
        }

        .channel-item.active {
            background: #1a1a2e;
            border-left-color: #e65c00;
        }

        .channel-logo {
            width: 40px;
            height: 40px;
            background: #2a2a32;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .channel-info {
            flex: 1;
            min-width: 0;
        }

        .channel-name-side {
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .channel-category {
            font-size: 11px;
            color: #888;
            margin-top: 2px;
        }

        .drm-badge {
            background: #ff9800;
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 8px;
            color: #000;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 90;
            display: none;
        }

        .overlay.active {
            display: block;
        }

        @media (max-width: 480px) {
            .now-playing {
                max-width: 50%;
            }
            .channel-name {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }
    </style>
    <!-- Shaka Player CDN -->
    <script src="https://cdn.jsdelivr.net/npm/shaka-player@4.11.2/dist/shaka-player.compiled.js"></script>
</head>
<body>
<div class="app">
    <div class="header">
        <div class="logo">
            <span class="logo-icon">📺</span>
            <span class="logo-text">ShakaTV</span>
        </div>
        <div class="now-playing">
            <span class="live-badge">LIVE</span>
            <span class="channel-name" id="currentChannelName">Loading...</span>
        </div>
        <button class="menu-btn" id="menuBtn">☰</button>
    </div>
    <div class="video-container">
        <video id="videoPlayer" controls autoplay playsinline></video>
        <div class="video-overlay" id="videoOverlay">
            <div class="loading-spinner"></div>
            <span>Loading stream...</span>
        </div>
        <div class="error-message" id="errorMessage" style="display: none;">
            <span>⚠️</span>
            <span id="errorText"></span>
            <button id="closeError">×</button>
        </div>
    </div>
</div>
<div class="overlay" id="overlay"></div>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3>📡 All Channels</h3>
        <button class="close-sidebar" id="closeSidebar">×</button>
    </div>
    <div class="channel-search">
        <input type="text" id="searchInput" placeholder="Search channels...">
    </div>
    <div class="channels-list" id="channelsList">
        <div style="padding:20px;text-align:center;">Loading channels...</div>
    </div>
</div>

<script>
    // ============================================
    // SHAKA PLAYER TV - DRM Support
    // ============================================

    const GITHUB_JSON_URL = "https://raw.githubusercontent.com/Joker7263/Nba-live/refs/heads/main/all%20channel.json";
    
    let channels = [];
    let currentChannelIndex = 0;
    let player = null;
    let isDRMSupported = false;

    // Check if browser supports Widevine
    function checkDRMSupport() {
        if (shaka.Player.isBrowserSupported()) {
            const manifest = shaka.util.Platform.supportMediaSource();
            if (manifest) {
                isDRMSupported = true;
                return true;
            }
        }
        return false;
    }

    // Initialize Shaka Player
    function initPlayer() {
        if (!checkDRMSupport()) {
            showError("Your browser doesn't support Shaka Player or MediaSource Extensions.");
            return false;
        }

        const video = document.getElementById('videoPlayer');
        player = new shaka.Player(video);

        // Error handling
        player.addEventListener('error', onPlayerError);
        player.addEventListener('buffering', onBuffering);
        player.addEventListener('loading', onLoading);
        
        return true;
    }

    function onPlayerError(event) {
        const error = event.detail;
        console.error('Shaka Player Error:', error);
        
        let errorMsg = "Playback error";
        if (error && error.code === 6012) {
            errorMsg = "DRM license error. This stream may require a valid license URL.";
        } else if (error && error.message) {
            errorMsg = error.message.substring(0, 100);
        }
        showError(errorMsg);
        hideOverlay();
    }

    function onBuffering() {
        showOverlay();
    }

    function onLoading() {
        showOverlay();
    }

    function showOverlay() {
        const overlay = document.getElementById('videoOverlay');
        if (overlay) overlay.classList.remove('hide');
    }

    function hideOverlay() {
        const overlay = document.getElementById('videoOverlay');
        if (overlay) overlay.classList.add('hide');
    }

    function showError(msg) {
        const errorDiv = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        if (errorDiv && errorText) {
            errorText.innerText = msg;
            errorDiv.style.display = 'flex';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 8000);
        }
    }

    // Convert DRM keys to Shaka format
    function getDrmConfiguration(channel) {
        if (!channel.drm) return null;
        
        const drmKeys = channel.drm;
        const clearkeys = {};
        
        // Handle different DRM formats
        if (drmKeys.keys && Array.isArray(drmKeys.keys)) {
            // Format: { keys: [{ kid, k }] }
            for (const key of drmKeys.keys) {
                if (key.kid && key.k) {
                    clearkeys[key.kid] = key.k;
                }
            }
        } else {
            // Format: { "keyid": "key" }
            for (const [kid, key] of Object.entries(drmKeys)) {
                clearkeys[kid] = key;
            }
        }
        
        if (Object.keys(clearkeys).length === 0) return null;
        
        return {
            'clearkey': {
                keyIds: Object.keys(clearkeys),
                clearkeys: clearkeys
            }
        };
    }

    // Get stream URL (with fallback for standby URLs)
    function getStreamUrl(channel) {
        let url = channel.streamUrl || '';
        
        // For YouTube channels
        if (channel.type === 'youtube-channel' && url.startsWith('@')) {
            return null;
        }
        
        // For channels with standby URLs (xxip9.top, kstv.us)
        if ((!url || url.includes('xxip9.top') || url.includes('kstv.us')) && 
            channel.standbyUrls && channel.standbyUrls.length > 0) {
            return channel.standbyUrls[0];
        }
        
        return url;
    }

    // Load channel with Shaka Player
    async function loadChannel(index) {
        if (!player) {
            if (!initPlayer()) return;
        }
        
        if (index < 0 || index >= channels.length) return;
        
        currentChannelIndex = index;
        const channel = channels[index];
        if (!channel) return;
        
        // Update UI
        document.getElementById('currentChannelName').innerText = channel.name;
        document.title = `ShakaTV - ${channel.name}`;
        
        // Update sidebar active state
        document.querySelectorAll('.channel-item').forEach(item => {
            item.classList.remove('active');
            if (parseInt(item.dataset.index) === index) {
                item.classList.add('active');
            }
        });
        
        const streamUrl = getStreamUrl(channel);
        const hasDrm = channel.drm && Object.keys(channel.drm).length > 0;
        const drmConfig = getDrmConfiguration(channel);
        
        if (!streamUrl) {
            showError(`${channel.name}: No stream URL available.`);
            hideOverlay();
            return;
        }
        
        showOverlay();
        
        try {
            // Configure DRM if needed
            if (hasDrm && drmConfig) {
                console.log('Configuring DRM for:', channel.name);
                await player.configure({
                    drm: drmConfig
                });
            } else {
                // Reset DRM config for non-DRM streams
                await player.configure({
                    drm: { clearkey: null }
                });
            }
            
            // Load the stream
            await player.load(streamUrl);
            hideOverlay();
            console.log('Stream loaded successfully:', channel.name);
            
        } catch (error) {
            console.error('Failed to load stream:', error);
            hideOverlay();
            
            let errorMsg = error.message || 'Unknown error';
            if (errorMsg.includes('CORS')) {
                errorMsg = "CORS error: The stream server doesn't allow access from this domain.";
            } else if (errorMsg.includes('DRM') || errorMsg.includes('license')) {
                errorMsg = "DRM error: This channel requires a valid license. Try downloading the M3U file for external player.";
            }
            showError(`${channel.name}: ${errorMsg}`);
        }
    }

    // Get channel logo emoji
    function getChannelLogo(name, category) {
        const emojiMap = {
            'GMA': '📺', 'TV5': '📡', 'ABS': '⭐', 'Kapatid': '🔴',
            'HBO': '🎬', 'Movies': '🎥', 'Cinema': '🍿', 'News': '📰',
            'Sports': '⚽', 'Music': '🎵', 'Kids': '🧸', 'Anime': '🎌',
            'Documentary': '🌍', 'Lifestyle': '🏠', 'Religious': '⛪'
        };
        for (let [key, emoji] of Object.entries(emojiMap)) {
            if (name.includes(key)) return emoji;
        }
        if (category === 'Movies') return '🎬';
        if (category === 'Sports') return '⚽';
        if (category === 'News') return '📰';
        return '📡';
    }

    // Render channel list
    function renderChannels(searchTerm = '') {
        const container = document.getElementById('channelsList');
        if (!container) return;
        
        let filteredChannels = channels;
        if (searchTerm) {
            filteredChannels = channels.filter(ch => 
                ch.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                (ch.category && ch.category.toLowerCase().includes(searchTerm.toLowerCase()))
            );
        }
        
        if (filteredChannels.length === 0) {
            container.innerHTML = '<div style="padding: 20px; text-align: center; color: #888;">No channels found</div>';
            return;
        }
        
        let html = '';
        filteredChannels.forEach((channel) => {
            const originalIndex = channels.findIndex(c => c.name === channel.name);
            const isActive = (originalIndex === currentChannelIndex);
            const logo = getChannelLogo(channel.name, channel.category);
            const category = channel.category || 'Uncategorized';
            const hasDrm = channel.drm && Object.keys(channel.drm).length > 0;
            const drmBadge = hasDrm ? '<span class="drm-badge">🔒 DRM</span>' : '';
            
            html += `
                <div class="channel-item ${isActive ? 'active' : ''}" data-index="${originalIndex}">
                    <div class="channel-logo">${logo}</div>
                    <div class="channel-info">
                        <div class="channel-name-side">${escapeHtml(channel.name)} ${drmBadge}</div>
                        <div class="channel-category">${escapeHtml(category)}</div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
        // Add click handlers
        document.querySelectorAll('.channel-item').forEach(item => {
            item.addEventListener('click', () => {
                const idx = parseInt(item.dataset.index);
                if (!isNaN(idx) && idx !== currentChannelIndex) {
                    loadChannel(idx);
                }
                closeSidebar();
            });
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Sidebar functions
    function openSidebar() {
        document.getElementById('sidebar').classList.add('open');
        document.getElementById('overlay').classList.add('active');
    }

    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('overlay').classList.remove('active');
    }

    // Load channels from GitHub
    async function loadChannels() {
        try {
            const response = await fetch(GITHUB_JSON_URL);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            channels = data.channels || [];
            
            if (channels.length === 0) throw new Error('No channels found');
            
            renderChannels();
            
            // Initialize player and load first channel
            if (initPlayer()) {
                // Find first channel with a valid stream URL
                let firstValidIndex = 0;
                for (let i = 0; i < channels.length; i++) {
                    const url = getStreamUrl(channels[i]);
                    if (url && !channels[i].type?.includes('youtube')) {
                        firstValidIndex = i;
                        break;
                    }
                }
                loadChannel(firstValidIndex);
            }
        } catch (error) {
            document.getElementById('channelsList').innerHTML = `<div style="padding:20px;text-align:center;color:#e74c3c;">⚠️ Failed to load channels: ${error.message}</div>`;
            console.error(error);
        }
    }

    // Event listeners
    document.getElementById('menuBtn').addEventListener('click', openSidebar);
    document.getElementById('closeSidebar').addEventListener('click', closeSidebar);
    document.getElementById('overlay').addEventListener('click', closeSidebar);
    document.getElementById('closeError').addEventListener('click', () => {
        document.getElementById('errorMessage').style.display = 'none';
    });
    
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => renderChannels(e.target.value));
    }
    
    // Handle page visibility (resume playback when tab becomes visible)
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && player && player.isLive()) {
            // Optionally refresh the stream
        }
    });
    
    // Start the app
    loadChannels();
</script>
</body>
</html>
