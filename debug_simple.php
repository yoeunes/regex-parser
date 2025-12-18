<?php

$content = '<?php preg_match("/test/", $subject);';
echo "Content: $content\n";

$tokens = token_get_all($content);

echo "\nTokens:\n";
foreach ($tokens as $i => $token) {
    if (is_array($token)) {
        $text = $token[1];
        echo $i . ": " . token_name($token[0]) . " => '" . $text . "' (line " . $token[2] . ")\n";
    } else {
        echo $i . ": '" . $token . "'\n";
    }
}

// Check token[3] specifically
echo "\nToken 3: ";
var_dump($tokens[3]);