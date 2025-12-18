<?php

$tempFile = '/tmp/test_methods.php';
file_put_contents($tempFile, '<?php
            $pattern = "/test/";
            preg_match($pattern, $subject); // Method call, not direct function call
            MyClass::preg_match("/test/", $subject); // Static method call, not direct function call
        ');

$tokens = token_get_all(file_get_contents($tempFile), TOKEN_PARSE);

echo "Looking for direct preg_* calls:\n";
foreach ($tokens as $i => $token) {
    if (is_array($token) && $token[1] === 'preg_match') {
        // Look at previous token
        if ($i > 0) {
            $prevToken = $tokens[$i - 1];
            if (is_array($prevToken)) {
                echo "  Previous token: " . token_name($prevToken[0]) . "\n";
            }
        }
    }
}

unlink($tempFile);