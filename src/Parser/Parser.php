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
use RegexParser\Ast\AssertionNode;
use RegexParser\Ast\BackrefNode;
use RegexParser\Ast\CharClassNode;
use RegexParser\Ast\CharTypeNode;
use RegexParser\Ast\CommentNode;
use RegexParser\Ast\ConditionalNode;
use RegexParser\Ast\DotNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\GroupType;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\NodeInterface;
use RegexParser\Ast\OctalNode;
use RegexParser\Ast\PosixClassNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\QuantifierType;
use RegexParser\Ast\RangeNode;
use RegexParser\Ast\RegexNode;
use RegexParser\Ast\SequenceNode;
use RegexParser\Ast\UnicodeNode;
use RegexParser\Ast\UnicodePropNode;
use RegexParser\Exception\ParserException;
use RegexParser\Lexer\Lexer;
use RegexParser\Lexer\Token;
use RegexParser\Lexer\TokenType;

/**
 * The Parser.
 * It consumes a stream of Tokens from the Lexer and builds an
 * Abstract Syntax Tree (AST) based on a formal grammar.
 * It is now responsible for handling delimiters.
 */
class Parser
{
    /** @var array<Token> */
    private array $tokens;
    private int $position = 0;
    private string $delimiter;
    private string $flags;

    public function __construct()
    {
    }

    /**
     * Parses the full regex string, including delimiters and flags.
     *
     * @return RegexNode the root node of the AST, containing the pattern and flags
     *
     * @throws ParserException if a syntax error is found
     */
    public function parse(string $regex): RegexNode
    {
        [$pattern, $flags, $delimiter] = $this->extractPatternAndFlags($regex);

        $this->delimiter = $delimiter;
        $this->flags = $flags;

        $lexer = new Lexer($pattern);
        $this->tokens = $lexer->tokenize();
        $this->position = 0;

        $patternNode = $this->parseAlternation();
        $this->consume(TokenType::T_EOF, 'Unexpected content at end of pattern');

        return new RegexNode($patternNode, $this->flags, $this->delimiter);
    }

    /**
     * Extracts the pattern, flags, and delimiter from the full regex string.
     *
     * @return array{0: string, 1: string, 2: string} [pattern, flags, delimiter]
     *
     * @throws ParserException
     */
    private function extractPatternAndFlags(string $regex): array
    {
        if (\strlen($regex) < 2) {
            throw new ParserException('Regex is too short.');
        }

        $delimiter = $regex[0];
        $delimiterMap = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'];
        $closingDelimiter = $delimiterMap[$delimiter] ?? $delimiter;

        $lastPos = strrpos($regex, $closingDelimiter);

        // Check for escaped delimiter
        if (false !== $lastPos && $lastPos > 0) {
            $escaped = false;
            for ($i = $lastPos - 1; $i >= 0 && '\\' === $regex[$i]; --$i) {
                $escaped = !$escaped;
            }
            if ($escaped) {
                $lastPos = strrpos(substr($regex, 0, $lastPos), $closingDelimiter);
            }
        }

        if (false === $lastPos || 0 === $lastPos) {
            throw new ParserException(\sprintf('No closing delimiter "%s" found.', $closingDelimiter));
        }

        $pattern = substr($regex, 1, $lastPos - 1);
        $flags = substr($regex, $lastPos + 1);

        $unknownFlags = preg_replace('/[imsxADSUXJu]/', '', $flags);
        if ('' !== $unknownFlags) {
            throw new ParserException(\sprintf('Unknown regex flag(s) found: "%s"', $unknownFlags));
        }

        return [$pattern, $flags, $delimiter];
    }

    // --- GRAMMAR ---
    // alternation    → sequence ( "|" sequence )*
    // sequence       → quantifiedAtom*
    // quantifiedAtom → atom ( QUANTIFIER )?
    // atom           → T_LITERAL | T_CHAR_TYPE | T_DOT | T_ANCHOR | group | char_class
    // group          → T_GROUP_OPEN alternation T_GROUP_CLOSE
    //                  | T_GROUP_MODIFIER_OPEN group_modifier T_GROUP_CLOSE
    // ... (etc)

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
     * Parses an atom that may or may not be quantified (e.g., "a", "a*?").
     */
    private function parseQuantifiedAtom(): NodeInterface
    {
        $node = $this->parseAtom();

        if ($this->match(TokenType::T_QUANTIFIER)) {
            $token = $this->previous();
            if ($node instanceof LiteralNode && '' === $node->value) {
                throw new ParserException('Quantifier without target at position '.$token->position);
            }
            if ($node instanceof AnchorNode || $node instanceof AssertionNode) {
                throw new ParserException(\sprintf('Quantifier "%s" cannot be applied to assertion "%s" at position %d', $token->value, $node->value, $token->position));
            }

            [$quantifier, $type] = $this->parseQuantifierValue($token->value);
            $node = new QuantifierNode($node, $quantifier, $type);
        }

        return $node;
    }

    /**
     * Helper to split quantifier value (e.g. "*?") into value ("*") and type (LAZY).
     *
     * @return array{0: string, 1: QuantifierType}
     */
    private function parseQuantifierValue(string $value): array
    {
        $lastChar = substr($value, -1);
        $baseValue = substr($value, 0, -1);

        // e.g. *? or +? or ??
        // Note: $value can be '?' so strlen > 1 is crucial
        if ('?' === $lastChar && \strlen($value) > 1) {
            return [$baseValue, QuantifierType::T_LAZY];
        }

        // e.g. *+ or ++
        // Note: $value can be '+' so strlen > 1 is crucial
        if ('+' === $lastChar && \strlen($value) > 1) {
            return [$baseValue, QuantifierType::T_POSSESSIVE];
        }

        // It's a normal, greedy quantifier (e.g. *, +, or ?)
        return [$value, QuantifierType::T_GREEDY];
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

        if ($this->match(TokenType::T_ASSERTION)) {
            return new AssertionNode($this->previous()->value);
        }

        if ($this->match(TokenType::T_BACKREF)) {
            return new BackrefNode($this->previous()->value);
        }

        if ($this->match(TokenType::T_UNICODE)) {
            return new UnicodeNode($this->previous()->value);
        }

        if ($this->match(TokenType::T_OCTAL)) {
            return new OctalNode($this->previous()->value);
        }

        if ($this->match(TokenType::T_UNICODE_PROP)) {
            return new UnicodePropNode($this->previous()->value);
        }

        if ($this->match(TokenType::T_GROUP_OPEN)) {
            $expr = $this->parseAlternation(); // Recurse
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_CAPTURING);
        }

        if ($this->match(TokenType::T_GROUP_MODIFIER_OPEN)) {
            return $this->parseGroupModifier();
        }

        if ($this->match(TokenType::T_COMMENT_OPEN)) {
            return $this->parseComment();
        }

        if ($this->match(TokenType::T_CHAR_CLASS_OPEN)) {
            return $this->parseCharClass();
        }

        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
        $val = $this->current()->value;
        $type = $this->current()->type->value;
        throw new ParserException(\sprintf('Unexpected token "%s" (%s) at %s.', $val, $type, $at));
    }

    /**
     * Parses a comment group (?#comment).
     *
     * @throws ParserException
     */
    private function parseComment(): CommentNode
    {
        $start = $this->previous()->position;
        ++$this->position; // #
        $comment = $this->consumeWhile(fn (string $c) => ')' !== $c);
        $this->consumeLiteral(')', 'Expected ) to close comment');

        return new CommentNode((string) $comment);
    }

    /**
     * Parses a special group that starts with "(?".
     * This is the new, robust logic.
     *
     * @throws ParserException
     */
    private function parseGroupModifier(): NodeInterface
    {
        $startPos = $this->previous()->position;
        $flags = '';
        $type = GroupType::T_GROUP_NON_CAPTURING;
        $name = null;
        $condition = null;

        $next = $this->current()->value;
        if (preg_match('/^[imsxADSUXJ-]+$/', $next)) {
            // Inline flags (?i-m:)
            $flags = $next;
            $this->advance();
            $this->consumeLiteral(':', 'Expected : after inline flags');
            $expr = $this->parseAlternation();
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
            return new GroupNode($expr, GroupType::T_GROUP_INLINE_FLAGS, null, $flags);
        }

        if ($this->matchLiteral('P')) {
            // (?P<...> named group or (?P=... backref (not supported yet)
            if ($this->matchLiteral('<')) {
                // (?P<name>...)
                $name = $this->parseGroupName();
                $this->consumeLiteral('>', 'Expected > after group name');
                $expr = $this->parseAlternation();
                $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

                return new GroupNode($expr, GroupType::T_GROUP_NAMED, $name);
            } elseif ($this->matchLiteral('=')) {
                // (?P=name) backref
                throw new ParserException('Backreferences (?P=name) are not supported yet.');
            } else {
                throw new ParserException('Invalid syntax after (?P at position '.$startPos);
            }
        } elseif ($this->matchLiteral('<')) {
            // (?<...> : lookbehind or named group (non-Python)
            if ($this->matchLiteral('=')) {
                // (?<=...)
                $expr = $this->parseAlternation();
                $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

                return new GroupNode($expr, GroupType::T_GROUP_LOOKBEHIND_POSITIVE);
            } elseif ($this->matchLiteral('!')) {
                // (?<!...)
                $expr = $this->parseAlternation();
                $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

                return new GroupNode($expr, GroupType::T_GROUP_LOOKBEHIND_NEGATIVE);
            } else {
                // (?<name>...)
                $name = $this->parseGroupName();
                $this->consumeLiteral('>', 'Expected > after group name');
                $expr = $this->parseAlternation();
                $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

                return new GroupNode($expr, GroupType::T_GROUP_NAMED, $name);
            }
        } elseif ($this->matchLiteral(':')) {
            // (?:...)
            $expr = $this->parseAlternation();
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_NON_CAPTURING);
        } elseif ($this->matchLiteral('=')) {
            // (?=...)
            $expr = $this->parseAlternation();
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_LOOKAHEAD_POSITIVE);
        } elseif ($this->matchLiteral('!')) {
            // (?!...)
            $expr = $this->parseAlternation();
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_LOOKAHEAD_NEGATIVE);
        } elseif ($this->matchLiteral('(')) {
            // Conditional (?(condition)yes|no)
            $condition = $this->parseConditionalCondition();
            $yes = $this->parseAlternation();
            if ($this->match(TokenType::T_ALTERNATION)) {
                $no = $this->parseAlternation();
            } else {
                $no = new LiteralNode('');
            }
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new ConditionalNode($condition, $yes, $no);
        }

        throw new ParserException('Invalid group modifier syntax at position '.$startPos);
    }

    /**
     * Parses the condition in a conditional group (?(condition)...).
     */
    private function parseConditionalCondition(): NodeInterface
    {
        if ($this->match(TokenType::T_LITERAL) && ctype_digit($this->previous()->value)) {
            // Numeric (?(1)...)
            $num = (string) ($this->previous()->value . $this->consumeWhile(fn ($c) => ctype_digit($c)));
            return new BackrefNode($num);
        } elseif ($this->matchLiteral('<') || $this->matchLiteral('{')) {
            // Named (?(<name>)...) or (?({name})...)
            $open = $this->previous()->value;
            $name = $this->parseGroupName();
            $close = $open === '<' ? '>' : '}';
            $this->consumeLiteral($close, "Expected $close after condition name");
            return new BackrefNode($name);
        } elseif ($this->matchLiteral('R')) {
            // Recursion (?(R)...)
            return new LiteralNode('R'); // Special recursion condition
        } else {
            // Lookaround or assertion as condition (?(?=...)...)
            return $this->parseAtom();
        }
    }

    /**
     * Parses the name of a named group, handling quotes.
     *
     * @throws ParserException
     */
    private function parseGroupName(): string
    {
        $token = $this->current();

        // Handle 'name' or "name"
        if (TokenType::T_LITERAL === $token->type && ("'" === $token->value || '"' === $token->value)) {
            $quote = $token->value;
            $this->advance(); // Consume opening quote
            $nameToken = $this->consume(TokenType::T_LITERAL, 'Expected group name');
            if ($this->current()->value !== $quote) {
                throw new ParserException('Expected closing quote '.$quote.' at position '.$this->current()->position);
            }
            $this->advance(); // Consume closing quote

            return $nameToken->value;
        }

        // Handle <name>
        $name = '';
        while (!$this->checkLiteral('>') && !$this->isAtEnd()) {
            // A name can be any literal, but not special chars like '(', '[', etc.
            // Our lexer tokenizes these separately.
            if ($this->check(TokenType::T_LITERAL)) {
                $name .= $this->current()->value;
                $this->advance();
            } else {
                throw new ParserException('Unexpected token in group name: '.$this->current()->value);
            }
        }

        if ('' === $name) {
            throw new ParserException('Expected group name at position '.$this->current()->position);
        }

        return $name;
    }

    /**
     * Parses a character class (e.g., "[a-z\d]").
     *
     * @throws ParserException
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
     *
     * @throws ParserException
     */
    private function parseCharClassPart(): NodeInterface
    {
        $startNode = null;
        if ($this->match(TokenType::T_LITERAL)) {
            $startNode = new LiteralNode($this->previous()->value);
        } elseif ($this->match(TokenType::T_CHAR_TYPE)) {
            $startNode = new CharTypeNode($this->previous()->value);
        } elseif ($this->match(TokenType::T_UNICODE_PROP)) {
            $startNode = new UnicodePropNode($this->previous()->value);
        } elseif ($this->match(TokenType::T_UNICODE)) {
            $startNode = new UnicodeNode($this->previous()->value);
        } elseif ($this->match(TokenType::T_OCTAL)) {
            $startNode = new OctalNode($this->previous()->value);
        } elseif ($this->match(TokenType::T_RANGE)) {
            // This handles a literal "-" at the start (e.g. "[-a]")
            return new LiteralNode($this->previous()->value);
        } else {
            $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
            throw new ParserException(\sprintf('Unexpected token "%s" (%s) in character class at %s. Expected literal, range, or character type.', $this->current()->value, $this->current()->type->value, $at));
        }

        if ($this->match(TokenType::T_POSIX_CLASS)) {
            return new PosixClassNode($this->previous()->value);
        }

        // Check for a range (e.g., "a-z")
        if ($this->match(TokenType::T_RANGE)) {
            // Check if the range is followed by a "]" (e.g., "[a-]")
            if ($this->check(TokenType::T_CHAR_CLASS_CLOSE)) {
                // This is a trailing "-", (e.g., "[a-]")
                // We treat the "-" as a literal.
                --$this->position; // Rewind to re-parse "-" as a literal in the next loop iteration

                return $startNode;
            }

            $endNode = null;
            if ($this->match(TokenType::T_LITERAL)) {
                $endNode = new LiteralNode($this->previous()->value);
            } elseif ($this->match(TokenType::T_CHAR_TYPE)) {
                $endNode = new CharTypeNode($this->previous()->value);
            } elseif ($this->match(TokenType::T_UNICODE_PROP)) {
                $endNode = new UnicodePropNode($this->previous()->value);
            } elseif ($this->match(TokenType::T_UNICODE)) {
                $endNode = new UnicodeNode($this->previous()->value);
            } elseif ($this->match(TokenType::T_OCTAL)) {
                $endNode = new OctalNode($this->previous()->value);
            } else {
                // This means a range ending with a meta-char, e.g. [a-\]
                // We treat the "-" as a literal.
                --$this->position; // Rewind

                return $startNode;
            }

            return new RangeNode($startNode, $endNode);
        }

        return $startNode;
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
     * Checks if the current token is a T_LITERAL with a specific value.
     */
    private function matchLiteral(string $value): bool
    {
        if ($this->checkLiteral($value)) {
            $this->advance();

            return true;
        }

        return false;
    }

    /**
     * Checks if the current token is a T_LITERAL with a specific value.
     */
    private function checkLiteral(string $value): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }
        $token = $this->current();

        return TokenType::T_LITERAL === $token->type && $token->value === $value;
    }

    /**
     * Consumes the current token, throwing an error if it doesn't match the expected type.
     *
     * @throws ParserException
     */
    private function consume(TokenType $type, string $error): Token
    {
        if ($this->check($type)) {
            $token = $this->current();
            $this->advance();

            return $token;
        }
        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
        throw new ParserException($error.' at '.$at.' (found '.$this->current()->type->value.')');
    }

    /**
     * Consumes the current token if it's a T_LITERAL with the given value.
     *
     * @throws ParserException
     */
    private function consumeLiteral(string $value, string $error): Token
    {
        if ($this->checkLiteral($value)) {
            $token = $this->current();
            $this->advance();

            return $token;
        }
        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;
        throw new ParserException($error.' at '.$at.' (found '.$this->current()->type->value.' with value '.$this->current()->value.')');
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

    /**
     * Consumes characters from tokens as long as the predicate is true (adapted for tokens).
     */
    private function consumeWhile(callable $predicate): string
    {
        $value = '';
        while (!$this->isAtEnd() && $predicate($this->current()->value) && TokenType::T_LITERAL === $this->current()->type) {
            $value .= $this->current()->value;
            $this->advance();
        }

        return $value;
    }
}
