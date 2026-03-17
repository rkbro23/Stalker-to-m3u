<?php
require_once 'config.php';

$host = $stalkerCredentials['host'];
$parsedUrl = parse_url($host);
$hostname = isset($parsedUrl['host']) ? $parsedUrl['host'] : 'playlist';
$playlistName = preg_replace('/[^a-zA-Z0-9]/', '', $hostname);

$currentUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$scriptName = basename($_SERVER['SCRIPT_NAME']);
if (empty($scriptName) || $scriptName == "filter.php") {    
    $playlistUrl = rtrim($currentUrl, "/") . "/playlist.php";
} else {    
    $playlistUrl = str_replace($scriptName, "playlist.php", $currentUrl);
}

// Generate device hashes for display
$hashes = generateDeviceHashes($stalkerCredentials['mac']);
$deviceInfo = [
    'mac' => $stalkerCredentials['mac'],
    'sn' => $hashes['sn_cut'],
    'stb_type' => $stalkerCredentials['stb_type'],
    'host' => $host
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 RK STALKER FILTER - <?= htmlspecialchars($hostname) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0a0f0f 0%, #1a2f2f 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            color: #e0f2e0;
        }

        .container {
            background: rgba(10, 25, 20, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            margin: 20px;
            width: 90%;
            max-width: 550px;
            box-shadow: 0 20px 60px rgba(0, 255, 0, 0.15);
            border: 1px solid #2a5a2a;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h2 {
            color: #7fff7f;
            text-shadow: 0 0 15px rgba(0, 255, 0, 0.3);
            margin: 0 0 20px 0;
            text-align: center;
            font-size: 2em;
            font-weight: 600;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        h2 i {
            font-size: 1.2em;
            color: #4aff4a;
        }

        .device-badge {
            background: rgba(0, 30, 0, 0.6);
            border: 1px solid #2a5a2a;
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            font-size: 0.9em;
        }

        .device-badge span {
            background: #1a3a1a;
            padding: 8px 12px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #3a6a3a;
        }

        .device-badge i {
            color: #7fff7f;
            width: 16px;
        }

        .search-container {
            margin-bottom: 20px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 14px 45px 14px 18px;
            border: 2px solid #2a5a2a;
            border-radius: 40px;
            background: rgba(0, 30, 0, 0.6);
            color: #e0f2e0;
            font-size: 15px;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #00ff00;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.2);
            background: rgba(0, 40, 0, 0.8);
        }

        .search-container::after {
            content: '🔍';
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #7fff7f;
            font-size: 1.2em;
            pointer-events: none;
        }

        .checkbox-container {
            max-height: 300px;
            overflow-y: auto;
            padding: 15px;
            background: rgba(0, 20, 0, 0.4);
            border-radius: 16px;
            margin-bottom: 20px;
            border: 1px solid #2a5a2a;
            scrollbar-width: thin;
            scrollbar-color: #2a5a2a #0a1a0a;
        }

        .checkbox-container::-webkit-scrollbar {
            width: 6px;
        }

        .checkbox-container::-webkit-scrollbar-track {
            background: #0a1a0a;
            border-radius: 10px;
        }

        .checkbox-container::-webkit-scrollbar-thumb {
            background: #2a5a2a;
            border-radius: 10px;
        }

        .form-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 8px 0;
            padding: 10px 15px;
            border-radius: 12px;
            background: rgba(0, 40, 0, 0.3);
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .form-group:hover {
            background: rgba(0, 80, 0, 0.4);
            border-color: #4aff4a;
            transform: translateX(5px);
        }

        .form-group label {
            flex: 1;
            text-align: left;
            font-weight: 500;
            color: #c0ffc0;
            padding-left: 10px;
            cursor: pointer;
            font-size: 0.95em;
        }

        .form-group input[type="checkbox"] {
            transform: scale(1.2);
            cursor: pointer;
            margin-right: 10px;
            accent-color: #00ff00;
        }

        #selectAll.form-group {
            background: rgba(0, 60, 0, 0.5);
            border: 1px solid #4aff4a;
            margin-bottom: 15px;
        }

        button.save-btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(45deg, #006600, #00aa00);
            color: white;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border: 1px solid #00ff00;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        button.save-btn:hover {
            background: linear-gradient(45deg, #008800, #00ff00);
            box-shadow: 0 10px 25px rgba(0, 255, 0, 0.4);
            transform: translateY(-2px);
        }

        button.save-btn i {
            font-size: 1.1em;
        }

        .playlist-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .playlist-container input {
            flex: 1;
            padding: 14px;
            border: 2px solid #2a5a2a;
            border-radius: 40px;
            background: rgba(0, 30, 0, 0.6);
            color: #e0f2e0;
            font-size: 14px;
            font-family: monospace;
        }

        .playlist-container input:focus {
            outline: none;
            border-color: #00ff00;
        }

        .btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(0, 50, 0, 0.6);
            border: 2px solid #2a5a2a;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: rgba(0, 100, 0, 0.8);
            border-color: #00ff00;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
            transform: scale(1.05);
        }

        .btn i {
            font-size: 1.2em;
            color: #7fff7f;
        }

        .stats {
            margin-top: 15px;
            font-size: 0.9em;
            color: #8fbf8f;
            border-top: 1px solid #2a5a2a;
            padding-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stats span {
            background: #1a3a1a;
            padding: 5px 12px;
            border-radius: 20px;
            border: 1px solid #3a6a3a;
        }

        #loadingIndicator {
            text-align: center;
            padding: 30px;
            color: #7fff7f;
            display: none;
        }

        #loadingIndicator i {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(10, 30, 10, 0.98);
            backdrop-filter: blur(10px);
            padding: 25px 35px;
            border-radius: 20px;
            box-shadow: 0 0 40px rgba(0, 255, 0, 0.3);
            z-index: 1000;
            display: none;
            max-width: 90%;
            text-align: center;
            border: 2px solid #00ff00;
            animation: popIn 0.3s ease;
        }

        @keyframes popIn {
            from { transform: translate(-50%, -50%) scale(0.8); opacity: 0; }
            to { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        }

        .popup p {
            margin: 0 0 20px;
            color: #b0ffb0;
            font-size: 1.1em;
            line-height: 1.5;
        }

        .popup button {
            padding: 10px 40px;
            background: linear-gradient(45deg, #006600, #00aa00);
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid #00ff00;
        }

        .popup button:hover {
            background: linear-gradient(45deg, #008800, #00ff00);
            box-shadow: 0 5px 15px rgba(0, 255, 0, 0.4);
            transform: scale(1.05);
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
            display: none;
            z-index: 999;
        }

        .error-message {
            background: rgba(255, 50, 50, 0.2);
            border: 1px solid #ff5555;
            border-radius: 12px;
            padding: 15px;
            margin: 10px 0;
            color: #ffaaaa;
            text-align: center;
            display: none;
        }

        @media (max-width: 480px) {
            .container {
                padding: 20px;
                margin: 10px;
            }

            h2 {
                font-size: 1.6em;
            }

            .device-badge {
                grid-template-columns: 1fr;
            }

            .form-group {
                flex-direction: column;
                align-items: flex-start;
                padding: 12px;
            }

            .form-group label {
                padding-left: 0;
                margin-top: 8px;
            }

            .stats {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>
            <i class="fas fa-skull"></i>
            RK STALKER FILTER
            <i class="fas fa-skull"></i>
        </h2>
        
        <div class="device-badge">
            <span><i class="fas fa-wifi"></i> <?= htmlspecialchars($deviceInfo['mac']) ?></span>
            <span><i class="fas fa-microchip"></i> SN: <?= htmlspecialchars(substr($deviceInfo['sn'], 0, 8)) ?>...</span>
            <span><i class="fas fa-tv"></i> <?= htmlspecialchars($deviceInfo['stb_type']) ?></span>
            <span><i class="fas fa-server"></i> <?= htmlspecialchars($deviceInfo['host']) ?></span>
        </div>
        
        <div class="search-container">
            <input type="text" id="searchBox" class="search-input" placeholder="Search categories..." oninput="filterCategories()">
        </div>
        
        <div id="loadingIndicator">
            <i class="fas fa-spinner fa-spin"></i>
            <div>Loading categories...</div>
        </div>
        
        <div id="errorMessage" class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <span id="errorText"></span>
        </div>
        
        <div class="checkbox-container" id="categoryList">
            <div class="form-group" id="selectAllGroup">
                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                <label for="selectAll"><strong>🔰 SELECT ALL CATEGORIES</strong></label>
            </div>
        </div>
        
        <button class="save-btn" onclick="saveM3U()">
            <i class="fas fa-bolt"></i>
            GENERATE FILTERED PLAYLIST
            <i class="fas fa-bolt"></i>
        </button>
        
        <div class="playlist-container">
            <input type="text" id="playlist_url" value="<?= htmlspecialchars($playlistUrl) ?>" readonly>
            <button class="btn" onclick="copyToClipboard()" title="Copy to clipboard">
                <i class="fas fa-copy"></i>
            </button>
        </div>
        
        <div class="stats">
            <span><i class="fas fa-tags"></i> <span id="selectedCount">0</span> selected</span>
            <span><i class="fas fa-list"></i> <span id="totalCount">0</span> total</span>
            <span><i class="fas fa-check-circle" style="color: #7fff7f;"></i> RK EDITION</span>
        </div>
    </div>

    <div class="overlay" id="overlay"></div>
    <div class="popup" id="popup">
        <i class="fas fa-check-circle" style="color: #7fff7f; font-size: 3em; margin-bottom: 15px;"></i>
        <p id="popupMessage"></p>
        <button onclick="closePopup()">OK</button>
    </div>

    <script>
        let categories = [];
        let channels = [];
        let selectedCategories = new Set();
        const basePlaylistUrl = <?php echo json_encode($playlistUrl); ?>;
        const deviceInfo = <?php echo json_encode($deviceInfo); ?>;

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            errorText.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }

        async function fetchCategoriesAndChannels() {
            const loading = document.getElementById("loadingIndicator");
            const categoryList = document.getElementById("categoryList");
            
            loading.style.display = "block";
            categoryList.style.display = "none";

            try {
                // First test the connection
                const testRes = await fetch(`feeder.php?url=${encodeURIComponent('type=stb&action=handshake&JsHttpRequest=1-xml')}`);
                if (!testRes.ok) {
                    throw new Error('Cannot connect to portal');
                }

                // Fetch categories
                const catRes = await fetch(`feeder.php?url=${encodeURIComponent('type=itv&action=get_genres&JsHttpRequest=1-xml')}`);
                const catData = await catRes.json();
                
                if (!catRes.ok) {
                    throw new Error(catData.error || 'Failed to fetch categories');
                }

                categories = catData.js ? catData.js.filter(cat => cat.id !== '*') : [];
                
                // Fetch channels
                const chanRes = await fetch(`feeder.php?url=${encodeURIComponent('type=itv&action=get_all_channels&JsHttpRequest=1-xml')}`);
                const chanData = await chanRes.json();
                
                if (!chanRes.ok) {
                    throw new Error(chanData.error || 'Failed to fetch channels');
                }

                channels = chanData.js?.data || [];
                
                displayCategories(categories);
                document.getElementById("totalCount").textContent = categories.length;
                
                showPopup(`✅ Loaded ${categories.length} categories • ${channels.length} channels`);
                
            } catch (error) {
                console.error("Error:", error);
                showError(`Connection failed: ${error.message}`);
                showPopup(`❌ Error: ${error.message}`);
            } finally {
                loading.style.display = "none";
                categoryList.style.display = "block";
            }
        }

        function displayCategories(filteredCategories) {
            const categoryList = document.getElementById("categoryList");
            const selectAllDiv = document.getElementById('selectAllGroup');
            categoryList.innerHTML = '';
            categoryList.appendChild(selectAllDiv);

            if (filteredCategories.length === 0) {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'form-group';
                emptyDiv.style.justifyContent = 'center';
                emptyDiv.innerHTML = '<i class="fas fa-search"></i> No categories found';
                categoryList.appendChild(emptyDiv);
                return;
            }

            filteredCategories.forEach(cat => {
                const formGroup = document.createElement("div");
                formGroup.className = "form-group";

                const checkbox = document.createElement("input");
                checkbox.type = "checkbox";
                checkbox.value = cat.id;
                checkbox.id = `cat_${cat.id}`;
                checkbox.checked = selectedCategories.has(cat.id);
                checkbox.addEventListener("change", () => {
                    if (checkbox.checked) {
                        selectedCategories.add(cat.id);
                    } else {
                        selectedCategories.delete(cat.id);
                    }
                    updateSelectAllCheckbox();
                    updatePlaylistUrl();
                    updateSelectedCount();
                });

                const label = document.createElement("label");
                label.htmlFor = `cat_${cat.id}`;
                label.textContent = cat.title;

                formGroup.appendChild(checkbox);
                formGroup.appendChild(label);
                categoryList.appendChild(formGroup);
            });

            updateSelectAllCheckbox();
            updatePlaylistUrl();
            updateSelectedCount();
        }

        function filterCategories() {
            const searchValue = document.getElementById("searchBox").value.toLowerCase().trim();
            const filtered = searchValue 
                ? categories.filter(cat => cat.title.toLowerCase().includes(searchValue))
                : categories;
            displayCategories(filtered);
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById("selectAll");
            const searchValue = document.getElementById("searchBox").value.toLowerCase().trim();
            const filteredCategories = searchValue 
                ? categories.filter(cat => cat.title.toLowerCase().includes(searchValue))
                : c
