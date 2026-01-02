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
 * Token-based extraction strategy mirroring PHPStan's preg_* handling.
 *
 * This relies on a small token state machine to track argument positions
 * and only extracts patterns from constant string expressions.
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

    private const PREG_ARGUMENT_MAP = [
        'preg_match' => 0,
        'preg_match_all' => 0,
        'preg_replace' => 0,
        'preg_replace_callback' => 0,
        'preg_split' => 0,
        'preg_grep' => 0,
        'preg_filter' => 0,
        'preg_replace_callback_array' => 0,
    ];

    /**
     * @var array<string, int>
     */
    private array $customFunctionMap;

    /**
     * @var array<string, int>
     */
    private array $customStaticFunctionMap;

    /**
     * @param array<string> $customFunctions Additional functions/static methods to check (e.g., 'MyClass::customRegexCheck')
     */
    public function __construct(array $customFunctions = [])
    {
        $customFunctionMap = [];
        $customStaticFunctionMap = [];

        foreach ($customFunctions as $customFunction) {
            if (!\is_string($customFunction) || '' === $customFunction) {
                continue;
            }

            $normalized = strtolower(ltrim($customFunction, '\\'));
            if (str_contains($normalized, '::')) {
                $customStaticFunctionMap[$normalized] = 0;

                continue;
            }

            $customFunctionMap[$normalized] = 0;
        }

        $this->customFunctionMap = $customFunctionMap;
        $this->customStaticFunctionMap = $customStaticFunctionMap;
    }

    public function extract(array $files): array
    {
        $occurrences = [];

        foreach ($files as $file) {
            $this->appendOccurrences($occurrences, $this->extractFromFile($file));
        }

        return $occurrences;
    }

    /**
     * @return array<RegexPatternOccurrence>
     */
    private function extractFromFile(string $file): array
    {
        $content = file_get_contents($file);
        if (false === $content || '' === $content) {
            return [];
        }

        if ($this->shouldSkipContent($content)) {
            return [];
        }

        $content = $this->ensureValidUtf8($content);
        if (null === $content) {
            return [];
        }

        $tokens = token_get_all($content);
        $tokenOffsets = $this->buildTokenOffsets($tokens);
        $occurrences = [];
        $totalTokens = \count($tokens);

        for ($i = 0; $i < $totalTokens; $i++) {
            $token = $tokens[$i];
            if (!\is_array($token)) {
                continue;
            }

            $match = $this->matchFunctionCall($tokens, $i, $totalTokens);
            if (null === $match) {
                continue;
            }

            [$sourceName, $openParenIndex, $targetArgIndex, $isCallbackArray] = $match;

            $occurrences = [
                ...$occurrences,
                ...$this->extractFromCall(
                    $tokens,
                    $openParenIndex + 1,
                    $totalTokens,
                    $targetArgIndex,
                    $sourceName,
                    $file,
                    $isCallbackArray,
                    $tokenOffsets,
                    $content,
                ),
            ];
        }

        return $occurrences;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     *
     * @return array{string, int, int, bool}|null
     */
    private function matchFunctionCall(array $tokens, int $index, int $totalTokens): ?array
    {
        $name = $this->readNameToken($tokens[$index]);
        if (null === $name) {
            return null;
        }

        $nextIndex = $this->nextSignificantTokenIndex($tokens, $index + 1, $totalTokens);
        if (null !== $nextIndex && $this->isDoubleColonToken($tokens[$nextIndex])) {
            return $this->matchCustomStaticMethod($tokens, $index, $nextIndex, $totalTokens);
        }

        // From here on we treat this as a plain function call and decide
        // whether it is a preg_* call or a configured custom function.
        $prevIndex = $this->previousSignificantTokenIndex($tokens, $index - 1);
        if (null !== $prevIndex) {
            $prevToken = $tokens[$prevIndex];
            if ($this->isDefinitionToken($prevToken) || $this->isObjectOrStaticOperator($prevToken)) {
                return null;
            }
        }

        if ($this->isNamespacedFunctionName($tokens, $index)) {
            return null;
        }

        if (null === $nextIndex || '(' !== $tokens[$nextIndex]) {
            return null;
        }

        $trimmedName = ltrim($name, '\\');
        if (str_contains($trimmedName, '\\')) {
            return null;
        }

        $lookupName = strtolower($trimmedName);

        if (isset(self::PREG_ARGUMENT_MAP[$lookupName])) {
            return [
                $trimmedName,
                $nextIndex,
                self::PREG_ARGUMENT_MAP[$lookupName],
                'preg_replace_callback_array' === $lookupName,
            ];
        }

        if (isset($this->customFunctionMap[$lookupName])) {
            return [
                $trimmedName,
                $nextIndex,
                $this->customFunctionMap[$lookupName],
                false,
            ];
        }

        return null;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     *
     * @return array{string, int, int, bool}|null
     */
    private function matchCustomStaticMethod(array $tokens, int $classIndex, int $doubleColonIndex, int $totalTokens): ?array
    {
        $methodIndex = $this->nextSignificantTokenIndex($tokens, $doubleColonIndex + 1, $totalTokens);
        if (null === $methodIndex) {
            return null;
        }

        $methodToken = $tokens[$methodIndex];
        if (!\is_array($methodToken) || \T_STRING !== $methodToken[0]) {
            return null;
        }

        $openParenIndex = $this->nextSignificantTokenIndex($tokens, $methodIndex + 1, $totalTokens);
        if (null === $openParenIndex || '(' !== $tokens[$openParenIndex]) {
            return null;
        }

        $className = $this->readNameToken($tokens[$classIndex]);
        if (null === $className) {
            return null;
        }

        $className = ltrim($className, '\\');
        $methodName = $methodToken[1];
        $fullName = $className.'::'.$methodName;
        $lookupName = strtolower($fullName);

        if (!isset($this->customStaticFunctionMap[$lookupName])) {
            return null;
        }

        return [
            $fullName,
            $openParenIndex,
            $this->customStaticFunctionMap[$lookupName],
            false,
        ];
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @param array<int, int>                            $tokenOffsets
     *
     * @return array<RegexPatternOccurrence>
     */
    private function extractFromCall(
        array $tokens,
        int $startIndex,
        int $totalTokens,
        int $targetArgIndex,
        string $sourceName,
        string $file,
        bool $isCallbackArray,
        array $tokenOffsets,
        string $content,
    ): array {
        $argIndex = 0;
        $parenDepth = 0;
        $bracketDepth = 0;
        $braceDepth = 0;
        $argTokens = [];
        $argTokenIndexes = [];
        $collecting = $argIndex === $targetArgIndex;

        for ($i = $startIndex; $i < $totalTokens; $i++) {
            $token = $tokens[$i];

            if ('(' === $token) {
                $parenDepth++;
                if ($collecting) {
                    $argTokens[] = $token;
                    $argTokenIndexes[] = $i;
                }

                continue;
            }

            if (')' === $token) {
                if (0 === $parenDepth && 0 === $bracketDepth && 0 === $braceDepth) {
                    if ($collecting) {
                        return $this->extractFromArgumentTokens($argTokens, $argTokenIndexes, $tokenOffsets, $content, $file, $sourceName, $isCallbackArray);
                    }

                    return [];
                }

                if ($parenDepth > 0) {
                    $parenDepth--;
                }

                if ($collecting) {
                    $argTokens[] = $token;
                    $argTokenIndexes[] = $i;
                }

                continue;
            }

            if ('[' === $token) {
                $bracketDepth++;
                if ($collecting) {
                    $argTokens[] = $token;
                    $argTokenIndexes[] = $i;
                }

                continue;
            }

            if (']' === $token) {
                if ($bracketDepth > 0) {
                    $bracketDepth--;
                }

                if ($collecting) {
                    $argTokens[] = $token;
                    $argTokenIndexes[] = $i;
                }

                continue;
            }

            if ('{' === $token) {
                $braceDepth++;
                if ($collecting) {
                    $argTokens[] = $token;
                }

                continue;
            }

            if ('}' === $token) {
                if ($braceDepth > 0) {
                    $braceDepth--;
                }

                if ($collecting) {
                    $argTokens[] = $token;
                }

                continue;
            }

            if (',' === $token && 0 === $parenDepth && 0 === $bracketDepth && 0 === $braceDepth) {
                if ($collecting) {
                    return $this->extractFromArgumentTokens($argTokens, $argTokenIndexes, $tokenOffsets, $content, $file, $sourceName, $isCallbackArray);
                }

                $argIndex++;
                $collecting = $argIndex === $targetArgIndex;
                $argTokens = [];
                $argTokenIndexes = [];

                continue;
            }

            if ($collecting) {
                $argTokens[] = $token;
                $argTokenIndexes[] = $i;
            }
        }

        if ($collecting) {
            return $this->extractFromArgumentTokens($argTokens, $argTokenIndexes, $tokenOffsets, $content, $file, $sourceName, $isCallbackArray);
        }

        return [];
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @param array<int, int>                            $tokenIndexes
     * @param array<int, int>                            $tokenOffsets
     *
     * @return array<RegexPatternOccurrence>
     */
    private function extractFromArgumentTokens(
        array $tokens,
        array $tokenIndexes,
        array $tokenOffsets,
        string $content,
        string $file,
        string $sourceName,
        bool $isCallbackArray
    ): array {
        if ($isCallbackArray) {
            return $this->extractFromCallbackArray($tokens, $tokenIndexes, $tokenOffsets, $content, $file, $sourceName);
        }

        $patternInfo = $this->parseRegexExpression($tokens, $tokenIndexes, $tokenOffsets, $content);
        if (null === $patternInfo) {
            // Fallback to regular string parsing
            $patternInfo = $this->parseConstantStringExpression($tokens, $tokenIndexes, $tokenOffsets, $content);
            if (null === $patternInfo) {
                return [];
            }

            if ('' === $patternInfo['pattern']) {
                return [];
            }

            return [new RegexPatternOccurrence(
                $patternInfo['pattern'],
                $file,
                $patternInfo['line'],
                $sourceName.'()',
                column: $patternInfo['column'] ?? null,
                fileOffset: $patternInfo['offset'] ?? null,
            )];
        }

        // @codeCoverageIgnoreStart
        if ('' === $patternInfo['pattern']) {
            return [];
        }
        // @codeCoverageIgnoreEnd

        return [new RegexPatternOccurrence(
            $patternInfo['pattern'],
            $file,
            $patternInfo['line'],
            $sourceName.'()',
            column: $patternInfo['column'] ?? null,
            fileOffset: $patternInfo['offset'] ?? null,
        )];
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @param array<int, int>                            $tokenIndexes
     * @param array<int, int>                            $tokenOffsets
     *
     * @return array<RegexPatternOccurrence>
     */
    private function extractFromCallbackArray(
        array $tokens,
        array $tokenIndexes,
        array $tokenOffsets,
        string $content,
        string $file,
        string $sourceName
    ): array {
        [$tokens, $tokenIndexes] = $this->stripOuterParentheses($tokens, $tokenIndexes);
        $startIndex = $this->findArrayStartIndex($tokens);
        if (null === $startIndex) {
            return [];
        }

        $occurrences = [];
        $totalTokens = \count($tokens);
        $stack = [$this->closingTokenFor($tokens[$startIndex])];
        $collectingKey = true;
        $keyTokens = [];
        /** @var array<int, int> $keyTokenIndexes */
        $keyTokenIndexes = [];

        for ($i = $startIndex + 1; $i < $totalTokens; $i++) {
            $token = $tokens[$i];
            $tokenIndex = $tokenIndexes[$i] ?? -1;

            if ($this->isIgnorableToken($token)) {
                if ($collectingKey) {
                    $keyTokens[] = $token;
                    $keyTokenIndexes[] = $tokenIndex;
                }

                continue;
            }

            if (\is_array($token) && \T_ARRAY === $token[0]) {
                $nextIndex = $this->nextSignificantTokenIndex($tokens, $i + 1, $totalTokens);
                if (null !== $nextIndex && '(' === $tokens[$nextIndex]) {
                    $stack[] = ')';
                    if ($collectingKey) {
                        $keyTokens[] = '(';
                        $keyTokenIndexes[] = $tokenIndex;
                    }
                    $i = $nextIndex;

                    continue;
                }
            }

            if ('(' === $token || '[' === $token || '{' === $token) {
                $stack[] = $this->closingTokenFor($token);
                if ($collectingKey) {
                    $keyTokens[] = $token;
                    $keyTokenIndexes[] = $tokenIndex;
                }

                continue;
            }

            if ($this->isClosingToken($token, end($stack))) {
                array_pop($stack);
                if (empty($stack)) {
                    break;
                }

                if ($collectingKey) {
                    $keyTokens[] = $token;
                    $keyTokenIndexes[] = $tokenIndex;
                }

                continue;
            }

            $atTopLevel = 1 === \count($stack);

            if ($atTopLevel && ',' === $token) {
                $collectingKey = true;
                $keyTokens = [];
                $keyTokenIndexes = [];

                continue;
            }

            if ($atTopLevel && $this->isDoubleArrowToken($token)) {
                $patternInfo = $this->parseConstantStringExpression($keyTokens, $keyTokenIndexes, $tokenOffsets, $content);
                if (null !== $patternInfo && '' !== $patternInfo['pattern']) {
                    $occurrences[] = new RegexPatternOccurrence(
                        $patternInfo['pattern'],
                        $file,
                        $patternInfo['line'],
                        $sourceName.'()',
                        column: $patternInfo['column'] ?? null,
                        fileOffset: $patternInfo['offset'] ?? null,
                    );
                }

                $collectingKey = false;
                $keyTokens = [];
                $keyTokenIndexes = [];

                continue;
            }

            if ($collectingKey) {
                $keyTokens[] = $token;
                $keyTokenIndexes[] = $tokenIndex;
            }
        }

        return $occurrences;
    }

    /**
     * Parse a regex expression, handling patterns with flags.
     *
     * @param array<int, array{int, string, int}|string> $tokens
     * @param array<int, int>                            $tokenIndexes
     * @param array<int, int>                            $tokenOffsets
     *
     * @return array{pattern: string, line: int, offset?: int|null, column?: int|null}|null
     */
    private function parseRegexExpression(array $tokens, array $tokenIndexes, array $tokenOffsets, string $content): ?array
    {
        $result = $this->parseConstantStringExpression($tokens, $tokenIndexes, $tokenOffsets, $content);
        if (null === $result) {
            return null;
        }

        $pattern = $result['pattern'];

        // Check if this looks like a regex with flags (e.g., "/pattern/m" or "{pattern}u")
        // Need to handle escaped delimiters in the string
        if (preg_match('/^([\'"{}\/#~%])(.*?)([\'"{}\/#~%])([A-Za-z]*)$/', $pattern, $matches)) {
            $delimiter = $matches[1];
            $regexBody = $matches[2];
            $flags = $matches[4];

            // The pattern body returned from parseConstantStringExpression()
            // has already been decoded from the PHP string literal. Avoid
            // running stripslashes() again here, which would incorrectly
            // drop significant escapes like \\d, \\w, or \\x7f.
            $closingDelimiter = '{' === $delimiter ? '}' : $delimiter;

            // Reconstruct the pattern with flags preserved, using the proper
            // closing delimiter for bracket-style delimiters.
            $fullPattern = $delimiter.$regexBody.$closingDelimiter.$flags;

            return [
                'pattern' => $fullPattern,
                'line' => $result['line'],
                'offset' => $result['offset'] ?? null,
                'column' => $result['column'] ?? null,
            ];
        }

        // Not a regex with flags, let the caller fall back to plain string parsing.
        return null;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @param array<int, int>                            $tokenIndexes
     * @param array<int, int>                            $tokenOffsets
     *
     * @return array{pattern: string, line: int, offset?: int|null, column?: int|null}|null
     */
    private function parseConstantStringExpression(array $tokens, array $tokenIndexes, array $tokenOffsets, string $content): ?array
    {
        $parts = [];
        $firstLine = null;
        $firstTokenOffset = null;
        $firstTokenColumn = null;
        $expectString = true;

        foreach ($tokens as $index => $token) {
            if ($this->isIgnorableToken($token)) {
                continue;
            }

            if ('(' === $token || ')' === $token) {
                continue;
            }

            if ($expectString) {
                if (\is_array($token) && \T_CONSTANT_ENCAPSED_STRING === $token[0]) {
                    $parts[] = $this->decodeStringToken($token[1]);
                    if (null === $firstLine) {
                        $firstLine = $token[2];
                    }
                    if (null === $firstTokenOffset) {
                        $tokenIndex = $tokenIndexes[$index] ?? null;
                        if (\is_int($tokenIndex) && isset($tokenOffsets[$tokenIndex])) {
                            $firstTokenOffset = $tokenOffsets[$tokenIndex];
                            $firstTokenColumn = $this->columnFromOffset($content, $firstTokenOffset);
                        }
                    }
                    $expectString = false;

                    continue;
                }

                return null;
            }

            if ('.' === $token) {
                $expectString = true;

                continue;
            }

            return null;
        }

        if ($expectString || null === $firstLine) {
            return null;
        }

        $pattern = implode('', $parts);

        // Special handling for regex patterns with flags that might have been concatenated
        // Check if this looks like a regex that might have flags after closing delimiter
        /*
            * if (preg_match('/^([\'"{}\/#~%])([^\'"{\/#~%]*)([\'"{\/\#~%])([A-Za-z]*)$/', $pattern, $matches)) {
            $delimiter = $matches[1];
            $body = $matches[2];
            $endDelimiter = $matches[3];
            $flags = $matches[4];

            // Currently we keep $pattern as-is; this block mainly validates
            // that it already looks like a well-formed /body/flags pattern.
        // }
            */

        return [
            'pattern' => $pattern,
            'line' => $firstLine,
            'offset' => $firstTokenOffset,
            'column' => $firstTokenColumn,
        ];
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     *
     * @return array<int, int>
     */
    private function buildTokenOffsets(array $tokens): array
    {
        $offsets = [];
        $offset = 0;

        foreach ($tokens as $index => $token) {
            $offsets[$index] = $offset;
            $text = \is_array($token) ? $token[1] : $token;
            $offset += \strlen($text);
        }

        return $offsets;
    }

    private function columnFromOffset(string $content, int $offset): ?int
    {
        if ($offset < 0) {
            return null;
        }

        $prefix = substr($content, 0, $offset);
        $lastNewline = strrpos($prefix, "\n");
        if (false === $lastNewline) {
            return $offset + 1;
        }

        return $offset - $lastNewline;
    }

    private function decodeStringToken(string $token): string
    {
        if (\strlen($token) < 2) {
            return '';
        }

        $quote = $token[0];
        $body = substr($token, 1, -1);

        if ("'" === $quote) {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $body);
        }

        if ('"' === $quote) {
            return $this->decodeDoubleQuotedString($body);
        }

        return $body;
    }

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

            if ($i + 1 >= $length) {
                $result .= $char;
                $i++;

                continue;
            }

            $nextChar = $body[$i + 1];

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
                    $hexResult = $this->parseHexEscape($body, $i, $length);
                    $result .= $hexResult['value'];
                    $i = $hexResult['newIndex'];

                    break;
                case 'u':
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
                    $octalResult = $this->parseOctalEscape($body, $i, $length);
                    $result .= $octalResult['value'];
                    $i = $octalResult['newIndex'];

                    break;
                default:
                    $result .= '\\'.$nextChar;
                    $i += 2;

                    break;
            }
        }

        return $result;
    }

    /**
     * @return array{value: string, newIndex: int}
     */
    private function parseHexEscape(string $body, int $i, int $length): array
    {
        $startPos = $i + 2;

        if ($startPos >= $length) {
            return ['value' => '\\x', 'newIndex' => $startPos];
        }

        if ('{' === $body[$startPos]) {
            $closeBrace = strpos($body, '}', $startPos);
            if (false !== $closeBrace) {
                $sequence = substr($body, $i, $closeBrace - $i + 1);

                return ['value' => $sequence, 'newIndex' => $closeBrace + 1];
            }

            return ['value' => '\\x{', 'newIndex' => $startPos + 1];
        }

        $hexDigits = '';
        $pos = $startPos;
        while ($pos < $length && $pos < $startPos + 2 && ctype_xdigit($body[$pos])) {
            $hexDigits .= $body[$pos];
            $pos++;
        }

        if ('' === $hexDigits) {
            return ['value' => '\\x', 'newIndex' => $startPos];
        }

        $charCode = (int) hexdec($hexDigits);

        return ['value' => \chr($charCode), 'newIndex' => $pos];
    }

    /**
     * @return array{value: string, newIndex: int}
     */
    private function parseUnicodeEscape(string $body, int $i, int $length): array
    {
        $startPos = $i + 2;

        if ($startPos >= $length || '{' !== $body[$startPos]) {
            return ['value' => '\\u', 'newIndex' => $startPos];
        }

        $closeBrace = strpos($body, '}', $startPos);
        if (false === $closeBrace) {
            return ['value' => '\\u{', 'newIndex' => $startPos + 1];
        }

        $hexPart = substr($body, $startPos + 1, $closeBrace - $startPos - 1);

        if ('' === $hexPart || !ctype_xdigit($hexPart)) {
            return ['value' => substr($body, $i, $closeBrace - $i + 1), 'newIndex' => $closeBrace + 1];
        }

        $codepoint = (int) hexdec($hexPart);

        return ['value' => $this->codepointToUtf8($codepoint), 'newIndex' => $closeBrace + 1];
    }

    /**
     * @return array{value: string, newIndex: int}
     */
    private function parseOctalEscape(string $body, int $i, int $length): array
    {
        $startPos = $i + 1;
        $octalDigits = '';
        $pos = $startPos;

        while ($pos < $length && $pos < $startPos + 3 && $body[$pos] >= '0' && $body[$pos] <= '7') {
            $octalDigits .= $body[$pos];
            $pos++;
        }

        if ('' === $octalDigits) {
            return ['value' => '\\', 'newIndex' => $startPos];
        }

        $charCode = (int) octdec($octalDigits);

        return ['value' => \chr($charCode & 0xFF), 'newIndex' => $pos];
    }

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
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private function isNamespacedFunctionName(array $tokens, int $index): bool
    {
        $token = $tokens[$index];
        if (!\is_array($token) || \T_STRING !== $token[0]) {
            return false;
        }

        $prevIndex = $this->previousSignificantTokenIndex($tokens, $index - 1);
        if (null === $prevIndex) {
            return false;
        }

        $prevToken = $tokens[$prevIndex];
        if (!\is_array($prevToken) || \T_NS_SEPARATOR !== $prevToken[0]) {
            return false;
        }

        $prevPrevIndex = $this->previousSignificantTokenIndex($tokens, $prevIndex - 1);
        if (null === $prevPrevIndex) {
            return false;
        }

        $prevPrevToken = $tokens[$prevPrevIndex];

        return \is_array($prevPrevToken) && $this->isNameToken($prevPrevToken);
    }

    /**
     * @param array{0:int, 1:string, 2?:int} $token
     */
    private function isNameToken(array $token): bool
    {
        $id = $token[0];

        if (\T_STRING === $id) {
            return true;
        }

        if (\defined('T_NAME_QUALIFIED') && \T_NAME_QUALIFIED === $id) {
            return true;
        }

        if (\defined('T_NAME_FULLY_QUALIFIED') && \T_NAME_FULLY_QUALIFIED === $id) {
            return true;
        }

        if (\defined('T_NAME_RELATIVE') && \T_NAME_RELATIVE === $id) {
            return true;
        }

        return false;
    }

    /**
     * @param array{0:int, 1:string, 2?:int}|string $token
     */
    private function readNameToken(array|string $token): ?string
    {
        if (!\is_array($token)) {
            return null;
        }

        $id = $token[0];
        if (\T_STRING === $id) {
            return $token[1];
        }

        if (\defined('T_NAME_QUALIFIED') && \T_NAME_QUALIFIED === $id) {
            return $token[1];
        }

        if (\defined('T_NAME_FULLY_QUALIFIED') && \T_NAME_FULLY_QUALIFIED === $id) {
            return $token[1];
        }

        if (\defined('T_NAME_RELATIVE') && \T_NAME_RELATIVE === $id) {
            return $token[1];
        }

        return null;
    }

    private function shouldSkipContent(string $content): bool
    {
        if ([] !== $this->customFunctionMap || [] !== $this->customStaticFunctionMap) {
            return false;
        }

        return false === stripos($content, 'preg_');
    }

    /**
     * @param array<RegexPatternOccurrence> $occurrences
     * @param array<RegexPatternOccurrence> $items
     */
    private function appendOccurrences(array &$occurrences, array $items): void
    {
        foreach ($items as $item) {
            $occurrences[] = $item;
        }
    }

    /**
     * @param array{0:int, 1:string, 2?:int}|string $token
     */
    private function isDoubleColonToken(array|string $token): bool
    {
        return \is_array($token) && \T_DOUBLE_COLON === $token[0];
    }

    /**
     * @param array{0:int, 1:string, 2?:int}|string $token
     */
    private function isDefinitionToken(array|string $token): bool
    {
        return \is_array($token) && \in_array($token[0], [\T_FUNCTION, \T_FN, \T_NEW], true);
    }

    /**
     * @param array{0:int, 1:string, 2?:int}|string $token
     */
    private function isObjectOrStaticOperator(array|string $token): bool
    {
        if (!\is_array($token)) {
            return false;
        }

        $operators = [\T_DOUBLE_COLON, \T_OBJECT_OPERATOR];
        if (\defined('T_NULLSAFE_OBJECT_OPERATOR')) {
            $operators[] = \T_NULLSAFE_OBJECT_OPERATOR;
        }

        return \in_array($token[0], $operators, true);
    }

    /**
     * @param array{0:int, 1:string, 2?:int}|string $token
     */
    private function isIgnorableToken(array|string $token): bool
    {
        return \is_array($token) && isset(self::IGNORABLE_TOKENS[$token[0]]);
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private function nextSignificantTokenIndex(array $tokens, int $startIndex, int $totalTokens): ?int
    {
        for ($i = $startIndex; $i < $totalTokens; $i++) {
            if (!$this->isIgnorableToken($tokens[$i])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private function previousSignificantTokenIndex(array $tokens, int $startIndex): ?int
    {
        for ($i = $startIndex; $i >= 0; $i--) {
            if (!$this->isIgnorableToken($tokens[$i])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param array{0:int, 1:string, 2?:int}|string $token
     */
    private function isDoubleArrowToken(array|string $token): bool
    {
        if (!\is_array($token)) {
            return '=>' === $token;
        }

        return \T_DOUBLE_ARROW === $token[0];
    }

    /**
     * @param array{0:int, 1:string, 2?:int}|string $token
     */
    private function closingTokenFor(array|string $token): string
    {
        return match ($token) {
            '(' => ')',
            '[' => ']',
            '{' => '}',
            default => ')',
        };
    }

    /**
     * @param array{0:int, 1:string, 2?:int}|string $token
     */
    private function isClosingToken(array|string $token, string $expected): bool
    {
        return $token === $expected;
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     * @param array<int, int>                            $tokenIndexes
     *
     * @return array{0: array<int, array{int, string, int}|string>, 1: array<int, int>}
     */
    private function stripOuterParentheses(array $tokens, array $tokenIndexes): array
    {
        $totalTokens = \count($tokens);
        if (0 === $totalTokens) {
            return [$tokens, $tokenIndexes];
        }

        while (true) {
            $startIndex = $this->nextSignificantTokenIndex($tokens, 0, $totalTokens);
            $endIndex = $this->previousSignificantTokenIndex($tokens, $totalTokens - 1);

            if (null === $startIndex || null === $endIndex) {
                break;
            }

            if ('(' !== $tokens[$startIndex] || ')' !== $tokens[$endIndex]) {
                break;
            }

            $depth = 0;
            $wrapsAll = true;
            for ($i = $startIndex; $i <= $endIndex; $i++) {
                $token = $tokens[$i];
                if ('(' === $token) {
                    $depth++;
                } elseif (')' === $token) {
                    $depth--;
                    if (0 === $depth && $i < $endIndex) {
                        $wrapsAll = false;

                        break;
                    }
                }
            }

            if (!$wrapsAll || 0 !== $depth) {
                break;
            }

            $tokens = \array_slice($tokens, $startIndex + 1, $endIndex - $startIndex - 1);
            $tokenIndexes = \array_slice($tokenIndexes, $startIndex + 1, $endIndex - $startIndex - 1);
            $totalTokens = \count($tokens);
        }

        return [$tokens, $tokenIndexes];
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private function findArrayStartIndex(array $tokens): ?int
    {
        $totalTokens = \count($tokens);
        $startIndex = $this->nextSignificantTokenIndex($tokens, 0, $totalTokens);
        if (null === $startIndex) {
            return null;
        }

        $token = $tokens[$startIndex];
        if ('[' === $token) {
            return $startIndex;
        }

        if (\is_array($token) && \T_ARRAY === $token[0]) {
            $openParenIndex = $this->nextSignificantTokenIndex($tokens, $startIndex + 1, $totalTokens);
            if (null !== $openParenIndex && '(' === $tokens[$openParenIndex]) {
                return $openParenIndex;
            }
        }

        return null;
    }

    /**
     * Ensure the content is valid UTF-8, attempting conversion if needed.
     * Returns null if the content is binary or cannot be converted.
     */
    private function ensureValidUtf8(string $content): ?string
    {
        if (mb_check_encoding($content, 'UTF-8')) {
            if (str_contains($content, "\x00")) {
                return null;
            }

            return $content;
        }

        $converted = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        if (\is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            if (str_contains($converted, "\x00")) {
                return null;
            }

            return $converted;
        }

        return null;
    }
}
