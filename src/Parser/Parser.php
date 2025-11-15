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

/**
 * The Parser.
 * It consumes a stream of Tokens from the Lexer and builds an
 * Abstract Syntax Tree (AST) based on a formal grammar.
 */
class Parser
{
    /** @var array<Token> */
    private array $tokens;
    private int $position = 0;

    public function __construct(private readonly Lexer $lexer)
    {
    }

    /**
     * Parses the full regex string.
     *
     * @return NodeInterface<mixed> the root node of the AST
     *
     * @throws ParserException if a syntax error is found
     */
    public function parse(string $regex): NodeInterface
    {
        $this->tokens = $this->lexer->tokenize();
        $this->position = 0;

        $this->consume(TokenType::T_DELIMITER, 'Expected opening delimiter');

        $node = $this->parseAlternation();

        $this->consume(TokenType::T_DELIMITER, 'Expected closing delimiter');
        // TODO: Parse flags (T_FLAG) here

        $this->consume(TokenType::T_EOF, 'Unexpected content after closing delimiter');

        return $node;
    }

    // Grammar:
    // alternation      → sequence ( "|" sequence )*
    // sequence         → quantifiedAtom*
    // quantifiedAtom   → atom ( QUANTIFIER )?
    // atom             → T_LITERAL | T_BACKSLASH T_LITERAL | group
    // group            → "(" alternation ")"

    /**
     * @return NodeInterface<mixed>
     */
    private function parseAlternation(): NodeInterface
    {
        $nodes = [$this->parseSequence()];

        while ($this->match(TokenType::T_ALTERNATION)) {
            $nodes[] = $this->parseSequence();
        }

        return \count($nodes) > 1 ? new AlternationNode($nodes) : $nodes[0];
    }

    /**
     * @return NodeInterface<mixed>
     */
    private function parseSequence(): NodeInterface
    {
        $nodes = [];

        // Continue parsing as long as it's not a sequence terminator
        while (!$this->check(TokenType::T_GROUP_CLOSE)
               && !$this->check(TokenType::T_ALTERNATION)
               && !$this->check(TokenType::T_DELIMITER)
               && !$this->check(TokenType::T_EOF)
        ) {
            $nodes[] = $this->parseQuantifiedAtom();
        }

        // Handle empty sequence (e.g., `()`, `(a||b)`)
        if (empty($nodes)) {
            return new LiteralNode(''); // "Empty" node
        }

        return \count($nodes) > 1 ? new SequenceNode($nodes) : $nodes[0];
    }

    /**
     * @return NodeInterface<mixed>
     */
    private function parseQuantifiedAtom(): NodeInterface
    {
        $node = $this->parseAtom();

        if ($this->match(TokenType::T_QUANTIFIER)) {
            $quantifier = $this->previous()->value;
            // Check for invalid quantifiers (e.g., `*`, `+` at the start)
            if ($node instanceof LiteralNode && '' === $node->value) {
                throw new ParserException('Quantifier without target at position '.$this->previous()->position);
            }
            $node = new QuantifierNode($node, $quantifier);
        }

        return $node;
    }

    /**
     * @return NodeInterface<mixed>
     */
    private function parseAtom(): NodeInterface
    {
        if ($this->match(TokenType::T_LITERAL)) {
            return new LiteralNode($this->previous()->value);
        }

        // Handle escaped meta-characters like \*, \+, \?
        if ($this->match(TokenType::T_BACKSLASH)) {
            if ($this->match(TokenType::T_LITERAL)) {
                return new LiteralNode($this->previous()->value);
            }
            throw new ParserException('Expected character after backslash at position '.$this->previous()->position);
        }

        if ($this->match(TokenType::T_GROUP_OPEN)) {
            $expr = $this->parseAlternation(); // Recurse
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr);
        }

        // Handle empty expression before a terminator (e.g., /|/)
        if ($this->check(TokenType::T_ALTERNATION) || $this->check(TokenType::T_GROUP_CLOSE) || $this->check(TokenType::T_DELIMITER) || $this->check(TokenType::T_EOF)) {
            return new LiteralNode(''); // "Empty" node
        }

        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
        throw new ParserException('Unexpected token '.$this->current()->type->value.' at '.$at);
    }

    /**
     * Checks if the current token matches the given type. If so, consumes it.
     */
    private function match(TokenType $type): bool
    {
        if ($this->check($type)) {
            $this->advance();

            return true;
        }

        return false;
    }

    /**
     * Consumes the current token, throwing an error if it doesn't match the expected type.
     */
    private function consume(TokenType $type, string $error): void
    {
        if ($this->check($type)) {
            $this->advance();

            return;
        }
        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
        throw new ParserException($error.' at '.$at.' (found '.$this->current()->type->value.')');
    }

    /**
     * Checks the type of the current token without consuming it.
     */
    private function check(TokenType $type): bool
    {
        if ($this->isAtEnd()) {
            return TokenType::T_EOF === $type;
        }

        return $this->current()->type === $type;
    }

    /**
     * Advances to the next token.
     */
    private function advance(): void
    {
        if (!$this->isAtEnd()) {
            ++$this->position;
        }
    }

    /**
     * Checks if the parser has reached the end of the token stream.
     */
    private function isAtEnd(): bool
    {
        // We are only "at the end" when we hit the T_EOF token.
        return TokenType::T_EOF === $this->tokens[$this->position]->type;
    }

    /**
     * Gets the current token.
     */
    private function current(): Token
    {
        return $this->tokens[$this->position];
    }

    /**
     * Gets the previously consumed token.
     */
    private function previous(): Token
    {
        return $this->tokens[$this->position - 1];
    }
}
