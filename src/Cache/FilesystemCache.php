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

namespace RegexParser\Cache;

final readonly class FilesystemCache implements RemovableCacheInterface
{
    private string $directory;

    public function __construct(string $directory, private string $extension = '.php')
    {
        $this->directory = rtrim($directory, '\\/');
    }

    #[\Override]
    public function generateKey(string $regex): string
    {
        $hash = hash('sha256', $regex);

        return \sprintf(
            '%s%s%s%s%s%s',
            $this->directory,
            \DIRECTORY_SEPARATOR,
            $hash[0],
            $hash[1],
            \DIRECTORY_SEPARATOR,
            substr($hash, 2).$this->extension,
        );
    }

    #[\Override]
    public function write(string $key, string $content): void
    {
        $directory = \dirname($key);
        $this->createDirectory($directory);

        $tmpFile = @tempnam($directory, 'regex');
        if (false === $tmpFile) {
            throw new \RuntimeException(\sprintf('Unable to create temporary file in "%s".', $directory));
        }

        if (false === @file_put_contents($tmpFile, $content)) {
            @unlink($tmpFile);

            throw new \RuntimeException(\sprintf('Failed to write cache file "%s".', $key));
        }

        if (!@rename($tmpFile, $key)) {
            if (!@copy($tmpFile, $key)) {
                @unlink($tmpFile);

                throw new \RuntimeException(\sprintf('Failed to move cache file "%s".', $tmpFile));
            }

            @unlink($tmpFile);
        }

        @chmod($key, 0o666 & ~umask());

        if (\function_exists('opcache_invalidate')) {
            @opcache_invalidate($key, true);
        }
    }

    #[\Override]
    public function load(string $key): mixed
    {
        if (!is_file($key)) {
            return null;
        }

        try {
            /* @psalm-suppress UnresolvableInclude */
            return include $key;
        } catch (\Throwable) {
            return null;
        }
    }

    #[\Override]
    public function getTimestamp(string $key): int
    {
        return is_file($key) ? (int) filemtime($key) : 0;
    }

    #[\Override]
    public function clear(?string $regex = null): void
    {
        if (null !== $regex) {
            $file = $this->generateKey($regex);
            if (is_file($file)) {
                @unlink($file);
            }

            return;
        }

        if (!is_dir($this->directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }

            $path = $fileInfo->getRealPath();
            if (!\is_string($path)) {
                continue;
            }

            if ($fileInfo->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($this->directory);
    }

    private function createDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        $umask = umask(0o002);

        try {
            if (!@mkdir($directory, 0o777, true) && !is_dir($directory)) {
                throw new \RuntimeException(\sprintf('Unable to create the cache directory "%s".', $directory));
            }
        } finally {
            umask($umask);
        }
    }
}
