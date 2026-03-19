<?php
session_start();

// Function to parse M3U playlist
function parseM3U($content) {
    $lines = explode("\n", $content);
    $channels = [];
    $currentChannel = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        if (strpos($line, '#EXTINF:') === 0) {
            // Parse EXTINF line
            preg_match('/#EXTINF:(?P<duration>-?\d+)(?:\s+(?P<attributes>.*?))?,(?P<title>.*)/', $line, $matches);
            
            $currentChannel = [
                'duration' => $matches['duration'] ?? '-1',
                'title' => $matches['title'] ?? '',
                'attributes' => [],
                'url' => '',
                'type' => 'live'
            ];
            
            // Parse attributes like group-title, tvg-logo, etc.
            if (!empty($matches['attributes'])) {
                preg_match_all('/([a-zA-Z0-9_-]+)=("[^"]*"|[^\s]+)/', $matches['attributes'], $attrMatches);
                foreach ($attrMatches[1] as $index => $key) {
                    $value = trim($attrMatches[2][$index], '"');
                    $currentChannel['attributes'][$key] = $value;
                    
                    // Detect type based on group-title
                    if ($key === 'group-title') {
                        $groupLower = strtolower($value);
                        if (strpos($groupLower, 'movie') !== false || strpos($groupLower, 'film') !== false) {
                            $currentChannel['type'] = 'vod';
                        } elseif (strpos($groupLower, 'series') !== false || strpos($groupLower, 'show') !== false) {
                            $currentChannel['type'] = 'series';
                        }
                    }
                }
            }
        } elseif (strpos($line, '#EXTGRP:') === 0) {
            // Alternative group declaration
            if ($currentChannel) {
                $group = substr($line, 8);
                $currentChannel['attributes']['group-title'] = $group;
                
                $groupLower = strtolower($group);
                if (strpos($groupLower, 'movie') !== false || strpos($groupLower, 'film') !== false) {
                    $currentChannel['type'] = 'vod';
                } elseif (strpos($groupLower, 'series') !== false || strpos($groupLower, 'show') !== false) {
                    $currentChannel['type'] = 'series';
                }
            }
        } elseif (strpos($line, '#') !== 0) {
            // This is a URL line
            if ($currentChannel) {
                $currentChannel['url'] = $line;
                $channels[] = $currentChannel;
                $currentChannel = null;
            }
        }
    }
    
    return $channels;
}

// Handle file upload
$uploadedContent = null;
$channels = [];
$statistics = ['live' => 0, 'vod' => 0, 'series' => 0];
$categories = ['live' => [], 'vod' => [], 'series' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['playlist_file'])) {
    $file = $_FILES['playlist_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $content = file_get_contents($file['tmp_name']);
        $channels = parseM3U($content);
        
        // Calculate statistics and categories
        foreach ($channels as $channel) {
            $type = $channel['type'];
            $statistics[$type]++;
            
            $groupTitle = $channel['attributes']['group-title'] ?? 'Ungrouped';
            if (!isset($categories[$type][$groupTitle])) {
                $categories[$type][$groupTitle] = [];
            }
            $categories[$type][$groupTitle][] = $channel;
        }
        
        $_SESSION['channels'] = $channels;
        $_SESSION['categories'] = $categories;
        $_SESSION['statistics'] = $statistics;
        
        // Initialize selected categories with all selected by default
        if (!isset($_SESSION['selected_categories'])) {
            $_SESSION['selected_categories'] = [
                'live' => array_fill_keys(array_keys($categories['live']), true),
                'vod' => array_fill_keys(array_keys($categories['vod']), true),
                'series' => array_fill_keys(array_keys($categories['series']), true)
            ];
        }
        
        if (!isset($_SESSION['selected_types'])) {
            $_SESSION['selected_types'] = ['live' => true, 'vod' => true, 'series' => true];
        }
    }
}

// Handle filtered playlist download
if (isset($_GET['download'])) {
    $selectedCategories = $_SESSION['selected_categories'] ?? [];
    $selectedTypes = $_SESSION['selected_types'] ?? ['live' => true, 'vod' => true, 'series' => true];
    $channels = $_SESSION['channels'] ?? [];
    
    $filteredChannels = array_filter($channels, function($channel) use ($selectedCategories, $selectedTypes) {
        // Check if channel type is selected
        if (!($selectedTypes[$channel['type']] ?? false)) {
            return false;
        }
        
        // Check if category is selected
        $groupTitle = $channel['attributes']['group-title'] ?? 'Ungrouped';
        return isset($selectedCategories[$channel['type']][$groupTitle]) && $selectedCategories[$channel['type']][$groupTitle];
    });
    
    // Generate M3U content
    $m3uContent = "#EXTM3U\n";
    foreach ($filteredChannels as $channel) {
        $attributes = '';
        foreach ($channel['attributes'] as $key => $value) {
            $attributes .= " $key=\"$value\"";
        }
        $m3uContent .= "#EXTINF:{$channel['duration']}{$attributes},{$channel['title']}\n";
        $m3uContent .= $channel['url'] . "\n";
    }
    
    header('Content-Type: audio/x-mpegurl');
    header('Content-Disposition: attachment; filename="filtered_playlist.m3u"');
    echo $m3uContent;
    exit;
}

// Handle AJAX save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_filter') {
    $_SESSION['selected_categories'] = json_decode($_POST['categories'], true) ?? [];
    $_SESSION['selected_types'] = json_decode($_POST['types'], true) ?? ['live' => true, 'vod' => true, 'series' => true];
    echo json_encode(['success' => true]);
    exit;
}

// Get data from session
$channels = $_SESSION['channels'] ?? [];
$categories = $_SESSION['categories'] ?? ['live' => [], 'vod' => [], 'series' => []];
$statistics = $_SESSION['statistics'] ?? ['live' => 0, 'vod' => 0, 'series' => 0];
$selectedCategories = $_SESSION['selected_categories'] ?? [
    'live' => [],
    'vod' => [],
    'series' => []
];
$selectedTypes = $_SESSION['selected_types'] ?? ['live' => true, 'vod' => true, 'series' => true];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M3U Playlist Filter Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bg-animation span {
            position: absolute;
            display: block;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.05);
            animation: animate 25s linear infinite;
            bottom: -150px;
        }

        @keyframes animate {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
            }
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        /* Header Section */
        .header {
            text-align: center;
            margin-bottom: 40px;
            animation: slideDown 0.8s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header h1 {
            font-size: 3em;
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            margin-bottom: 10px;
        }

        .header h1 i {
            color: #ffd700;
            margin-right: 10px;
            animation: spin 4s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .header p {
            color: rgba(255,255,255,0.9);
            font-size: 1.2em;
        }

        /* Upload Section */
        .upload-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            border: 2px dashed rgba(255,255,255,0.3);
            transition: all 0.3s ease;
            animation: fadeIn 1s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .upload-section:hover {
            border-color: #ffd700;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .upload-area {
            text-align: center;
            cursor: pointer;
        }

        .upload-area i {
            font-size: 4em;
            color: #ffd700;
            margin-bottom: 15px;
            animation: bounce 2s ease infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .upload-area h3 {
            color: white;
            font-size: 1.5em;
            margin-bottom: 10px;
        }

        .upload-area p {
            color: rgba(255,255,255,0.8);
        }

        .file-input {
            display: none;
        }

        .or-divider {
            margin: 20px 0;
            color: rgba(255,255,255,0.5);
            font-size: 1.1em;
        }

        .select-file-btn {
            background: #ffd700;
            color: #1e3c72;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .select-file-btn:hover {
            background: white;
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(255,215,0,0.4);
        }

        /* Statistics Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            animation: slideUp 0.8s ease-out 0.2s both;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .stat-card.active {
            border-color: #ffd700;
            background: rgba(255, 215, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
        }

        .stat-icon.live { background: linear-gradient(45deg, #ff6b6b, #ee5253); }
        .stat-icon.vod { background: linear-gradient(45deg, #48dbfb, #0abde3); }
        .stat-icon.series { background: linear-gradient(45deg, #1dd1a1, #10ac84); }

        .stat-info h3 {
            color: white;
            font-size: 1.2em;
            margin-bottom: 5px;
        }

        .stat-info .count {
            color: #ffd700;
            font-size: 1.8em;
            font-weight: bold;
        }

        /* Filter Sections */
        .filters-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            animation: slideUp 0.8s ease-out 0.4s both;
        }

        .filter-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .filter-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .section-header {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .section-header.live { background: linear-gradient(45deg, #ff6b6b, #ee5253); }
        .section-header.vod { background: linear-gradient(45deg, #48dbfb, #0abde3); }
        .section-header.series { background: linear-gradient(45deg, #1dd1a1, #10ac84); }

        .section-header h2 {
            color: white;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header .toggle-all {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255,255,255,0.3);
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #ffd700;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease-out;
            background: rgba(0,0,0,0.2);
        }

        .section-content.expanded {
            max-height: 400px;
            overflow-y: auto;
        }

        .category-list {
            padding: 15px;
        }

        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .category-item:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }

        .category-name {
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .category-count {
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            color: #ffd700;
        }

        .search-box {
            padding: 10px 15px;
            background: rgba(255,255,255,0.1);
            margin: 10px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box i {
            color: rgba(255,255,255,0.5);
        }

        .search-box input {
            background: none;
            border: none;
            color: white;
            width: 100%;
            outline: none;
        }

        .search-box input::placeholder {
            color: rgba(255,255,255,0.5);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
            animation: slideUp 0.8s ease-out 0.6s both;
        }

        .btn {
            padding: 15px 40px;
            border: none;
            border-radius: 50px;
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: #ffd700;
            color: #1e3c72;
        }

        .btn-primary:hover {
            background: white;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255,215,0,0.4);
        }

        .btn-success {
            background: #00b894;
            color: white;
        }

        .btn-success:hover {
            background: #00a884;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,184,148,0.4);
        }

        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #00b894;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            transform: translateX(400px);
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.error {
            background: #d63031;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            backdrop-filter: blur(5px);
        }

        .loading-overlay.show {
            display: flex;
        }

        .loader {
            width: 80px;
            height: 80px;
            border: 5px solid rgba(255,255,255,0.1);
            border-top-color: #ffd700;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2em;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filters-container {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="bg-animation" id="bgAnimation"></div>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-film"></i> M3U Playlist Filter Pro</h1>
            <p>Upload, Filter, and Download your customized playlist</p>
        </div>

        <!-- Upload Section -->
        <div class="upload-section" id="uploadSection">
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h3>Drag & Drop your M3U file here</h3>
                    <p>or</p>
                    <button type="button" class="select-file-btn" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-folder-open"></i> Browse Files
                    </button>
                    <input type="file" name="playlist_file" id="fileInput" class="file-input" accept=".m3u,.m3u8,.txt" onchange="document.getElementById('uploadForm').submit()">
                </div>
            </form>
        </div>

        <?php if (!empty($channels)): ?>
        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card <?php echo $selectedTypes['live'] ? 'active' : ''; ?>" onclick="toggleType('live')" id="statLive">
                <div class="stat-icon live"><i class="fas fa-tv"></i></div>
                <div class="stat-info">
                    <h3>Live Channels</h3>
                    <div class="count" id="liveCount"><?php echo $statistics['live']; ?></div>
                </div>
            </div>
            
            <div class="stat-card <?php echo $selectedTypes['vod'] ? 'active' : ''; ?>" onclick="toggleType('vod')" id="statVod">
                <div class="stat-icon vod"><i class="fas fa-film"></i></div>
                <div class="stat-info">
                    <h3>VOD / Movies</h3>
                    <div class="count" id="vodCount"><?php echo $statistics['vod']; ?></div>
                </div>
            </div>
            
            <div class="stat-card <?php echo $selectedTypes['series'] ? 'active' : ''; ?>" onclick="toggleType('series')" id="statSeries">
                <div class="stat-icon series"><i class="fas fa-layer-group"></i></div>
                <div class="stat-info">
                    <h3>TV Series</h3>
                    <div class="count" id="seriesCount"><?php echo $statistics['series']; ?></div>
                </div>
            </div>
        </div>

        <!-- Filter Sections -->
        <div class="filters-container">
            <!-- Live Channels Section -->
            <div class="filter-section">
                <div class="section-header live" onclick="toggleSection('live')">
                    <h2><i class="fas fa-tv"></i> Live Channels (<?php echo $statistics['live']; ?>)</h2>
                    <div class="toggle-all" onclick="event.stopPropagation()">
                        <span>Toggle All</span>
                        <label class="switch">
                            <input type="checkbox" id="toggleAllLive" <?php echo !empty($selectedCategories['live']) && count($selectedCategories['live']) === count($categories['live']) ? 'checked' : ''; ?> onchange="toggleAllCategories('live', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="section-content expanded" id="liveContent">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search categories..." onkeyup="searchCategories('live', this.value)">
                    </div>
                    <div class="category-list" id="liveCategories">
                        <?php foreach ($categories['live'] as $category => $items): ?>
                        <div class="category-item" data-category="<?php echo htmlspecialchars($category); ?>">
                            <div class="category-name">
                                <i class="fas fa-folder"></i>
                                <span><?php echo htmlspecialchars($category); ?></span>
                                <span class="category-count"><?php echo count($items); ?></span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" class="category-checkbox live" value="<?php echo htmlspecialchars($category); ?>" <?php echo isset($selectedCategories['live'][$category]) && $selectedCategories['live'][$category] ? 'checked' : ''; ?> onchange="updateCategory('live', '<?php echo htmlspecialchars($category); ?>', this.checked)">
                                <span class="slider"></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- VOD Section -->
            <div class="filter-section">
                <div class="section-header vod" onclick="toggleSection('vod')">
                    <h2><i class="fas fa-film"></i> VOD / Movies (<?php echo $statistics['vod']; ?>)</h2>
                    <div class="toggle-all" onclick="event.stopPropagation()">
                        <span>Toggle All</span>
                        <label class="switch">
                            <input type="checkbox" id="toggleAllVod" <?php echo !empty($selectedCategories['vod']) && count($selectedCategories['vod']) === count($categories['vod']) ? 'checked' : ''; ?> onchange="toggleAllCategories('vod', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="section-content expanded" id="vodContent">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search categories..." onkeyup="searchCategories('vod', this.value)">
                    </div>
                    <div class="category-list" id="vodCategories">
                        <?php foreach ($categories['vod'] as $category => $items): ?>
                        <div class="category-item" data-category="<?php echo htmlspecialchars($category); ?>">
                            <div class="category-name">
                                <i class="fas fa-folder"></i>
                                <span><?php echo htmlspecialchars($category); ?></span>
                                <span class="category-count"><?php echo count($items); ?></span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" class="category-checkbox vod" value="<?php echo htmlspecialchars($category); ?>" <?php echo isset($selectedCategories['vod'][$category]) && $selectedCategories['vod'][$category] ? 'checked' : ''; ?> onchange="updateCategory('vod', '<?php echo htmlspecialchars($category); ?>', this.checked)">
                                <span class="slider"></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Series Section -->
            <div class="filter-section">
                <div class="section-header series" onclick="toggleSection('series')">
                    <h2><i class="fas fa-layer-group"></i> TV Series (<?php echo $statistics['series']; ?>)</h2>
                    <div class="toggle-all" onclick="event.stopPropagation()">
                        <span>Toggle All</span>
                        <label class="switch">
                            <input type="checkbox" id="toggleAllSeries" <?php echo !empty($selectedCategories['series']) && count($selectedCategories['series']) === count($categories['series']) ? 'checked' : ''; ?> onchange="toggleAllCategories('series', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="section-content expanded" id="seriesContent">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search categories..." onkeyup="searchCategories('series', this.value)">
                    </div>
                    <div class="category-list" id="seriesCategories">
                        <?php foreach ($categories['series'] as $category => $items): ?>
                        <div class="category-item" data-category="<?php echo htmlspecialchars($category); ?>">
                            <div class="category-name">
                                <i class="fas fa-folder"></i>
                                <span><?php echo htmlspecialchars($category); ?></span>
                                <span class="category-count"><?php echo count($items); ?></span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" class="category-checkbox series" value="<?php echo htmlspecialchars($category); ?>" <?php echo isset($selectedCategories['series'][$category]) && $selectedCategories['series'][$category] ? 'checked' : ''; ?> onchange="updateCategory('series', '<?php echo htmlspecialchars($category); ?>', this.checked)">
                                <span class="slider"></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="saveFilter()">
                <i class="fas fa-save"></i> Apply Filters
            </button>
            <button class="btn btn-success" onclick="downloadPlaylist()">
                <i class="fas fa-download"></i> Download Playlist
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Notification -->
    <div class="notification" id="notification">
        <i class="fas fa-check-circle"></i>
        <span id="notificationMessage"></span>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
    </div>

    <script>
        // Generate animated background bubbles
        function createBubbles() {
            const bgAnimation = document.getElementById('bgAnimation');
            for (let i = 0; i < 20; i++) {
                const span = document.createElement('span');
                const size = Math.random() * 100 + 20;
                span.style.width = size + 'px';
                span.style.height = size + 'px';
                span.style.left = Math.random() * 100 + '%';
                span.style.animationDuration = Math.random() * 20 + 10 + 's';
                span.style.animationDelay = Math.random() * 5 + 's';
                bgAnimation.appendChild(span);
            }
        }

        createBubbles();

        // Toggle section expansion
        function toggleSection(section) {
            const content = document.getElementById(section + 'Content');
            content.classList.toggle('expanded');
        }

        // Toggle type selection
        function toggleType(type) {
            const checkbox = document.querySelector(`#toggleAll${type.charAt(0).toUpperCase() + type.slice(1)}`);
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                toggleAllCategories(type, checkbox.checked);
            }
        }

        // Toggle all categories in a section
        function toggleAllCategories(type, checked) {
            const checkboxes = document.querySelectorAll(`.category-checkbox.${type}`);
            checkboxes.forEach(cb => {
                cb.checked = checked;
            });
            updateStatCard(type, checked);
        }

        // Update category
        function updateCategory(type, category, checked) {
            const statCard = document.getElementById(`stat${type.charAt(0).toUpperCase() + type.slice(1)}`);
            const anyChecked = document.querySelectorAll(`.category-checkbox.${type}:checked`).length > 0;
            if (anyChecked) {
                statCard.classList.add('active');
            } else {
                statCard.classList.remove('active');
            }
        }

        // Update stat card
        function updateStatCard(type, checked) {
            const statCard = document.getElementById(`stat${type.charAt(0).toUpperCase() + type.slice(1)}`);
            if (checked) {
                statCard.classList.add('active');
            } else {
                const anyChecked = document.querySelectorAll(`.category-checkbox.${type}:checked`).length > 0;
                if (!anyChecked) {
                    statCard.classList.remove('active');
                }
            }
        }

        // Search categories
        function searchCategories(type, searchTerm) {
            const items = document.querySelectorAll(`#${type}Categories .category-item`);
            searchTerm = searchTerm.toLowerCase();
            
            items.forEach(item => {
                const category = item.getAttribute('data-category').toLowerCase();
                if (category.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Save filter
        function saveFilter() {
            showLoading();
            
            const categories = {
                live: {},
                vod: {},
                series: {}
            };
            
            const types = {
                live: document.querySelectorAll('.category-checkbox.live:checked').length > 0,
                vod: document.querySelectorAll('.category-checkbox.vod:checked').length > 0,
                series: document.querySelectorAll('.category-checkbox.series:checked').length > 0
            };
            
            // Get live categories
            document.querySelectorAll('.category-checkbox.live').forEach(cb => {
                categories.live[cb.value] = cb.checked;
            });
            
            // Get vod categories
            document.querySelectorAll('.category-checkbox.vod').forEach(cb => {
                categories.vod[cb.value] = cb.checked;
            });
            
            // Get series categories
            document.querySelectorAll('.category-checkbox.series').forEach(cb => {
                categories.series[cb.value] = cb.checked;
            });
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=save_filter&categories=' + encodeURIComponent(JSON.stringify(categories)) + '&types=' + encodeURIComponent(JSON.stringify(types))
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Filters applied successfully!', 'success');
                }
            })
            .catch(error => {
                showNotification('Error saving filters: ' + error, 'error');
            })
            .finally(() => {
                hideLoading();
            });
        }

        // Download playlist
        function downloadPlaylist() {
            window.location.href = '?download=1';
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const messageSpan = document.getElementById('notificationMessage');
            
            messageSpan.textContent = message;
            notification.className = 'notification show ' + (type === 'error' ? 'error' : '');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        // Show loading
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('show');
        }

        // Hide loading
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('show');
        }

        // Drag and drop functionality
        const uploadSection = document.getElementById('uploadSection');
        
        uploadSection.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadSection.style.borderColor = '#ffd700';
            uploadSection.style.background = 'rgba(255, 255, 255, 0.2)';
        });
        
        uploadSection.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadSection.style.borderColor = 'rgba(255,255,255,0.3)';
            uploadSection.style.background = 'rgba(255, 255, 255, 0.1)';
        });
        
        uploadSection.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadSection.style.borderColor = 'rgba(255,255,255,0.3)';
            uploadSection.style.background = 'rgba(255, 255, 255, 0.1)';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                if (file.name.endsWith('.m3u') || file.name.endsWith('.m3u8') || file.name.endsWith('.txt')) {
                    const formData = new FormData();
                    formData.append('playlist_file', file);
                    
                    showLoading();
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(() => {
                        window.location.reload();
                    })
                    .catch(error => {
                        showNotification('Error uploading file: ' + error, 'error');
                        hideLoading();
                    });
                } else {
                    showNotification('Please upload a valid M3U file', 'error');
                }
            }
        });

        // Initialize tooltips
        document.querySelectorAll('[title]').forEach(el => {
            el.addEventListener('mouseenter', (e) => {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = el.getAttribute('title');
                document.body.appendChild(tooltip);
                
                const rect = el.getBoundingClientRect();
                tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
                tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
                
                el.addEventListener('mouseleave', () => {
                    tooltip.remove();
                });
            });
        });
    </script>
</body>
</html>
