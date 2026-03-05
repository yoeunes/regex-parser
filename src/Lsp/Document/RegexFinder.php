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

namespace RegexParser\Lsp\Document;

use RegexParser\Lsp\Converter\PositionConverter;

/**
 * Finds regex patterns in PHP source code.
 */
final class RegexFinder
{
    /**
     * Known preg_* functions that take regex patterns.
     */
    private const PREG_FUNCTIONS = [
        'preg_match',
        'preg_match_all',
        'preg_replace',
        'preg_replace_callback',
        'preg_replace_callback_array',
        'preg_filter',
        'preg_grep',
        'preg_split',
    ];

    /**
     * Find all regex patterns in PHP content.
     *
     * @return array<RegexOccurrence>
     */
    public function find(string $content): array
    {
        $occurrences = [];
        $tokens = @token_get_all($content);
        if (false === $tokens) {
            return [];
        }

        $positionConverter = new PositionConverter($content);
        $lastFunctionName = null;
        $expectingPattern = false;

        foreach ($tokens as $index => $token) {
            if (!\is_array($token)) {
                // Single character token
                if ('(' === $token && $expectingPattern) {
                    // Next string token should be the pattern
                    $expectingPattern = 'next_arg';
                } elseif (')' === $token || ',' === $token) {
                    $expectingPattern = false;
                }

                continue;
            }

            [$tokenType, $tokenValue, $line] = $token;

            // Track function calls
            if (\T_STRING === $tokenType && \in_array($tokenValue, self::PREG_FUNCTIONS, true)) {
                $lastFunctionName = $tokenValue;
                $expectingPattern = true;

                continue;
            }

            // Find string patterns after preg_* function calls
            if ('next_arg' === $expectingPattern && \in_array($tokenType, [\T_CONSTANT_ENCAPSED_STRING, \T_ENCAPSED_AND_WHITESPACE], true)) {
                $pattern = $this->extractPattern($tokenValue);
                if (null !== $pattern && $this->isValidRegexDelimiter($pattern)) {
                    $byteOffset = $this->findByteOffset($content, $tokens, $index);
                    $startPos = $positionConverter->offsetToPosition($byteOffset);
                    $endPos = $positionConverter->offsetToPosition($byteOffset + \strlen($tokenValue));

                    $occurrences[] = new RegexOccurrence(
                        pattern: $pattern,
                        start: $startPos,
                        end: $endPos,
                        byteOffset: $byteOffset,
                    );
                }
                $expectingPattern = false;
            }
        }

        return $occurrences;
    }

    /**
     * Extract the actual pattern from a quoted string token.
     */
    private function extractPattern(string $tokenValue): ?string
    {
        if (\strlen($tokenValue) < 2) {
            return null;
        }

        $quote = $tokenValue[0];
        if ("'" === $quote || '"' === $quote) {
            // Remove quotes
            $pattern = substr($tokenValue, 1, -1);

            // For double-quoted strings, handle escape sequences
            if ('"' === $quote) {
                // Basic unescaping
                $pattern = stripcslashes($pattern);
            } else {
                // Single quotes only escape \' and \\
                $pattern = str_replace(["\\'", '\\\\'], ["'", '\\'], $pattern);
            }

            return $pattern;
        }

        return null;
    }

    /**
     * Check if a string looks like a regex with valid delimiter.
     */
    private function isValidRegexDelimiter(string $pattern): bool
    {
        if (\strlen($pattern) < 2) {
            return false;
        }

        $delimiter = $pattern[0];

        // Common regex delimiters
        if (preg_match('/^[\/~#@!%]/', $delimiter)) {
            return true;
        }

        // Paired delimiters
        $pairs = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'];
        if (isset($pairs[$delimiter])) {
            return true;
        }

        return false;
    }

    /**
     * Calculate byte offset for a token.
     *
     * @param array<int|string|array{int, string, int}> $tokens
     */
    private function findByteOffset(string $content, array $tokens, int $targetIndex): int
    {
        $offset = 0;

        for ($i = 0; $i < $targetIndex; $i++) {
            $token = $tokens[$i];
            if (\is_array($token)) {
                $offset += \strlen($token[1]);
            } else {
                $offset += \strlen((string) $token);
            }
        }

        return $offset;
    }
}
