<?php
require __DIR__ . '/../lib/Db.php';
header('Content-Type: application/json');
$db = db(); $now = time(); $off = cfg()['offline_after'];
$act = $_GET['action'] ?? 'summary';

if ($act === 'summary') {
  foreach ($db->query("SELECT id FROM nodes WHERE last_seen < " . ($now - $off) . " AND offline_notified=0")->fetchAll(PDO::FETCH_COLUMN) as $id) {
    add_event((int)$id, 'warn', 'Node offline');
    $db->exec("UPDATE nodes SET offline_notified=1 WHERE id=" . (int)$id);
  }
  
  $nodes = $db->query("SELECT n.id, n.last_seen, r.soil_moisture, r.soil_temp, r.air_temp, r.humidity, r.ph, r.mq2, r.light
    FROM nodes n LEFT JOIN readings r ON r.id=(SELECT MAX(id) FROM readings WHERE node_id=n.id) ORDER BY n.id")->fetchAll(PDO::FETCH_ASSOC);
  
  foreach ($nodes as &$n) { $n['ago'] = $now - $n['last_seen']; $n['online'] = $n['ago'] < $off; }
  
  $events = $db->query("SELECT ts,node_id,type,message FROM events ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['nodes' => $nodes, 'events' => $events]); exit;
}

if ($act === 'analytics') {
  $period = $_GET['period'] ?? 'day';
  [$since, $fmt, $grp] = [
    'day'   => ['-1 day',  '%H:00', '%Y-%m-%d %H'],
    'week'  => ['-7 days', '%m-%d', '%Y-%m-%d'],
    'month' => ['-30 days','%m-%d', '%Y-%m-%d'],
  ][$period] ?? ['-1 day', '%H:00', '%Y-%m-%d %H'];
  $metric = in_array($_GET['metric'] ?? '', ['soil_moisture','soil_temp','air_temp','humidity','ph','mq2','light']) ? $_GET['metric'] : 'soil_moisture';
  $node = !empty($_GET['node']) ? "AND node_id=" . (int)$_GET['node'] : "";
  $q = "SELECT strftime('$fmt', MIN(ts),'unixepoch','localtime') l, ROUND(AVG($metric),2) v FROM readings
        WHERE ts >= strftime('%s','now','$since') $node
        GROUP BY strftime('$grp', ts,'unixepoch','localtime') ORDER BY MIN(ts)";
  echo json_encode($db->query($q)->fetchAll(PDO::FETCH_ASSOC)); exit;
}

if ($act === 'system') {
  $c = cfg();
  echo json_encode(['php' => PHP_VERSION,
    'readings' => (int)$db->query("SELECT COUNT(*) FROM readings")->fetchColumn(),
    'events' => (int)$db->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'db_kb' => round(filesize($c['db']) / 1024, 1),
    'offline_after' => $c['offline_after'], 'low_moisture' => $c['low_moisture'], 'time' => date('c')]); exit;
}
http_response_code(404); echo '{}';
