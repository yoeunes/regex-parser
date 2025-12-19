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

namespace RegexParser\Bridge\Symfony\Extractor;

/**
 * Fallback strategy using token-based regex pattern extraction.
 *
 * This scanner is intentionally conservative: it only reports patterns that are
 * PHP constant strings passed directly to `preg_*` calls.
 *
 * @internal
 */
final readonly class TokenBasedExtractionStrategy implements ExtractorInterface
{
    private const IGNORABLE_TOKENS = [
        \T_WHITESPACE => true,
        \T_COMMENT => true,
        \T_DOC_COMMENT => true,
    ];

    private const PREG_FUNCTIONS = [
        'preg_match' => true,
        'preg_match_all' => true,
        'preg_replace' => true,
        'preg_replace_callback' => true,
        'preg_split' => true,
        'preg_grep' => true,
        'preg_filter' => true,
        'preg_replace_callback_array' => true,
    ];

    public function __construct() {}

    public function extract(array $files): array
    {
        $occurrences = [];

        foreach ($files as $file) {
            $occurrences = [...$occurrences, ...$this->extractFromFile($file)];
        }

        return $occurrences;
    }

    /**
     * @return list<RegexPatternOccurrence>
     */
    private function extractFromFile(string $file): array
    {
        $content = file_get_contents($file);
        if (false === $content || '' === $content) {
            return [];
        }

        $tokens = token_get_all($content, \TOKEN_PARSE);
        $occurrences = [];

        foreach ($tokens as $token) {
            $tokenOccurrences = $this->extractFromToken($token, $file);
            $occurrences = [...$occurrences, ...$tokenOccurrences];
        }

        return $occurrences;
    }

    /**
     * @param array{int, string, int}|string $token
     *
     * @return list<RegexPatternOccurrence>
     */
    private function extractFromToken($token, string $file): array
    {
        if (!\is_array($token)) {
            return [];
        }

        $tokenType = $token[0];
        $tokenValue = $token[1];
        $tokenLine = $token[2];

        if (\T_STRING === $tokenType && isset(self::PREG_FUNCTIONS[$tokenValue])) {
            return $this->extractFromNextTokens($tokenLine, $file);
        }

        return [];
    }

    /**
     * @return list<RegexPatternOccurrence>
     */
    private function extractFromNextTokens(int $line, string $file): array
    {
        $content = file_get_contents($file);
        if (false === $content) {
            return [];
        }
        $tokens = token_get_all($content, \TOKEN_PARSE);
        $totalTokens = \count($tokens);

        for ($i = 0; $i < $totalTokens; $i++) {
            $token = $tokens[$i];
            if (!\is_array($token)) {
                continue;
            }

            if ($token[2] !== $line) {
                continue;
            }

            if (\T_STRING === $token[0] && isset(self::PREG_FUNCTIONS[$token[1]])) {
                $patternToken = $this->findNextNonIgnorableToken($tokens, $i + 1);
                if (null === $patternToken) {
                    continue;
                }

                return $this->extractPatternFromToken($patternToken, $file, $token[1]);
            }
        }

        return [];
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array{int, string, int}|null
     */
    private function findNextNonIgnorableToken(array $tokens, int $startIndex): ?array
    {
        $totalTokens = \count($tokens);

        for ($i = $startIndex; $i < $totalTokens; $i++) {
            $token = $tokens[$i];

            if (!\is_array($token)) {
                continue;
            }

            $tokenType = $token[0];
            /** @var int $tokenType */
            if (!isset(self::IGNORABLE_TOKENS[$tokenType])) {
                return $token;
            }

            // Handle nested function calls
            if (\T_STRING === $tokenType && isset(self::PREG_FUNCTIONS[$token[1]])) {
                return $this->findNextNonIgnorableToken($tokens, $i + 1);
            }
        }

        return null;
    }

    /**
     * @param array{int, string, int} $token
     *
     * @return list<RegexPatternOccurrence>
     */
    private function extractPatternFromToken(array $token, string $file, string $functionName): array
    {
        if (\T_CONSTANT_ENCAPSED_STRING === $token[0]) {
            $pattern = stripcslashes(substr($token[1], 1, -1));

            if ('' === $pattern) {
                return [];
            }

            return [new RegexPatternOccurrence(
                $pattern,
                $file,
                $token[2],
                $functionName.'()',
            )];
        }

        // Handle concatenation of strings
        if (\T_STRING === $token[0] && isset(self::PREG_FUNCTIONS[$token[1]])) {
            $content = file_get_contents($file);
            if (false === $content) {
                return [];
            }
            $patternToken = $this->findNextNonIgnorableToken(
                token_get_all($content, \TOKEN_PARSE),
                $token[2],
            );

            return $patternToken ? $this->extractPatternFromToken($patternToken, $file, $token[1]) : [];
        }

        return [];
    }
}
