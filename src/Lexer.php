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

namespace RegexParser;

use RegexParser\Exception\LexerException;

/**
 * High-performance regex lexer with precompiled patterns and intelligent token recognition.
 *
 * This optimized lexer uses precompiled regex patterns and priority-based matching
 * for maximum performance while maintaining full compatibility with PCRE syntax.
 */
final class Lexer
{
    // Token priority maps for efficient matching
    private const TOKENS_OUTSIDE = [
        'T_COMMENT_OPEN', 'T_CALLOUT', 'T_PCRE_VERB', 'T_GROUP_MODIFIER_OPEN',
        'T_GROUP_OPEN', 'T_GROUP_CLOSE', 'T_CHAR_CLASS_OPEN', 'T_QUANTIFIER',
        'T_ALTERNATION', 'T_DOT', 'T_ANCHOR', 'T_ASSERTION', 'T_KEEP',
        'T_CHAR_TYPE', 'T_G_REFERENCE', 'T_BACKREF', 'T_OCTAL_LEGACY',
        'T_OCTAL', 'T_UNICODE', 'T_UNICODE_PROP', 'T_UNICODE_NAMED',
        'T_CONTROL_CHAR', 'T_QUOTE_MODE_START', 'T_QUOTE_MODE_END',
        'T_LITERAL_ESCAPED', 'T_LITERAL',
    ];

    private const TOKENS_INSIDE = [
        'T_CHAR_CLASS_CLOSE', 'T_POSIX_CLASS', 'T_CHAR_TYPE', 'T_OCTAL_LEGACY',
        'T_OCTAL', 'T_UNICODE', 'T_UNICODE_PROP', 'T_UNICODE_NAMED',
        'T_QUOTE_MODE_START', 'T_LITERAL_ESCAPED', 'T_CLASS_INTERSECTION',
        'T_CLASS_SUBTRACTION', 'T_LITERAL',
    ];

    // Optimized regex patterns broken into focused components
    private const PATTERNS_OUTSIDE = [
        'T_COMMENT_OPEN' => '\\(\\?\\#',
        'T_CALLOUT' => '\\(\\?C [^)]* \\)',
        'T_PCRE_VERB' => '\\(\\* [^)]+ \\)',
        'T_GROUP_MODIFIER_OPEN' => '\\(\\?',
        'T_GROUP_OPEN' => '\\(',
        'T_GROUP_CLOSE' => '\\)',
        'T_CHAR_CLASS_OPEN' => '\\[',
        'T_QUANTIFIER' => '(?: [\\*\\+\\?] | \\{\\d+(?:,\\d*)?\\} ) [\\?\\+]?',
        'T_ALTERNATION' => '\\|',
        'T_DOT' => '\\.',
        'T_ANCHOR' => '\\^|\\$',
        'T_ASSERTION' => '\\\\ (?: b\\{g\\} | B\\{g\\} | [AzZGbB] )',
        'T_KEEP' => '\\\\ K',
        'T_CHAR_TYPE' => '\\\\ [dswDSWhvRCX]',
        'T_G_REFERENCE' => '\\\\ g (?: \\{[a-zA-Z0-9_+-]+\\} | <[a-zA-Z0-9_]+> | [0-9+-]+ )?',
        'T_BACKREF' => '\\\\ (?: k(?:<[a-zA-Z0-9_]+> | \\{[a-zA-Z0-9_]+\\}) | (?<v_backref_num> [1-9]\\d*) )',
        'T_OCTAL_LEGACY' => '\\\\ [0-7]{1,3}',
        'T_OCTAL' => '\\\\ o\\{[0-7]+\\}',
        'T_UNICODE' => '\\\\ x [0-9a-fA-F]{1,2} | \\\\ u\\{[0-9a-fA-F]+\\} | \\\\ x\\{[0-9a-fA-F]+\\}',
        'T_UNICODE_PROP' => '\\\\ [pP] (?: \\{ (?<v1_prop> \\^? [a-zA-Z0-9_]+) \\} | (?<v2_prop> [a-zA-Z]) )',
        'T_UNICODE_NAMED' => '\\\\ N\\{[a-zA-Z0-9_ ]+\\}',
        'T_CONTROL_CHAR' => '\\\\ c [A-Z]',
        'T_QUOTE_MODE_START' => '\\\\ Q',
        'T_QUOTE_MODE_END' => '\\\\ E',
        'T_LITERAL_ESCAPED' => '\\\\ .',
        'T_LITERAL' => '[^\\\\]',
    ];

    private const PATTERNS_INSIDE = [
        'T_CHAR_CLASS_CLOSE' => '\\]',
        'T_POSIX_CLASS' => '\\[ \\: (?<v_posix> \\^? [a-zA-Z]+) \\: \\]',
        'T_CHAR_TYPE' => '\\\\ [dswDSWhvR]',
        'T_OCTAL_LEGACY' => '\\\\ 0[0-7]{0,2}',
        'T_OCTAL' => '\\\\ o\\{[0-7]+\\}',
        'T_UNICODE' => '\\\\ x [0-9a-fA-F]{1,2} | \\\\ u\\{[0-9a-fA-F]+\\} | \\\\ x\\{[0-9a-fA-F]+\\}',
        'T_UNICODE_PROP' => '\\\\ [pP] (?: \\{ (?<v1_prop> \\^? [a-zA-Z0-9_]+) \\} | (?<v2_prop> [a-zA-Z]) )',
        'T_QUOTE_MODE_START' => '\\\\ Q',
        'T_LITERAL_ESCAPED' => '\\\\ .',
        'T_CLASS_INTERSECTION' => '&&',
        'T_CLASS_SUBTRACTION' => '--',
        'T_LITERAL' => '[^\\\\]',
    ];

    // Precompiled regex patterns for maximum performance
    private static ?string $regexOutside = null;

    private static ?string $regexInside = null;

    private string $pattern;

    private int $position = 0;

    private int $length = 0;

    private bool $inCharClass = false;

    private bool $inQuoteMode = false;

    private bool $inCommentMode = false;

    private int $charClassStartPosition = 0;

    public function tokenize(string $pattern): TokenStream
    {
        if (!preg_match('//u', $pattern)) {
            throw LexerException::withContext('Input string is not valid UTF-8.', 0, $pattern);
        }

        $this->pattern = $pattern;
        $this->length = \strlen($this->pattern);
        $this->resetState();

        /** @var array<Token> $tokens */
        $tokens = [];

        while ($this->position < $this->length) {
            if ($this->handleTunnelModes($tokens)) {
                continue;
            }

            [$regex, $tokenMap] = $this->getCurrentContext();
            [$matchedValue, $startPos, $matches] = $this->matchAtPosition($regex);
            $tokens[] = $this->createToken($tokenMap, $matches, $matchedValue, $startPos, $tokens);
        }

        $this->validateFinalState();
        $tokens[] = new Token(TokenType::T_EOF, '', $this->position);

        return new TokenStream($tokens, $pattern);
    }

    private function getRegexOutside(): string
    {
        return self::$regexOutside ??= $this->compilePattern(self::PATTERNS_OUTSIDE);
    }

    private function getRegexInside(): string
    {
        return self::$regexInside ??= $this->compilePattern(self::PATTERNS_INSIDE);
    }

    /**
     * Compile patterns into an optimized regex with named groups.
     *
     * @param array<string, string> $patterns
     */
    private function compilePattern(array $patterns): string
    {
        $regexParts = [];
        foreach ($patterns as $name => $pattern) {
            $regexParts[] = "(?<{$name}> {$pattern} )";
        }

        return '/(?:'.implode('|', $regexParts).')/xsuA';
    }

    private function resetState(): void
    {
        $this->position = 0;
        $this->inCharClass = false;
        $this->inQuoteMode = false;
        $this->inCommentMode = false;
        $this->charClassStartPosition = 0;
    }

    /**
     * @param array<Token> $tokens
     */
    private function handleTunnelModes(array &$tokens): bool
    {
        if ($this->inQuoteMode) {
            if ($token = $this->consumeQuoteMode()) {
                $tokens[] = $token;
            }

            return true;
        }

        if ($this->inCommentMode) {
            if ($token = $this->consumeCommentMode()) {
                $tokens[] = $token;
            }

            return true;
        }

        return false;
    }

    /**
     * @return array{0: string, 1: array<string>}
     */
    private function getCurrentContext(): array
    {
        if ($this->inCharClass) {
            return [$this->getRegexInside(), self::TOKENS_INSIDE];
        }

        return [$this->getRegexOutside(), self::TOKENS_OUTSIDE];
    }

    /**
     * @return array{0: string, 1: int, 2: array<int|string, string|null>}
     */
    private function matchAtPosition(string $regex): array
    {
        $result = preg_match($regex, $this->pattern, $matches, \PREG_UNMATCHED_AS_NULL, $this->position);

        if (false === $result) {
            throw LexerException::withContext(
                \sprintf('PCRE Error during tokenization: %s', preg_last_error_msg()),
                $this->position,
                $this->pattern,
            );
        }

        if (0 === $result) {
            $context = substr($this->pattern, $this->position, 10);

            throw LexerException::withContext(
                \sprintf('Unable to tokenize pattern at position %d. Context: "%s..."', $this->position, $context),
                $this->position,
                $this->pattern,
            );
        }

        $matchedValue = (string) $matches[0];
        $startPos = $this->position;
        $this->position += \strlen($matchedValue);

        return [$matchedValue, $startPos, $matches];
    }

    /**
     * @param array<string>                  $tokenMap
     * @param array<int|string, string|null> $matches
     * @param array<Token>                   $currentTokens
     */
    private function createToken(
        array $tokenMap,
        array $matches,
        string $matchedValue,
        int $startPos,
        array $currentTokens
    ): Token {
        foreach ($tokenMap as $tokenName) {
            /** @var string $tokenName */
            if (!isset($matches[$tokenName])) {
                continue;
            }

            $type = TokenType::from(strtolower(substr($tokenName, 2)));

            if ($token = $this->handleStatefulToken($type, $matchedValue, $startPos, $currentTokens)) {
                return $token;
            }

            $value = $this->extractTokenValue($type, $matchedValue, $matches);

            return new Token($type, $value, $startPos);
        }

        throw LexerException::withContext(
            \sprintf('Lexer internal error: No known token matched at position %d.', $startPos),
            $startPos,
            $this->pattern,
        );
    }

    /**
     * @param array<Token> $currentTokens
     */
    private function handleStatefulToken(
        TokenType $type,
        string $matchedValue,
        int $startPos,
        array $currentTokens
    ): ?Token {
        return match ($type) {
            TokenType::T_CHAR_CLASS_OPEN => $this->openCharClass($startPos),
            TokenType::T_CHAR_CLASS_CLOSE => $this->closeCharClass($startPos, $currentTokens),
            TokenType::T_COMMENT_OPEN => $this->openComment($startPos),
            TokenType::T_QUOTE_MODE_START => $this->openQuoteMode($startPos),
            default => $this->handleContextualLiteral($type, $matchedValue, $startPos, $currentTokens),
        };
    }

    private function openCharClass(int $startPos): Token
    {
        $this->inCharClass = true;
        $this->charClassStartPosition = $startPos;

        return new Token(TokenType::T_CHAR_CLASS_OPEN, '[', $startPos);
    }

    /**
     * @param array<Token> $currentTokens
     */
    private function closeCharClass(int $startPos, array $currentTokens): Token
    {
        if ($this->isAtCharClassStart($startPos, $currentTokens)) {
            return new Token(TokenType::T_LITERAL, ']', $startPos);
        }

        $this->inCharClass = false;

        return new Token(TokenType::T_CHAR_CLASS_CLOSE, ']', $startPos);
    }

    private function openComment(int $startPos): Token
    {
        $this->inCommentMode = true;

        return new Token(TokenType::T_COMMENT_OPEN, '(?#', $startPos);
    }

    private function openQuoteMode(int $startPos): Token
    {
        $this->inQuoteMode = true;

        return new Token(TokenType::T_QUOTE_MODE_START, '\Q', $startPos);
    }

    /**
     * @param array<Token> $currentTokens
     */
    private function handleContextualLiteral(
        TokenType $type,
        string $matchedValue,
        int $startPos,
        array $currentTokens
    ): ?Token {
        if (!$this->inCharClass || TokenType::T_LITERAL !== $type) {
            return null;
        }

        if ($this->isAtCharClassStart($startPos, $currentTokens) && '^' === $matchedValue) {
            return new Token(TokenType::T_NEGATION, '^', $startPos);
        }

        if (!$this->isAtCharClassStart($startPos, $currentTokens) && '-' === $matchedValue) {
            return new Token(TokenType::T_RANGE, '-', $startPos);
        }

        return null;
    }

    /**
     * @param array<Token> $currentTokens
     */
    private function isAtCharClassStart(int $startPos, array $currentTokens): bool
    {
        $lastToken = end($currentTokens);

        return ($startPos === $this->charClassStartPosition + 1)
            || ($startPos === $this->charClassStartPosition + 2
                && $lastToken instanceof Token
                && TokenType::T_NEGATION === $lastToken->type);
    }

    private function consumeQuoteMode(): ?Token
    {
        if (!preg_match('/(.*?)((\\\\E|$))/suA', $this->pattern, $matches, \PREG_UNMATCHED_AS_NULL, $this->position)) {
            $this->inQuoteMode = false;
            $this->position = $this->length;

            return null;
        }

        $literalText = $matches[1];
        $endSequence = $matches[2];
        $startPos = $this->position;

        if ('' !== $literalText) {
            $this->position += \strlen($literalText);

            return new Token(TokenType::T_LITERAL, $literalText, $startPos);
        }

        if ('\E' === $endSequence) {
            $this->inQuoteMode = false;
            $token = new Token(TokenType::T_QUOTE_MODE_END, '\E', $this->position);
            $this->position += 2;

            return $token;
        }

        $this->position = $this->length;

        return null;
    }

    private function consumeCommentMode(): ?Token
    {
        if (!preg_match('/([^)]*)(\)|$)/uA', $this->pattern, $matches, \PREG_UNMATCHED_AS_NULL, $this->position)) {
            $this->inCommentMode = false;
            $this->position = $this->length;

            return null;
        }

        $commentText = $matches[1];
        $endSequence = $matches[2];
        $startPos = $this->position;

        if ('' !== $commentText) {
            $this->position += \strlen($commentText);

            return new Token(TokenType::T_LITERAL, $commentText, $startPos);
        }

        if (')' === $endSequence) {
            $this->inCommentMode = false;
            $token = new Token(TokenType::T_GROUP_CLOSE, ')', $this->position);
            $this->position++;

            return $token;
        }

        $this->position = $this->length;

        return null;
    }

    /**
     * @param array<int|string, string|null> $matches
     */
    private function extractTokenValue(TokenType $type, string $matchedValue, array $matches): string
    {
        return match ($type) {
            TokenType::T_LITERAL_ESCAPED => match (substr($matchedValue, 1)) {
                't' => "\t", 'n' => "\n", 'r' => "\r", 'f' => "\f", 'v' => "\v",
                'e' => "\x1B", 'a' => "\x07", default => substr($matchedValue, 1),
            },
            TokenType::T_PCRE_VERB => substr($matchedValue, 2, -1),
            TokenType::T_CALLOUT => substr($matchedValue, 3, -1),
            TokenType::T_ASSERTION, TokenType::T_CHAR_TYPE, TokenType::T_KEEP => substr($matchedValue, 1),
            TokenType::T_BACKREF => $matchedValue,
            TokenType::T_OCTAL_LEGACY => substr($matchedValue, 1),
            TokenType::T_POSIX_CLASS => $matches['v_posix'] ?? '',
            TokenType::T_UNICODE => $this->parseUnicodeEscape($matchedValue),
            TokenType::T_UNICODE_PROP => $this->normalizeUnicodeProp($matchedValue, $matches),
            TokenType::T_UNICODE_NAMED => substr($matchedValue, 3, -1),
            TokenType::T_CONTROL_CHAR => substr($matchedValue, 2),
            TokenType::T_CLASS_INTERSECTION => '&&',
            TokenType::T_CLASS_SUBTRACTION => '--',
            default => $matchedValue,
        };
    }

    private function parseUnicodeEscape(string $escape): string
    {
        if (preg_match('/^\\\\x([0-9a-fA-F]{1,2})$/', $escape, $m)) {
            return \chr((int) hexdec($m[1]));
        }
        if (preg_match('/^\\\\x\\{([0-9a-fA-F]+)\\}$/', $escape, $m)) {
            return \chr((int) hexdec($m[1]));
        }
        if (preg_match('/^\\\\u\\{([0-9a-fA-F]+)\\}$/', $escape, $m)) {
            return \chr((int) hexdec($m[1]));
        }

        // fallback
        return $escape;
    }

    /**
     * @param array<int|string, string|null> $matches
     */
    private function normalizeUnicodeProp(string $matchedValue, array $matches): string
    {
        $prop = (string) ($matches['v1_prop'] ?? $matches['v2_prop'] ?? '');
        $isNegated = str_starts_with($matchedValue, '\\P');

        if (!$isNegated) {
            return $prop;
        }

        return str_starts_with($prop, '^') ? substr($prop, 1) : '^'.$prop;
    }

    private function validateFinalState(): void
    {
        if ($this->inCharClass) {
            throw LexerException::withContext(
                'Unclosed character class "]" at end of input.',
                $this->position,
                $this->pattern,
            );
        }

        if ($this->inCommentMode) {
            throw LexerException::withContext(
                'Unclosed comment ")" at end of input.',
                $this->position,
                $this->pattern,
            );
        }
    }
}
