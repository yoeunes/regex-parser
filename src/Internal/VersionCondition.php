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
 * The condition "(?(VERSION>=10.4)yes|no)" asks about.
 *
 * PCRE lets a pattern branch on the version of the library reading it. The
 * text between the parentheses is all there is to it, so reading it needs no
 * tokens and no parser.
 *
 * @internal
 */
final readonly class VersionCondition
{
    /**
     * The comparisons that may follow "VERSION", longest first so that ">="
     * is not read as ">".
     *
     * PCRE itself only takes ">=" and "="; the others are read anyway, and
     * left to the validator to judge.
     */
    private const OPERATORS = ['>=', '<=', '==', '!=', '>', '<', '='];

    private function __construct(public string $operator, public string $version) {}

    /**
     * Read "VERSION>=10.4", or null when the text says something else.
     */
    public static function read(string $text): ?self
    {
        $text = trim($text);
        if (!str_starts_with($text, 'VERSION')) {
            return null;
        }

        $rest = ltrim(substr($text, \strlen('VERSION')));

        foreach (self::OPERATORS as $operator) {
            if (!str_starts_with($rest, $operator)) {
                continue;
            }

            $version = ltrim(substr($rest, \strlen($operator)));

            return self::isVersionNumber($version) ? new self($operator, $version) : null;
        }

        return null;
    }

    private static function isVersionNumber(string $version): bool
    {
        if ('' === $version) {
            return false;
        }

        foreach (explode('.', $version) as $part) {
            if ('' === $part || !ctype_digit($part)) {
                return false;
            }
        }

        return true;
    }
}
