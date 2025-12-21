<?php

require_once 'vendor/autoload.php';

use RegexParser\Lint\TokenBasedExtractionStrategy;

$strategy = new TokenBasedExtractionStrategy();

// Test the exact pattern from the file
$testContent = "<?php\npreg_replace('/QUICK_CHECK = .*;/m', 'QUICK_CHECK', \$file);";
file_put_contents('/tmp/test_extract.php', $testContent);

$results = $strategy->extract(['/tmp/test_extract.php']);

foreach ($results as $result) {
    echo "Pattern: [" . $result->pattern . "]\n";
    echo "Line: " . $result->line . "\n";
    echo "Source: " . $result->source . "\n\n";
}

unlink('/tmp/test_extract.php');