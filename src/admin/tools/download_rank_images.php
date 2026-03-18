<?php
$ranks = [
    'GA' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f8/US-O11_insignia.svg/512px-US-O11_insignia.svg.png',
    'GEN' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/40/US-O10_insignia.svg/512px-US-O10_insignia.svg.png',
    'LTG' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/da/US-O9_insignia.svg/512px-US-O9_insignia.svg.png',
    'MG' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/91/US-O8_insignia.svg/512px-US-O8_insignia.svg.png',
    'BG' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/23/US-O7_insignia.svg/512px-US-O7_insignia.svg.png',
    'COL' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c5/US-O6_insignia.svg/512px-US-O6_insignia.svg.png',
    'LTC' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6e/US-O5_insignia.svg/512px-US-O5_insignia.svg.png',
    'MAJ' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8f/US-O4_insignia.svg/512px-US-O4_insignia.svg.png',
    'CPT' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/72/US-O3_insignia.svg/512px-US-O3_insignia.svg.png',
    '1LT' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/72/US-O2_insignia.svg/512px-US-O2_insignia.svg.png',
    '2LT' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/05/US-O1_insignia.svg/512px-US-O1_insignia.svg.png',
    'WO1' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e3/US-Army-WO1.svg/512px-US-Army-WO1.svg.png',
    'CW2' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/de/US-Army-CW2.svg/512px-US-Army-CW2.svg.png',
    'CW3' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/US-Army-CW3.svg/512px-US-Army-CW3.svg.png',
    'CW4' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/42/US-Army-CW4.svg/512px-US-Army-CW4.svg.png',
    'CW5' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b9/US-Army-CW5.svg/512px-US-Army-CW5.svg.png',
    'PV2' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/91/Army-USA-OR-02.svg/512px-Army-USA-OR-02.svg.png',
    'PFC' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cc/Army-USA-OR-03.svg/512px-Army-USA-OR-03.svg.png',
    'CPL' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a2/Army-USA-OR-04a.svg/512px-Army-USA-OR-04a.svg.png',
    'SPC' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1c/Army-USA-OR-04b.svg/512px-Army-USA-OR-04b.svg.png',
    'SGT' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6c/Army-USA-OR-05-2015.svg/512px-Army-USA-OR-05-2015.svg.png',
    'SSG' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0b/Army-USA-OR-06.svg/512px-Army-USA-OR-06.svg.png',
    'SFC' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/71/Army-USA-OR-07.svg/512px-Army-USA-OR-07.svg.png',
    'MSG' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Army-USA-OR-08b.svg/512px-Army-USA-OR-08b.svg.png',
    '1SG' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3c/Army-USA-OR-08a.svg/512px-Army-USA-OR-08a.svg.png',
    'SGM' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/40/Army-USA-OR-09c-2015.svg/512px-Army-USA-OR-09c-2015.svg.png',
    'CSM' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/31/Army-USA-OR-09b.svg/512px-Army-USA-OR-09b.svg.png',
    'SMA' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f6/Army-USA-OR-09a.svg/512px-Army-USA-OR-09a.svg.png',
];

$dir = __DIR__ . '/../assets/images/ranks/';
if (!is_dir($dir))
    mkdir($dir, 0777, true);

$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\nAccept: image/png,image/*\r\n",
        'timeout' => 10,
        'follow_location' => true,
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);

$success = 0;
$fail = 0;

foreach ($ranks as $abbr => $url) {
    $outPath = $dir . $abbr . '.png';
    $data = @file_get_contents($url, false, $ctx);

    if ($data !== false && strlen($data) > 500) {
        // Check if it's actually a PNG (starts with PNG header)
        $header = substr($data, 0, 8);
        $isPng = (substr($header, 1, 3) === 'PNG');

        if ($isPng) {
            file_put_contents($outPath, $data);
            echo "OK: $abbr.png (" . strlen($data) . " bytes)\n";
            $success++;
        } else {
            echo "FAIL: $abbr.png (not a PNG, got " . strlen($data) . " bytes)\n";
            $fail++;
        }
    } else {
        echo "FAIL: $abbr.png (download failed)\n";
        $fail++;
    }

    // Small delay to avoid rate limiting
    usleep(500000); // 0.5 second
}

echo "\nDone! Success: $success, Failed: $fail\n";
