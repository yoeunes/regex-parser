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
 */
class Lexer
{
    private int $position = 0;
    private readonly int $length;
    /** @var array<string> */
    private readonly array $characters;

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
                    // or a literal (e.g., \a, \b)
                    $tokens[] = new Token(TokenType::T_LITERAL, $char, $this->position++);
                }
                continue;
            }

            if ('\\' === $char) {
                $isEscaped = true;
                ++$this->position;
                continue;
            }

            if ('(' === $char) {
                $tokens[] = new Token(TokenType::T_GROUP_OPEN, '(', $this->position++);
            } elseif (')' === $char) {
                $tokens[] = new Token(TokenType::T_GROUP_CLOSE, ')', $this->position++);
            } elseif ('[' === $char) {
                $tokens[] = new Token(TokenType::T_CHAR_CLASS_OPEN, '[', $this->position++);
            } elseif (']' === $char) {
                $tokens[] = new Token(TokenType::T_CHAR_CLASS_CLOSE, ']', $this->position++);
            } elseif (\in_array($char, ['*', '+', '?'], true)) {
                $tokens[] = new Token(TokenType::T_QUANTIFIER, $char, $this->position++);
            } elseif ('{' === $char) {
                $tokens[] = $this->consumeBraceQuantifier();
            } elseif ('|' === $char) {
                $tokens[] = new Token(TokenType::T_ALTERNATION, '|', $this->position++);
            } elseif ('/' === $char) {
                // This assumes / is the delimiter, a more robust parser
                // would treat the *first* char as the delimiter.
                $tokens[] = new Token(TokenType::T_DELIMITER, '/', $this->position++);
            } elseif ('.' === $char) {
                $tokens[] = new Token(TokenType::T_DOT, '.', $this->position++);
            } elseif ('^' === $char || '$' === $char) {
                $tokens[] = new Token(TokenType::T_ANCHOR, $char, $this->position++);
            } else {
                // Everything else is a T_LITERAL
                $tokens[] = new Token(TokenType::T_LITERAL, $char, $this->position++);
            }
        }

        if ($isEscaped) {
            throw new LexerException('Trailing backslash at position '.($this->position - 1));
        }

        $tokens[] = new Token(TokenType::T_EOF, '', $this->position);

        return $tokens;
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
            // This is a more robust approach than throwing an exception.
            $this->position = $start + 1;

            return new Token(TokenType::T_LITERAL, '{', $start);
        }

        $quant .= '}'; // Consume '}'
        ++$this->position;

        // Validate the inner part (e.g., {1,3}, {2,}, {5})
        if (!preg_match('/^{\d+(,\d*)?}$/', $quant)) {
            // Invalid content like {,} or {1,2,3}
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
