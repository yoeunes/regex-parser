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

namespace RegexParser\Tests\Unit\Cache {
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

        public function test_clear_specific_regex(): void
        {
            $cache = new FilesystemCache($this->cacheDir);
            $key1 = $cache->generateKey('/abc/');
            $key2 = $cache->generateKey('/def/');

            $cache->write($key1, "<?php return 'value1';\n");
            $cache->write($key2, "<?php return 'value2';\n");

            // Clear only the first regex
            $cache->clear('/abc/');

            $this->assertFileDoesNotExist($key1);
            $this->assertFileExists($key2);
            $this->assertSame('value2', $cache->load($key2));
        }

        public function test_clear_nonexistent_regex(): void
        {
            $cache = new FilesystemCache($this->cacheDir);
            $key = $cache->generateKey('/existing/');

            $cache->write($key, "<?php return 'value';\n");

            // Clear a non-existent regex - should not affect existing files
            $cache->clear('/nonexistent/');

            $this->assertFileExists($key);
            $this->assertSame('value', $cache->load($key));
        }

        public function test_clear_returns_when_directory_missing(): void
        {
            $cache = new FilesystemCache($this->cacheDir);

            $cache->clear();

            $this->assertDirectoryDoesNotExist($this->cacheDir);
        }

        public function test_clear_skips_broken_symlink_paths(): void
        {
            $cacheDir = $this->cacheDir.'/sub';
            @mkdir($cacheDir, 0o777, true);
            $broken = $cacheDir.'/broken';
            @symlink($cacheDir.'/missing', $broken);

            $cache = new FilesystemCache($cacheDir);
            $cache->clear();

            $this->assertFileDoesNotExist($broken);
        }

        public function test_create_directory_throws_when_path_is_file(): void
        {
            $filePath = $this->cacheDir.'-file';
            copy(__DIR__.'/../../Fixtures/Cache/x.txt', $filePath);

            $cache = new FilesystemCache($filePath);

            $this->expectException(\RuntimeException::class);
            $cache->write($cache->generateKey('/file/'), 'content');
        }

        public function test_generate_key_with_custom_extension(): void
        {
            $cache = new FilesystemCache($this->cacheDir, '.cache');
            $key = $cache->generateKey('/test/');

            $this->assertStringEndsWith('.cache', $key);
            $this->assertStringContainsString($this->cacheDir, $key);
        }

        public function test_load_handles_corrupted_file(): void
        {
            $cache = new FilesystemCache($this->cacheDir);
            $key = $cache->generateKey('/test/');

            // Write invalid PHP that will cause an exception when included
            $cache->write($key, "<?php throw new Exception('corrupted');\n");

            $result = $cache->load($key);

            $this->assertNull($result);
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
}
