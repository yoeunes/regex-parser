<?php

declare(strict_types=1);

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$host = '0.0.0.0';
$port = 5000;
$docRoot = __DIR__.'/public';

echo "Starting PHP development server on http://{$host}:{$port}\n";
echo "Document root: {$docRoot}\n";
echo "Server is ready!\n\n";
echo "Press Ctrl+C to stop the server.\n";

chdir($docRoot);

exec("php -S {$host}:{$port} -t {$docRoot}");
