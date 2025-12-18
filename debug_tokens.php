<?php

$tempDir = '/tmp/test_debug';
mkdir($tempDir);
$tempFile = $tempDir . '/test.php';
file_put_contents($tempFile, '<?php preg_match("/" . "test" . "/i", $subject);');

$tokens = token_get_all(file_get_contents($tempFile), TOKEN_PARSE);

echo "File content: " . file_get_contents($tempFile) . "\n";
echo "Tokens:\n";
foreach ($tokens as $i => $token) {
    if (is_array($token)) {
        echo "  $i: T_" . token_name($token[0]) . " = " . var_export($token[1], true) . " (line {$token[2]})\n";
    }
}

unlink($tempFile);
rmdir($tempDir);