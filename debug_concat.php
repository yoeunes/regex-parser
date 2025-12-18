<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use RegexParser\Bridge\Symfony\Extractor\TokenBasedExtractionStrategy;

$strategy = new TokenBasedExtractionStrategy();

// Test the concatenation case that's failing
$tempFile = '/tmp/test_concat.php';
file_put_contents($tempFile, '<?php preg_match("/" . "test" . "/i", $subject);');

$result = $strategy->extract([$tempFile]);

echo "Expected: /test/i\n";
echo "Actual: " . ($result[0]->pattern ?? 'null') . "\n";

unlink($tempFile);