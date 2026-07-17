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

use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 bridge for AST caching.
 *
 * This adapter lets you plug a PSR-16 cache implementation into the
 * RegexParser cache system. It receives the compiled cache payload string
 * from the Regex service and stores the decoded RegexNode instance so
 * later reads can return the AST directly.
 */
final readonly class PsrSimpleCacheAdapter implements RemovableCacheInterface
{
    public function __construct(
        private CacheInterface $cache,
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
        $value = CachePayloadDecoder::decode($content);

        $this->cache->set($key, $value ?? $content);
    }

    public function load(string $key): mixed
    {
        $value = $this->cache->get($key);

        return $value ?? null;
    }

    public function getTimestamp(string $key): int
    {
        return 0;
    }

    public function clear(?string $regex = null): void
    {
        if (null !== $regex) {
            $this->cache->delete($this->generateKey($regex));

            return;
        }

        $this->cache->clear();
    }

    public function getStats(): array
    {
        return ['hits' => 0, 'misses' => 0];
    }
}
