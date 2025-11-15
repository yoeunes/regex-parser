<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Lexer;

use RegexParser\Exception\LexerException;

/**
 * The Lexer (or Tokenizer).
 * Its job is to split the input regex string into a stream of Tokens.
 * It handles single characters, basic escape sequences, and is UTF-8 safe.
 * It manages state to handle different tokenization rules inside character classes.
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
     * @throws LexerException If the input is not valid UTF-8
     */
    public function __construct(private readonly string $input)
    {
        if (!mb_check_encoding($this->input, 'UTF-8')) {
            throw new LexerException('Input string is not valid UTF-8.');
        }

        // Split the string into an array of UTF-8 characters.
        $this->characters = preg_split('//u', $this->input, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $this->length = \count($this->characters);
    }

    /**
     * Tokenizes the input string.
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
                $isEscaped = false;
                // Check for known character types (e.g., \d, \s, \w)
                if (preg_match('/^[dswDSW]$/u', $char)) {
                    $tokens[] = new Token(TokenType::T_CHAR_TYPE, $char, $this->position - 1); // Position of the \
                    ++$this->position;
                } else {
                    // It's an escaped meta-character (e.g., \*, \+, \()
                    // or a literal (e.g., \a, \b, \-)
                    $tokens[] = new Token(TokenType::T_LITERAL, $char, $this->position++);
                }
                continue;
            }

            if ('\\' === $char) {
                $isEscaped = true;
                ++$this->position;
                continue;
            }

            // If we are inside a character class, rules are different.
            if ($this->inCharClass) {
                $tokens[] = $this->tokenizeCharClassToken($char);
                continue;
            }

            // Default tokenization (outside character class)
            if ('(' === $char) {
                $tokens[] = new Token(TokenType::T_GROUP_OPEN, '(', $this->position++);
            } elseif (')' === $char) {
                $tokens[] = new Token(TokenType::T_GROUP_CLOSE, ')', $this->position++);
            } elseif ('[' === $char) {
                $this->inCharClass = true; // Enter char class mode
                $tokens[] = new Token(TokenType::T_CHAR_CLASS_OPEN, '[', $this->position++);
            } elseif (\in_array($char, ['*', '+', '?'], true)) {
                $tokens[] = new Token(TokenType::T_QUANTIFIER, $char, $this->position++);
            } elseif ('{' === $char) {
                $tokens[] = $this->consumeBraceQuantifier();
            } elseif ('|' === $char) {
                $tokens[] = new Token(TokenType::T_ALTERNATION, '|', $this->position++);
            } elseif ('/' === $char) {
                $tokens[] = new Token(TokenType::T_DELIMITER, '/', $this->position++);
            } elseif ('.' === $char) {
                $tokens[] = new Token(TokenType::T_DOT, '.', $this->position++);
            } elseif ('^' === $char || '$' === $char) {
                $tokens[] = new Token(TokenType::T_ANCHOR, $char, $this->position++);
            } else {
                // ']' is a literal if not in char class, same for '-'
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
     * Tokenizes a single character while inside a character class.
     */
    private function tokenizeCharClassToken(string $char): Token
    {
        // Inside a class, most meta-characters are literals
        if (']' === $char) {
            $this->inCharClass = false; // Exit char class mode

            return new Token(TokenType::T_CHAR_CLASS_CLOSE, ']', $this->position++);
        }

        // Negation ^ (only if it's the *first* char after [)
        if ('^' === $char && isset($this->characters[$this->position - 1]) && '[' === $this->characters[$this->position - 1]) {
            return new Token(TokenType::T_NEGATION, '^', $this->position++);
        }

        // Range - (if not first or last char)
        $isFirstChar = isset($this->characters[$this->position - 1]) && '[' === $this->characters[$this->position - 1];
        $isFirstCharAfterNegation = isset($this->characters[$this->position - 1], $this->characters[$this->position - 2])
            && '^' === $this->characters[$this->position - 1]
            && '[' === $this->characters[$this->position - 2];
        $isAtStart = $isFirstChar || $isFirstCharAfterNegation;

        $isAtEnd = ($this->position + 1 < $this->length) && ']' === $this->characters[$this->position + 1];

        if ('-' === $char && !$isAtStart && !$isAtEnd) {
            return new Token(TokenType::T_RANGE, '-', $this->position++);
        }

        // All other characters (including *, +, ?, ., |) are literals
        return new Token(TokenType::T_LITERAL, $char, $this->position++);
    }

    /**
     * Consumes a brace-style quantifier like {n,m}.
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

        if (!preg_match('/^{\d+(,\d*)?}$/', $quant)) {
            throw new LexerException(\sprintf('Invalid quantifier syntax "%s" at position %d', $quant, $start));
        }

        return new Token(TokenType::T_QUANTIFIER, $quant, $start);
    }

    /**
     * Consumes characters from input as long as the predicate is true.
     */
    private function consumeWhile(callable $predicate): string
    {
        $value = '';
        while ($this->position < $this->length && $predicate($this->characters[$this->position])) {
            $value .= $this->characters[$this->position++];
        }

        return $value;
    }
}
