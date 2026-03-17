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
        /* (keep all your beautiful CSS from before) */
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fas fa-skull"></i> RK STALKER FILTER <i class="fas fa-skull"></i></h2>
        
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
            <i class="fas fa-bolt"></i> GENERATE FILTERED PLAYLIST <i class="fas fa-bolt"></i>
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
            <span><i class="fas fa-check-circle"></i> RK EDITION</span>
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
                // ✅ FIXED: Use "get_categories" instead of "get_genres"
                const catRes = await fetch(`feeder.php?url=${encodeURIComponent('type=itv&action=get_categories&JsHttpRequest=1-xml')}`);
                const catData = await catRes.json();
                
                if (!catRes.ok) {
                    throw new Error(catData.error || 'Failed to fetch categories');
                }

                // The response might be under "js" key or directly an array
                categories = catData.js || catData;
                if (!Array.isArray(categories)) {
                    categories = [];
                }
                // Filter out any with id '*'
                categories = categories.filter(cat => cat.id !== '*');
                
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
                : categories;

            if (selectAllCheckbox.checked) {
                filteredCategories.forEach(cat => selectedCategories.add(cat.id));
            } else {
                filteredCategories.forEach(cat => selectedCategories.delete(cat.id));
            }

            document.querySelectorAll('.checkbox-container input[type="checkbox"]:not(#selectAll)').forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });

            updateSelectAllCheckbox();
            updatePlaylistUrl();
            updateSelectedCount();
        }

        function updateSelectAllCheckbox() {
            const selectAll = document.getElementById("selectAll");
            const checkboxes = document.querySelectorAll('.checkbox-container input[type="checkbox"]:not(#selectAll)');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const someChecked = Array.from(checkboxes).some(cb => cb.checked);

            selectAll.checked = allChecked;
            selectAll.indeterminate = someChecked && !allChecked;
        }

        function updatePlaylistUrl() {
            const input = document.getElementById("playlist_url");
            const selected = Array.from(selectedCategories);
            if (selected.length > 0) {
                input.value = `${basePlaylistUrl}?categories=${encodeURIComponent(selected.join(','))}`;
            } else {
                input.value = basePlaylistUrl;
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

            const url = selected.length > 0 
                ? `${basePlaylistUrl}?categories=${encodeURIComponent(selected.join(','))}`
                : basePlaylistUrl;
            
            document.getElementById("playlist_url").value = url;

            try {
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                showPopup(`✅ Playlist generated with ${selected.length} categories`);
                copyToClipboard();
            } catch (error) {
                showPopup(`❌ Error: ${error.message}`);
            }
        }

        function copyToClipboard() {
            const input = document.getElementById("playlist_url");
            input.select();
            try {
                document.execCommand('copy');
                showPopup("📋 URL copied to clipboard!");
            } catch (err) {
                showPopup("❌ Failed to copy");
            }
        }

        function showPopup(message) {
            const popup = document.getElementById("popup");
            const popupMessage = document.getElementById("popupMessage");
            const overlay = document.getElementById("overlay");
            
            popupMessage.textContent = message;
            popup.style.display = "block";
            overlay.style.display = "block";
            
            setTimeout(closePopup, 3000);
        }

        function closePopup() {
            document.getElementById("popup").style.display = "none";
            document.getElementById("overlay").style.display = "none";
        }

        window.onload = fetchCategoriesAndChannels;
    </script>
</body>
</html>
