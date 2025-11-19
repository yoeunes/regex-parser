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
 * Represents a set of literal strings extracted from a regex pattern.
 * This object is immutable.
 */
readonly class LiteralSet
{
    /**
     * @param array<string> $prefixes Possible starting strings
     * @param array<string> $suffixes Possible ending strings
     * @param bool          $complete Whether these literals cover the entire match
     */
    public function __construct(
        public array $prefixes = [],
        public array $suffixes = [],
        public bool $complete = false,
    ) {
    }

    public static function empty(): self
    {
        return new self([], [], false);
    }

    public static function fromString(string $literal): self
    {
        return new self([$literal], [$literal], true);
    }

    /**
     * Concatenates this set with another set (Sequence operation).
     * Logic: A . B
     */
    public function concat(self $other): self
    {
        // If either is totally empty (and not just an empty string literal), result is empty/incomplete
        if ($this->isVoid() || $other->isVoid()) {
            return self::empty();
        }

        // 1. Calculate new Prefixes
        // Start with current prefixes.
        // If current is "complete" (fixed width, no ambiguity), we append other's prefixes.
        $newPrefixes = $this->prefixes;
        if ($this->complete) {
            $newPrefixes = $this->crossProduct($this->prefixes, $other->prefixes);
        }

        // 2. Calculate new Suffixes
        // Start with other's suffixes.
        // If other is "complete", we prepend current suffixes.
        $newSuffixes = $other->suffixes;
        if ($other->complete) {
            $newSuffixes = $this->crossProduct($this->suffixes, $other->suffixes);
        }

        // 3. Completeness
        // A sequence is complete only if both parts are complete.
        $newComplete = $this->complete && $other->complete;

        return new self($this->deduplicate($newPrefixes), $this->deduplicate($newSuffixes), $newComplete);
    }

    /**
     * Unites this set with another set (Alternation operation).
     * Logic: A | B
     */
    public function unite(self $other): self
    {
        // Union of prefixes and suffixes
        $newPrefixes = array_merge($this->prefixes, $other->prefixes);
        $newSuffixes = array_merge($this->suffixes, $other->suffixes);

        // An alternation is complete only if ALL branches are complete
        $newComplete = $this->complete && $other->complete;

        return new self($this->deduplicate($newPrefixes), $this->deduplicate($newSuffixes), $newComplete);
    }

    public function getLongestPrefix(): ?string
    {
        return $this->getLongestString($this->prefixes);
    }

    public function getLongestSuffix(): ?string
    {
        return $this->getLongestString($this->suffixes);
    }

    public function isVoid(): bool
    {
        return empty($this->prefixes) && empty($this->suffixes);
    }

    /**
     * @param array<string> $left
     * @param array<string> $right
     * @return array<string>
     */
    private function crossProduct(array $left, array $right): array
    {
        $result = [];
        foreach ($left as $l) {
            foreach ($right as $r) {
                $result[] = $l . $r;
            }
        }
        return $result;
    }

    /**
     * @param array<string> $items
     * @return array<string>
     */
    private function deduplicate(array $items): array
    {
        return array_values(array_unique($items));
    }

    /**
     * @param array<string> $candidates
     */
    private function getLongestString(array $candidates): ?string
    {
        if (empty($candidates)) {
            return null;
        }

        $longest = '';
        foreach ($candidates as $s) {
            if (strlen($s) > strlen($longest)) {
                $longest = $s;
            }
        }

        return '' === $longest && !in_array('', $candidates, true) ? null : $longest;
    }
}
