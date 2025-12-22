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
     * @param list<string> $customFunctions Additional functions/static methods to check (e.g., 'MyClass::customRegexCheck')
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
                ),
            ];
        }

        return $occurrences;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
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

        if (isset($this->customFunctionMap[$lookupName])) {
            continue;
        }

        // Fallback: check if the function call argument itself might be a regex pattern
        if ($this->isPregFunction($lookupName) && isset($tokens[$startIndex + 1]) && 
            \is_array($tokens[$startIndex + 1]) && 
            \T_CONSTANT_ENCAPSED_STRING === $tokens[$startIndex + 1][0]) {
            
            $patternInfo = $this->extractRegexPatternFromTokens($tokens, $startIndex + 1);
            if (null !== $patternInfo) {
                return [
                    $lookupName,
                    $startIndex + 1,
                    self::PREG_ARGUMENT_MAP[$lookupName],
                    false,
                ];
            }
        }

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
     * @param list<array{int, string, int}|string> $tokens
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
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return list<RegexPatternOccurrence>
     */
    private function extractFromCall(
        array $tokens,
        int $startIndex,
        int $totalTokens,
        int $targetArgIndex,
        string $sourceName,
        string $file,
        bool $isCallbackArray,
    ): array {
        $argIndex = 0;
        $parenDepth = 0;
        $bracketDepth = 0;
        $braceDepth = 0;
        $argTokens = [];
        $collecting = $argIndex === $targetArgIndex;

        for ($i = $startIndex; $i < $totalTokens; $i++) {
            $token = $tokens[$i];

            if ('(' === $token) {
                $parenDepth++;
                if ($collecting) {
                    $argTokens[] = $token;
                }

                continue;
            }

            if (')' === $token) {
                if (0 === $parenDepth && 0 === $bracketDepth && 0 === $braceDepth) {
                    if ($collecting) {
                        return $this->extractFromArgumentTokens($argTokens, $file, $sourceName, $isCallbackArray);
                    }

                    return [];
                }

                if ($parenDepth > 0) {
                    $parenDepth--;
                }

                if ($collecting) {
                    $argTokens[] = $token;
                }

                continue;
            }

            if ('[' === $token) {
                $bracketDepth++;
                if ($collecting) {
                    $argTokens[] = $token;
                }

                continue;
            }

            if (']' === $token) {
                if ($bracketDepth > 0) {
                    $bracketDepth--;
                }

                if ($collecting) {
                    $argTokens[] = $token;
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
                    return $this->extractFromArgumentTokens($argTokens, $file, $sourceName, $isCallbackArray);
                }

                $argIndex++;
                $collecting = $argIndex === $targetArgIndex;
                $argTokens = [];

                continue;
            }

            if ($collecting) {
                $argTokens[] = $token;
            }
        }

        if ($collecting) {
            return $this->extractFromArgumentTokens($argTokens, $file, $sourceName, $isCallbackArray);
        }

        return [];
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return list<RegexPatternOccurrence>
     */
    private function extractFromArgumentTokens(array $tokens, string $file, string $sourceName, bool $isCallbackArray): array
    {
        if ($isCallbackArray) {
            return $this->extractFromCallbackArray($tokens, $file, $sourceName);
        }

        $patternInfo = $this->parseRegexExpression($tokens);
        if (null === $patternInfo) {
            // Fallback to regular string parsing
            $patternInfo = $this->parseConstantStringExpression($tokens);
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
            )];
        }

        if ('' === $patternInfo['pattern']) {
            return [];
        }

        return [new RegexPatternOccurrence(
            $patternInfo['pattern'],
            $file,
            $patternInfo['line'],
            $sourceName.'()',
        )];
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return list<RegexPatternOccurrence>
     */
    private function extractFromCallbackArray(array $tokens, string $file, string $sourceName): array
    {
        $tokens = $this->stripOuterParentheses($tokens);
        $startIndex = $this->findArrayStartIndex($tokens);
        if (null === $startIndex) {
            return [];
        }

        $occurrences = [];
        $totalTokens = \count($tokens);
        $stack = [$this->closingTokenFor($tokens[$startIndex])];
        $collectingKey = true;
        $keyTokens = [];

        for ($i = $startIndex + 1; $i < $totalTokens; $i++) {
            $token = $tokens[$i];

            if ($this->isIgnorableToken($token)) {
                if ($collectingKey) {
                    $keyTokens[] = $token;
                }

                continue;
            }

            if (\is_array($token) && \T_ARRAY === $token[0]) {
                $nextIndex = $this->nextSignificantTokenIndex($tokens, $i + 1, $totalTokens);
                if (null !== $nextIndex && '(' === $tokens[$nextIndex]) {
                    $stack[] = ')';
                    if ($collectingKey) {
                        $keyTokens[] = '(';
                    }
                    $i = $nextIndex;

                    continue;
                }
            }

            if ('(' === $token || '[' === $token || '{' === $token) {
                $stack[] = $this->closingTokenFor($token);
                if ($collectingKey) {
                    $keyTokens[] = $token;
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
                }

                continue;
            }

            $atTopLevel = 1 === \count($stack);

            if ($atTopLevel && ',' === $token) {
                $collectingKey = true;
                $keyTokens = [];

                continue;
            }

            if ($atTopLevel && $this->isDoubleArrowToken($token)) {
                $patternInfo = $this->parseConstantStringExpression($keyTokens);
                if (null !== $patternInfo && '' !== $patternInfo['pattern']) {
                    $occurrences[] = new RegexPatternOccurrence(
                        $patternInfo['pattern'],
                        $file,
                        $patternInfo['line'],
                        $sourceName.'()',
                    );
                }

                $collectingKey = false;
                $keyTokens = [];

                continue;
            }

            if ($collectingKey) {
                $keyTokens[] = $token;
            }
        }

        return $occurrences;
    }

    /**
     * Parse a regex expression, handling patterns with flags.
     *
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array{pattern: string, line: int}|null
     */
    private function parseRegexExpression(array $tokens): ?array
    {
        $result = $this->parseConstantStringExpression($tokens);
        if (null === $result) {
            return null;
        }

        $pattern = $result['pattern'];
        
        // Check if this looks like a regex with flags (e.g., "/pattern/m" or "{pattern}u")
        // Need to handle escaped delimiters in the string
        if (preg_match('/^([\\\'"{}\/\#~%])(.*?)([\\\'"{}\/\#~%])([a-zA-Z]*)$/', $pattern, $matches)) {
            $delimiter = $matches[1];
            $regexBody = $matches[2];
            $flags = $matches[3];
            
            // Unescape the body to get the actual regex pattern
            $unescapedBody = stripslashes($regexBody);
            
            // Reconstruct the pattern with flags preserved
            $fullPattern = $delimiter . $unescapedBody . $delimiter . $flags;
            
            return [
                'pattern' => $fullPattern,
                'line' => $result['line'],
            ];
        }
        
        // Not a regex with flags, return as-is
        return $result;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array{pattern: string, line: int}|null
     */
    private function parseConstantStringExpression(array $tokens): ?array
    {
        $parts = [];
        $firstLine = null;
        $expectString = true;

        foreach ($tokens as $token) {
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
        if (preg_match('/^([\\\'"{}\/\#~%])([^\'"{\/\#~%]*)([\'"{\/\#~%])([a-zA-Z]*)$/', $pattern, $matches)) {
            $delimiter = $matches[1];
            $body = $matches[2];
            $endDelimiter = $matches[3];
            $flags = $matches[4] ?? '';
            
            // If the end delimiter is missing and we have flags, fix it
            if ('' === $endDelimiter && '' !== $flags) {
                $pattern = $delimiter . $body . $delimiter . $flags;
            }
        }

        return [
            'pattern' => $pattern,
            'line' => $firstLine,
        ];
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

        $charCode = hexdec($hexDigits);

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

        $codepoint = hexdec($hexPart);

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

        $charCode = octdec($octalDigits);

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
     * @param list<array{int, string, int}|string> $tokens
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

    private function isDoubleColonToken(array|string $token): bool
    {
        return \is_array($token) && \T_DOUBLE_COLON === $token[0];
    }

    private function isDefinitionToken(array|string $token): bool
    {
        return \is_array($token) && \in_array($token[0], [\T_FUNCTION, \T_FN, \T_NEW], true);
    }

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

    private function isIgnorableToken(array|string $token): bool
    {
        return \is_array($token) && isset(self::IGNORABLE_TOKENS[$token[0]]);
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
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
     * @param list<array{int, string, int}|string> $tokens
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

    private function isDoubleArrowToken(array|string $token): bool
    {
        return \is_array($token) && \T_DOUBLE_ARROW === $token[0];
    }

    private function closingTokenFor(array|string $token): string
    {
        if ('(' === $token) {
            return ')';
        }
        if ('[' === $token) {
            return ']';
        }
        if ('{' === $token) {
            return '}';
        }

        return ')';
    }

    private function isClosingToken(array|string $token, ?string $expected): bool
    {
        if (null === $expected) {
            return false;
        }

        return \is_string($token) && $token === $expected;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return list<array{int, string, int}|string>
     */
    private function stripOuterParentheses(array $tokens): array
    {
        $totalTokens = \count($tokens);
        if (0 === $totalTokens) {
            return $tokens;
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
            $totalTokens = \count($tokens);
        }

        return $tokens;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
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
