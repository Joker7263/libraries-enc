<?php
// ============================================
// WebTV - Televizo Style Interface
// Fetches channels directly from GitHub JSON
// ============================================

// GitHub raw JSON URL (palitan mo kung iba ang repo mo)
$githubJsonUrl = "https://raw.githubusercontent.com/Joker7263/Nba-live/refs/heads/main/all%20channel.json";

// Fetch channels from GitHub
$channels = [];
$error = null;

$jsonContent = @file_get_contents($githubJsonUrl);

if ($jsonContent === false) {
    // Try cURL if file_get_contents fails
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $githubJsonUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $jsonContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $error = "Failed to fetch channels. HTTP Code: " . $httpCode;
        }
    } else {
        $error = "Unable to fetch channels. Please enable allow_url_fopen or cURL on your server.";
    }
}

if ($jsonContent && !$error) {
    $data = json_decode($jsonContent, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($data['channels'])) {
        $channels = $data['channels'];
    } else {
        $error = "Invalid JSON format from GitHub.";
    }
}

// Get selected channel index from URL
$selectedIndex = isset($_GET['channel']) ? (int)$_GET['channel'] : 0;
if ($selectedIndex < 0 || empty($channels)) $selectedIndex = 0;
if ($selectedIndex >= count($channels)) $selectedIndex = 0;

$selectedChannel = !empty($channels) ? $channels[$selectedIndex] : null;

// Helper function to get playable URL
function getStreamUrl($channel) {
    if (!$channel) return '';
    $url = $channel['streamUrl'] ?? '';
    
    // For channels with standby URLs, use the first one
    if ((empty($url) || strpos($url, 'xxip9.top') !== false || strpos($url, 'kstv.us') !== false) && 
        !empty($channel['standbyUrls'])) {
        return $channel['standbyUrls'][0];
    }
    return $url;
}

$currentStreamUrl = $selectedChannel ? getStreamUrl($selectedChannel) : '';
$hasDrm = $selectedChannel && isset($selectedChannel['drm']) && !empty($selectedChannel['drm']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <title>WebTV - <?php echo $selectedChannel ? htmlspecialchars($selectedChannel['name']) : 'Channel Player'; ?></title>
    <style>
        /* ============================================
           CSS STYLES - Televizo Style Interface
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
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

        /* Video Area */
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

        .drm-warning {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(0,0,0,0.9);
            border-left: 4px solid #ff9800;
            padding: 14px 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            z-index: 10;
            backdrop-filter: blur(10px);
            font-size: 13px;
        }

        .drm-warning button {
            background: #ff9800;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            color: #000;
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

        /* Overlay when sidebar is open */
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

        /* Error Message */
        .error-message {
            background: #2a0a0a;
            border-left: 4px solid #e74c3c;
            padding: 16px;
            margin: 20px;
            border-radius: 8px;
            text-align: center;
        }

        /* Responsive */
        @media (min-width: 768px) {
            .sidebar {
                width: 320px;
            }
            .channel-name {
                font-size: 16px;
            }
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
</head>
<body>
    <div class="app">
        <div class="header">
            <div class="logo">
                <span class="logo-icon">📺</span>
                <span class="logo-text">WebTV</span>
            </div>
            <div class="now-playing">
                <span class="live-badge">LIVE</span>
                <span class="channel-name" id="currentChannelName">
                    <?php echo $selectedChannel ? htmlspecialchars($selectedChannel['name']) : 'No Channel'; ?>
                </span>
            </div>
            <button class="menu-btn" id="menuBtn">☰</button>
        </div>

        <div class="video-container">
            <video id="videoPlayer" controls autoplay playsinline></video>
            <div class="video-overlay" id="videoOverlay">
                <div class="loading-spinner"></div>
                <span>Loading stream...</span>
            </div>
            <?php if ($hasDrm && $selectedChannel): ?>
            <div class="drm-warning" id="drmWarning">
                <span>⚠️</span>
                <p><strong><?php echo htmlspecialchars($selectedChannel['name']); ?></strong> is DRM protected. Web player may not work.</p>
                <button id="downloadM3uBtn">📥 Download M3U</button>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($channels) && $error): ?>
        <div class="error-message">
            <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="overlay" id="overlay"></div>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>📡 All Channels</h3>
            <button class="close-sidebar" id="closeSidebar">×</button>
        </div>
        <div class="channel-search">
            <input type="text" id="searchInput" placeholder="Search channels..." autocomplete="off">
        </div>
        <div class="channels-list" id="channelsList">
            <!-- Channels will be populated by JavaScript -->
        </div>
    </div>

    <script>
        // ============================================
        // JAVASCRIPT - Channel List and Player
        // ============================================
        
        // Channel data from PHP (converted to JS)
        const channels = <?php echo json_encode($channels); ?>;
        const currentIndex = <?php echo $selectedIndex; ?>;
        
        let currentChannelIndex = currentIndex;
        
        // Helper to get playable URL
        function getStreamUrl(channel) {
            if (!channel) return '';
            let url = channel.streamUrl || '';
            
            // For channels with standby URLs, try first working one
            if ((!url || url.includes('xxip9.top') || url.includes('kstv.us')) && 
                channel.standbyUrls && channel.standbyUrls.length > 0) {
                return channel.standbyUrls[0];
            }
            return url;
        }
        
        // Get channel logo emoji (fallback)
        function getChannelLogo(name, category) {
            const emojiMap = {
                'GMA': '📺', 'TV5': '📡', 'ABS': '⭐', 'Kapatid': '🔴',
                'HBO': '🎬', 'Movies': '🎥', 'Cinema': '🍿', 'News': '📰',
                'Sports': '⚽', 'Music': '🎵', 'Kids': '🧸'
            };
            for (let [key, emoji] of Object.entries(emojiMap)) {
                if (name.includes(key)) return emoji;
            }
            if (category === 'Movies') return '🎬';
            if (category === 'Entertainment') return '📺';
            if (category === 'Sports') return '⚽';
            return '📡';
        }
        
        // Render channel list in sidebar
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
            filteredChannels.forEach((channel, idx) => {
                const originalIndex = channels.findIndex(c => c.name === channel.name);
                const isActive = (originalIndex === currentChannelIndex);
                const logo = getChannelLogo(channel.name, channel.category);
                const category = channel.category || 'Uncategorized';
                
                html += `
                    <div class="channel-item ${isActive ? 'active' : ''}" data-index="${originalIndex}">
                        <div class="channel-logo">${logo}</div>
                        <div class="channel-info">
                            <div class="channel-name-side">${escapeHtml(channel.name)}</div>
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
                        switchChannel(idx);
                    }
                    closeSidebar();
                });
            });
        }
        
        // Switch channel
        function switchChannel(index) {
            if (index < 0 || index >= channels.length) return;
            
            currentChannelIndex = index;
            const channel = channels[index];
            if (!channel) return;
            
            // Update URL without reload
            const newUrl = new URL(window.location.href);
            newUrl.searchParams.set('channel', index);
            window.history.pushState({}, '', newUrl);
            
            // Update UI
            document.getElementById('currentChannelName').innerText = channel.name;
            document.title = `WebTV - ${channel.name}`;
            
            // Update active state in sidebar
            document.querySelectorAll('.channel-item').forEach(item => {
                item.classList.remove('active');
                if (parseInt(item.dataset.index) === index) {
                    item.classList.add('active');
                }
            });
            
            // Get stream URL
            const streamUrl = getStreamUrl(channel);
            const hasDrm = channel.drm && Object.keys(channel.drm).length > 0;
            
            // Handle DRM warning
            const drmWarning = document.getElementById('drmWarning');
            if (drmWarning) {
                if (hasDrm) {
                    drmWarning.style.display = 'flex';
                    drmWarning.querySelector('p').innerHTML = `<strong>${escapeHtml(channel.name)}</strong> is DRM protected. Web player may not work.`;
                } else {
                    drmWarning.style.display = 'none';
                }
            }
            
            // Load video
            const video = document.getElementById('videoPlayer');
            const overlay = document.getElementById('videoOverlay');
            
            if (overlay) overlay.classList.remove('hide');
            
            // Clear and load new source
            video.pause();
            video.removeAttribute('src');
            video.load();
            
            if (streamUrl) {
                video.src = streamUrl;
                video.load();
                
                video.oncanplay = () => {
                    if (overlay) overlay.classList.add('hide');
                    video.play().catch(e => console.log('Autoplay prevented:', e));
                };
                
                video.onerror = () => {
                    if (overlay) overlay.classList.remove('hide');
                    console.error('Video error for:', streamUrl);
                };
            } else {
                if (overlay) overlay.classList.remove('hide');
                console.error('No stream URL for channel:', channel.name);
            }
        }
        
        // Helper function to escape HTML
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        // Download M3U for current channel
        function downloadM3u() {
            const channel = channels[currentChannelIndex];
            if (!channel) return;
            
            let m3uContent = '#EXTM3U\n';
            let streamUrl = getStreamUrl(channel);
            
            m3uContent += `#EXTINF:-1 group-title="${channel.category || 'Entertainment'}" `;
            m3uContent += `tvg-logo="${channel.logoLocal || ''}" `;
            m3uContent += `tvg-name="${channel.name}" `;
            m3uContent += `${channel.name}\n`;
            m3uContent += streamUrl + '\n';
            
            // Add DRM info if exists
            if (channel.drm && Object.keys(channel.drm).length > 0) {
                m3uContent += `#DRM-KEYS: ${JSON.stringify(channel.drm)}\n`;
            }
            
            const blob = new Blob([m3uContent], { type: 'audio/x-mpegurl' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${channel.name.replace(/[^a-z0-9]/gi, '_')}.m3u`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
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
        
        // Event listeners
        document.getElementById('menuBtn').addEventListener('click', openSidebar);
        document.getElementById('closeSidebar').addEventListener('click', closeSidebar);
        document.getElementById('overlay').addEventListener('click', closeSidebar);
        
        const downloadBtn = document.getElementById('downloadM3uBtn');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', downloadM3u);
        }
        
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                renderChannels(e.target.value);
            });
        }
        
        // Initial render
        renderChannels();
        
        // Handle video loading overlay
        const video = document.getElementById('videoPlayer');
        const overlay = document.getElementById('videoOverlay');
        
        video.addEventListener('loadstart', () => {
            if (overlay) overlay.classList.remove('hide');
        });
        
        video.addEventListener('canplay', () => {
            if (overlay) overlay.classList.add('hide');
        });
        
        video.addEventListener('error', () => {
            if (overlay) overlay.classList.remove('hide');
        });
        
        // Initial video load
        if (channels.length > 0 && channels[currentChannelIndex]) {
            const initialStreamUrl = getStreamUrl(channels[currentChannelIndex]);
            if (initialStreamUrl) {
                video.src = initialStreamUrl;
                video.load();
                video.oncanplay = () => {
                    if (overlay) overlay.classList.add('hide');
                    video.play().catch(e => console.log('Autoplay prevented:', e));
                };
            }
        }
        
        // Handle back/forward navigation
        window.addEventListener('popstate', (event) => {
            const urlParams = new URLSearchParams(window.location.search);
            const idx = parseInt(urlParams.get('channel'));
            if (!isNaN(idx) && idx >= 0 && idx < channels.length && idx !== currentChannelIndex) {
                switchChannel(idx);
            }
        });
    </script>
</body>
</html>
