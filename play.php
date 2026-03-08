<?php
error_reporting(0);

$portal = "http://jiotv.be/stalker_portal";
$mac = "00:1A:79:17:4D:B0";
$dev_id = "7A47133DA3598788858CCADC6202F897C224A83948D442E6F7C76364F78CE82B";
$dev_id2 = "7A47133DA3598788858CCADC6202F897C224A83948D442E6F7C76364F78CE82B";
$sn = "43AA16CF41C90";

if (!isset($_GET['cmd']) || !isset($_GET['type'])) {
    http_response_code(403);
    die();
}

$cmd = $_GET['cmd'];
$type = $_GET['type'];
$token_file = sys_get_temp_dir() . "/stb_token_" . md5($mac) . ".txt";

function req($t_val, $action, $params = [], $token = "") {
    global $portal, $mac, $dev_id, $dev_id2, $sn;
    $url = $portal . "/server/load.php?type=" . $t_val . "&action=" . $action . "&mac=" . $mac . "&device_id=" . $dev_id . "&device_id2=" . $dev_id2 . "&sn=" . $sn . "&JsHttpRequest=1-xml";
    foreach ($params as $k => $v) {
        $url .= "&" . $k . "=" . urlencode($v);
    }
    
    $headers = [
        "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG250 stbapp ver: 4 rev: 250 Safari/533.3",
        "X-User-Agent: Model: MAG250; Link: WiFi",
        "Referer: " . $portal . "/c/",
        "Accept: application/json, text/javascript, */*; q=0.01",
        "X-STB-JSON: 1",
        "Cookie: mac=" . $mac . "; stb_lang=en; timezone=Europe/London"
    ];
    
    if (!empty($token)) {
        $headers[] = "Authorization: Bearer " . $token;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($res, true);
    return isset($data['js']) ? $data['js'] : [];
}

$token = "";

if (file_exists($token_file) && (time() - filemtime($token_file)) < 1800) {
    $token = file_get_contents($token_file);
} else {
    $hs = req("stb", "handshake");
    if (isset($hs['token'])) {
        $token = $hs['token'];
        file_put_contents($token_file, $token);
        req("stb", "get_profile", [], $token);
    }
}

$link_data = req($type, "create_link", ["cmd" => $cmd, "forced_storage" => "0", "disable_ad" => "0"], $token);

if (isset($link_data['cmd'])) {
    $stream_url = explode(" ", $link_data['cmd']);
    $final_url = end($stream_url);
    if (strpos($final_url, "http") === 0) {
        header("Location: " . $final_url);
        exit;
    }
}

http_response_code(404);
die();
?>
