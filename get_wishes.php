<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: https://ordinarymantrying.com");
header("Cache-Control: public, max-age=300");

$pool_file = __DIR__ . '/wishes_pool.json';
if (!file_exists($pool_file)) {
    echo json_encode(["status"=>"ok","wishes"=>[]]);
    exit;
}

$pool = json_decode(file_get_contents($pool_file), true) ?? [];
$approved = array_values(array_filter($pool, fn($w) => ($w['status'] ?? '') === 'approved'));
$out = array_slice(array_map(fn($w) => [
    "text" => $w['text'],
    "date" => $w['date'] ?? ''
], $approved), 0, 100);

echo json_encode(["status"=>"ok","wishes"=>$out], JSON_UNESCAPED_UNICODE);
