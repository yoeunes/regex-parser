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

namespace RegexParser\Lint;

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

        $tokens = token_get_all($content);
        $occurrences = [];
        $totalTokens = \count($tokens);

        for ($i = 0; $i < $totalTokens; $i++) {
            $token = $tokens[$i];
            if (!\is_array($token)) {
                continue;
            }

            if (\T_STRING !== $token[0] || !isset(self::PREG_FUNCTIONS[$token[1]])) {
                continue;
            }

            $patternToken = $this->findNextNonIgnorableToken($tokens, $i + 1);
            if (null === $patternToken) {
                continue;
            }

            $occurrences = [...$occurrences, ...$this->extractPatternFromToken($patternToken, $file, $token[1])];
        }

        return $occurrences;
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
            if (!isset(self::IGNORABLE_TOKENS[$tokenType])) {
                return $token;
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
            $pattern = $this->decodeStringToken($token[1]);

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

        return [];
    }

    private function decodeStringToken(string $token): string
    {
        if (\strlen($token) < 2) {
            return '';
        }

        $quote = $token[0];
        $body = substr($token, 1, -1);

        if ("'" === $quote) {
            return str_replace(["\\\\", "\\'"], ["\\", "'"], $body);
        }

        if ('"' === $quote) {
            return stripcslashes($body);
        }

        return $body;
    }
}
