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

// Get device info for display
$deviceInfo = [
    'mac' => $stalkerCredentials['mac'],
    'sn' => substr($stalkerCredentials['sn'], 0, 8) . '...',
    'stb_type' => $stalkerCredentials['stb_type']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 RK Stalker Filter - <?= htmlspecialchars($hostname) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            height: auto;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #0a0f0f, #1a2f2f);
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            color: #e0f2e0;
        }

        .container {
            background: rgba(20, 40, 30, 0.95);
            border-radius: 20px;
            padding: 30px;
            margin: 20px;
            overflow: auto;
            max-height: 85vh;
            width: 90%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 255, 0, 0.2);
            border: 1px solid #2a5a2a;
        }

        h2 {
            color: #7fff7f;
            text-shadow: 0 0 10px rgba(0, 255, 0, 0.5);
            margin: 0 0 10px 0;
            text-align: center;
            font-size: 1.8em;
        }

        .device-badge {
            background: rgba(0, 30, 0, 0.6);
            border: 1px solid #2a5a2a;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #9fff9f;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .device-badge span {
            background: #1a3a1a;
            padding: 3px 8px;
            border-radius: 15px;
            margin: 2px;
        }

        .checkbox-container {
            max-height: 250px;
            overflow-y: auto;
            padding: 15px;
            background: rgba(0, 20, 0, 0.4);
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #2a5a2a;
        }

        .form-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 8px 0;
            padding: 8px 12px;
            border-radius: 8px;
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
        }

        .form-group input[type="checkbox"] {
            transform: scale(1.2);
            cursor: pointer;
            margin-right: 10px;
            accent-color: #00ff00;
        }

        button.save-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(45deg, #006600, #00aa00);
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid #00ff00;
        }

        button.save-btn:hover {
            background: linear-gradient(45deg, #008800, #00ff00);
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.4);
            transform: translateY(-2px);
        }

        .popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(10, 30, 10, 0.98);
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0, 255, 0, 0.3);
            z-index: 1000;
            display: none;
            max-width: 90%;
            text-align: center;
            border: 2px solid #00ff00;
        }

        .popup p {
            margin: 0 0 20px;
            color: #b0ffb0;
            font-size: 1.1em;
        }

        .popup button {
            width: auto;
            padding: 10px 30px;
            margin: 0 auto;
            display: inline-block;
            background: linear-gradient(45deg, #006600, #00aa00);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid #00ff00;
        }

        .popup button:hover {
            background: linear-gradient(45deg, #008800, #00ff00);
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.6);
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            z-index: 999;
            backdrop-filter: blur(3px);
        }

        .search-container {
            margin-bottom: 20px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 1px solid #2a5a2a;
            border-radius: 8px;
            background: rgba(0, 30, 0, 0.6);
            color: #e0f2e0;
            font-size: 14px;
            box-sizing: border-box;
        }

        .search-input:focus {
            outline: none;
            border-color: #00ff00;
            box-shadow: 0 0 10px rgba(0, 255, 0, 0.5);
            background: rgba(0, 40, 0, 0.8);
        }

        .search-container::after {
            content: '🔍';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7fff7f;
            font-size: 1.2em;
        }

        #loadingIndicator {
            font-size: 18px;
            font-weight: bold;
            display: none;
            color: #7fff7f;
            margin: 20px 0;
            text-shadow: 0 0 10px rgba(0, 255, 0, 0.5);
        }

        .playlist-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .playlist-container input {
            flex: 1;
            padding: 12px;
            border: 1px solid #2a5a2a;
            border-radius: 10px;
            background: rgba(0, 30, 0, 0.6);
            color: #e0f2e0;
            font-size: 14px;
        }

        .playlist-container input:focus {
            outline: none;
            border-color: #00ff00;
        }

        .btn {
            padding: 10px;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(0, 50, 0, 0.6);
            border: 1px solid #2a5a2a;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: rgba(0, 100, 0, 0.8);
            border-color: #00ff00;
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.3);
            transform: scale(1.05);
        }

        .btn i {
            font-size: 16px;
            color: #7fff7f;
        }

        .stats {
            margin-top: 15px;
            font-size: 0.85em;
            color: #6f9f6f;
            border-top: 1px solid #2a5a2a;
            padding-top: 10px;
        }

        @media (max-width: 480px) {
            .container {
                padding: 20px;
            }

            h2 {
                font-size: 1.5em;
            }

            .device-badge {
                flex-direction: column;
                gap: 5px;
            }

            .form-group {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px;
            }

            .form-group label {
                padding-left: 0;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🎯 RK STALKER FILTER</h2>
        
        <div class="device-badge">
            <span><i class="fas fa-wifi"></i> <?= htmlspecialchars($deviceInfo['mac']) ?></span>
            <span><i class="fas fa-microchip"></i> SN: <?= htmlspecialchars($deviceInfo['sn']) ?></span>
            <span><i class="fas fa-tv"></i> <?= htmlspecialchars($deviceInfo['stb_type']) ?></span>
        </div>
        
        <div class="search-container">
            <input type="text" id="searchBox" class="search-input" placeholder="Search categories..." oninput="filterCategories()">
        </div>
        
        <div id="loadingIndicator">
            <i class="fas fa-spinner fa-spin"></i> Loading categories...
        </div>
        
        <div class="checkbox-container" id="categoryList">
            <div class="form-group" style="background: rgba(0, 80, 0, 0.5);">
                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                <label for="selectAll"><strong>🔰 SELECT ALL CATEGORIES</strong></label>
            </div>
        </div>
        
        <button class="save-btn" onclick="saveM3U()">
            <i class="fas fa-save"></i> GENERATE FILTERED PLAYLIST
        </button>
        
        <div class="playlist-container">
            <input type="text" id="playlist_url" value="<?= htmlspecialchars($playlistUrl) ?>" readonly>
            <button class="btn" onclick="copyToClipboard()" title="Copy to clipboard">
                <i class="fas fa-copy"></i>
            </button>
        </div>
        
        <div class="stats" id="stats">
            <span id="selectedCount">0</span> categories selected • 
            <span id="totalCount">0</span> total
        </div>
    </div>

    <div class="overlay" id="overlay"></div>
    <div class="popup" id="popup">
        <p id="popupMessage"></p>
        <button onclick="closePopup()">OK</button>
    </div>

    <script>
        let categories = [];
        let channels = [];
        let selectedCategories = new Set();
        const basePlaylistUrl = <?php echo json_encode($playlistUrl); ?>;
        const stalkerCredentials = <?php echo json_encode($stalkerCredentials); ?>;

        async function fetchCategoriesAndChannels() {
            const baseURL = `http://${stalkerCredentials.host}/stalker_portal/server/load.php`;
            
            document.getElementById("loadingIndicator").style.display = "block";
            document.getElementById("categoryList").style.display = "none";

            try {
                // Fetch categories
                const categoryRes = await fetch(`feeder.php?url=${encodeURIComponent(baseURL + '?type=itv&action=get_genres&JsHttpRequest=1-xml')}`);
                const categoryData = await categoryRes.json();
                
                if (!categoryRes.ok) {
                    throw new Error(categoryData.error || 'Failed to fetch categories');
                }

                categories = categoryData.js ? categoryData.js.filter(cat => cat.id !== '*') : [];
                
                // Fetch channels
                const channelRes = await fetch(`feeder.php?url=${encodeURIComponent(baseURL + '?type=itv&action=get_all_channels&JsHttpRequest=1-xml')}`);
                const channelData = await channelRes.json();
                
                if (!channelRes.ok) {
                    throw new Error(channelData.error || 'Failed to fetch channels');
                }

                channels = channelData.js?.data || [];
                
                // Display categories
                displayCategories(categories);
                
                document.getElementById("totalCount").textContent = categories.length;
                showPopup(`✅ Loaded ${categories.length} categories and ${channels.length} channels`);
                
            } catch (error) {
                console.error("Error:", error);
                showPopup(`❌ Error: ${error.message}`);
            } finally {
                document.getElementById("loadingIndicator").style.display = "none";
                document.getElementById("categoryList").style.display = "block";
            }
        }

        function displayCategories(filteredCategories) {
            const categoryList = document.getElementById("categoryList");
            const selectAllDiv = categoryList.querySelector('.form-group');
            categoryList.innerHTML = '';
            categoryList.appendChild(selectAllDiv);

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
            const searchValue = document.getElementById("searchBox").value.toLowerCase();
            const filtered = categories.filter(cat => cat.title.toLowerCase().includes(searchValue));
            displayCategories(filtered);
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById("selectAll");
            const isChecked = selectAllCheckbox.checked;
            const searchValue = document.getElementById("searchBox").value.toLowerCase();
            const filteredCategories = searchValue 
                ? categories.filter(cat => cat.title.toLowerCase().includes(searchValue))
                : categories;

            if (isChecked) {
                filteredCategories.forEach(cat => selectedCategories.add(cat.id));
            } else {
                filteredCategories.forEach(cat => selectedCategories.delete(cat.id));
            }

            document.querySelectorAll('.checkbox-container input[type="checkbox"]:not(#selectAll)').forEach(checkbox => {
                checkbox.checked = isChecked;
            });

            updateSelectAllCheckbox();
            updatePlaylistUrl();
            updateSelectedCount();
        }

        function updateSelectAllCheckbox() {
            const selectAllCheckbox = document.getElementById("selectAll");
            const allCheckboxes = document.querySelectorAll('.checkbox-container input[type="checkbox"]:not(#selectAll)');
            const allChecked = Array.from(allCheckboxes).every(checkbox => checkbox.checked);
            const someChecked = Array.from(allCheckboxes).some(checkbox => checkbox.checked);

            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
        }

        function updatePlaylistUrl() {
            const playlistInput = document.getElementById("playlist_url");
            const selected = Array.from(selectedCategories);
            if (selected.length > 0) {
                playlistInput.value = `${basePlaylistUrl}?categories=${encodeURIComponent(selected.join(','))}`;
            } else {
                playlistInput.value = `${basePlaylistUrl}`;
            }
        }

        function updateSelectedCount() {
            document.getElementById("selectedCount").textContent = selectedCategories.size;
        }

        async function saveM3U() {
            const selected = Array.from(selectedCategories);
            if (!selected.length) {
                showPopup("⚠️ Please select at least one category");
                return;
            }

            const playlistUrlWithCategories = `${basePlaylistUrl}?categories=${encodeURIComponent(selected.join(','))}`;
            document.getElementById("playlist_url").value = playlistUrlWithCategories;

            try {
                const response = await fetch(playlistUrlWithCategories);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                showPopup(`✅ Filtered playlist ready! Copied to clipboard?`);
                copyToClipboard();
            } catch (error) {
                showPopup(`❌ Error: ${error.message}`);
            }
        }

        function copyToClipboard() {
            const playlistUrl = document.getElementById("playlist_url");
            playlistUrl.select();
            try {
                document.execCommand('copy');
                showPopup("📋 Playlist URL copied!");
            } catch (err) {
                showPopup("❌ Failed to copy. Please copy manually.");
            }
        }

        function showPopup(message) {
            const popup = document.getElementById("popup");
            const popupMessage = document.getElementById("popupMessage");
            const overlay = document.getElementById("overlay");
            popupMessage.textContent = message;
            popup.style.display = "block";
            overlay.style.display = "block";
            
            // Auto close after 3 seconds
            setTimeout(closePopup, 3000);
        }

        function closePopup() {
            const popup = document.getElementById("popup");
            const overlay = document.getElementById("overlay");
            popup.style.display = "none";
            overlay.style.display = "none";
        }

        window.onload = fetchCategoriesAndChannels;
    </script>
</body>
</html>
