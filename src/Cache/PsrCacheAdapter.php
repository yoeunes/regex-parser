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

use Psr\Cache\CacheItemPoolInterface;

/**
 * PSR-6 bridge for AST caching.
 *
 * Uses the configured pool to store serialized AST payloads.
 */
final readonly class PsrCacheAdapter implements RemovableCacheInterface
{
    public function __construct(
        private CacheItemPoolInterface $pool,
        private string $prefix = 'regex_',
        private ?\Closure $keyFactory = null
    ) {}

    public function generateKey(string $regex): string
    {
        if (null !== $this->keyFactory) {
            $custom = ($this->keyFactory)($regex);

            return $this->prefix.(\is_string($custom) ? $custom : hash('sha256', serialize($custom)));
        }

        return $this->prefix.hash('sha256', $regex);
    }

    public function write(string $key, string $content): void
    {
        $item = $this->pool->getItem($key);
        $item->set($content);
        $this->pool->save($item);
    }

    public function load(string $key): mixed
    {
        $item = $this->pool->getItem($key);

        return $item->isHit() ? $item->get() : null;
    }

    public function getTimestamp(string $key): int
    {
        // PSR-6 does not expose timestamps; return 0 (unknown).
        return 0;
    }

    public function clear(?string $regex = null): void
    {
        if (null !== $regex) {
            $this->pool->deleteItem($this->generateKey($regex));

            return;
        }

        $this->pool->clear();
    }
}
