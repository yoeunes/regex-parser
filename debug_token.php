<?php

$tempDir = sys_get_temp_dir() . '/test_' . uniqid();
mkdir($tempDir);
$tempFile = $tempDir . '/test.php';
file_put_contents($tempFile, '<?php preg_match("/" . "test" . "/i", $subject);');

echo "Testing concatenated pattern extraction\n";

$strategy = new \RegexParser\Bridge\Symfony\Extractor\Strategy\TokenBasedExtractionStrategy();
$result = $strategy->extract([$tempFile]);

echo "Found patterns: " . count($result) . "\n";
if (!empty($result)) {
    echo "Pattern: '" . $result[0]->pattern . "'\n";
}

// Debug token by token
$code = file_get_contents($tempFile);
$tokens = token_get_all($code);

echo "\nTokens:\n";
foreach ($tokens as $i => $token) {
    if (is_array($token)) {
        echo $i . ": " . token_name($token[0]) . " => '" . $token[1] . "' (line " . $token[2] . ")\n";
    } else {
        echo $i . ": '" . $token . "'\n";
    }
}

require_once __DIR__ . '/src/Bridge/Symfony/Extractor/Strategy/ExtractionStrategyInterface.php';
require_once __DIR__ . '/src/Bridge/Symfony/Extractor/RegexPatternOccurrence.php';
require_once __DIR__ . '/src/Bridge/Symfony/Extractor/Strategy/TokenBasedExtractionStrategy.php';

use RegexParser\Bridge\Symfony\Extractor\Strategy\TokenBasedExtractionStrategy;

$tokens = token_get_all('<?php preg_match("/test/", $subject);');

echo "Tokens:\n";
foreach ($tokens as $i => $token) {
    if (is_array($token)) {
        echo $i . ": " . token_name($token[0]) . " => '" . $token[1] . "' (line " . $token[2] . ")\n";
    } else {
        echo $i . ": '" . $token . "'\n";
    }
}

// Test the decodeConstantString function
$literal = '"/test/"';
$pattern = trim($literal, "'\"");

echo "\nOriginal literal: $literal\n";
echo "Trimmed pattern: $pattern\n";

// Test extraction directly
$reflection = new ReflectionClass(TokenBasedExtractionStrategy::class);
$method = $reflection->getMethod('decodeConstantString');
$method->setAccessible(true);

$decoded = $method->invoke(new TokenBasedExtractionStrategy(), $literal);
echo "Decoded pattern: " . ($decoded ?? 'null') . "\n";

// Test the strategy step by step
$strategy = new TokenBasedExtractionStrategy();
$reflection = new ReflectionClass($strategy);

// Test iteratePhpFiles method
$iterateMethod = $reflection->getMethod('iteratePhpFiles');
$iterateMethod->setAccessible(true);
$files = iterator_to_array($iterateMethod->invoke($strategy, [$tempFile]));
echo "Files found: " . count($files) . "\n";

// Test extractFromFile method
$extractMethod = $reflection->getMethod('extractFromFile');
$extractMethod->setAccessible(true);
$occurrences = $extractMethod->invoke($strategy, $tempFile);
echo "Occurrences found in file: " . count($occurrences) . "\n";

$result = $strategy->extract([$tempFile]);

echo "Final patterns found: " . count($result) . "\n";
if (!empty($result)) {
    echo "First pattern: " . $result[0]->pattern . "\n";
}

$code = file_get_contents($tempFile);
$tokens = token_get_all($code);

echo "Tokens:\n";
foreach ($tokens as $i => $token) {
    if (is_array($token)) {
        echo $i . ": " . token_name($token[0]) . " => '" . $token[1] . "' (line " . $token[2] . ")\n";
    } else {
        echo $i . ": '" . $token . "'\n";
    }
}

// Test the decodeConstantString function
$literal = '"/test/"';
$pattern = trim($literal, "'\"");

echo "Original literal: $literal\n";
echo "Trimmed pattern: $pattern\n";

// Test extraction directly
$reflection = new ReflectionClass(TokenBasedExtractionStrategy::class);
$method = $reflection->getMethod('decodeConstantString');
$method->setAccessible(true);

$decoded = $method->invoke(new TokenBasedExtractionStrategy(), $literal);
echo "Decoded pattern: " . ($decoded ?? 'null') . "\n";

$strategy = new TokenBasedExtractionStrategy();
$result = $strategy->extract([$tempFile]);

echo "Found patterns: " . count($result) . "\n";
if (!empty($result)) {
    echo "First pattern: " . $result[0]->pattern . "\n";
}

unlink($tempFile);