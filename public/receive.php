<?php
// POST a hex string or raw bytes. Header: X-Token. Bridge scripts (MQTT/serial) call this.
require __DIR__ . '/../lib/Db.php'; require __DIR__ . '/../lib/Decoder.php';
header('Content-Type: application/json');
if (($_SERVER['HTTP_X_TOKEN'] ?? '') !== cfg()['token']) { http_response_code(401); echo '{"ok":false}'; exit; }
$body = file_get_contents('php://input');
$t = trim($body);
if ($t !== '' && ctype_xdigit($t) && strlen($t) % 2 === 0) $body = hex2bin($t);
$p = decode_packet($body);
if ($p === null) { http_response_code(400); echo json_encode(['ok'=>false,'data'=>null]); exit; }
try { echo json_encode(['ok'=>true,'stored'=>store_packet($p)]); }
catch (Throwable $e) { http_response_code(500); echo '{"ok":false}'; }
