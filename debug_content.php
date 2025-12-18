<?php

$content = '<?php preg_match("/" . "test" . "/i", $subject);';
echo "Writing content: " . var_export($content, true) . "\n";

$tempDir = sys_get_temp_dir() . '/test_' . uniqid();
mkdir($tempDir);
$tempFile = $tempDir . '/test.php';
file_put_contents($tempFile, $content);

$readBack = file_get_contents($tempFile);
echo "Read back: " . var_export($readBack, true) . "\n";

$tokens = token_get_all($readBack);

echo "\nTokens:\n";
foreach ($tokens as $i => $token) {
    if (is_array($token) && T_CONSTANT_ENCAPSED_STRING === $token[0]) {
        echo $i . ": " . token_name($token[0]) . " => " . var_export($token[1], true) . " (line " . $token[2] . ")\n";
    }
}

unlink($tempFile);
rmdir($tempDir);