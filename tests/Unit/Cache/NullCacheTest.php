<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use RegexParser\Cache\NullCache;

final class NullCacheTest extends TestCase
{
    public function testNullCacheNoOps(): void
    {
        $cache = new NullCache();

        $key = $cache->generateKey('/foo/');
        self::assertSame(hash('sha256', '/foo/'), $key);

        $cache->write($key, 'content'); // should not throw
        self::assertNull($cache->load($key));
        self::assertSame(0, $cache->getTimestamp($key));

        $cache->clear();
        $cache->clear('/bar/'); // should not throw
    }
}
