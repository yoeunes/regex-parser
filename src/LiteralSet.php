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

namespace RegexParser;

/**
 * High-performance immutable set of literal strings extracted from regex patterns.
 *
 * This optimized data structure provides efficient literal extraction with intelligent
 * size limits, lazy evaluation, and memory-efficient operations for regex optimization.
 */
final readonly class LiteralSet
{
    private const MAX_SET_SIZE = 100; // Prevent memory explosion
    private const MAX_STRING_LENGTH = 1000; // Prevent extremely long literals

    /**
     * @var array<string>
     */
    public array $prefixes;

    /**
     * @var array<string>
     */
    public array $suffixes;

    /**
     * @param array<string> $prefixes
     * @param array<string> $suffixes
     */
    public function __construct(
        array $prefixes = [],
        array $suffixes = [],
        public bool $complete = false,
    ) {
        // Enforce size limits to prevent performance degradation
        $this->prefixes = \count($prefixes) > self::MAX_SET_SIZE
            ? \array_slice($prefixes, 0, self::MAX_SET_SIZE)
            : $prefixes;

        $this->suffixes = \count($suffixes) > self::MAX_SET_SIZE
            ? \array_slice($suffixes, 0, self::MAX_SET_SIZE)
            : $suffixes;
    }

    public static function empty(): self
    {
        return new self([], [], false);
    }

    public static function fromString(string $literal): self
    {
        // Limit string length to prevent memory issues
        if (\strlen($literal) > self::MAX_STRING_LENGTH) {
            return self::empty();
        }

        return new self([$literal], [$literal], true);
    }

    public function concat(self $other): self
    {
        $newPrefixes = $this->prefixes;
        $newSuffixes = $other->suffixes;
        $newComplete = $this->complete && $other->complete;

        // Only compute cross products when necessary and possible
        if ($this->complete && !empty($other->prefixes)) {
            $newPrefixes = $this->crossProduct($this->prefixes, $other->prefixes);
        }

        if ($other->complete && !empty($this->suffixes)) {
            $newSuffixes = $this->crossProduct($this->suffixes, $other->suffixes);
        }

        return new self(
            $this->deduplicate($newPrefixes),
            $this->deduplicate($newSuffixes),
            $newComplete,
        );
    }

    public function unite(self $other): self
    {
        // Fast path for identical sets
        if ($this === $other) {
            return $this;
        }

        $newPrefixes = array_merge($this->prefixes, $other->prefixes);
        $newSuffixes = array_merge($this->suffixes, $other->suffixes);
        $newComplete = $this->complete && $other->complete;

        return new self(
            $this->deduplicate($newPrefixes),
            $this->deduplicate($newSuffixes),
            $newComplete,
        );
    }

    public function getLongestPrefix(): ?string
    {
        return $this->computeLongestString($this->prefixes);
    }

    public function getLongestSuffix(): ?string
    {
        return $this->computeLongestString($this->suffixes);
    }

    public function isVoid(): bool
    {
        return empty($this->prefixes) && empty($this->suffixes);
    }

    /**
     * Optimized cross product with size limits and early termination.
     *
     * @param array<string> $left
     * @param array<string> $right
     *
     * @return array<string>
     */
    private function crossProduct(array $left, array $right): array
    {
        $result = [];
        $maxResults = self::MAX_SET_SIZE;

        foreach ($left as $l) {
            foreach ($right as $r) {
                $combined = $l.$r;

                // Skip if result would be too long
                if (\strlen($combined) > self::MAX_STRING_LENGTH) {
                    continue;
                }

                $result[] = $combined;

                // Early termination if we hit the limit
                if (\count($result) >= $maxResults) {
                    return $result;
                }
            }
        }

        return $result;
    }

    /**
     * Memory-efficient deduplication with size limits.
     *
     * @param array<string> $items
     *
     * @return array<string>
     */
    private function deduplicate(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $unique = array_unique($items);

        // Enforce size limit after deduplication
        if (\count($unique) > self::MAX_SET_SIZE) {
            $unique = \array_slice($unique, 0, self::MAX_SET_SIZE, true);
        }

        return array_values($unique);
    }

    /**
     * Optimized longest string computation with early termination.
     *
     * @param array<string> $candidates
     */
    /**
     * @param array<string> $candidates
     */
    private function computeLongestString(array $candidates): ?string
    {
        if (empty($candidates)) {
            return null;
        }

        $longest = '';
        $maxLength = 0;

        foreach ($candidates as $candidate) {
            $length = \strlen($candidate);
            if ($length > $maxLength) {
                $longest = $candidate;
                $maxLength = $length;
            }
        }

        // Handle edge case where longest is empty string but empty isn't in candidates
        return 0 === $maxLength && !\in_array('', $candidates, true) ? null : $longest;
    }
}
