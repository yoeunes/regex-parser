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

namespace RegexParser\Automata;

/**
 * Immutable character set for byte-based automata.
 */
final readonly class CharSet
{
    public const MIN_CODEPOINT = 0;
    public const MAX_CODEPOINT = 255;

    /**
     * @param array<array{0:int, 1:int}> $ranges
     */
    private function __construct(
        private array $ranges,
    ) {}

    /**
     * @return self
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return self
     */
    public static function full(): self
    {
        return new self([[self::MIN_CODEPOINT, self::MAX_CODEPOINT]]);
    }

    /**
     * @param string $char
     *
     * @return self
     */
    public static function fromChar(string $char): self
    {
        if ('' === $char) {
            return self::empty();
        }

        return self::fromCodePoint(\ord($char[0]));
    }

    /**
     * @param int $codePoint
     *
     * @return self
     */
    public static function fromCodePoint(int $codePoint): self
    {
        return self::fromRange($codePoint, $codePoint);
    }

    /**
     * @param int $start
     * @param int $end
     *
     * @return self
     */
    public static function fromRange(int $start, int $end): self
    {
        if ($end < self::MIN_CODEPOINT || $start > self::MAX_CODEPOINT) {
            return self::empty();
        }

        $clampedStart = \max(self::MIN_CODEPOINT, $start);
        $clampedEnd = \min(self::MAX_CODEPOINT, $end);

        if ($clampedStart > $clampedEnd) {
            return self::empty();
        }

        return new self([[$clampedStart, $clampedEnd]]);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return [] === $this->ranges;
    }

    /**
     * @param int $codePoint
     *
     * @return bool
     */
    public function contains(int $codePoint): bool
    {
        foreach ($this->ranges as [$start, $end]) {
            if ($codePoint >= $start && $codePoint <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param self $other
     *
     * @return self
     */
    public function union(self $other): self
    {
        if ([] === $this->ranges) {
            return $other;
        }

        if ([] === $other->ranges) {
            return $this;
        }

        return new self($this->normalize(\array_merge($this->ranges, $other->ranges)));
    }

    /**
     * @param self $other
     *
     * @return self
     */
    public function intersect(self $other): self
    {
        if ([] === $this->ranges || [] === $other->ranges) {
            return self::empty();
        }

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

        return new self($this->normalize($result));
    }

    /**
     * @param self $other
     *
     * @return self
     */
    public function subtract(self $other): self
    {
        if ([] === $this->ranges) {
            return self::empty();
        }

        if ([] === $other->ranges) {
            return $this;
        }

        $result = [];
        $otherRanges = $other->normalize($other->ranges);

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

        return new self($this->normalize($result));
    }

    /**
     * @return self
     */
    public function complement(): self
    {
        if ([] === $this->ranges) {
            return self::full();
        }

        $normalized = $this->normalize($this->ranges);
        $result = [];
        $cursor = self::MIN_CODEPOINT;

        foreach ($normalized as [$start, $end]) {
            if ($cursor < $start) {
                $result[] = [$cursor, $start - 1];
            }
            $cursor = $end + 1;
        }

        if ($cursor <= self::MAX_CODEPOINT) {
            $result[] = [$cursor, self::MAX_CODEPOINT];
        }

        return new self($result);
    }

    /**
     * @return string|null
     */
    public function sampleChar(): ?string
    {
        if ([] === $this->ranges) {
            return null;
        }

        $value = $this->ranges[0][0] ?? null;
        if (null === $value) {
            return null;
        }

        return \chr($value);
    }

    /**
     * @param array<array{0:int, 1:int}> $ranges
     *
     * @return array<array{0:int, 1:int}>
     */
    private function normalize(array $ranges): array
    {
        if ([] === $ranges) {
            return [];
        }

        \usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($ranges as [$start, $end]) {
            if ($end < self::MIN_CODEPOINT || $start > self::MAX_CODEPOINT) {
                continue;
            }

            $start = \max(self::MIN_CODEPOINT, $start);
            $end = \min(self::MAX_CODEPOINT, $end);

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
}
