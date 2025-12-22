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

use RegexParser\Exception\ParserException;

/**
 * @internal
 */
final class PatternParser
{
    /**
     * @return array{0: string, 1: string, 2: string}
     */
    public static function extractPatternAndFlags(string $regex): array
    {
        // Trim leading whitespace to match PHP's PCRE behavior
        $regex = ltrim($regex);

        $len = \strlen($regex);
        if ($len < 2) {
            throw new ParserException('Regex is too short. It must include delimiters.');
        }

        $delimiter = $regex[0];
        if (!self::isValidDelimiter($delimiter)) {
            $suggested = self::suggestPattern($regex);

            throw new ParserException(\sprintf(
                'Invalid delimiter "%s". Delimiters must not be alphanumeric, backslash, or whitespace. Try %s.',
                $delimiter,
                $suggested,
            ));
        }
        // Handle bracket delimiters style: (pattern), [pattern], {pattern}, <pattern>
        $closingDelimiter = match ($delimiter) {
            '(' => ')',
            '[' => ']',
            '{' => '}',
            '<' => '>',
            default => $delimiter,
        };

        // Find the last occurrence of the closing delimiter that is NOT escaped
        // We scan from the end to optimize for flags
        for ($i = $len - 1; $i > 0; $i--) {
            if ($regex[$i] === $closingDelimiter) {
                // Check if escaped (count odd number of backslashes before it)
                $escapes = 0;
                for ($j = $i - 1; $j > 0 && '\\' === $regex[$j]; $j--) {
                    $escapes++;
                }

                if (0 === $escapes % 2) {
                    // Found the end delimiter
                    $pattern = substr($regex, 1, $i - 1);
                    $flagsWithWhitespace = substr($regex, $i + 1);
                    $flags = preg_replace('/\s+/', '', $flagsWithWhitespace) ?? '';

                    // Validate flags (only allow standard PCRE flags)
                    // n = NO_AUTO_CAPTURE
                    if (!preg_match('/^[imsxADSUXJun]*+$/', $flags)) {
                        // Find the invalid flag for a better error message
                        $invalid = preg_replace('/[imsxADSUXJun]/', '', $flags);

                        // Format each invalid flag individually with quotes
                        $formattedFlags = implode(', ', array_map(fn ($flag) => \sprintf('"%s"', $flag), str_split($invalid ?? $flags)));

                        throw new ParserException(\sprintf('Unknown regex flag(s) found: %s', $formattedFlags));
                    }

                    return [$pattern, $flags, $delimiter];
                }
            }
        }

        $pattern = substr($regex, 1);
        $suggested = self::suggestPattern($pattern, $delimiter);

        throw new ParserException(\sprintf(
            'No closing delimiter "%s" found. You opened with "%s"; expected closing "%s". Tip: escape "%s" inside the pattern (\\%s) or use a different delimiter, e.g. %s.',
            $closingDelimiter,
            $delimiter,
            $closingDelimiter,
            $closingDelimiter,
            $closingDelimiter,
            $suggested,
        ));
    }

    private static function isValidDelimiter(string $delimiter): bool
    {
        return 1 === \strlen($delimiter)
            && !ctype_alnum($delimiter)
            && !ctype_space($delimiter)
            && '\\' !== $delimiter;
    }

    private static function suggestPattern(string $pattern, ?string $avoidDelimiter = null): string
    {
        $delimiter = str_contains($pattern, '#') && !str_contains($pattern, '~') ? '~' : '#';
        if (null !== $avoidDelimiter && $delimiter === $avoidDelimiter) {
            $delimiter = '#' === $delimiter ? '~' : '#';
        }
        $escaped = str_replace($delimiter, '\\'.$delimiter, $pattern);

        return $delimiter.$escaped.$delimiter;
    }
}
