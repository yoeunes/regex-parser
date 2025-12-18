<?php

$tempDir = sys_get_temp_dir() . '/test_' . uniqid();
mkdir($tempDir);
$tempFile = $tempDir . '/test.php';
file_put_contents($tempFile, '<?php preg_match("/" . "test" . "/i", $subject);');

require_once __DIR__ . '/src/Bridge/Symfony/Extractor/Strategy/ExtractionStrategyInterface.php';
require_once __DIR__ . '/src/Bridge/Symfony/Extractor/RegexPatternOccurrence.php';
require_once __DIR__ . '/src/Bridge/Symfony/Extractor/Strategy/TokenBasedExtractionStrategy.php';

use RegexParser\Bridge\Symfony\Extractor\Strategy\TokenBasedExtractionStrategy;

$strategy = new TokenBasedExtractionStrategy();
$result = $strategy->extract([$tempFile]);

echo "Found patterns: " . count($result) . "\n";
if (!empty($result)) {
    echo "Pattern: '" . $result[0]->pattern . "'\n";
} else {
    echo "No patterns found\n";
}

unlink($tempFile);
rmdir($tempDir);