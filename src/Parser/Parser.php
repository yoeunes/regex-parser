<?php

namespace RegexParser\Parser;

use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\NodeInterface;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Exception\ParserException;
use RegexParser\Lexer\Lexer;
use RegexParser\Lexer\Token;
use RegexParser\Lexer\TokenType;

class Parser
{
    /** @var array<Token> */
    private array $tokens;
    private int $position = 0;

    public function parse(string $regex): NodeInterface
    {
        $lexer = new Lexer($regex);
        $this->tokens = $lexer->tokenize();
        $node = $this->parseExpression();
        $this->consume(TokenType::T_EOF, 'Expected end of input');

        return $node;
    }

    private function parseExpression(): NodeInterface
    {
        $node = $this->parsePrimary();
        while ($this->match(TokenType::T_QUANTIFIER)) {
            $quantifier = $this->previous()->value;
            $node = new QuantifierNode($node, $quantifier);
        }

        return $node;
    }

    private function parsePrimary(): NodeInterface
    {
        if ($this->match(TokenType::T_LITERAL)) {
            return new LiteralNode($this->previous()->value);
        } elseif ($this->match(TokenType::T_GROUP_OPEN)) {
            $expr = $this->parseExpression();
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode([$expr]);
        }
        throw new ParserException('Unexpected token at position '.$this->current()->position);
    }

    private function match(TokenType $type): bool
    {
        if ($this->check($type)) {
            $this->advance();

            return true;
        }

        return false;
    }

    private function consume(TokenType $type, string $error): void
    {
        if ($this->check($type)) {
            $this->advance();

            return;
        }
        throw new ParserException($error.' at position '.$this->current()->position);
    }

    private function check(TokenType $type): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }

        return $this->current()->type === $type;
    }

    private function advance(): void
    {
        if (!$this->isAtEnd()) {
            ++$this->position;
        }
    }

    private function isAtEnd(): bool
    {
        return TokenType::T_EOF === $this->current()->type;
    }

    private function current(): Token
    {
        return $this->tokens[$this->position];
    }

    private function previous(): Token
    {
        return $this->tokens[$this->position - 1];
    }
}
