<?php
// Mixed content - only the preg_match should be detected

// SQL - should NOT be detected
$sql = "SELECT * FROM users WHERE id = 1";

// HTML - should NOT be detected
$html = "<div class='container'></div>";

// CSS color - should NOT be detected
$color = "#FF5733";

// Simple text - should NOT be detected
$text = "This is just a regular string";

// Valid regex - SHOULD be detected
preg_match('/^valid-regex$/', (string) $input);

// Concatenated - should NOT be detected
preg_match("/dynamic_" . $var . "/", (string) $subject);
