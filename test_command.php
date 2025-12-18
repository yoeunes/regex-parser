<?php

// Quick test of the RegexLintCommand functionality
require_once __DIR__ . '/vendor/autoload.php';

use RegexParser\Bridge\Symfony\Command\RegexLintCommand;
use RegexParser\Regex;

$command = new RegexLintCommand(new Regex(), 'phpstorm://open?file=%%file%%&line=%%line%%');
echo "Command created successfully with editor URL\n";
echo "Command name: " . $command->getName() . "\n";