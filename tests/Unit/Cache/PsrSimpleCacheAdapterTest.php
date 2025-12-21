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
use Psr\SimpleCache\CacheInterface;
use RegexParser\Cache\PsrSimpleCacheAdapter;

final class PsrSimpleCacheAdapterTest extends TestCase
{
    public function test_stores_and_loads_ast_payload(): void
    {
        $cache = new InMemorySimpleCache();
        $adapter = new PsrSimpleCacheAdapter($cache, prefix: 'simple_');

        $key = $adapter->generateKey('/foo/');
        $adapter->write($key, 'payload');

        $this->assertSame('payload', $adapter->load($key));
    }

    public function test_clear_by_regex_removes_entry(): void
    {
        $cache = new InMemorySimpleCache();
        $adapter = new PsrSimpleCacheAdapter($cache, prefix: 'simple_');

        $key = $adapter->generateKey('/bar/');
        $adapter->write($key, 'cached');
        $adapter->clear('/bar/');

        $this->assertNull($adapter->load($key));
    }
}

final class InMemorySimpleCache implements CacheInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        $this->values[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    /**
     * @param iterable<string> $keys
     *
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->values);
    }
}
