<?php

// Simulate the exact token flow and fix logic
$tokens = [
    3 => [T_CONSTANT_ENCAPSED_STRING, '"/"', 1], // startIndex = 3
    4 => ' ', // whitespace
    5 => '.', // concat
    6 => ' ', // whitespace  
    7 => [T_CONSTANT_ENCAPSED_STRING, '"test"', 1], // should be processed
    8 => ' ', // whitespace
    9 => '.', // concat
    10 => ' ', // whitespace
    11 => [T_CONSTANT_ENCAPSED_STRING, '"/i"', 1], // should be processed
    12 => ',',
];

$index = 3; // startIndex
$count = count($tokens);

function nextMeaningfulTokenIndex(array $tokens, int $start): ?int
{
    for ($i = $start; $i < count($tokens); $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_WHITESPACE) {
            return $i;
        }
    }
    return null;
}

$parts = [];
$firstLine = null;

echo "Starting at index $index\n";

while ($index < $count) {
    $token = $tokens[$index];
    echo "Processing index $index: " . (is_array($token) ? $token[1] : $token) . "\n";

    if (is_array($token)) {
        if (T_CONSTANT_ENCAPSED_STRING === $token[0]) {
            echo "  -> Found string\n";
            $parts[] = str_replace(['\\\\', "\\'"], ['\\', "'"], substr($token[1], 1, -1));
            if (null === $firstLine) {
                $firstLine = $token[2];
            }
        } else {
            echo "  -> Not string, breaking\n";
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

echo "Parts: " . json_encode($parts) . "\n";
echo "Pattern: " . implode('', $parts) . "\n";
echo "First line: " . ($firstLine ?? 'null') . "\n";