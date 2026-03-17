<?php
$stalkerCredentials = [
    'host' => 'main.light-ott.net',
    'mac'  => '00:1A:79:66:94:44',
    'api_file' => 'server/load.php'  // Direct API path
];

function generateDeviceHashes($mac) {
    $mac_clean = strtoupper($mac);
    return [
        'sn' => strtoupper(substr(md5($mac_clean), 0, 13)),
        'dev_id' => strtoupper(hash('sha256', $mac_clean)),
        'dev_id2' => strtoupper(hash('sha256', $mac_clean . 'mag250')),
        'signature' => strtoupper(hash('sha256', $mac_clean . 'signature'))
    ];
}

function generateDeviceHeaders($mac, $token = '') {
    $headers = [
        "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG250 stbapp ver: 4 rev: 250 Safari/533.3",
        "X-User-Agent: Model: MAG250; Link: Ethernet",
        "Cookie: mac={$mac}; stb_lang=en; timezone=GMT",
        "Accept: application/json, application/javascript, text/javascript, text/html",
        "Accept-Encoding: gzip, deflate",
        "Connection: Keep-Alive"
    ];
    
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    
    return $headers;
}

function handshake($host, $mac) {
    $hashes = generateDeviceHashes($mac);
    
    $timestamp = time();
    $random = rand(100000, 999999);
    
    $metrics = json_encode([
        'mac' => $mac,
        'sn' => $hashes['sn'],
        'model' => 'MAG250',
        'type' => 'STB',
        'uid' => $hashes['dev_id'],
        'random' => $random
    ]);
    
    $url = "http://{$host}/server/load.php";
    $params = [
        'type' => 'stb',
        'action' => 'handshake',
        'token' => '',
        'JsHttpRequest' => '1-xml',
        'sn' => $hashes['sn'],
        'stb_type' => 'MAG250',
        'client_type' => 'STB',
        'device_id' => $hashes['dev_id'],
        'device_id2' => $hashes['dev_id2'],
        'signature' => $hashes['signature'],
        'timestamp' => $timestamp,
        'metrics' => $metrics
    ];
    
    $fullUrl = $url . '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $fullUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => generateDeviceHeaders($mac),
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADER => false
    ]);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("Handshake CURL ERROR: " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    
    curl_close($ch);
    
    $data = json_decode($response, true);
    return $data['js']['token'] ?? null;
}

function generate_token() {
    global $stalkerCredentials;
    static $tokenCache = null;
    
    if ($tokenCache) {
        return $tokenCache;
    }
    
    $token = handshake($stalkerCredentials['host'], $stalkerCredentials['mac']);
    if ($token) {
        $tokenCache = $token;
    }
    return $token;
}

// 🔥 YOUR BEAUTIFUL FUNCTION – WORKS PERFECTLY
function stalkerRequest($type, $action, $extra = []) {
    global $stalkerCredentials;

    $mac = $stalkerCredentials['mac'];
    $host = $stalkerCredentials['host'];
    $hashes = generateDeviceHashes($mac);

    $token = generate_token();
    if (!$token) {
        error_log("No token available for $type/$action");
        return [];
    }

    $url = "http://{$host}/{$stalkerCredentials['api_file']}";

    $params = array_merge([
        'type' => $type,
        'action' => $action,
        'JsHttpRequest' => '1-xml',
        'mac' => $mac,
        'sn' => $hashes['sn'],
        'device_id' => $hashes['dev_id'],
        'device_id2' => $hashes['dev_id2'],
        'signature' => $hashes['signature']
    ], $extra);

    $fullUrl = $url . '?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $fullUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => generateDeviceHeaders($mac, $token),
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADER => false
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("CURL ERROR in $type/$action: " . curl_error($ch));
        curl_close($ch);
        return [];
    }

    curl_close($ch);

    // 🔥 DEBUG – Shows what's coming back
    if (!$response) {
        error_log("EMPTY RESPONSE from $type/$action");
        return [];
    }

    $data = json_decode($response, true);

    if (!$data) {
        error_log("INVALID JSON from $type/$action: " . substr($response, 0, 200));
        return [];
    }

    return $data['js'] ?? [];
}
?>
