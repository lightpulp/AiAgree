<?php
// Called by the dashboard button. Builds per-node context from SQLite, asks Ollama, returns JSON.
// Advisory only: nothing here executes actions.
require __DIR__ . '/../lib/Db.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"error":"POST only"}'; exit; }
set_time_limit(600);

const ACTIONS = ['START_IRRIGATION', 'STOP_IRRIGATION', 'SEND_ALERT', 'NONE'];
const FLAGS   = ['NODE_OFFLINE','NO_RECENT_DATA','MOISTURE_SENSOR_SUSPECT','SENSOR_STUCK','PH_OUT_OF_RANGE','GAS_DETECTED','HEAT_STRESS'];
const STATUS  = ['OK', 'WARNING', 'CRITICAL'];

$c = cfg(); $db = db(); $now = time();
$template = @file_get_contents(__DIR__ . '/../tools/ai_prompt.txt');
if ($template === false) { http_response_code(500); echo '{"error":"tools/ai_prompt.txt missing"}'; exit; }

function node_context(PDO $db, int $id, int $now, int $offAfter): array {
  $n = $db->prepare("SELECT last_seen FROM nodes WHERE id=?"); $n->execute([$id]); $seen = (int)$n->fetchColumn();
  $l = $db->prepare("SELECT soil_moisture,soil_temp,air_temp,humidity,ph,mq2,light FROM readings WHERE node_id=? ORDER BY id DESC LIMIT 1");
  $l->execute([$id]);
  $s = $db->prepare("SELECT COUNT(*) n, AVG(soil_moisture) m_avg, MIN(soil_moisture) m_min, MAX(soil_moisture) m_max,
      AVG(soil_temp) st_avg, AVG(air_temp) at_avg, AVG(humidity) h_avg, AVG(ph) ph_avg,
      MIN(soil_temp) st_min, MAX(soil_temp) st_max, MIN(ph) ph_min, MAX(ph) ph_max, MAX(mq2) mq2_max
      FROM readings WHERE node_id=? AND ts>=?");
  $s->execute([$id, $now - 3600]); $st = $s->fetch(PDO::FETCH_ASSOC);
  $f = $db->prepare("SELECT soil_moisture FROM readings WHERE node_id=? AND ts>=? ORDER BY ts LIMIT 1");
  $f->execute([$id, $now - 3600]); $first = $f->fetchColumn();
  $e = $db->prepare("SELECT ts,message FROM events WHERE node_id=? AND type!='packet' ORDER BY id DESC LIMIT 5");
  $e->execute([$id]);
  $latest = $l->fetch(PDO::FETCH_ASSOC) ?: null;
  $round = fn($a) => array_map(fn($v) => $v === null ? null : round((float)$v, 2), $a);
  return [
    'node_id' => $id,
    'online' => ($now - $seen) < $offAfter,
    'seconds_since_last_seen' => $now - $seen,
    'latest' => $latest,
    'last_hour' => ['readings_count_1h' => (int)$st['n']] + $round(array_diff_key($st, ['n' => 1])),
    'moisture_change_last_hour' => ($latest && $first !== false) ? round($latest['soil_moisture'] - $first, 1) : null,
    'recent_events' => array_map(fn($r) => ['seconds_ago' => $now - $r['ts'], 'message' => $r['message']], $e->fetchAll(PDO::FETCH_ASSOC)),
  ];
}

function ask_ollama(array $o, string $prompt): array {
  $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n",
    'timeout' => $o['timeout'], 'ignore_errors' => true,
    'content' => json_encode(['model' => $o['model'], 'prompt' => $prompt, 'stream' => false, 'format' => 'json',
                              'options' => ['temperature' => 0]])]]);
  $r = @file_get_contents(rtrim($o['url'], '/') . '/api/generate', false, $ctx);
  if ($r === false) throw new RuntimeException('Ollama unreachable at ' . $o['url']);
  $j = json_decode($r, true);
  if (!is_array($j) || isset($j['error'])) throw new RuntimeException('Ollama error: ' . ($j['error'] ?? 'bad response'));
  return json_decode($j['response'] ?? '', true) ?? ['_invalid' => $j['response'] ?? ''];
}

function sanitize(array $r, int $id): array {
  if (isset($r['_invalid']))
    return ['node_id' => $id, 'status' => 'WARNING', 'actions' => [], 'reason' => 'AI returned invalid output.',
            'confidence' => 0, 'sensor_flags' => [], 'requires_human_review' => true, 'raw' => $r['_invalid']];
  $actions = array_values(array_intersect(ACTIONS, (array)($r['actions'] ?? [])));
  $status  = in_array($r['status'] ?? '', STATUS, true) ? $r['status'] : 'WARNING';
  $conf    = max(0, min(1, (float)($r['confidence'] ?? 0)));
  return ['node_id' => $id, 'status' => $status, 'actions' => $actions ?: ['NONE'],
          'reason' => substr((string)($r['reason'] ?? ''), 0, 500), 'confidence' => $conf,
          'sensor_flags' => array_values(array_intersect(FLAGS, (array)($r['sensor_flags'] ?? []))),
          'requires_human_review' => (bool)($r['requires_human_review'] ?? false) || $conf < 0.6 || $status === 'WARNING' && !isset($r['status'])];
}

$results = [];
try {
  foreach ($db->query("SELECT id FROM nodes ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $id) {
    $ctx = node_context($db, (int)$id, $now, $c['offline_after']);
    $prompt = strtr($template, ['{{LOW_MOISTURE}}' => $c['low_moisture'], '{{NOW}}' => date('c', $now),
                                '{{NODE_DATA}}' => json_encode($ctx, JSON_PRETTY_PRINT)]);
    $results[] = sanitize(ask_ollama($c['ollama'], $prompt), (int)$id);
  }
  echo json_encode(['model' => $c['ollama']['model'], 'generated_at' => date('c'), 'results' => $results], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
  http_response_code(502); echo json_encode(['error' => $e->getMessage(), 'partial_results' => $results]);
}
