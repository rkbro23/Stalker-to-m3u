<?php
error_reporting(0);

$portal = "http://jiotv.be/stalker_portal";
$mac = "00:1A:79:17:4D:B0";
$dev_id = "7A47133DA3598788858CCADC6202F897C224A83948D442E6F7C76364F78CE82B";
$dev_id2 = "7A47133DA3598788858CCADC6202F897C224A83948D442E6F7C76364F78CE82B";
$sn = "43AA16CF41C90";

if (!isset($_GET['cmd']) || !isset($_GET['type'])) {
    http_response_code(400);
    die("Invalid Params");
}

$cmd = $_GET['cmd'];
$type = $_GET['type'];
$token_cache = sys_get_temp_dir() . "/rk_tok_" . md5($mac);
$cookie_cache = sys_get_temp_dir() . "/rk_cook_" . md5($mac);

$client_ip = $_SERVER['REMOTE_ADDR'];
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $client_ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
}

function call_portal($t, $action, $extra = [], $token = "") {
    global $portal, $mac, $dev_id, $dev_id2, $sn, $cookie_cache, $client_ip;
    $url = $portal . "/server/load.php?type=" . $t . "&action=" . $action . "&mac=" . $mac . "&device_id=" . $dev_id . "&device_id2=" . $dev_id2 . "&sn=" . $sn . "&JsHttpRequest=1-xml";
    
    foreach ($extra as $k => $v) { 
        $url .= "&" . $k . "=" . urlencode($v); 
    }

    $headers = [
        "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG250 stbapp ver: 4 rev: 250 Safari/533.3",
        "X-User-Agent: Model: MAG250; Link: WiFi",
        "Referer: " . $portal . "/c/",
        "X-STB-JSON: 1",
        "Accept: application/json, text/javascript, */*; q=0.01",
        "Accept-Language: en-US,en;q=0.9",
        "Accept-Encoding: gzip, deflate",
        "Connection: keep-alive",
        "Cache-Control: no-cache",
        "Pragma: no-cache",
        "X-Forwarded-For: " . $client_ip,
        "Client-IP: " . $client_ip,
        "Real-IP: " . $client_ip
    ];
    
    if ($token) { 
        $headers[] = "Authorization: Bearer " . $token; 
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_ENCODING, "");
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_cache);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_cache);
    curl_setopt($ch, CURLOPT_COOKIE, "mac=" . $mac . "; stb_lang=en; timezone=Europe/London");
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode($res, true);
    return $json['js'] ?? [];
}

$token = "";
if (file_exists($token_cache) && (time() - filemtime($token_cache)) < 600) {
    $token = file_get_contents($token_cache);
} else {
    $hs = call_portal("stb", "handshake");
    if (isset($hs['token'])) {
        $token = $hs['token'];
        file_put_contents($token_cache, $token);
        call_portal("stb", "get_profile", [], $token);
    }
}

$link_res = call_portal($type, "create_link", ["cmd" => $cmd, "forced_storage" => "0", "disable_ad" => "0"], $token);

if (isset($link_res['cmd'])) {
    $url_parts = explode(" ", $link_res['cmd']);
    $final_url = end($url_parts);
    if (strpos($final_url, "http") === 0) {
        header("Location: " . $final_url);
        exit;
    }
}

http_response_code(404);
echo "404 Stream Not Found - IP Bound or Dead Channel";
?>
