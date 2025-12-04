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
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use RegexParser\Cache\PsrCacheAdapter;

/**
 * @psalm-suppress MissingDependency
 */
final class PsrCacheAdapterTest extends TestCase
{
    public function test_stores_and_loads_ast_payload(): void
    {
        $pool = new InMemoryPool();
        $adapter = new PsrCacheAdapter($pool, prefix: 'ast_');

        $key = $adapter->generateKey('/foo/');
        $adapter->write($key, 'payload');

        $this->assertSame('payload', $adapter->load($key));
    }

    public function test_clear_by_regex_removes_entry(): void
    {
        $pool = new InMemoryPool();
        $adapter = new PsrCacheAdapter($pool, prefix: 'ast_');

        $key = $adapter->generateKey('/bar/');
        $adapter->write($key, 'cached');

        $adapter->clear('/bar/');

        $this->assertNull($adapter->load($key));
    }
}

/**
 * @psalm-suppress MissingDependency
 */
final class InMemoryPool implements CacheItemPoolInterface
{
    /**
     * @var array<string, \Psr\Cache\CacheItemInterface>
     */
    private array $items = [];

    public function getItem(string $key): CacheItemInterface
    {
        if (!isset($this->items[$key])) {
            $this->items[$key] = new InMemoryItem($key);
        }

        return $this->items[$key];
    }

    /**
     * @param array<string> $keys
     *
     * @return iterable<string, \Psr\Cache\CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->getItem($key);
        }
    }

    public function hasItem(string $key): bool
    {
        return isset($this->items[$key]) && $this->items[$key]->isHit();
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    /**
     * @param array<string> $keys
     */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->items[$key]);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->items[$item->getKey()] = $item;

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}

/**
 * @psalm-suppress MissingDependency
 */
final class InMemoryItem implements CacheItemInterface
{
    public function __construct(
        private readonly string $key,
        private mixed $value = null,
        private bool $hit = false
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        return $this;
    }
}
