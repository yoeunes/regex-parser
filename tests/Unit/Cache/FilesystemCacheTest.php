<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use RegexParser\Cache\FilesystemCache;

final class FilesystemCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/regex-parser-cache-'.uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDir()) {
                    @rmdir($fileInfo->getPathname());
                } else {
                    @unlink($fileInfo->getPathname());
                }
            }

            @rmdir($this->directory);
        }
    }

    public function testWriteLoadAndClear(): void
    {
        $cache = new FilesystemCache($this->directory, '.php');

        $key = $cache->generateKey('/foo/');
        self::assertStringContainsString($this->directory, $key);
        self::assertStringEndsWith('.php', $key);

        $payload = "<?php return 'cached';";
        $cache->write($key, $payload);

        self::assertIsInt($cache->getTimestamp($key));
        self::assertSame('cached', $cache->load($key));

        $cache->clear('/foo/');
        self::assertFalse(is_file($key));

        // Clear without regex should not fail even if directory already empty
        $cache->clear();
        $cache->clear();
    }
}
