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

namespace RegexParser\Internal;

/**
 * Escapes bytes that would break the layout of a terminal or a rendered
 * document, without rewriting characters that are safe to print.
 *
 * @internal
 */
final class DisplayEscaper
{
    private const CONTROL_BYTES = "\0..\37\177";

    private const CONTROL_AND_HIGH_BYTES = "\0..\37\177..\377";

    /**
     * Escape control characters, and non-ASCII bytes only when they do not
     * form valid UTF-8.
     *
     * Escaping every byte above 0x7E would turn a pattern such as
     * /《...》/u into octal escapes, which PCRE reads as different
     * characters: under /u, "\343" is U+00E3 rather than the first byte of a
     * multi-byte character. Printable UTF-8 is therefore left untouched.
     */
    public static function escape(string $text): string
    {
        return addcslashes($text, self::isUtf8($text) ? self::CONTROL_BYTES : self::CONTROL_AND_HIGH_BYTES);
    }

    private static function isUtf8(string $text): bool
    {
        return 1 === preg_match('//u', $text);
    }
}
