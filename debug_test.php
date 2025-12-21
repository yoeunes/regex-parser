<?php

require __DIR__ . '/vendor/autoload.php';

use RegexParser\Lint\RegexPatternExtractor;
use RegexParser\Lint\TokenBasedExtractionStrategy;

$extractor = new RegexPatternExtractor(new TokenBasedExtractionStrategy());

$phpCode = '<?php
preg_match("/^[a-z]+$/i", $subject);
';

$tempFile = tempnam(sys_get_temp_dir(), 'regex_test_');
file_put_contents($tempFile, $phpCode);

echo "Temp file: $tempFile\n";
echo "Content:\n$phpCode\n\n";

$occurrences = $extractor->extract([$tempFile]);
echo "Occurrences: " . count($occurrences) . "\n";
foreach ($occurrences as $o) {
    echo "Pattern: " . $o->pattern . "\n";
}

unlink($tempFile);

// Test the isValidPcrePattern logic directly
$strategy = new TokenBasedExtractionStrategy();
$reflection = new ReflectionClass($strategy);

$isValidMethod = $reflection->getMethod('isValidPcrePattern');
$isValidMethod->setAccessible(true);

$testPatterns = [
    '/^[a-z]+$/i',
    '/hello/',
    '~test~',
    '#pattern#',
];

echo "\nTesting isValidPcrePattern:\n";
foreach ($testPatterns as $pattern) {
    $result = $isValidMethod->invoke($strategy, $pattern);
    echo "  '$pattern' => " . ($result ? 'valid' : 'invalid') . "\n";
}

// Test findClosingDelimiter
$findMethod = $reflection->getMethod('findClosingDelimiter');
$findMethod->setAccessible(true);

echo "\nTesting findClosingDelimiter:\n";
foreach ($testPatterns as $pattern) {
    $result = $findMethod->invoke($strategy, $pattern, $pattern[0], $pattern[0]);
    echo "  '$pattern' => position: " . ($result ?? 'null') . "\n";
}
