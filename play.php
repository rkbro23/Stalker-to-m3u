<?php
$portal = "http://tatatv.cc/stalker_portal";
$mac = "00:1A:79:00:7C:7E";
$dev_id = "73F5AD9FC444D897AE32DD7066491F89CD420882E52C59A701B0FECF8ADBCE03";
$dev_id2 = "73F5AD9FC444D897AE32DD7066491F89CD420882E52C59A701B0FECF8ADBCE03";
$sn = "BCD8AE9B346BF";

if (!isset($_GET['cmd']) || !isset($_GET['type'])) {
    die("Invalid Request");
}

$cmd = $_GET['cmd'];
$type = $_GET['type'];

function req($t_val, $action, $params = []) {
    global $portal, $mac, $dev_id, $dev_id2, $sn;
    $url = $portal . "/server/load.php?type=" . $t_val . "&action=" . $action . "&mac=" . $mac . "&device_id=" . $dev_id . "&device_id2=" . $dev_id2 . "&sn=" . $sn . "&JsHttpRequest=1-xml";
    foreach ($params as $k => $v) {
        $url .= "&" . $k . "=" . urlencode($v);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: Mozilla/5.0 (QtEmbedded; U; Linux; C) AppleWebKit/533.3 (KHTML, like Gecko) MAG250 stbapp ver: 4 rev: 250 Safari/533.3",
        "X-User-Agent: Model: MAG250; Link: WiFi",
        "Referer: " . $portal . "/c/",
        "Accept: application/json, text/javascript, */*; q=0.01",
        "X-STB-JSON: 1",
        "Cookie: mac=" . $mac . "; stb_lang=en; timezone=Europe/London"
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($res, true);
    return isset($data['js']) ? $data['js'] : [];
}

req("stb", "handshake");
req("stb", "get_profile");

$link_data = req($type, "create_link", ["cmd" => $cmd, "forced_storage" => "0", "disable_ad" => "0"]);

if (isset($link_data['cmd'])) {
    $stream_url = explode(" ", $link_data['cmd']);
    $final_url = end($stream_url);
    if (strpos($final_url, "http") === 0) {
        header("Location: " . $final_url);
        exit;
    }
}
echo "Stream Offline or Blocked";
?>
