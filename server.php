<?php

$host = '0.0.0.0';
$port = 5000;
$docRoot = __DIR__ . '/public';

echo "Starting PHP development server on {$host}:{$port}\n";
echo "Document root: {$docRoot}\n";
echo "Server is ready!\n\n";

chdir($docRoot);

exec("php -S {$host}:{$port} -t {$docRoot}");
