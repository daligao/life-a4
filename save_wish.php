<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: https://ordinarymantrying.com");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status"=>"error","message"=>"Method not allowed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$wish  = isset($input['wish']) ? trim($input['wish']) : '';

// Layer 1: Honeypot
if (!empty($input['username'])) {
    echo json_encode(["status"=>"success","message"=>"Stored"]);
    exit;
}

// Layer 2: Length
$len = mb_strlen($wish, 'UTF-8');
if ($len < 5 || $len > 160) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"Wish must be 5–160 characters."]);
    exit;
}

// Layer 3: URL block
if (preg_match('/(https?:\/\/|www\.|\b[a-z0-9-]+\.(com|net|org|io|xyz|top|link|me|cc)\b)/i', $wish)) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"No links allowed."]);
    exit;
}

// Layer 4: Keyword blacklist
$bad = ['casino','viagra','porn','crypto','bitcoin','poker','betting','cialis','make money','博彩','发票','贷款','兼职'];
foreach ($bad as $w) {
    if (mb_stripos($wish, $w, 0, 'UTF-8') !== false) {
        http_response_code(400);
        echo json_encode(["status"=>"error","message"=>"Content blocked."]);
        exit;
    }
}

// Layer 5: IP rate limit — 3 per hour
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$now = time();
$rl_file = __DIR__ . '/wish_ip_rate.json';
$rl = file_exists($rl_file) ? (json_decode(file_get_contents($rl_file), true) ?? []) : [];
$rl = array_filter($rl, fn($r) => ($now - $r['t']) < 3600);
$hits = array_sum(array_map(fn($r) => $r['ip'] === $ip ? 1 : 0, $rl));
if ($hits >= 3) {
    http_response_code(429);
    echo json_encode(["status"=>"error","message"=>"Too many submissions. Try again in an hour."]);
    exit;
}
$rl[] = ['ip'=>$ip,'t'=>$now];
file_put_contents($rl_file, json_encode(array_values($rl)));

// Write wish as pending
$pool_file = __DIR__ . '/wishes_pool.json';
$entry = [
    "id"     => uniqid('w', true),
    "text"   => htmlspecialchars($wish, ENT_QUOTES, 'UTF-8'),
    "ts"     => $now,
    "date"   => gmdate("Y-m-d H:i", $now) . " UTC",
    "status" => "pending"
];

$fp = fopen($pool_file, file_exists($pool_file) ? 'r+' : 'w+');
if (flock($fp, LOCK_EX)) {
    $sz = filesize($pool_file);
    $pool = $sz > 0 ? (json_decode(fread($fp, $sz), true) ?? []) : [];
    array_unshift($pool, $entry);
    if (count($pool) > 2000) $pool = array_slice($pool, 0, 2000);
    ftruncate($fp, 0); fseek($fp, 0);
    fwrite($fp, json_encode($pool, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp); flock($fp, LOCK_UN);
}
fclose($fp);

echo json_encode(["status"=>"success","message"=>"Your wish is under review and will appear soon."]);
