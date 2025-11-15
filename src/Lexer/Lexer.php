<?php

namespace RegexParser\Lexer;

use RegexParser\Exception\LexerException;

/**
 * The Lexer (or Tokenizer).
 * Its job is to split the input regex string into a stream of Tokens.
 * It handles single characters and basic escape sequences.
 */
class Lexer
{
    private int $position = 0;
    private readonly int $length;

    public function __construct(private string $input)
    {
        $this->length = \strlen($this->input);
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
            $char = $this->input[$this->position];

            if ($isEscaped) {
                // If the previous char was \, treat this char
                // as a literal, no matter what it is.
                $tokens[] = new Token(TokenType::T_LITERAL, $char, $this->position++);
                $isEscaped = false;
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
                // Handling {n,m} is a pragmatic lookahead.
                // It's correct to keep it grouped as one token.
                $tokens[] = $this->consumeBraceQuantifier();
            } elseif ('|' === $char) {
                $tokens[] = new Token(TokenType::T_ALTERNATION, '|', $this->position++);
            } elseif ('/' === $char) {
                $tokens[] = new Token(TokenType::T_DELIMITER, '/', $this->position++);
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
        ++$this->position; // Skip '{'
        $inner = $this->consumeWhile(fn (string $c) => ctype_digit($c) || ',' === $c);

        if ($this->position >= $this->length || '}' !== $this->input[$this->position]) {
            // This might be a literal '{'.
            // For now, we throw an exception for simplicity.
            // An advanced version would "rewind" and emit T_LITERAL for '{'
            throw new LexerException('Unclosed quantifier or invalid content at '.$start);
        }

        $quant = '{'.$inner.'}';
        ++$this->position; // Skip '}'

        return new Token(TokenType::T_QUANTIFIER, $quant, $start);
    }

    /**
     * Consumes characters from input as long as the predicate is true.
     */
    private function consumeWhile(callable $predicate): string
    {
        $value = '';
        while ($this->position < $this->length && $predicate($this->input[$this->position])) {
            $value .= $this->input[$this->position++];
        }

        return $value;
    }
}
