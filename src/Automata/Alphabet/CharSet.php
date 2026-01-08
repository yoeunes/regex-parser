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

namespace RegexParser\Automata\Alphabet;

use RegexParser\Automata\Unicode\CodePointHelper;

/**
 * Immutable character set for automata alphabets.
 */
final readonly class CharSet
{
    public const MIN_CODEPOINT = 0;
    public const MAX_CODEPOINT = 255;
    public const UNICODE_MAX_CODEPOINT = 0x10FFFF;

    /**
     * @param array<array{0:int, 1:int}> $ranges
     */
    private function __construct(
        private array $ranges,
        private int $minCodePoint,
        private int $maxCodePoint,
    ) {}

    public static function empty(?int $maxCodePoint = null, int $minCodePoint = self::MIN_CODEPOINT): self
    {
        return new self([], $minCodePoint, $maxCodePoint ?? self::MAX_CODEPOINT);
    }

    public static function full(?int $maxCodePoint = null, int $minCodePoint = self::MIN_CODEPOINT): self
    {
        $max = $maxCodePoint ?? self::MAX_CODEPOINT;

        return new self([[$minCodePoint, $max]], $minCodePoint, $max);
    }

    public static function fromChar(string $char, ?int $maxCodePoint = null, int $minCodePoint = self::MIN_CODEPOINT): self
    {
        if ('' === $char) {
            return self::empty($maxCodePoint, $minCodePoint);
        }

        $codePoint = CodePointHelper::toCodePoint($char);
        if (null === $codePoint) {
            return self::empty($maxCodePoint, $minCodePoint);
        }

        return self::fromCodePoint($codePoint, $maxCodePoint, $minCodePoint);
    }

    public static function fromCodePoint(int $codePoint, ?int $maxCodePoint = null, int $minCodePoint = self::MIN_CODEPOINT): self
    {
        return self::fromRange($codePoint, $codePoint, $maxCodePoint, $minCodePoint);
    }

    /**
     * @param array<array{0:int, 1:int}> $ranges
     */
    public static function fromRanges(array $ranges, ?int $maxCodePoint = null, int $minCodePoint = self::MIN_CODEPOINT): self
    {
        $max = $maxCodePoint ?? self::MAX_CODEPOINT;

        return new self(self::normalizeRanges($ranges, $minCodePoint, $max), $minCodePoint, $max);
    }

    public static function fromRange(int $start, int $end, ?int $maxCodePoint = null, int $minCodePoint = self::MIN_CODEPOINT): self
    {
        $max = $maxCodePoint ?? self::MAX_CODEPOINT;

        if ($end < $minCodePoint || $start > $max) {
            return self::empty($max, $minCodePoint);
        }

        $clampedStart = \max($minCodePoint, $start);
        $clampedEnd = \min($max, $end);

        if ($clampedStart > $clampedEnd) {
            return self::empty($max, $minCodePoint);
        }

        return new self([[$clampedStart, $clampedEnd]], $minCodePoint, $max);
    }

    public function isEmpty(): bool
    {
        return [] === $this->ranges;
    }

    public function isFull(): bool
    {
        return 1 === \count($this->ranges)
            && $this->ranges[0][0] === $this->minCodePoint
            && $this->ranges[0][1] === $this->maxCodePoint;
    }

    /**
     * @return array<array{0:int, 1:int}>
     */
    public function ranges(): array
    {
        return $this->ranges;
    }

    public function minCodePoint(): int
    {
        return $this->minCodePoint;
    }

    public function maxCodePoint(): int
    {
        return $this->maxCodePoint;
    }

    public function contains(int $codePoint): bool
    {
        foreach ($this->ranges as [$start, $end]) {
            if ($codePoint >= $start && $codePoint <= $end) {
                return true;
            }
        }

        return false;
    }

    public function union(self $other): self
    {
        if ([] === $this->ranges) {
            return $other;
        }

        if ([] === $other->ranges) {
            return $this;
        }

        [$min, $max] = $this->resolveBounds($other);

        return new self(self::normalizeRanges(\array_merge($this->ranges, $other->ranges), $min, $max), $min, $max);
    }

    public function intersect(self $other): self
    {
        if ([] === $this->ranges || [] === $other->ranges) {
            [$min, $max] = $this->resolveBounds($other);

            return self::empty($max, $min);
        }

        [$min, $max] = $this->resolveBounds($other);
        $result = [];

        foreach ($this->ranges as [$start, $end]) {
            foreach ($other->ranges as [$oStart, $oEnd]) {
                $iStart = \max($start, $oStart);
                $iEnd = \min($end, $oEnd);
                if ($iStart <= $iEnd) {
                    $result[] = [$iStart, $iEnd];
                }
            }
        }

        return new self(self::normalizeRanges($result, $min, $max), $min, $max);
    }

    public function subtract(self $other): self
    {
        if ([] === $this->ranges) {
            return self::empty($this->maxCodePoint, $this->minCodePoint);
        }

        if ([] === $other->ranges) {
            return $this;
        }

        $result = [];
        $otherRanges = self::normalizeRanges($other->ranges, $this->minCodePoint, $this->maxCodePoint);

        foreach ($this->ranges as [$start, $end]) {
            $cursor = $start;
            foreach ($otherRanges as [$oStart, $oEnd]) {
                if ($oEnd < $cursor) {
                    continue;
                }

                if ($oStart > $end) {
                    break;
                }

                if ($oStart > $cursor) {
                    $result[] = [$cursor, $oStart - 1];
                }

                if ($oEnd >= $end) {
                    $cursor = $end + 1;

                    break;
                }

                $cursor = $oEnd + 1;
            }

            if ($cursor <= $end) {
                $result[] = [$cursor, $end];
            }
        }

        return new self(self::normalizeRanges($result, $this->minCodePoint, $this->maxCodePoint), $this->minCodePoint, $this->maxCodePoint);
    }

    public function complement(): self
    {
        if ([] === $this->ranges) {
            return self::full($this->maxCodePoint, $this->minCodePoint);
        }

        $normalized = self::normalizeRanges($this->ranges, $this->minCodePoint, $this->maxCodePoint);
        $result = [];
        $cursor = $this->minCodePoint;

        foreach ($normalized as [$start, $end]) {
            if ($cursor < $start) {
                $result[] = [$cursor, $start - 1];
            }
            $cursor = $end + 1;
        }

        if ($cursor <= $this->maxCodePoint) {
            $result[] = [$cursor, $this->maxCodePoint];
        }

        return new self($result, $this->minCodePoint, $this->maxCodePoint);
    }

    public function sampleChar(): ?string
    {
        if ([] === $this->ranges) {
            return null;
        }

        $value = $this->ranges[0][0] ?? null;
        if (null === $value) {
            return null;
        }

        return CodePointHelper::toString($value);
    }

    /**
     * @param array<array{0:int, 1:int}> $ranges
     *
     * @return array<array{0:int, 1:int}>
     */
    private static function normalizeRanges(array $ranges, int $minCodePoint, int $maxCodePoint): array
    {
        if ([] === $ranges) {
            return [];
        }

        \usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($ranges as [$start, $end]) {
            if ($end < $minCodePoint || $start > $maxCodePoint) {
                continue;
            }

            $start = \max($minCodePoint, $start);
            $end = \min($maxCodePoint, $end);

            if ([] === $merged) {
                $merged[] = [$start, $end];

                continue;
            }

            [$lastStart, $lastEnd] = $merged[\count($merged) - 1];
            if ($start <= $lastEnd + 1) {
                $merged[\count($merged) - 1] = [$lastStart, \max($lastEnd, $end)];

                continue;
            }

            $merged[] = [$start, $end];
        }

        return $merged;
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function resolveBounds(self $other): array
    {
        $min = \min($this->minCodePoint, $other->minCodePoint);
        $max = \max($this->maxCodePoint, $other->maxCodePoint);

        return [$min, $max];
    }
}
