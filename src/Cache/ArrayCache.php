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

/**
 * In-memory cache using an array for storage.
 */
final class ArrayCache implements RemovableCacheInterface
{
    private int $hits = 0;

    private int $misses = 0;

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @var array<string, int>
     */
    private array $timestamps = [];

    #[\Override]
    public function generateKey(string $regex): string
    {
        return hash('sha256', $regex);
    }

    #[\Override]
    public function write(string $key, string $content): void
    {
        $this->data[$key] = $content;
        $this->timestamps[$key] = time();
    }

    #[\Override]
    public function load(string $key): mixed
    {
        if (array_key_exists($key, $this->data)) {
            $this->hits++;

            return $this->data[$key];
        }

        $this->misses++;

        return null;
    }

    #[\Override]
    public function getTimestamp(string $key): int
    {
        return $this->timestamps[$key] ?? 0;
    }

    #[\Override]
    public function clear(?string $regex = null): void
    {
        if (null !== $regex) {
            $key = $this->generateKey($regex);
            unset($this->data[$key], $this->timestamps[$key]);

            return;
        }

        $this->data = [];
        $this->timestamps = [];
    }

    /**
     * @return array{hits: int, misses: int}
     */
    public function getStats(): array
    {
        return ['hits' => $this->hits, 'misses' => $this->misses];
    }
}
