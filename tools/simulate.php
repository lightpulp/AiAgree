<?php
// php tools/simulate.php -> tests decoder + seeds fake data for 4 nodes
require __DIR__ . '/../lib/Db.php'; require __DIR__ . '/../lib/Decoder.php';
print_r(decode_packet(hex2bin('0401002A25011C01384A520C91')));   // sample payload
var_dump(decode_packet('junk'), decode_packet(''), decode_packet(null)); // all NULL
for ($node = 1; $node <= 4; $node++) for ($i = 0; $i < 40; $i++) {
  $bin = pack('CCnC', $node, 1, $i, rand(10, 60)) . pack('nn', rand(200, 320), rand(250, 350))
       . pack('CCCC', rand(50, 90), rand(20, 120), rand(0, 255), rand(130, 160));
  if ($p = decode_packet($bin)) store_packet($p);
}
echo "seeded\n";
