<?php

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
 * The Lexer (or Tokenizer).
 * Its job is to split the regex *pattern* string into a stream of Tokens.
 * It no longer handles delimiters; that is the Parser's job.
 */
class Lexer
{
    private int $position = 0;
    private readonly int $length;
    /** @var array<string> */
    private readonly array $characters;

    /**
     * @var bool Tracks if the lexer is currently inside a character class `[...]`.
     */
    private bool $inCharClass = false;

    /**
     * @param string $pattern The regex pattern (without delimiters or flags)
     *
     * @throws LexerException If the input is not valid UTF-8
     */
    public function __construct(private readonly string $pattern)
    {
        if (!mb_check_encoding($this->pattern, 'UTF-8')) {
            throw new LexerException('Input string is not valid UTF-8.');
        }

        // Split the string into an array of UTF-8 characters.
        $this->characters = mb_str_split($this->pattern);
        $this->length = \count($this->characters);
    }

    /**
     * Tokenizes the input pattern string.
     *
     * @return array<Token>
     *
     * @throws LexerException if an invalid character or structure is found
     */
    public function tokenize(): array
    {
        $tokens = [];
        $isEscaped = false;
        while ($this->position < $this->length) {
            $char = $this->characters[$this->position];
            if ($isEscaped) {
                $tokens[] = $this->tokenizeEscaped($char);
                $isEscaped = false;
                continue;
            }
            if ('\\' === $char) {
                $isEscaped = true;
                ++$this->position;
                continue;
            }
            if ($this->inCharClass) {
                $tokens[] = $this->tokenizeCharClassToken($char);
                continue;
            }
            // Default tokenization (outside character class)
            if ('(' === $char) {
                $tokens[] = $this->consumeGroupOpen();
            } elseif (')' === $char) {
                $tokens[] = new Token(TokenType::T_GROUP_CLOSE, ')', $this->position++);
            } elseif ('[' === $char) {
                $this->inCharClass = true; // Enter char class mode
                $tokens[] = new Token(TokenType::T_CHAR_CLASS_OPEN, '[', $this->position++);
            } elseif (\in_array($char, ['*', '+', '?'], true)) {
                $tokens[] = $this->consumeSimpleQuantifier($char);
            } elseif ('{' === $char) {
                $tokens[] = $this->consumeBraceQuantifier();
            } elseif ('|' === $char) {
                $tokens[] = new Token(TokenType::T_ALTERNATION, '|', $this->position++);
            } elseif ('.' === $char) {
                $tokens[] = new Token(TokenType::T_DOT, '.', $this->position++);
            } elseif ('^' === $char || '$' === $char) {
                $tokens[] = new Token(TokenType::T_ANCHOR, $char, $this->position++);
            } else {
                // ']', ':', '=', '!', '<', '>', 'P' are all literals here
                $tokens[] = new Token(TokenType::T_LITERAL, $char, $this->position++);
            }
        }
        if ($isEscaped) {
            throw new LexerException('Trailing backslash at position '.($this->position - 1));
        }
        if ($this->inCharClass) {
            throw new LexerException('Unclosed character class "]" at end of input.');
        }
        $tokens[] = new Token(TokenType::T_EOF, '', $this->position);

        return $tokens;
    }

    /**
     * Handles escaped characters, including char types, backrefs, Unicode.
     */
    private function tokenizeEscaped(string $char): Token
    {
        $start = $this->position - 1; // Position of \
        // Assertions \A \z \Z \G \b \B
        if (preg_match('/^[AzZGbB]$/', $char)) {
            ++$this->position;

            return new Token(TokenType::T_ASSERTION, $char, $start);
        }
        // Char types \d \s etc.
        if (preg_match('/^[dswDSW]$/u', $char)) {
            ++$this->position;

            return new Token(TokenType::T_CHAR_TYPE, $char, $start);
        }
        // Backrefs \1 - \9, \10+
        if (preg_match('/^[1-9]$/', $char)) {
            $ref = $this->consumeWhile(fn (string $c) => ctype_digit($c));
            ++$this->position; // For the initial digit

            return new Token(TokenType::T_BACKREF, $char.$ref, $start);
        }
        // Named backrefs \k<name> or \k{name}
        if ('k' === $char) {
            ++$this->position; // 'k'
            $open = $this->peek();
            if ('<' === $open || '{' === $open) {
                ++$this->position; // < or {
                $name = $this->consumeWhile(fn (string $c) => preg_match('/^[a-zA-Z0-9_]$/', $c));
                $close = $this->peek();
                if (('<' === $open && '>' !== $close) || ('{' === $open && '}' !== $close)) {
                    throw new LexerException('Unclosed named backref at position '.$start);
                }
                ++$this->position; // > or }

                return new Token(TokenType::T_BACKREF, '\k'.$open.$name.$close, $start);
            }

            // Fallthrough to literal if not named
            return new Token(TokenType::T_LITERAL, $char, $start);
        }
        // Unicode \xHH, \u{HHHH}
        if ('x' === $char) {
            ++$this->position; // Consume 'x'
            $hex = $this->consumeHex(2);

            return new Token(TokenType::T_UNICODE, '\x'.$hex, $start);
        }
        if ('u' === $char) {
            ++$this->position; // 'u'
            if ('{' === $this->peek()) {
                ++$this->position; // '{'
                $hex = $this->consumeHex(1, 6); // Up to 6 hex digits
                if ('}' !== $this->peek()) {
                    throw new LexerException('Unclosed Unicode escape at position '.$start);
                }
                ++$this->position; // '}'

                return new Token(TokenType::T_UNICODE, '\u{'.$hex.'}', $start);
            }
            // If not \u{...}, fallthrough to literal
        }
        // Octal \o{777}
        if ('o' === $char) {
            ++$this->position; // 'o'
            if ('{' === $this->peek()) {
                ++$this->position; // '{'
                $oct = $this->consumeWhile(fn (string $c) => preg_match('/^[0-7]$/', $c), 1, 11); // Up to 11 octal digits for \o
                if ('}' !== $this->peek()) {
                    throw new LexerException('Unclosed octal escape at position '.$start);
                }
                ++$this->position; // '}'

                return new Token(TokenType::T_OCTAL, '\o{'.$oct.'}', $start);
            }
            // If not \o{...}, fallthrough to literal
        }
        // Unicode properties \p{L}, \P{^L}
        if ('p' === $char || 'P' === $char) {
            ++$this->position; // p or P
            $neg = 'P' === $char ? '^' : '';
            if ('{' === $this->peek()) { // Check char *after* p/P
                ++$this->position; // '{'
                $prop = $this->consumeWhile(fn (string $c) => preg_match('/^[a-zA-Z0-9_]+$/', $c));
                if ('^' === $this->peek()) {
                    $neg = '^';
                    ++$this->position;
                    $prop .= $this->consumeWhile(fn (string $c) => preg_match('/^[a-zA-Z0-9_]+$/', $c));
                }
                if ('}' !== $this->peek()) {
                    throw new LexerException('Unclosed Unicode property at position '.$start);
                }
                ++$this->position; // '}'

                return new Token(TokenType::T_UNICODE_PROP, $neg.$prop, $start);
            }
            // Single-char prop \pL, but PCRE requires {} for multi
            if (preg_match('/^[a-zA-Z]$/', (string) $this->peek())) { // Check char *after* p/P
                $prop = $this->characters[$this->position]; // Get 'L'
                ++$this->position; // Consume 'L'

                return new Token(TokenType::T_UNICODE_PROP, $neg.$prop, $start);
            }
            throw new LexerException('Invalid Unicode property at position '.$start);
        }
        // Default: escaped literal or meta
        ++$this->position;

        return new Token(TokenType::T_LITERAL, $char, $start);
    }

    /**
     * Consumes hex digits (for \x, \u).
     */
    private function consumeHex(int $min, ?int $max = null): string
    {
        $max ??= $min;
        $hex = '';
        $count = 0;
        while ($this->position < $this->length && preg_match('/^[0-9a-fA-F]$/i', $this->characters[$this->position]) && $count < $max) {
            $hex .= $this->characters[$this->position++];
            ++$count;
        }
        if ($count < $min) {
            throw new LexerException('Incomplete hex escape, expected at least '.$min.' digits.');
        }

        return $hex;
    }

    /**
     * Tokenizes a single character while inside a character class.
     */
    private function tokenizeCharClassToken(string $char): Token
    {
        // Check for first char (or first after negation)
        $isFirstChar = isset($this->characters[$this->position - 1]) && '[' === $this->characters[$this->position - 1];
        $isFirstCharAfterNegation = isset($this->characters[$this->position - 1], $this->characters[$this->position - 2])
            && '^' === $this->characters[$this->position - 1]
            && '[' === $this->characters[$this->position - 2];
        $isAtStart = $isFirstChar || $isFirstCharAfterNegation;

        // ']' is a literal if it's the first character
        if (']' === $char && $isAtStart) {
            return new Token(TokenType::T_LITERAL, ']', $this->position++);
        }

        // Inside a class, most meta-characters are literals
        if (']' === $char) { // Now it's the closing bracket
            $this->inCharClass = false; // Exit char class mode

            return new Token(TokenType::T_CHAR_CLASS_CLOSE, ']', $this->position++);
        }

        // POSIX [[:alpha:]]
        if (':' === $char && '[' === $this->peek(-1)) {
            $posix = $this->consumeWhile(fn (string $c) => preg_match('/^[a-zA-Z^]$/', $c));
            if (':' !== $this->peek() || ']' !== $this->peek(1)) {
                throw new LexerException('Invalid POSIX class at position '.$this->position);
            }
            $this->position += 2; // : ]

            return new Token(TokenType::T_POSIX_CLASS, $posix, $this->position - \strlen($posix) - 4); // Adjust pos
        }

        // Negation ^ (only if it's the *first* char after [)
        if ('^' === $char && $isFirstChar) {
            return new Token(TokenType::T_NEGATION, '^', $this->position++);
        }

        // Range - (if not first or last char)
        $isAtEnd = ($this->position + 1 < $this->length) && ']' === $this->characters[$this->position + 1];

        if ('-' === $char && !$isAtStart && !$isAtEnd) {
            return new Token(TokenType::T_RANGE, '-', $this->position++);
        }

        // All other characters (including *, +, ?, ., |) are literals
        return new Token(TokenType::T_LITERAL, $char, $this->position++);
    }

    private function peek(int $offset = 0): ?string
    {
        $pos = $this->position + $offset;

        return $pos < $this->length ? $this->characters[$pos] : null;
    }

    /**
     * Consumes a brace-style quantifier like {n,m} and its lazy/possessive modifier.
     */
    private function consumeBraceQuantifier(): Token
    {
        $start = $this->position;
        $quant = $this->characters[$this->position++]; // Consume '{'
        $inner = $this->consumeWhile(fn (string $c) => 1 === preg_match('/^[0-9,]$/', $c));
        $quant .= $inner;

        if ($this->position >= $this->length || '}' !== $this->characters[$this->position]) {
            // Rewind and treat as literal '{'
            $this->position = $start + 1;

            return new Token(TokenType::T_LITERAL, '{', $start);
        }

        $quant .= '}'; // Consume '}'
        ++$this->position;

        // Consume optional lazy/possessive modifier
        $quant .= $this->consumeQuantifierModifier();

        if (!preg_match('/^{\d+(,\d*)?}(\?|\+)?$/', $quant)) {
            throw new LexerException(\sprintf('Invalid quantifier syntax "%s" at position %d', $quant, $start));
        }

        return new Token(TokenType::T_QUANTIFIER, $quant, $start);
    }

    /**
     * Consumes a simple quantifier (*, +, ?) and its lazy/possessive modifier.
     */
    private function consumeSimpleQuantifier(string $char): Token
    {
        $start = $this->position;
        $quant = $char; // *, +, or ?
        ++$this->position;

        // Consume optional lazy/possessive modifier
        $quant .= $this->consumeQuantifierModifier();

        return new Token(TokenType::T_QUANTIFIER, $quant, $start);
    }

    /**
     * Consumes an optional '?' (lazy) or '+' (possessive) modifier.
     */
    private function consumeQuantifierModifier(): string
    {
        if ($this->position < $this->length) {
            $modifier = $this->characters[$this->position];
            if ('?' === $modifier || '+' === $modifier) {
                ++$this->position;

                return $modifier;
            }
        }

        return '';
    }

    /**
     * Consumes a group opening, checking for modifiers (e.g., "(?", "(?<").
     */
    private function consumeGroupOpen(): Token
    {
        $start = $this->position;
        if ($this->position + 1 < $this->length && '?' === $this->characters[$this->position + 1]) {
            // It's a special group, e.g., "(?:", "(?=", "(?<name>", "(?#comment)"
            $this->position += 2; // Consume "(?"

            // Peek next for comment (?#)
            if ('#' === $this->peek()) {
                ++$this->position; // Consume '#'

                return new Token(TokenType::T_COMMENT_OPEN, '(?#', $start);
            }

            return new Token(TokenType::T_GROUP_MODIFIER_OPEN, '(?', $start);
        }

        // It's a simple capturing group
        ++$this->position; // Consume "("

        return new Token(TokenType::T_GROUP_OPEN, '(', $start);
    }

    /**
     * Consumes characters from input as long as the predicate is true.
     */
    private function consumeWhile(callable $predicate, int $min = 0, ?int $max = null): string
    {
        $value = '';
        $count = 0;
        $max ??= \PHP_INT_MAX;
        while ($this->position < $this->length && $predicate($this->characters[$this->position]) && $count < $max) {
            $value .= $this->characters[$this->position++];
            ++$count;
        }
        if ($count < $min) {
            throw new LexerException('Incomplete consumeWhile, expected at least '.$min.' characters.');
        }

        return $value;
    }
}
