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

$srcDir = __DIR__.'/../src';
$outputFile = __DIR__.'/../public/library-bundle.json';

if (!is_dir($srcDir)) {
    fwrite(\STDERR, \sprintf("Error: Source directory not found at \"%s\".\n", $srcDir));
    exit(1);
}

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS),
);

echo "📦 Packaging library for WASM environment...\n";

/** @var SplFileInfo $file */
foreach ($iterator as $file) {
    if ($file->isFile() && 'php' === $file->getExtension()) {
        // Normalize path separators for consistency across environments
        $relativePath = str_replace(
            '\\',
            '/',
            substr($file->getPathname(), \strlen($srcDir) + 1),
        );

        $files[$relativePath] = file_get_contents($file->getPathname());
        echo \sprintf(" - Added: %s\n", $relativePath);
    }
}

// Inject a dedicated autoloader for the WASM environment.
// This replaces Composer, which is not available in the browser context.
$autoloader = <<<'PHP'
    <?php

    declare(strict_types=1);

    spl_autoload_register(function (string $class): void {
        $prefix = 'RegexParser\\';
        $baseDir = __DIR__.'/src/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir.str_replace('\\', '/', $relativeClass).'.php';

        if (file_exists($file)) {
            require $file;
        }
    });

    // Alias the main Regex class to global namespace for compatibility
    if (!class_exists('Regex')) {
        class_alias('RegexParser\\Regex', 'Regex');
    }
    PHP;

$files['autoload.php'] = $autoloader;

try {
    $json = json_encode($files, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
    file_put_contents($outputFile, $json);
} catch (JsonException $e) {
    fwrite(\STDERR, \sprintf("Error encoding JSON bundle: %s\n", $e->getMessage()));
    exit(1);
}

echo \sprintf("\n✅ Bundle successfully generated at \"%s\" (%d files).\n", $outputFile, \count($files));
