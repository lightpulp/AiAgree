<?php
// 13-byte binary packet -> array, or null if malformed. Never throws.
function decode_packet($bin): ?array {
  try {
    if (!is_string($bin) || strlen($bin) !== 13) return null;
    $u8  = fn($o) => ord($bin[$o]);
    $s16 = function ($o) use ($bin) {            // big-endian signed 16-bit
      $v = (ord($bin[$o]) << 8) | ord($bin[$o + 1]);
      return $v > 32767 ? $v - 65536 : $v;
    };
    if ($u8(0) === 0 || $u8(1) !== 1) return null;   // node 0 invalid; only type 1 (telemetry) defined
    $p = [
      'node_id' => $u8(0), 'type' => $u8(1),
      'seq' => ($u8(2) << 8) | $u8(3),
      'soil_moisture' => $u8(4),
      'soil_temp' => $s16(5) / 10, 'air_temp' => $s16(7) / 10,
      'humidity' => $u8(9), 'mq2' => $u8(10), 'light' => $u8(11),
      'ph' => round($u8(12) / 20, 2),               // 0..12.75, 0.05 steps
    ];
    if ($p['soil_moisture'] > 100 || $p['humidity'] > 100) return null;
    foreach (['soil_temp', 'air_temp'] as $k) if ($p[$k] < -40 || $p[$k] > 85) return null;
    return $p;
  } catch (Throwable $e) { return null; }
}
