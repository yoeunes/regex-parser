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

namespace RegexParser\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use RegexParser\Cache\FilesystemCache;

final class FilesystemCacheTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/regex-parser-cache-'.uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->cacheDir);
    }

    public function test_write_and_load_cache_file(): void
    {
        $cache = new FilesystemCache($this->cacheDir);
        $key = $cache->generateKey('/abc/');

        $cache->write($key, "<?php return 'cached-value';\n");

        $this->assertFileExists($key);
        $this->assertSame('cached-value', $cache->load($key));
        $this->assertGreaterThan(0, $cache->getTimestamp($key));
    }

    public function test_clear_removes_cached_entries(): void
    {
        $cache = new FilesystemCache($this->cacheDir);
        $key = $cache->generateKey('/def/');

        $cache->write($key, "<?php return 42;\n");
        $cache->clear();

        $this->assertFileDoesNotExist($key);
    }

    public function test_load_returns_null_for_nonexistent_file(): void
    {
        $cache = new FilesystemCache($this->cacheDir);
        $key = $cache->generateKey('/nonexistent/');

        $this->assertNull($cache->load($key));
    }

    public function test_get_timestamp_returns_zero_for_nonexistent_file(): void
    {
        $cache = new FilesystemCache($this->cacheDir);
        $key = $cache->generateKey('/nonexistent/');

        $this->assertSame(0, $cache->getTimestamp($key));
    }

    public function test_write_throws_on_unwritable_directory(): void
    {
        $cache = new FilesystemCache($this->cacheDir);
        $key = $cache->generateKey('/test/');
        $fileDir = \dirname($key);
        mkdir($fileDir, 0o755, true);
        chmod($fileDir, 0o444); // Make the file's directory read-only

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to move cache file');

        $cache->write($key, 'content');
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
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

        @rmdir($directory);
    }
}
