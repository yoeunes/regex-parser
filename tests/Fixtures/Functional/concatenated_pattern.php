<?php
// Concatenated pattern - should NOT be detected (cannot be validated statically)
$end = 'suffix/';
$regex = "/start" . $end;
preg_match("/prefix_" . $var . "/", (string) $subject);
