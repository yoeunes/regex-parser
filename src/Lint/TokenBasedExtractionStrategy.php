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

    /**
     * @var array<string, true>
     */
    private array $customFunctions;

    /**
     * @param list<string> $customFunctions Additional functions/static methods to check (e.g., 'MyClass::customRegexCheck')
     */
    public function __construct(array $customFunctions = [])
    {
        $this->customFunctions = array_fill_keys($customFunctions, true);
    }

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

            $functionMatch = $this->matchFunctionCall($tokens, $i, $totalTokens);
            if (null === $functionMatch) {
                continue;
            }

            [$functionName, $nextIndex] = $functionMatch;

            $patternTokenResult = $this->findPatternToken($tokens, $nextIndex, $totalTokens);
            if (null === $patternTokenResult) {
                continue;
            }

            [$patternToken, $patternIndex] = $patternTokenResult;

            // Skip concatenated patterns - they cannot be validated statically
            if ($this->isFollowedByConcatenation($tokens, $patternIndex, $totalTokens)) {
                continue;
            }

            $occurrences = [...$occurrences, ...$this->extractPatternFromToken($patternToken, $file, $functionName)];
        }

        return $occurrences;
    }

    /**
     * Match a function call (regular function or static method).
     *
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array{string, int}|null Function name and next token index, or null if no match
     */
    private function matchFunctionCall(array $tokens, int $index, int $totalTokens): ?array
    {
        $token = $tokens[$index];
        if (!\is_array($token) || \T_STRING !== $token[0]) {
            return null;
        }

        $functionName = $token[1];

        // Check for static method call (ClassName::methodName)
        $staticMethodName = $this->tryMatchStaticMethod($tokens, $index, $totalTokens);
        if (null !== $staticMethodName) {
            if (isset($this->customFunctions[$staticMethodName])) {
                return [$staticMethodName, $index + 3]; // Skip ClassName, ::, methodName
            }

            return null;
        }

        // Check for regular function call
        if (isset(self::PREG_FUNCTIONS[$functionName]) || isset($this->customFunctions[$functionName])) {
            return [$functionName, $index + 1];
        }

        return null;
    }

    /**
     * Try to match a static method call pattern (ClassName::methodName).
     *
     * @param list<array{int, string, int}|string> $tokens
     */
    private function tryMatchStaticMethod(array $tokens, int $index, int $totalTokens): ?string
    {
        // Look ahead for :: and method name
        if ($index + 2 >= $totalTokens) {
            return null;
        }

        $doubleColon = $tokens[$index + 1];
        if (!\is_array($doubleColon) || \T_DOUBLE_COLON !== $doubleColon[0]) {
            return null;
        }

        $methodToken = $tokens[$index + 2];
        if (!\is_array($methodToken) || \T_STRING !== $methodToken[0]) {
            return null;
        }

        $className = $tokens[$index][1];
        $methodName = $methodToken[1];

        return $className.'::'.$methodName;
    }

    /**
     * Find the pattern token after the opening parenthesis.
     *
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array{array{int, string, int}, int}|null Pattern token and its index, or null if not found
     */
    private function findPatternToken(array $tokens, int $startIndex, int $totalTokens): ?array
    {
        for ($i = $startIndex; $i < $totalTokens; $i++) {
            $token = $tokens[$i];

            // Skip non-array tokens (like '(' and ',')
            if (!\is_array($token)) {
                continue;
            }

            // Skip ignorable tokens
            if (isset(self::IGNORABLE_TOKENS[$token[0]])) {
                continue;
            }

            // Found a non-ignorable array token
            return [$token, $i];
        }

        return null;
    }

    /**
     * Check if a token is followed by a concatenation operator.
     *
     * @param list<array{int, string, int}|string> $tokens
     */
    private function isFollowedByConcatenation(array $tokens, int $tokenIndex, int $totalTokens): bool
    {
        for ($i = $tokenIndex + 1; $i < $totalTokens; $i++) {
            $token = $tokens[$i];

            // Skip whitespace and comments
            if (\is_array($token) && isset(self::IGNORABLE_TOKENS[$token[0]])) {
                continue;
            }

            // Check for concatenation operator
            return '.' === $token;
        }

        return false;
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
