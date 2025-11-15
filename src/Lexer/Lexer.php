<?php

namespace RegexParser\Lexer;

use RegexParser\Exception\LexerException;

class Lexer
{
    private string $input;
    private int $position = 0;
    private int $length;

    public function __construct(string $input)
    {
        $this->input = $input;
        $this->length = \strlen($input);
    }

    /**
     * @return array<Token>
     */
    public function tokenize(): array
    {
        $tokens = [];
        while ($this->position < $this->length) {
            $char = $this->input[$this->position];
            if (ctype_alnum($char) || \in_array($char, ['.', '-', '_'], true)) { // Étendu pour literals communs en regex
                $tokens[] = new Token(TokenType::T_LITERAL, $this->consumeWhile(fn ($c) => !\in_array($c, ['(', ')', '*', '+', '?', '|', '{', '}', '[', ']', '/'], true)), $this->position);
            } elseif ('(' === $char) {
                $tokens[] = new Token(TokenType::T_GROUP_OPEN, '(', $this->position++);
            } elseif (')' === $char) {
                $tokens[] = new Token(TokenType::T_GROUP_CLOSE, ')', $this->position++);
            } elseif (\in_array($char, ['*', '+', '?'], true)) {
                $tokens[] = new Token(TokenType::T_QUANTIFIER, $char, $this->position++);
            } elseif ('{' === $char) {
                // Gérer {n,m}
                $quant = $this->consumeWhile(fn ($c) => ctype_digit($c) || ',' === $c || '}' === $c);
                if (!str_ends_with($quant, '}')) {
                    throw new LexerException('Unclosed quantifier at '.$this->position);
                }
                $tokens[] = new Token(TokenType::T_QUANTIFIER, $quant, $this->position);
                $this->position += \strlen($quant) - 1; // Avance après }
            } elseif ('|' === $char) {
                $tokens[] = new Token(TokenType::T_ALTERNATION, '|', $this->position++);
            } elseif ('/' === $char) {
                $tokens[] = new Token(TokenType::T_DELIMITER, '/', $this->position++);
            } elseif (ctype_space($char)) {
                ++$this->position; // Skip whitespace
            } else {
                throw new LexerException("Unexpected char '$char' at position {$this->position}");
            }
        }
        $tokens[] = new Token(TokenType::T_EOF, '', $this->position);

        return $tokens;
    }

    private function consumeWhile(callable $predicate): string
    {
        $value = '';
        while ($this->position < $this->length && $predicate($this->input[$this->position])) {
            $value .= $this->input[$this->position++];
        }

        return $value;
    }
}
