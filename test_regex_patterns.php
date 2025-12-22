<?php

// Test different regex patterns for the escaped string
$patterns = [
    '/^([\'"{\/\#~%])(.*?)([\'"{\/\#~%])([a-zA-Z]*)$/',
    '/^([\'"{\/\#~%])(.*?)([\'"{\/\#~%])([a-zA-Z]*)$/',
    '/^([\\\'"{}\/#~%])(.*?)([\\\'"{}\/#~%])([a-zA-Z]*)$/',
    '/^([\'"{\/\#~%])(.*?)([\'"{\/\#~%])([a-zA-Z]*)$/',
];

$testPattern = "\/QUICK_CHECK = .*;\/m'";

foreach ($patterns as $i => $regex) {
    echo "Pattern $i: $regex\n";
    if (preg_match($regex, $testPattern, $matches)) {
        echo "  MATCHED!\n";
        echo "  Delimiter: [" . $matches[1] . "]\n";
        echo "  Body: [" . $matches[2] . "]\n";
        echo "  Flags: [" . ($matches[3] ?? '') . "]\n";
    } else {
        echo "  No match\n";
    }
    echo "\n";
}