<?php

$tempDir = sys_get_temp_dir() . '/test_' . uniqid();
mkdir($tempDir);
$tempFile = $tempDir . '/test.php';
file_put_contents($tempFile, '<?php preg_match("/" . "test" . "/i", $subject);');

$tokens = token_get_all('<?php preg_match("/" . "test" . "/i", $subject);');

echo "Tokens:\n";
foreach ($tokens as $i => $token) {
    if (is_array($token)) {
        echo $i . ": " . token_name($token[0]) . " => '" . $token[1] . "' (line " . $token[2] . ")\n";
    } else {
        echo $i . ": '" . $token . "'\n";
    }
}

function nextMeaningfulTokenIndex(array $tokens, int $start): ?int
{
    $count = count($tokens);
    for ($i = $start; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            return $i;
        }
        
        if (!in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $i;
        }
    }
    return null;
}

// Simulate exact extraction logic
$parts = [];
$index = 3; // Start at first string token
$count = count($tokens);
$firstLine = null;

echo "\nStarting extraction at index $index\n";

while ($index < $count) {
    $token = $tokens[$index];
    echo "\nIndex $index: " . (is_array($token) ? token_name($token[0]) . " => '" . $token[1] . "'" : $token) . "\n";

    if (is_array($token)) {
        if (T_CONSTANT_ENCAPSED_STRING === $token[0]) {
            echo "  -> Found string: " . $token[1] . "\n";
            // Simple decode for test
            $content = substr($token[1], 1, -1);
            $parts[] = $content;
            if (null === $firstLine) {
                $firstLine = $token[2];
            }
        } elseif (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
            echo "  -> Skipping ignorable\n";
        } else {
            echo "  -> Not string or ignorable, breaking\n";
            break;
        }
    } elseif ('.' === $token) {
        echo "  -> Found concat\n";
    } else {
        echo "  -> Other token, breaking\n";
        break;
    }

    $next = nextMeaningfulTokenIndex($tokens, $index + 1);
    echo "  -> Next meaningful: " . ($next ?? 'null') . "\n";
    if (null === $next) {
        break;
    }
    $index = $next;
}

echo "\nFinal parts: " . json_encode($parts) . "\n";
echo "Pattern: " . implode('', $parts) . "\n";

unlink($tempFile);
rmdir($tempDir);