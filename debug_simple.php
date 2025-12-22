<?php

require_once 'vendor/autoload.php';

use RegexParser\Lint\TokenBasedExtractionStrategy;

$strategy = new TokenBasedExtractionStrategy();

// Test the simple case that's failing
$line = 'preg_match("/test/", (string) $subject);';

// Manually tokenize to see what we get
$tokens = token_get_all("<?php\n" . $line . "\n");

echo "Tokens:\n";
foreach ($tokens as $token) {
    if (!is_array($token)) {
        echo "Simple: $token\n";
    }
}

// Now test the extraction
$results = $strategy->extract(['/tmp/test_failing.php']);
echo "\nExtracted pattern: [" . $results[0]->pattern . "]\n";

unlink('/tmp/test_failing.php');