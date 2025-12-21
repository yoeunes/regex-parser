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

        // Handle non-UTF8 / binary data
        $content = $this->ensureValidUtf8($content);
        if (null === $content) {
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

            // Validate that the pattern looks like a valid PCRE regex
            if (!$this->isValidPcrePattern($pattern)) {
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
            // Single-quoted strings: only \\ and \' are escape sequences
            return str_replace(["\\\\", "\\'"], ["\\", "'"], $body);
        }

        if ('"' === $quote) {
            // Double-quoted strings: handle PHP escape sequences properly
            // We cannot use stripcslashes() because it doesn't handle \x{XXXX} correctly
            return $this->decodeDoubleQuotedString($body);
        }

        return $body;
    }

    /**
     * Decode a double-quoted PHP string body, handling all PHP escape sequences.
     *
     * PHP escape sequences in double-quoted strings:
     * - \n, \r, \t, \v, \e, \f - control characters
     * - \\ - literal backslash
     * - \$ - literal dollar sign
     * - \" - literal double quote
     * - \[0-7]{1,3} - octal character code
     * - \x[0-9A-Fa-f]{1,2} - hex character code (1-2 digits)
     * - \u{XXXX} - Unicode codepoint (PHP 7+)
     *
     * IMPORTANT: \x{XXXX} is NOT a PHP escape sequence, it's a PCRE Unicode escape.
     * We must preserve it as-is so the regex parser can interpret it correctly.
     */
    private function decodeDoubleQuotedString(string $body): string
    {
        $result = '';
        $length = \strlen($body);
        $i = 0;

        while ($i < $length) {
            $char = $body[$i];

            if ('\\' !== $char) {
                $result .= $char;
                $i++;

                continue;
            }

            // We have a backslash - check what follows
            if ($i + 1 >= $length) {
                // Trailing backslash - keep as-is
                $result .= $char;
                $i++;

                continue;
            }

            $nextChar = $body[$i + 1];

            // Handle standard escape sequences
            switch ($nextChar) {
                case 'n':
                    $result .= "\n";
                    $i += 2;

                    break;
                case 'r':
                    $result .= "\r";
                    $i += 2;

                    break;
                case 't':
                    $result .= "\t";
                    $i += 2;

                    break;
                case 'v':
                    $result .= "\v";
                    $i += 2;

                    break;
                case 'e':
                    $result .= "\e";
                    $i += 2;

                    break;
                case 'f':
                    $result .= "\f";
                    $i += 2;

                    break;
                case '\\':
                    $result .= '\\';
                    $i += 2;

                    break;
                case '$':
                    $result .= '$';
                    $i += 2;

                    break;
                case '"':
                    $result .= '"';
                    $i += 2;

                    break;
                case 'x':
                    // Hex escape: \xHH or \x{HHHH} (PCRE Unicode - preserve as-is)
                    $hexResult = $this->parseHexEscape($body, $i, $length);
                    $result .= $hexResult['value'];
                    $i = $hexResult['newIndex'];

                    break;
                case 'u':
                    // PHP 7+ Unicode escape: \u{XXXX}
                    $unicodeResult = $this->parseUnicodeEscape($body, $i, $length);
                    $result .= $unicodeResult['value'];
                    $i = $unicodeResult['newIndex'];

                    break;
                case '0':
                case '1':
                case '2':
                case '3':
                case '4':
                case '5':
                case '6':
                case '7':
                    // Octal escape: \0 through \777
                    $octalResult = $this->parseOctalEscape($body, $i, $length);
                    $result .= $octalResult['value'];
                    $i = $octalResult['newIndex'];

                    break;
                default:
                    // Unknown escape sequence - keep backslash and character as-is
                    $result .= '\\'.$nextChar;
                    $i += 2;

                    break;
            }
        }

        return $result;
    }

    /**
     * Parse a hex escape sequence starting at position $i.
     *
     * @return array{value: string, newIndex: int}
     */
    private function parseHexEscape(string $body, int $i, int $length): array
    {
        // $i points to the backslash, $i+1 is 'x'
        $startPos = $i + 2;

        if ($startPos >= $length) {
            // Just \x at end - keep as-is
            return ['value' => '\\x', 'newIndex' => $startPos];
        }

        // Check for \x{...} - this is PCRE Unicode syntax, NOT PHP syntax
        // We must preserve it as-is for the regex parser
        if ('{' === $body[$startPos]) {
            // Find the closing brace
            $closeBrace = strpos($body, '}', $startPos);
            if (false !== $closeBrace) {
                // Preserve the entire \x{...} sequence as-is
                $sequence = substr($body, $i, $closeBrace - $i + 1);

                return ['value' => $sequence, 'newIndex' => $closeBrace + 1];
            }
            // No closing brace - keep as-is
            return ['value' => '\\x{', 'newIndex' => $startPos + 1];
        }

        // Standard PHP hex escape: \xHH (1-2 hex digits)
        $hexDigits = '';
        $pos = $startPos;
        while ($pos < $length && $pos < $startPos + 2 && ctype_xdigit($body[$pos])) {
            $hexDigits .= $body[$pos];
            $pos++;
        }

        if ('' === $hexDigits) {
            // No hex digits after \x - keep as-is
            return ['value' => '\\x', 'newIndex' => $startPos];
        }

        // Convert hex to character
        $charCode = hexdec($hexDigits);

        return ['value' => \chr($charCode), 'newIndex' => $pos];
    }

    /**
     * Parse a PHP 7+ Unicode escape sequence: \u{XXXX}.
     *
     * @return array{value: string, newIndex: int}
     */
    private function parseUnicodeEscape(string $body, int $i, int $length): array
    {
        // $i points to the backslash, $i+1 is 'u'
        $startPos = $i + 2;

        if ($startPos >= $length || '{' !== $body[$startPos]) {
            // Not a valid \u{...} sequence - keep as-is
            return ['value' => '\\u', 'newIndex' => $startPos];
        }

        // Find the closing brace
        $closeBrace = strpos($body, '}', $startPos);
        if (false === $closeBrace) {
            // No closing brace - keep as-is
            return ['value' => '\\u{', 'newIndex' => $startPos + 1];
        }

        $hexPart = substr($body, $startPos + 1, $closeBrace - $startPos - 1);

        // Validate hex digits
        if ('' === $hexPart || !ctype_xdigit($hexPart)) {
            // Invalid hex - keep as-is
            return ['value' => substr($body, $i, $closeBrace - $i + 1), 'newIndex' => $closeBrace + 1];
        }

        // Convert Unicode codepoint to UTF-8
        $codepoint = hexdec($hexPart);

        return ['value' => $this->codepointToUtf8($codepoint), 'newIndex' => $closeBrace + 1];
    }

    /**
     * Parse an octal escape sequence: \0 through \777.
     *
     * @return array{value: string, newIndex: int}
     */
    private function parseOctalEscape(string $body, int $i, int $length): array
    {
        // $i points to the backslash
        $startPos = $i + 1;
        $octalDigits = '';
        $pos = $startPos;

        // Read up to 3 octal digits
        while ($pos < $length && $pos < $startPos + 3 && $body[$pos] >= '0' && $body[$pos] <= '7') {
            $octalDigits .= $body[$pos];
            $pos++;
        }

        if ('' === $octalDigits) {
            // No octal digits - keep backslash
            return ['value' => '\\', 'newIndex' => $startPos];
        }

        // Convert octal to character
        $charCode = octdec($octalDigits);

        // Octal values > 255 are truncated to 8 bits in PHP
        return ['value' => \chr($charCode & 0xFF), 'newIndex' => $pos];
    }

    /**
     * Convert a Unicode codepoint to a UTF-8 string.
     */
    private function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint < 0x80) {
            return \chr($codepoint);
        }
        if ($codepoint < 0x800) {
            return \chr(0xC0 | ($codepoint >> 6)).\chr(0x80 | ($codepoint & 0x3F));
        }
        if ($codepoint < 0x10000) {
            return \chr(0xE0 | ($codepoint >> 12)).\chr(0x80 | (($codepoint >> 6) & 0x3F)).\chr(0x80 | ($codepoint & 0x3F));
        }

        return \chr(0xF0 | ($codepoint >> 18)).\chr(0x80 | (($codepoint >> 12) & 0x3F)).\chr(0x80 | (($codepoint >> 6) & 0x3F)).\chr(0x80 | ($codepoint & 0x3F));
    }

    /**
     * Check if a pattern looks like a valid PCRE regex.
     *
     * This performs basic structural validation:
     * - Must be at least 2 characters long
     * - Must start with a valid delimiter (non-alphanumeric, not backslash)
     * - Must not look like a URL or file path
     * - Must have a matching closing delimiter (not escaped)
     */
    private function isValidPcrePattern(string $pattern): bool
    {
        // Must be at least 2 characters (delimiter + delimiter)
        if (\strlen($pattern) < 2) {
            return false;
        }

        $firstChar = $pattern[0];

        // Skip strings starting with '?' - likely URL query strings
        if ('?' === $firstChar) {
            return false;
        }

        // Delimiter must be non-alphanumeric and not backslash
        if (ctype_alnum($firstChar) || '\\' === $firstChar) {
            return false;
        }

        // Skip URL-like patterns
        if ($this->looksLikeUrl($pattern)) {
            return false;
        }

        // Find the expected closing delimiter
        $closingDelimiter = $this->getClosingDelimiter($firstChar);

        // Find the actual closing delimiter position (accounting for escapes)
        $closingDelimiterPos = $this->findClosingDelimiter($pattern, $firstChar, $closingDelimiter);
        if (null === $closingDelimiterPos || 0 === $closingDelimiterPos) {
            return false;
        }

        // Verify that everything after the closing delimiter is valid modifiers
        $afterDelimiter = substr($pattern, $closingDelimiterPos + 1);
        if ('' !== $afterDelimiter && !preg_match('/^[imsxADSUXJu]*$/', $afterDelimiter)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a string looks like a URL or file path rather than a regex.
     */
    private function looksLikeUrl(string $pattern): bool
    {
        // Check for common URL schemes (case-insensitive check after delimiter)
        $withoutDelimiter = substr($pattern, 1);

        // URLs starting with http://, https://, ftp://, file://
        if (preg_match('/^https?:\/\//i', $withoutDelimiter)) {
            return true;
        }
        if (preg_match('/^(ftp|file|mailto|tel|data):[\/@]/i', $withoutDelimiter)) {
            return true;
        }

        // Check for path-like patterns that are unlikely to be regexes
        // e.g., /path/to/file, /api/v1/users
        if ('/' === $pattern[0]) {
            // If it starts with / and contains typical URL/path characters without regex metacharacters
            // Look for patterns like /?param=value or /path/to/something
            if (preg_match('/^\/\?[a-zA-Z]/', $pattern)) {
                // Starts with /? followed by a letter - likely URL query string
                return true;
            }

            // Check if it looks like a simple path without regex metacharacters
            // Paths typically have: letters, numbers, /, -, _, .
            // Regexes typically have: ^, $, *, +, ?, [, ], (, ), {, }, |, \
            $body = substr($pattern, 1);

            // Find potential closing delimiter
            $lastSlash = strrpos($body, '/');
            if (false !== $lastSlash) {
                $pathPart = substr($body, 0, $lastSlash);
                // If the path part has no regex metacharacters, it's likely a URL
                if ('' !== $pathPart && !preg_match('/[\^\$\*\+\?\[\]\(\)\{\}\|\\\\]/', $pathPart)) {
                    // Additional check: if it contains multiple path segments, likely a URL
                    if (substr_count($pathPart, '/') >= 2) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Find the position of the closing delimiter, accounting for escaped delimiters.
     */
    private function findClosingDelimiter(string $pattern, string $openingDelimiter, string $closingDelimiter): ?int
    {
        $length = \strlen($pattern);
        $depth = 0;
        $isPaired = $openingDelimiter !== $closingDelimiter;

        for ($i = 1; $i < $length; $i++) {
            $char = $pattern[$i];

            // Check for escape sequences
            if ('\\' === $char && $i + 1 < $length) {
                // Skip the escaped character
                $i++;

                continue;
            }

            // Handle paired delimiters (brackets)
            if ($isPaired) {
                if ($char === $openingDelimiter) {
                    $depth++;
                } elseif ($char === $closingDelimiter) {
                    if (0 === $depth) {
                        return $i;
                    }
                    $depth--;
                }
            } else {
                // Same opening and closing delimiter
                if ($char === $closingDelimiter) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Get the closing delimiter for a given opening delimiter.
     */
    private function getClosingDelimiter(string $openingDelimiter): string
    {
        return match ($openingDelimiter) {
            '(' => ')',
            '[' => ']',
            '{' => '}',
            '<' => '>',
            default => $openingDelimiter,
        };
    }

    /**
     * Ensure the content is valid UTF-8, attempting conversion if needed.
     * Returns null if the content is binary or cannot be converted.
     */
    private function ensureValidUtf8(string $content): ?string
    {
        // Check if already valid UTF-8
        if (mb_check_encoding($content, 'UTF-8')) {
            // Check for binary control characters (null bytes indicate binary data)
            if (str_contains($content, "\x00")) {
                return null;
            }

            return $content;
        }

        // Try to convert from ISO-8859-1
        $converted = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        if (\is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            // Check for binary control characters after conversion
            if (str_contains($converted, "\x00")) {
                return null;
            }

            return $converted;
        }

        // Cannot convert, skip this file
        return null;
    }
}
