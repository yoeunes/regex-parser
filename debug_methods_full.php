<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use RegexParser\Bridge\Symfony\Extractor\TokenBasedExtractionStrategy;

// Test: MyClass::preg_match case
$tempFile = '/tmp/test_methods.php';
file_put_contents($tempFile, '<?php
            $pattern = "/test/";
            preg_match($pattern, $subject); // Method call, not direct function call
            MyClass::preg_match("/test/", $subject); // Static method call, not direct function call
        ');

// Load the strategy to test it
$strategy = new TokenBasedExtractionStrategy();
$result = $strategy->extract([$tempFile]);

echo "Found " . count($result) . " patterns:\n";
foreach ($result as $i => $occurrence) {
    echo "  $i: " . $occurrence->pattern . " at line " . $occurrence->line . "\n";
}

unlink($tempFile);