<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use RegexParser\Bridge\Symfony\Extractor\TokenBasedExtractionStrategy;

$strategy = new TokenBasedExtractionStrategy();

// Test the specific case that's failing
$tempFile = '/tmp/test_array.php';
file_put_contents($tempFile, '<?php
    preg_replace_callback_array([
        "/pattern1/" => "callback1",
        "/pattern2/" => "callback2",
    ], $data);
');

$result = $strategy->extract([$tempFile]);

echo "Found " . count($result) . " patterns:\n";
foreach ($result as $i => $occurrence) {
    echo "  $i: " . $occurrence->pattern . "\n";
}

unlink($tempFile);