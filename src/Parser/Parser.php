<?php

namespace RegexParser\Parser;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\NodeInterface;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\SequenceNode;
use RegexParser\Exception\ParserException;
use RegexParser\Lexer\Lexer;
use RegexParser\Lexer\Token;
use RegexParser\Lexer\TokenType;

class Parser
{
    /** @var array<Token> */
    private array $tokens;
    private int $position = 0;

    public function __construct(private readonly Lexer $lexer)
    {
    }

    public function parse(string $regex): NodeInterface
    {
        $this->tokens = $this->lexer->tokenize();
        $this->position = 0;

        $this->consume(TokenType::T_DELIMITER, 'Expected opening delimiter');

        $node = $this->parseAlternation();

        $this->consume(TokenType::T_DELIMITER, 'Expected closing delimiter');
        // Ici, on pourrait parser les T_FLAG

        $this->consume(TokenType::T_EOF, 'Unexpected content after closing delimiter');

        return $node;
    }

    // Grammaire:
    // alternation      → sequence ( "|" sequence )*
    // sequence         → quantifiedAtom*
    // quantifiedAtom   → atom ( QUANTIFIER )?
    // atom             → T_LITERAL | T_BACKSLASH T_LITERAL | group
    // group            → "(" alternation ")"

    private function parseAlternation(): NodeInterface
    {
        $nodes = [$this->parseSequence()];

        while ($this->match(TokenType::T_ALTERNATION)) {
            $nodes[] = $this->parseSequence();
        }

        return \count($nodes) > 1 ? new AlternationNode($nodes) : $nodes[0];
    }

    private function parseSequence(): NodeInterface
    {
        $nodes = [];

        // Continue de parser tant qu'on n'est pas à un "terminateur" de séquence
        while (!$this->check(TokenType::T_GROUP_CLOSE)
               && !$this->check(TokenType::T_ALTERNATION)
               && !$this->check(TokenType::T_DELIMITER)
               && !$this->check(TokenType::T_EOF)
        ) {
            $nodes[] = $this->parseQuantifiedAtom();
        }

        // Gérer le cas d'une séquence vide (ex: `()`, `(a||b)`)
        if (empty($nodes)) {
            return new LiteralNode(''); // Nœud "vide"
        }

        return \count($nodes) > 1 ? new SequenceNode($nodes) : $nodes[0];
    }

    private function parseQuantifiedAtom(): NodeInterface
    {
        $node = $this->parseAtom();

        if ($this->match(TokenType::T_QUANTIFIER)) {
            $quantifier = $this->previous()->value;
            // Gérer les quantifieurs invalides (ex: `*`, `+` au début)
            if ($node instanceof LiteralNode && '' === $node->value) {
                throw new ParserException('Quantifier without target at position '.$this->previous()->position);
            }
            $node = new QuantifierNode($node, $quantifier);
        }

        return $node;
    }

    private function parseAtom(): NodeInterface
    {
        if ($this->match(TokenType::T_LITERAL)) {
            return new LiteralNode($this->previous()->value);
        }

        // Gère \*, \+, \? etc.
        if ($this->match(TokenType::T_BACKSLASH)) {
            if ($this->match(TokenType::T_LITERAL)) {
                return new LiteralNode($this->previous()->value);
            }
            throw new ParserException('Expected character after backslash at position '.$this->previous()->position);
        }

        if ($this->match(TokenType::T_GROUP_OPEN)) {
            $expr = $this->parseAlternation(); // Récursion
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr);
        }

        // Gérer le cas d'une expression vide avant un terminateur (ex: /|/)
        if ($this->check(TokenType::T_ALTERNATION) || $this->check(TokenType::T_GROUP_CLOSE) || $this->check(TokenType::T_DELIMITER) || $this->check(TokenType::T_EOF)) {
            return new LiteralNode(''); // Nœud "vide"
        }

        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
        throw new ParserException('Unexpected token '.$this->current()->type->value.' at '.$at);
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
        throw new ParserException($error.' at '.$at.' (found '.$this->current()->type->value.')');
    }

    private function check(TokenType $type): bool
    {
        if ($this->isAtEnd()) {
            return TokenType::T_EOF === $type;
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
        // On ne sera jamais "at end" avant le T_EOF.
        return TokenType::T_EOF === $this->tokens[$this->position]->type;
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
