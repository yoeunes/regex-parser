<?php

require_once 'vendor/autoload.php';

// Test the exact processing with our current implementation
$pattern = "\/QUICK_CHECK = .*;\/m'";
echo "Input pattern: [" . $pattern . "]\n";

if (preg_match('/^([\'"{\/\#~%])(.*?)([\'"{\/\#~%])([a-zA-Z]*)$/', $pattern, $matches)) {
    echo "Regex matched!\n";
    echo "Delimiter: [" . $matches[1] . "]\n";
    echo "Body: [" . $matches[2] . "]\n";
    echo "Flags: [" . $matches[3] . "]\n";
    
    $unescapedBody = stripslashes($matches[2]);
    echo "Unescaped body: [" . $unescapedBody . "]\n";
    
    $fullPattern = $matches[1] . $unescapedBody . $matches[1] . $matches[3];
    echo "Reconstructed: [" . $fullPattern . "]\n";
} else {
    echo "No match!\n";
}