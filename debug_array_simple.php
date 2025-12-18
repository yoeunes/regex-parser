<?php

$tempFile = '/tmp/test_array.php';
file_put_contents($tempFile, '<?php
    preg_replace_callback_array([
        "/pattern1/" => "callback1",
        "/pattern2/" => "callback2",
    ], $data);
');

$tokens = token_get_all(file_get_contents($tempFile), TOKEN_PARSE);

echo "Looking for preg_replace_callback_array:\n";
$foundCallbackArray = false;
foreach ($tokens as $i => $token) {
    if (is_array($token) && $token[1] === 'preg_replace_callback_array') {
        echo "  Found preg_replace_callback_array at index $i\n";
        $foundCallbackArray = true;
        
        // Look ahead for array syntax
        $j = $i + 1;
        while ($j < count($tokens) && isset($tokens[$j]) && (!is_array($tokens[$j]) || $tokens[$j][0] === T_WHITESPACE)) {
            $j++;
        }
        
        if (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_ARRAY) {
            echo "  Found T_ARRAY at index $j\n";
            break;
        }
        break;
    }
}

if (!$foundCallbackArray) {
    echo "  preg_replace_callback_array not found!\n";
}

unlink($tempFile);