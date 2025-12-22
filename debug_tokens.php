<?php

require_once 'vendor/autoload.php';

use RegexParser\Lint\TokenBasedExtractionStrategy;

$strategy = new TokenBasedExtractionStrategy();

// Test the actual line content from the file
$line = '        $fs->dumpFile($file, preg_replace(\'/QUICK_CHECK = .*;/m\', "QUICK_CHECK = {$quickCheck};", $fs->readFile($file)));';

echo "Original line: [" . $line . "]\n";

// Check how token-based extraction would handle this
$tokens = token_get_all("<?php\n" . $line . "\n");

foreach ($tokens as $i => $token) {
    if (!is_array($token)) {
        echo "Token $i: [$token]\n";
    } else {
        echo "Token $i: [" . $token[0] . "] " . json_encode($token[1]) . " (line " . $token[2] . ")\n";
    }
}