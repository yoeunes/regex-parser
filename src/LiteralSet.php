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
 *
 * This immutable data structure holds prefixes, suffixes, and completeness info
 * for literal extraction optimizations.
 */
final readonly class LiteralSet
{
    /**
     * @param array<string> $prefixes
     * @param array<string> $suffixes
     * @param bool $complete
     */
    public function __construct(
        public array $prefixes = [],
        public array $suffixes = [],
        public bool $complete = false,
    ) {}

    /** @return self */
    public static function empty(): self
    {
        return new self([], [], false);
    }

    /** @param string $literal
     *  @return self */
    public static function fromString(string $literal): self
    {
        return new self([$literal], [$literal], true);
    }

    /** @param self $other
     *  @return self */
    public function concat(self $other): self
    {
        if ($this->isVoid() && empty($this->prefixes)) {
            return self::empty();
        }

        $newPrefixes = $this->prefixes;

        if ($this->complete) {
            if (!empty($other->prefixes)) {
                $newPrefixes = $this->crossProduct($this->prefixes, $other->prefixes);
            }
        }

        $newSuffixes = $other->suffixes;

        if ($other->complete) {
            if (!empty($this->suffixes)) {
                $newSuffixes = $this->crossProduct($this->suffixes, $other->suffixes);
            }
        }

        $newComplete = $this->complete && $other->complete;

        if (empty($newPrefixes) && empty($newSuffixes)) {
            return self::empty();
        }

        return new self($this->deduplicate($newPrefixes), $this->deduplicate($newSuffixes), $newComplete);
    }

    /** @param self $other
     *  @return self */
    public function unite(self $other): self
    {
        // Union of prefixes and suffixes
        $newPrefixes = array_merge($this->prefixes, $other->prefixes);
        $newSuffixes = array_merge($this->suffixes, $other->suffixes);

        // An alternation is complete only if ALL branches are complete
        $newComplete = $this->complete && $other->complete;

        return new self($this->deduplicate($newPrefixes), $this->deduplicate($newSuffixes), $newComplete);
    }

    /** @return string|null */
    public function getLongestPrefix(): ?string
    {
        return $this->getLongestString($this->prefixes);
    }

    /** @return string|null */
    public function getLongestSuffix(): ?string
    {
        return $this->getLongestString($this->suffixes);
    }

    /** @return bool */
    public function isVoid(): bool
    {
        return empty($this->prefixes) && empty($this->suffixes);
    }

    private function crossProduct(array $left, array $right): array
    {
        $result = [];
        foreach ($left as $l) {
            foreach ($right as $r) {
                $result[] = $l.$r;
            }
        }

        return $result;
    }

    private function deduplicate(array $items): array
    {
        return array_values(array_unique($items));
    }

    private function getLongestString(array $candidates): ?string
    {
        if (empty($candidates)) {
            return null;
        }

        $longest = '';
        foreach ($candidates as $s) {
            if (\strlen($s) > \strlen($longest)) {
                $longest = $s;
            }
        }

        return '' === $longest && !\in_array('', $candidates, true) ? null : $longest;
    }
}
