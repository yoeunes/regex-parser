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

namespace RegexParser\Automata\Unicode;

use RegexParser\Automata\Alphabet\CharSet;

/**
 * UTF-8 code point helpers for automata output and decoding.
 */
final class CodePointHelper
{
    public static function toString(int $codePoint): ?string
    {
        if ($codePoint < CharSet::MIN_CODEPOINT || $codePoint > CharSet::UNICODE_MAX_CODEPOINT) {
            return null;
        }

        if (\function_exists('mb_chr')) {
            $char = \mb_chr($codePoint, 'UTF-8');

            return false === $char || '' === $char ? null : $char;
        }

        if (\class_exists(\IntlChar::class)) {
            $char = \IntlChar::chr($codePoint);

            return false === $char ? null : $char;
        }

        if ($codePoint <= 0x7F) {
            return \chr($codePoint);
        }

        if ($codePoint >= 0xD800 && $codePoint <= 0xDFFF) {
            return null;
        }

        if ($codePoint <= 0x7FF) {
            return \chr(0xC0 | ($codePoint >> 6))
                .\chr(0x80 | ($codePoint & 0x3F));
        }

        if ($codePoint <= 0xFFFF) {
            return \chr(0xE0 | ($codePoint >> 12))
                .\chr(0x80 | (($codePoint >> 6) & 0x3F))
                .\chr(0x80 | ($codePoint & 0x3F));
        }

        return \chr(0xF0 | ($codePoint >> 18))
            .\chr(0x80 | (($codePoint >> 12) & 0x3F))
            .\chr(0x80 | (($codePoint >> 6) & 0x3F))
            .\chr(0x80 | ($codePoint & 0x3F));
    }

    public static function toCodePoint(string $char): ?int
    {
        if ('' === $char) {
            return null;
        }

        if (\function_exists('mb_ord')) {
            $value = \mb_ord($char, 'UTF-8');

            return false === $value ? null : $value;
        }

        if (\class_exists(\IntlChar::class)) {
            $value = \IntlChar::ord($char);

            return false === $value ? null : $value;
        }

        $bytes = \unpack('C*', $char);
        if (false === $bytes || [] === $bytes) {
            return null;
        }

        /** @var array<int, int> $bytes */
        $bytes = \array_values($bytes);
        $lead = $bytes[0];

        if ($lead < 0x80) {
            return $lead;
        }

        if ($lead < 0xC2) {
            return null;
        }

        $length = null;
        if ($lead <= 0xDF) {
            $length = 2;
        } elseif ($lead <= 0xEF) {
            $length = 3;
        } elseif ($lead <= 0xF4) {
            $length = 4;
        }

        if (null === $length || \count($bytes) !== $length) {
            return null;
        }

        for ($i = 1; $i < $length; $i++) {
            if ($bytes[$i] < 0x80 || $bytes[$i] > 0xBF) {
                return null;
            }
        }

        switch ($length) {
            case 2:
                $codePoint = (($lead & 0x1F) << 6) | ($bytes[1] & 0x3F);
                $minValue = 0x80;

                break;
            case 3:
                $codePoint = (($lead & 0x0F) << 12) | (($bytes[1] & 0x3F) << 6) | ($bytes[2] & 0x3F);
                $minValue = 0x800;

                break;
            case 4:
                $codePoint = (($lead & 0x07) << 18) | (($bytes[1] & 0x3F) << 12) | (($bytes[2] & 0x3F) << 6) | ($bytes[3] & 0x3F);
                $minValue = 0x10000;

                break;
            default:
                return null;
        }

        if ($codePoint < $minValue || $codePoint > CharSet::UNICODE_MAX_CODEPOINT) {
            return null;
        }

        if ($codePoint >= 0xD800 && $codePoint <= 0xDFFF) {
            return null;
        }

        return $codePoint;
    }

    /**
     * @return array<int>
     */
    public static function toCodePoints(string $text): array
    {
        if ('' === $text) {
            return [];
        }

        if (!self::isValidUtf8($text)) {
            return [];
        }

        $chars = \preg_split('//u', $text, -1, \PREG_SPLIT_NO_EMPTY);
        if (false === $chars) {
            return [];
        }

        $codePoints = [];
        foreach ($chars as $char) {
            $value = self::toCodePoint($char);
            if (null !== $value) {
                $codePoints[] = $value;
            }
        }

        return $codePoints;
    }

    public static function isValidUtf8(string $text): bool
    {
        if (\function_exists('mb_check_encoding')) {
            return \mb_check_encoding($text, 'UTF-8');
        }

        return 1 === \preg_match('//u', $text);
    }

    public static function singleCodePoint(string $text): ?int
    {
        if ('' === $text) {
            return null;
        }

        $chars = \preg_split('//u', $text, -1, \PREG_SPLIT_NO_EMPTY);
        if (false === $chars || 1 !== \count($chars)) {
            return null;
        }

        return self::toCodePoint($chars[0]);
    }
}
