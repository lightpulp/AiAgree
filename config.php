<?php
return [
  'db'            => __DIR__ . '/data/basecamp.sqlite',
  'offline_after' => 300,   // seconds without packet => offline
  'low_moisture'  => 20,    // % => "Low soil moisture" event
  'token'         => 'change-me', // shared secret for receive.php
  'ollama' => ['url' => 'http://127.0.0.1:11434', 'model' => 'qwen3:8b', 'timeout' => 180],
];
