<?php
function cfg(): array { static $c; return $c ??= require __DIR__ . '/../config.php'; }
function db(): PDO {
  static $pdo;
  if ($pdo) return $pdo;
  $pdo = new PDO('sqlite:' . cfg()['db']);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec("PRAGMA journal_mode=WAL");
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS nodes(id INTEGER PRIMARY KEY, last_seen INTEGER, last_seq INTEGER, offline_notified INTEGER DEFAULT 0);
    CREATE TABLE IF NOT EXISTS readings(id INTEGER PRIMARY KEY, node_id INTEGER, seq INTEGER, ts INTEGER,
      soil_moisture REAL, soil_temp REAL, air_temp REAL, humidity REAL, mq2 REAL, light REAL, ph REAL);
    CREATE INDEX IF NOT EXISTS idx_r ON readings(node_id, ts);
    CREATE TABLE IF NOT EXISTS events(id INTEGER PRIMARY KEY, ts INTEGER, node_id INTEGER, type TEXT, message TEXT);
  ");
  return $pdo;
}
function add_event(int $node, string $type, string $msg): void {
  db()->prepare("INSERT INTO events(ts,node_id,type,message) VALUES(?,?,?,?)")->execute([time(), $node, $type, $msg]);
}
function store_packet(array $p): bool {
  $db = db();
  $q = $db->prepare("SELECT last_seq, offline_notified FROM nodes WHERE id=?"); $q->execute([$p['node_id']]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if ($row && (int)$row['last_seq'] === $p['seq']) return false;   // mesh duplicate
  $db->prepare("INSERT INTO readings(node_id,seq,ts,soil_moisture,soil_temp,air_temp,humidity,mq2,light,ph) VALUES(?,?,?,?,?,?,?,?,?,?)")
     ->execute([$p['node_id'],$p['seq'],time(),$p['soil_moisture'],$p['soil_temp'],$p['air_temp'],$p['humidity'],$p['mq2'],$p['light'],$p['ph']]);
  $db->prepare("INSERT INTO nodes(id,last_seen,last_seq,offline_notified) VALUES(?,?,?,0)
                ON CONFLICT(id) DO UPDATE SET last_seen=excluded.last_seen,last_seq=excluded.last_seq,offline_notified=0")
     ->execute([$p['node_id'], time(), $p['seq']]);
  add_event($p['node_id'], 'packet', 'Packet received');
  if ($p['soil_moisture'] < cfg()['low_moisture']) add_event($p['node_id'], 'alert', 'Low soil moisture');
  if ($row && $row['offline_notified']) add_event($p['node_id'], 'info', 'Node back online');
  return true;
}
