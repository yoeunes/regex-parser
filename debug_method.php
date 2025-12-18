<?php

$tempDir = sys_get_temp_dir() . '/test_' . uniqid();
mkdir($tempDir);
$tempFile = $tempDir . '/test.php';
file_put_contents($tempFile, '<?php preg_match("/" . "test" . "/i", $subject);');

$tokens = token_get_all(file_get_contents($tempFile));

// Copy current extractConcatenatedPattern method
function extractConcatenatedPattern(array $tokens, int $startIndex): ?array
{
    $parts = [];
    $index = $startIndex;
    $count = count($tokens);
    $firstLine = null;
    $foundConcatenation = false;

    echo "Starting extraction at index $startIndex\n";

    while ($index < $count) {
        $token = $tokens[$index];
        echo "Index $index: " . (is_array($token) ? token_name($token[0]) : $token) . "\n";

        if (is_array($token)) {
            if (T_CONSTANT_ENCAPSED_STRING === $token[0]) {
                echo "  Found string: " . var_export($token[1], true) . "\n";
                $content = substr($token[1], 1, -1);
                $parts[] = $content;
                if (null === $firstLine) {
                    $firstLine = $token[2];
                }
            } elseif (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                echo "  Skipping ignorable\n";
            } else {
                echo "  Not string or ignorable, breaking\n";
                break;
            }
        } elseif ('.' === $token) {
            echo "  Found concatenation\n";
            $foundConcatenation = true;
        } else {
            echo "  Other token, breaking\n";
            break;
        }

        $index++;
    }

    echo "Found " . count($parts) . " parts: " . json_encode($parts) . "\n";
    echo "Found concatenation: " . ($foundConcatenation ? 'yes' : 'no') . "\n";
    
    if (!$foundConcatenation || count($parts) < 2 || null === $firstLine) {
        echo "Returning null\n";
        return null;
    }

    return ['pattern' => implode('', $parts), 'line' => $firstLine];
}

$result = extractConcatenatedPattern($tokens, 3);
echo "Final result: " . ($result ? $result['pattern'] : 'null') . "\n";

unlink($tempFile);
rmdir($tempDir);