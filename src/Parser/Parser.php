<?php

namespace RegexParser\Parser;

use RegexParser\Ast\AlternationNode;
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
        $node = $this->parseAlternation();
        if (!$this->isAtEnd()) {
            throw new ParserException('Extra input after expression at position '.$this->current()->position);
        }

        return $node;
    }

    private function parseAlternation(): NodeInterface
    {
        $node = $this->parseExpression();
        $alternatives = [$node];
        while ($this->match(TokenType::T_ALTERNATION)) {
            $alternatives[] = $this->parseExpression();
        }

        return \count($alternatives) > 1 ? new AlternationNode($alternatives) : $node;
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
            $expr = $this->parseAlternation(); // Permet alternations dans groups
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode([$expr]);
        }
        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
        throw new ParserException('Unexpected token at '.$at);
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
        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
        throw new ParserException($error.' at '.$at);
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
        ++$this->position;
    }

    private function isAtEnd(): bool
    {
        return $this->position >= \count($this->tokens);
    }

    private function current(): Token
    {
        if ($this->isAtEnd()) {
            throw new ParserException('Unexpected end of input');
        }

        return $this->tokens[$this->position];
    }

    private function previous(): Token
    {
        return $this->tokens[$this->position - 1];
    }
}
