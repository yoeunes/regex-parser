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

use RegexParser\Cache\FilesystemCache;

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/Support/LintFunctionOverrides.php';
require __DIR__.'/Support/SelfUpdateFunctionOverrides.php';
require __DIR__.'/Support/SymfonyExtractorFunctionOverrides.php';

// The default cache lives in the system temp directory and survives the
// process. A previous run — another test session, or a mutation-testing
// mutant that parsed with altered code — may have stored ASTs this code
// would never build, and a test reading one back would see behavior the
// current parser does not have. Every run starts from an empty cache.
$defaultCacheDirectory = FilesystemCache::defaultDirectory();
if (is_dir($defaultCacheDirectory)) {
    // A read-only directory left behind by a failed cache test would keep
    // its children from being deleted; ownership still allows restoring
    // access, so do that first.
    $directories = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($defaultCacheDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($directories as $directory) {
        if ($directory instanceof SplFileInfo && $directory->isDir()) {
            @chmod($directory->getPathname(), 0o755);
        }
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($defaultCacheDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }

        @($file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()));
    }

    @rmdir($defaultCacheDirectory);
}
