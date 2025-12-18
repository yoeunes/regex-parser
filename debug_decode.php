<?php

function decodeConstantString(string $literal): ?string
{
    $len = strlen($literal);
    if ($len < 2) {
        return null;
    }

    $quote = $literal[0];
    if (("'" !== $quote && '"' !== $quote) || $quote !== $literal[$len - 1]) {
        echo "Quote mismatch: $quote != {$literal[$len - 1]}\n";
        return null;
    }

    $content = substr($literal, 1, -1);
    echo "Content: '$content'\n";

    if ("'" === $quote) {
        return str_replace(['\\\\', "\\'"], ['\\', "'"], $content);
    }

    return stripcslashes($content);
}

$tests = ['"/"', '"test"', '"/i"'];
foreach ($tests as $test) {
    echo "Testing: $test\n";
    $result = decodeConstantString($test);
    echo "Result: " . ($result ?? 'null') . "\n\n";
}