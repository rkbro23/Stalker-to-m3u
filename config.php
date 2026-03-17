<?php
// Configuration file for Stalker Portal credentials
$stalkerCredentials = [
    'host' => 'new.dittotvv.cc',
    'mac' => '00:1A:79:00:29:6F',
    'sn' => 'DB21CB2379515',
    'device_id1' => '0FF335ABEE09FADFD6E02EA542957002B9AFAAC6DF01DD34B66A43EDC6B3CFEA',
    'device_id2' => '0FF335ABEE09FADFD6E02EA542957002B9AFAAC6DF01DD34B66A43EDC6B3CFEA',
    'signature' => '546E3CF35236A17083E8AA056105A48A9DEDB88698A28878790F7ABE368CB6EC',
    'stb_type' => 'MAG270'
];

function generateDeviceHeaders($mac, $token = '') {
    global $stalkerCredentials;
    
    $headers = [
        "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG270 stbapp ver: 2 rev: 250 Safari/533.3",
        "X-User-Agent: Model: MAG270; Link: Ethernet",
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

function handshake($host, $mac, $forceRegenerate = false) {
    static $tokenCache = null;
    
    if (!$forceRegenerate && $tokenCache) {
        return $tokenCache;
    }
    
    global $stalkerCredentials;
    
    // Build handshake URL with device signatures
    $timestamp = time();
    $sn = $stalkerCredentials['sn'];
    $deviceId = $stalkerCredentials['device_id1'];
    $deviceId2 = $stalkerCredentials['device_id2'];
    $signature = $stalkerCredentials['signature'];
    $stbType = $stalkerCredentials['stb_type'];
    
    $metrics = json_encode([
        'mac' => $mac,
        'sn' => $sn,
        'model' => $stbType,
        'type' => 'STB',
        'uid' => $deviceId,
        'random' => rand(100000, 999999)
    ]);
    
    $url = "http://{$host}/stalker_portal/server/load.php";
    $params = [
        'type' => 'stb',
        'action' => 'handshake',
        'token' => '',
        'JsHttpRequest' => '1-xml',
        'sn' => $sn,
        'stb_type' => $stbType,
        'client_type' => 'STB',
        'device_id' => $deviceId,
        'device_id2' => $deviceId2,
        'signature' => $signature,
        'timestamp' => $timestamp,
        'metrics' => $metrics
    ];
    
    $fullUrl = $url . '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, generateDeviceHeaders($mac));
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $headerSize);
    curl_close($ch);
    
    if ($httpCode >= 400) {
        error_log("Handshake failed: HTTP $httpCode - Body: $body");
        return null;
    }
    
    $data = json_decode($body, true);
    $token = $data['js']['token'] ?? null;
    
    if ($token) {
        $tokenCache = $token;
        return $token;
    }
    
    error_log("No token in response: $body");
    return null;
}

function generate_token($forceRegenerate = false) {
    global $stalkerCredentials;
    return handshake($stalkerCredentials['host'], $stalkerCredentials['mac'], $forceRegenerate);
}
?>
