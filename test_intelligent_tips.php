<?php

require_once __DIR__ . '/vendor/autoload.php';

use RegexParser\Regex;
use RegexParser\Lint\RegexAnalysisService;

$regex = Regex::create();
$analysisService = new RegexAnalysisService($regex);

// Test patterns with different errors
$testPatterns = [
    '/[a-z/' => 'Unclosed character class',
    '/unclosed' => 'Missing closing delimiter',
    '/a{5,3}/' => 'Invalid quantifier range',
    '/backref\\9/' => 'Invalid backreference',
    '/(a+)+/' => 'Pattern to test ReDoS (but validation passes)',
];

foreach ($testPatterns as $pattern => $description) {
    echo "Testing: $pattern ($description)\n";

    $validation = $regex->validate($pattern);
    if (!$validation->isValid) {
        echo "Error: " . $validation->error . "\n";

        // Test our intelligent tip generation
        $reflection = new ReflectionClass($analysisService);
        $method = $reflection->getMethod('getTipForValidationError');
        $method->setAccessible(true);

        $tip = $method->invoke($analysisService, $validation->error, $pattern, $validation);
        echo "Intelligent Tip: $tip\n";
    }
    echo "\n";
}