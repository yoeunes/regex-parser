<?php

$tempDir = sys_get_temp_dir() . '/test_' . uniqid();
mkdir($tempDir);
$tempFile = $tempDir . '/test.php';
$content = '<?php preg_match("/" . "test" . "/i", $subject);';
echo "Content: [" . var_export($content, true) . "]\n";
echo "Length: " . strlen($content) . "\n";
file_put_contents($tempFile, $content);

echo "Testing concatenated pattern extraction\n";

// Test concatenated pattern logic directly
$tokens = token_get_all('<?php preg_match("/" . "test" . "/i", $subject);');

echo "\nTokens:\n";
foreach ($tokens as $i => $token) {
    if (is_array($token)) {
        echo $i . ": " . token_name($token[0]) . " => '" . $token[1] . "' (line " . $token[2] . ")\n";
    } else {
        echo $i . ": '" . $token . "'\n";
    }
}

// Test concatenated extraction logic
$parts = [];
$index = 4; // Start at the first string token
$count = count($tokens);
$firstLine = null;

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

while ($index < $count) {
    $token = $tokens[$index];
    
    if (is_array($token) && T_CONSTANT_ENCAPSED_STRING === $token[0]) {
        $literal = $token[1];
        echo "\nProcessing literal: $literal\n";
        
        $len = strlen($literal);
        if ($len >= 2) {
            $quote = $literal[0];
            if (("'" === $quote || '"' === $quote) && $quote === $literal[$len - 1]) {
                $content = substr($literal, 1, -1);
                if ("'" === $quote) {
                    $content = str_replace(['\\\\', "\\'"], ['\\', "'"], $content);
                } else {
                    $content = stripcslashes($content);
                }
                echo "Decoded content: '$content'\n";
                $parts[] = $content;
                if (null === $firstLine) {
                    $firstLine = $token[2];
                }
            }
        }
    } elseif ('.' === $token) {
        echo "Found concatenation\n";
    } else {
        echo "Stopping at: " . (is_array($token) ? token_name($token[0]) : $token) . "\n";
        break;
    }
    
    $next = nextMeaningfulTokenIndex($tokens, $index + 1);
    if (null === $next) {
        break;
    }
    $index = $next;
}

echo "\nFinal concatenated pattern: '" . implode('', $parts) . "'\n";
echo "First line: " . ($firstLine ?? 'null') . "\n";

unlink($tempFile);
rmdir($tempDir);