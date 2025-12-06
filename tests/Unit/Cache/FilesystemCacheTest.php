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
                if (!$fileInfo instanceof \SplFileInfo) {
                    continue;
                }

                $path = $fileInfo->getPathname();
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
    }

    public function test_write_load_and_clear(): void
    {
        $cache = new FilesystemCache($this->directory, '.php');

        $key = $cache->generateKey('/foo/');
        $this->assertStringContainsString($this->directory, $key);
        $this->assertStringEndsWith('.php', $key);

        $payload = "<?php return 'cached';";
        $cache->write($key, $payload);

        $this->assertIsInt($cache->getTimestamp($key));
        $this->assertSame('cached', $cache->load($key));

        $cache->clear('/foo/');
        $this->assertFalse(is_file($key));

        // Clear without regex should not fail even if directory already empty
        $cache->clear();
        $cache->clear();
    }
}
