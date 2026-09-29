<?php
// php tools/simulate.php -> tests decoder + seeds fake data for 4 nodes
require __DIR__ . '/../lib/Db.php'; 
require __DIR__ . '/../lib/Decoder.php';


// sample 1
//print_r(decode_packet(hex2bin('0401002A25011C01384A520C91')));  


// sample 2
// print_r(decode_packet(hex2bin('0101002B0F011E0128503C0E90')));

// sample 3
$packets = [
    // Node 1 — CRITICAL soil moisture
    pack('CCnCnnCCCC',
        1,      // node
        1,      // version
        101,    // sequence
        5,      // soil moisture %
        280,    // soil temp 28.0°C
        320,    // air temp 32.0°C
        45,     // humidity
        30,     // MQ2
        220,    // light
        140     // pH encoded value
    ),

    // Node 2 — GOOD condition
    pack('CCnCnnCCCC',
        2,
        1,
        101,
        55,     // soil moisture %
        250,    // soil temp 25.0°C
        280,    // air temp 28.0°C
        70,     // humidity
        25,     // MQ2
        150,    // light
        140     // pH encoded value
    ),

    // Node 3 — GOOD condition
    pack('CCnCnnCCCC',
        3,
        1,
        101,
        60,     // soil moisture %
        260,    // soil temp 26.0°C
        290,    // air temp 29.0°C
        68,     // humidity
        28,     // MQ2
        160,    // light
        140     // pH encoded value
    ),

    // Node 4 — CRITICAL soil moisture
    pack('CCnCnnCCCC',
        4,
        1,
        101,
        7,      // soil moisture %
        300,    // soil temp 30.0°C
        340,    // air temp 34.0°C
        40,     // humidity
        35,     // MQ2
        230,    // light
        140     // pH encoded value
    )
];

foreach ($packets as $bin) {
    $p = decode_packet($bin);

    if ($p) {
        print_r($p);
        store_packet($p);
    }
}
