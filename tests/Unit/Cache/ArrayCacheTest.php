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
use RegexParser\Cache\ArrayCache;

final class ArrayCacheTest extends TestCase
{
    public function test_generate_key_returns_hash(): void
    {
        $cache = new ArrayCache();
        $key = $cache->generateKey('/foo/');

        $this->assertSame(hash('sha256', '/foo/'), $key);
    }

    public function test_write_and_load_cache_entry(): void
    {
        $cache = new ArrayCache();
        $key = $cache->generateKey('/test/');
        $content = 'cached-content';

        $cache->write($key, $content);
        $this->assertSame($content, $cache->load($key));
        $this->assertGreaterThan(0, $cache->getTimestamp($key));
    }

    public function test_load_increments_hits_on_cache_hit(): void
    {
        $cache = new ArrayCache();
        $key = $cache->generateKey('/hit/');

        $cache->write($key, 'value');
        $cache->load($key);
        $cache->load($key);

        $stats = $cache->getStats();
        $this->assertSame(2, $stats['hits']);
        $this->assertSame(0, $stats['misses']);
    }

    public function test_load_increments_misses_on_cache_miss(): void
    {
        $cache = new ArrayCache();
        $key = $cache->generateKey('/miss/');

        $cache->load($key);
        $cache->load($key);

        $stats = $cache->getStats();
        $this->assertSame(0, $stats['hits']);
        $this->assertSame(2, $stats['misses']);
    }

    public function test_load_returns_null_for_nonexistent_key(): void
    {
        $cache = new ArrayCache();
        $key = $cache->generateKey('/nonexistent/');

        $this->assertNull($cache->load($key));
    }

    public function test_get_timestamp_returns_zero_for_nonexistent_key(): void
    {
        $cache = new ArrayCache();
        $key = $cache->generateKey('/nonexistent/');

        $this->assertSame(0, $cache->getTimestamp($key));
    }

    public function test_clear_removes_all_entries(): void
    {
        $cache = new ArrayCache();
        $key1 = $cache->generateKey('/abc/');
        $key2 = $cache->generateKey('/def/');

        $cache->write($key1, 'value1');
        $cache->write($key2, 'value2');
        $cache->clear();

        $this->assertNull($cache->load($key1));
        $this->assertNull($cache->load($key2));
        $this->assertSame(0, $cache->getTimestamp($key1));
        $this->assertSame(0, $cache->getTimestamp($key2));
    }

    public function test_clear_with_specific_regex_removes_only_that_entry(): void
    {
        $cache = new ArrayCache();
        $key1 = $cache->generateKey('/abc/');
        $key2 = $cache->generateKey('/def/');

        $cache->write($key1, 'value1');
        $cache->write($key2, 'value2');
        $cache->clear('/abc/');

        $this->assertNull($cache->load($key1));
        $this->assertSame(0, $cache->getTimestamp($key1));
        $this->assertSame('value2', $cache->load($key2));
        $this->assertGreaterThan(0, $cache->getTimestamp($key2));
    }

    public function test_clear_nonexistent_regex_does_not_affect_other_entries(): void
    {
        $cache = new ArrayCache();
        $key1 = $cache->generateKey('/existing/');

        $cache->write($key1, 'value');

        $cache->clear('/nonexistent/');

        $this->assertSame('value', $cache->load($key1));
        $this->assertGreaterThan(0, $cache->getTimestamp($key1));
    }

    public function test_get_stats_returns_current_stats(): void
    {
        $cache = new ArrayCache();
        $key = $cache->generateKey('/stats/');

        $stats = $cache->getStats();
        $this->assertSame(0, $stats['hits']);
        $this->assertSame(0, $stats['misses']);

        $cache->write($key, 'value');
        $cache->load($key);

        $stats = $cache->getStats();
        $this->assertSame(1, $stats['hits']);
        $this->assertSame(0, $stats['misses']);

        $cache->load($cache->generateKey('/nonexistent/'));

        $stats = $cache->getStats();
        $this->assertSame(1, $stats['hits']);
        $this->assertSame(1, $stats['misses']);
    }

    public function test_write_updates_timestamp(): void
    {
        $cache = new ArrayCache();
        $key = $cache->generateKey('/timestamp/');

        $cache->write($key, 'value1');
        $timestamp1 = $cache->getTimestamp($key);

        sleep(1);
        $cache->write($key, 'value2');
        $timestamp2 = $cache->getTimestamp($key);

        $this->assertGreaterThan($timestamp1, $timestamp2);
    }

    public function test_clear_resets_stats(): void
    {
        $cache = new ArrayCache();
        $key = $cache->generateKey('/reset/');

        $cache->write($key, 'value');
        $cache->load($key);
        $cache->load($cache->generateKey('/nonexistent/'));

        $stats = $cache->getStats();
        $this->assertSame(1, $stats['hits']);
        $this->assertSame(1, $stats['misses']);

        $cache->clear();

        $stats = $cache->getStats();
        $this->assertSame(1, $stats['hits']);
        $this->assertSame(1, $stats['misses']);
    }

    public function test_multiple_entries(): void
    {
        $cache = new ArrayCache();
        $entries = [
            '/pattern1/' => 'value1',
            '/pattern2/' => 'value2',
            '/pattern3/' => 'value3',
        ];

        foreach ($entries as $pattern => $value) {
            $key = $cache->generateKey($pattern);
            $cache->write($key, $value);
        }

        foreach ($entries as $pattern => $value) {
            $key = $cache->generateKey($pattern);
            $this->assertSame($value, $cache->load($key));
        }

        $stats = $cache->getStats();
        $this->assertSame(3, $stats['hits']);
        $this->assertSame(0, $stats['misses']);
    }
}
