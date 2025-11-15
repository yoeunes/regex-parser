<?php

namespace RegexParser\Lexer;

use RegexParser\Exception\LexerException;

class Lexer
{
    private int $position = 0;
    private readonly int $length;

    public function __construct(private string $input)
    {
        $this->length = \strlen($this->input);
    }

    /**
     * @return array<Token>
     */
    public function tokenize(): array
    {
        $tokens = [];
        $isEscaped = false;

        while ($this->position < $this->length) {
            $char = $this->input[$this->position];

            if ($isEscaped) {
                // Si le char précédent était \, on traite ce char
                // comme un littéral, PEU IMPORTE ce que c'est.
                $tokens[] = new Token(TokenType::T_LITERAL, $char, $this->position++);
                $isEscaped = false;
                continue;
            }

            if ('\\' === $char) {
                $isEscaped = true;
                $tokens[] = new Token(TokenType::T_BACKSLASH, $char, $this->position++);
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
                // La gestion du {n,m} est un "lookahead" pragmatique.
                // C'est correct de le garder groupé.
                $tokens[] = $this->consumeBraceQuantifier();
            } elseif ('|' === $char) {
                $tokens[] = new Token(TokenType::T_ALTERNATION, '|', $this->position++);
            } elseif ('/' === $char) {
                $tokens[] = new Token(TokenType::T_DELIMITER, '/', $this->position++);
            } else {
                // Tout le reste est un T_LITERAL
                $tokens[] = new Token(TokenType::T_LITERAL, $char, $this->position++);
            }
        }

        if ($isEscaped) {
            throw new LexerException('Trailing backslash at position '.($this->position - 1));
        }

        $tokens[] = new Token(TokenType::T_EOF, '', $this->position);

        return $tokens;
    }

    private function consumeBraceQuantifier(): Token
    {
        $start = $this->position;
        ++$this->position; // Skip '{'
        $inner = $this->consumeWhile(fn (string $c) => ctype_digit($c) || ',' === $c);

        if ($this->position >= $this->length || '}' !== $this->input[$this->position]) {
            // C'est peut-être un T_LITERAL '{'
            // Pour l'instant, on lève une exception pour la simplicité
            // Une version avancée "reculerait" et émettrait T_LITERAL pour '{'
            throw new LexerException('Unclosed quantifier or invalid content at '.$start);
        }

        $quant = '{'.$inner.'}';
        ++$this->position; // Skip '}'

        return new Token(TokenType::T_QUANTIFIER, $quant, $start);
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
