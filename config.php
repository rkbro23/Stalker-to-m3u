<?php
$stalkerCredentials = [
    'host'      => 'pro.true8k.xyz:80',
    'mac'       => '00:1A:79:4D:9A:C3',
    'base_path' => '/c',
    'api_file'  => 'portal.php',
    'stb_type'  => 'MAG250'
];

function generateDeviceHashes($mac) {
    $mac_clean = strtoupper($mac);
    $sn_full   = strtoupper(substr(md5($mac_clean), 0, 13));
    return [
        'sn'        => $sn_full,
        'sn_cut'    => $sn_full,   // alias - dono same hain
        'dev_id'    => strtoupper(hash('sha256', $mac_clean)),
        'dev_id2'   => strtoupper(hash('sha256', $mac_clean . 'mag250')),
        'signature' => strtoupper(hash('sha256', $mac_clean . 'signature'))
    ];
}

function generateDeviceHeaders($mac, $token = '') {
    $stb = $GLOBALS['stalkerCredentials']['stb_type'] ?? 'MAG250';
    $headers = [
        "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) {$stb} stbapp ver: 4 rev: 250 Safari/533.3",
        "X-User-Agent: Model: {$stb}; Link: Ethernet",
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

function handshake($host, $mac, $debug = false) {
    global $stalkerCredentials;
    $hashes    = generateDeviceHashes($mac);
    $timestamp = time();
    $random    = rand(100000, 999999);
    $stb_type  = $stalkerCredentials['stb_type'];
    $base_path = $stalkerCredentials['base_path'];

    $metrics = json_encode([
        'mac'    => $mac,
        'sn'     => $hashes['sn'],
        'model'  => $stb_type,
        'type'   => 'STB',
        'uid'    => $hashes['dev_id'],
        'random' => $random
    ]);

    $url    = "http://{$host}{$base_path}/server/load.php";
    $params = [
        'type'        => 'stb',
        'action'      => 'handshake',
        'token'       => '',
        'JsHttpRequest'=> '1-xml',
        'sn'          => $hashes['sn'],
        'stb_type'    => $stb_type,
        'client_type' => 'STB',
        'device_id'   => $hashes['dev_id'],
        'device_id2'  => $hashes['dev_id2'],
        'signature'   => $hashes['signature'],
        'timestamp'   => $timestamp,
        'metrics'     => $metrics
    ];

    $fullUrl = $url . '?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $fullUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => generateDeviceHeaders($mac),
        CURLOPT_ENCODING       => 'gzip',
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HEADER         => false
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

function generate_token($force_refresh = false) {
    global $stalkerCredentials;
    static $tokenCache = null;

    if ($tokenCache && !$force_refresh) {
        return $tokenCache;
    }

    $token = handshake($stalkerCredentials['host'], $stalkerCredentials['mac']);
    if ($token) {
        $tokenCache = $token;
    }
    return $token;
}

function stalkerRequest($type, $action, $extra = []) {
    global $stalkerCredentials;

    $mac    = $stalkerCredentials['mac'];
    $host   = $stalkerCredentials['host'];
    $hashes = generateDeviceHashes($mac);
    $token  = generate_token();

    if (!$token) {
        error_log("No token available for $type/$action");
        return [];
    }

    $url    = "http://{$host}{$stalkerCredentials['base_path']}/{$stalkerCredentials['api_file']}";
    $params = array_merge([
        'type'         => $type,
        'action'       => $action,
        'JsHttpRequest'=> '1-xml',
        'mac'          => $mac,
        'sn'           => $hashes['sn'],
        'device_id'    => $hashes['dev_id'],
        'device_id2'   => $hashes['dev_id2'],
        'signature'    => $hashes['signature']
    ], $extra);

    $fullUrl = $url . '?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $fullUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => generateDeviceHeaders($mac, $token),
        CURLOPT_ENCODING       => 'gzip',
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HEADER         => false
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("CURL ERROR in $type/$action: " . curl_error($ch));
        curl_close($ch);
        return [];
    }
    curl_close($ch);

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
