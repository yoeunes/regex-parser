<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Parser;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\AnchorNode;
use RegexParser\Ast\CharClassNode;
use RegexParser\Ast\CharTypeNode;
use RegexParser\Ast\DotNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\NodeInterface;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\RangeNode;
use RegexParser\Ast\RegexNode;
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
     * @return RegexNode the root node of the AST, containing the pattern and flags
     *
     * @throws ParserException if a syntax error is found
     */
    public function parse(string $regex): RegexNode
    {
        $this->tokens = $this->lexer->tokenize();
        $this->position = 0;

        $this->consume(TokenType::T_DELIMITER, 'Expected opening delimiter');
        $pattern = $this->parseAlternation();
        $this->consume(TokenType::T_DELIMITER, 'Expected closing delimiter');
        $flags = $this->parseFlags();
        $this->consume(TokenType::T_EOF, 'Unexpected content after closing delimiter');

        return new RegexNode($pattern, $flags);
    }

    // Grammar:
    // alternation      → sequence ( "|" sequence )*
    // sequence         → quantifiedAtom*
    // quantifiedAtom   → atom ( QUANTIFIER )?
    // atom             → T_LITERAL | T_CHAR_TYPE | T_DOT | T_ANCHOR | group | char_class
    // group            → "(" alternation ")"
    // char_class       → "[" ( T_NEGATION )? ( char_class_part )* "]"
    // char_class_part  → T_LITERAL ( T_RANGE T_LITERAL )? | T_CHAR_TYPE

    /**
     * Parses an alternation (e.g., "a|b").
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
     * Parses a sequence of atoms (e.g., "abc").
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

        if (empty($nodes)) {
            return new LiteralNode(''); // "Empty" node
        }

        return \count($nodes) > 1 ? new SequenceNode($nodes) : $nodes[0];
    }

    /**
     * Parses an atom that may or may not be quantified (e.g., "a", "a*").
     */
    private function parseQuantifiedAtom(): NodeInterface
    {
        $node = $this->parseAtom();

        if ($this->match(TokenType::T_QUANTIFIER)) {
            $quantifier = $this->previous()->value;
            if ($node instanceof LiteralNode && '' === $node->value) {
                throw new ParserException('Quantifier without target at position '.$this->previous()->position);
            }
            if ($node instanceof AnchorNode) {
                throw new ParserException(\sprintf('Quantifier "%s" cannot be applied to anchor "%s" at position %d', $quantifier, $node->value, $this->previous()->position));
            }
            $node = new QuantifierNode($node, $quantifier);
        }

        return $node;
    }

    /**
     * Parses a single "atom" (the smallest unit).
     */
    private function parseAtom(): NodeInterface
    {
        if ($this->match(TokenType::T_LITERAL)) {
            return new LiteralNode($this->previous()->value);
        }

        if ($this->match(TokenType::T_CHAR_TYPE)) {
            return new CharTypeNode($this->previous()->value);
        }

        if ($this->match(TokenType::T_DOT)) {
            return new DotNode();
        }

        if ($this->match(TokenType::T_ANCHOR)) {
            return new AnchorNode($this->previous()->value);
        }

        if ($this->match(TokenType::T_GROUP_OPEN)) {
            $expr = $this->parseAlternation(); // Recurse
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr);
        }

        if ($this->match(TokenType::T_CHAR_CLASS_OPEN)) {
            return $this->parseCharClass();
        }

        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
        $expected = [
            TokenType::T_LITERAL->value,
            TokenType::T_CHAR_TYPE->value,
            TokenType::T_DOT->value,
            TokenType::T_ANCHOR->value,
            TokenType::T_GROUP_OPEN->value,
            TokenType::T_CHAR_CLASS_OPEN->value,
        ];
        throw new ParserException(\sprintf('Unexpected token "%s" (%s) at %s. Expected one of: %s', $this->current()->value, $this->current()->type->value, $at, implode(', ', $expected)));
    }

    /**
     * Parses a character class (e.g., "[a-z\d]").
     */
    private function parseCharClass(): CharClassNode
    {
        $isNegated = $this->match(TokenType::T_NEGATION);
        $parts = [];

        while (!$this->check(TokenType::T_CHAR_CLASS_CLOSE) && !$this->isAtEnd()) {
            $parts[] = $this->parseCharClassPart();
        }

        $this->consume(TokenType::T_CHAR_CLASS_CLOSE, 'Expected "]" to close character class');

        return new CharClassNode($parts, $isNegated);
    }

    /**
     * Parses a single part of a character class (a literal, a range, or a char type).
     */
    private function parseCharClassPart(): NodeInterface
    {
        $startNode = null;
        if ($this->match(TokenType::T_LITERAL)) {
            $startNode = new LiteralNode($this->previous()->value);
        } elseif ($this->match(TokenType::T_CHAR_TYPE)) {
            $startNode = new CharTypeNode($this->previous()->value);
        } else {
            $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
            throw new ParserException(\sprintf('Unexpected token "%s" (%s) in character class at %s. Expected literal or character type.', $this->current()->value, $this->current()->type->value, $at));
        }

        // Check for a range (e.g., "a-z")
        if ($this->match(TokenType::T_RANGE)) {
            $endNode = null;
            if ($this->match(TokenType::T_LITERAL)) {
                $endNode = new LiteralNode($this->previous()->value);
            } elseif ($this->match(TokenType::T_CHAR_TYPE)) {
                $endNode = new CharTypeNode($this->previous()->value);
            } else {
                // This means a trailing "-", (e.g., "[a-]")
                // We treat the "-" as a literal.
                --$this->position; // Rewind to re-parse "-" as a literal

                return $startNode;
            }

            return new RangeNode($startNode, $endNode);
        }

        return $startNode;
    }

    /**
     * Parses flags after the closing delimiter.
     */
    private function parseFlags(): string
    {
        $flags = '';
        while ($this->match(TokenType::T_LITERAL)) {
            $flags .= $this->previous()->value;
        }

        return $flags;
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
