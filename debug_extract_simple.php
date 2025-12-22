<?php

require_once 'vendor/autoload.php';

use RegexParser\Lint\TokenBasedExtractionStrategy;

$strategy = new TokenBasedExtractionStrategy();

// Test the extraction directly
$tempFile = tempnam(sys_get_temp_dir(), 'debug_extract');
file_put_contents($tempFile, "<?php\npreg_match('/test/', \$subject);\n");

$results = $strategy->extract([$tempFile]);

echo "Results:\n";
var_dump($results);

unlink($tempFile);