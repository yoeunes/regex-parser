<?php

declare(strict_types=1);

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser;

use RegexParser\Exception\ParserException;
use RegexParser\Exception\RecursionLimitException;
use RegexParser\Exception\ResourceLimitException;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\OctalLegacyNode;
use RegexParser\Node\OctalNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\Stream\TokenStream;

use function count;
use function sprintf;
use function strlen;

/**
 * The Parser.
 *
 * Transforms a stream of Tokens into an Abstract Syntax Tree (AST).
 * Implements a Recursive Descent Parser based on PCRE grammar.
 *
 * IMPORTANT: This parser operates ONLY on TokenStream, not raw strings.
 * For parsing raw regex strings, use the RegexCompiler which combines
 * Lexer + Parser for a convenient high-level API.
 *
 * Architecture:
 * - Parser has NO knowledge of raw strings or lexer internals
 * - Input: TokenStream (produced by Lexer)
 * - Output: RegexNode (the AST)
 * - Enforces resource limits (recursion depth, node count)
 *
 * @example Direct TokenStream parsing:
 * ```php
 * $lexer = new Lexer($pattern);
 * $stream = new TokenStream($lexer->tokenize());
 * $parser = new Parser();
 * $ast = $parser->parseTokenStream($stream, 'i', '/', strlen($pattern));
 * ```
 *
 * @example Using RegexCompiler (recommended for most use cases):
 * ```php
 * $compiler = new RegexCompiler();
 * $ast = $compiler->parse('/pattern/flags');
 * ```
 */
final class Parser
{
    /**
     * Default maximum recursion depth (prevents stack overflow).
     */
    public const int DEFAULT_MAX_RECURSION_DEPTH = 200;

    /**
     * Default maximum number of AST nodes (prevents DoS).
     */
    public const int DEFAULT_MAX_NODES = 10000;

    private readonly int $maxRecursionDepth;

    private readonly int $maxNodes;

    /**
     * Current recursion depth (tracks during parsing).
     */
    private int $recursionDepth = 0;

    /**
     * Current node count (tracks during parsing).
     */
    private int $nodeCount = 0;

    /**
     * Token stream for current parse operation.
     */
    private TokenStream $stream;

    /**
     * @param array{
     *     max_recursion_depth?: int,
     *     max_nodes?: int,
     * } $options Configuration options
     */
    public function __construct(array $options = [])
    {
        $this->maxRecursionDepth = (int) ($options['max_recursion_depth'] ?? self::DEFAULT_MAX_RECURSION_DEPTH);
        $this->maxNodes = (int) ($options['max_nodes'] ?? self::DEFAULT_MAX_NODES);
    }

    /**
     * Parses a TokenStream into a complete RegexNode AST.
     *
     * This is the primary parsing method. It operates purely on the provided
     * TokenStream with no knowledge of how that stream was created.
     *
     * @param TokenStream $stream        The token stream to parse
     * @param string      $flags         Regex flags (e.g., 'i', 'ms')
     * @param string      $delimiter     The delimiter used (e.g., '/')
     * @param int         $patternLength Length of the original pattern
     *
     * @throws ParserException         if the regex syntax is invalid
     * @throws RecursionLimitException if recursion depth exceeds limit
     * @throws ResourceLimitException  if node count exceeds limit
     */
    public function parseTokenStream(
        TokenStream $stream,
        string $flags = '',
        string $delimiter = '/',
        int $patternLength = 0
    ): RegexNode {
        // Reset state for new parse
        $this->recursionDepth = 0;
        $this->nodeCount = 0;
        $this->stream = $stream;

        // Parse the pattern content
        $patternNode = $this->parseAlternation();

        // Ensure we reached the end of the pattern
        $this->consume(TokenType::T_EOF, 'Unexpected content at end of pattern');

        return new RegexNode($patternNode, $flags, $delimiter, 0, $patternLength);
    }

    // ========================================================================
    // GRAMMAR IMPLEMENTATION (Recursive Descent)
    // ========================================================================

    /**
     * alternation -> sequence ( "|" sequence )*
     */
    private function parseAlternation(): NodeInterface
    {
        $startPos = $this->current()->position;
        $nodes = [$this->parseSequence()];

        while ($this->match(TokenType::T_ALTERNATION)) {
            $nodes[] = $this->parseSequence();
        }

        if (1 === count($nodes)) {
            return $nodes[0];
        }

        $endPos = end($nodes)->getEndPosition();

        return new AlternationNode($nodes, $startPos, $endPos);
    }

    /**
     * sequence -> quantifiedAtom*
     */
    private function parseSequence(): NodeInterface
    {
        $nodes = [];
        $startPos = $this->current()->position;

        while (!$this->isAtEnd()
            && !$this->check(TokenType::T_GROUP_CLOSE)
            && !$this->check(TokenType::T_ALTERNATION)
        ) {
            if ($this->match(TokenType::T_QUOTE_MODE_START) || $this->match(TokenType::T_QUOTE_MODE_END)) {
                continue;
            }

            $nodes[] = $this->parseQuantifiedAtom();
        }

        if (empty($nodes)) {
            return new LiteralNode('', $startPos, $startPos);
        }

        if (1 === count($nodes)) {
            return $nodes[0];
        }

        $endPos = end($nodes)->getEndPosition();

        return new SequenceNode($nodes, $startPos, $endPos);
    }

    /**
     * quantifiedAtom -> atom ( QUANTIFIER )?
     */
    private function parseQuantifiedAtom(): NodeInterface
    {
        $node = $this->parseAtom();

        if ($this->match(TokenType::T_QUANTIFIER)) {
            $token = $this->previous();

            if ($node instanceof LiteralNode && '' === $node->value) {
                throw new ParserException(sprintf('Quantifier without target at position %d', $token->position));
            }

            if ($node instanceof GroupNode) {
                $child = $node->child;
                if (($child instanceof LiteralNode && '' === $child->value)
                    || ($child instanceof SequenceNode && empty($child->children))) {
                    throw new ParserException(sprintf('Quantifier without target at position %d', $token->position));
                }
            }

            $quantifier = $token->value;
            $len = strlen($quantifier);
            $lastChar = $quantifier[$len - 1];
            $type = QuantifierType::GREEDY;

            if ('?' === $lastChar) {
                $type = QuantifierType::LAZY;
                $quantifier = substr($quantifier, 0, -1);
            } elseif ('+' === $lastChar) {
                $type = QuantifierType::POSSESSIVE;
                $quantifier = substr($quantifier, 0, -1);
            }

            [$min, $max] = $this->parseQuantifierRange($quantifier);

            return new QuantifierNode($node, $quantifier.$lastChar, $type, $min, $max, $node->getStartPosition(), $token->position + strlen($token->value));
        }

        return $node;
    }

    /**
     * @return array{int, int}
     */
    private function parseQuantifierRange(string $quantifier): array
    {
        return match ($quantifier) {
            '*' => [0, -1],
            '+' => [1, -1],
            '?' => [0, 1],
            default => $this->parseNumericRange($quantifier),
        };
    }

    /**
     * @return array{int, int}
     */
    private function parseNumericRange(string $quantifier): array
    {
        $inner = substr($quantifier, 1, -1);
        $parts = explode(',', $inner);

        $min = (int) $parts[0];

        if (1 === count($parts)) {
            return [$min, $min];
        }

        if ('' === $parts[1]) {
            return [$min, -1];
        }

        return [$min, (int) $parts[1]];
    }

    /**
     * atom -> literal | group | charClass | ...
     */
    private function parseAtom(): NodeInterface
    {
        $token = $this->current();
        $startPos = $token->position;

        if ($this->match(TokenType::T_DOT)) {
            return new DotNode($startPos, $startPos + 1);
        }

        if ($this->match(TokenType::T_ANCHOR)) {
            return new AnchorNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_ASSERTION)) {
            return new AssertionNode(AssertionNode::simpleTypeFromChar($token->value), null, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_KEEP)) {
            return new KeepNode($startPos, $startPos + 2);
        }

        if ($this->match(TokenType::T_CHAR_TYPE)) {
            return new CharTypeNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_BACKREF)) {
            return new BackrefNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_G_REFERENCE)) {
            return $this->parseGReference($token);
        }

        if ($this->match(TokenType::T_UNICODE)) {
            return new UnicodeNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_UNICODE_PROP)) {
            return new UnicodePropNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_OCTAL)) {
            return new OctalNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_OCTAL_LEGACY)) {
            return new OctalLegacyNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_PCRE_VERB)) {
            return new PcreVerbNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->check(TokenType::T_CHAR_CLASS_OPEN)) {
            return $this->parseCharClass();
        }

        if ($this->check(TokenType::T_GROUP_OPEN) || $this->check(TokenType::T_GROUP_MODIFIER_OPEN) || $this->check(TokenType::T_COMMENT_OPEN)) {
            return $this->parseGroup();
        }

        if ($this->match(TokenType::T_LITERAL) || $this->match(TokenType::T_LITERAL_ESCAPED)) {
            return new LiteralNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        throw new ParserException(sprintf('Unexpected token "%s" at position %d', $token->type->value, $token->position));
    }

    private function parseGReference(Token $token): NodeInterface
    {
        $value = $token->value;
        $startPos = $token->position;
        $endPos = $startPos + strlen($value);

        if (preg_match('/^\\\\g(?:<([a-zA-Z_][a-zA-Z0-9_]*)>|\{([a-zA-Z_][a-zA-Z0-9_]*)\})$/', $value, $m)) {
            $name = $m[1] !== '' ? $m[1] : $m[2];

            return new SubroutineNode($name, $startPos, $endPos);
        }

        if (preg_match('/^\\\\g(?:(-?\d+)|\{(-?\d+)\})$/', $value, $m)) {
            $ref = $m[1] !== '' ? $m[1] : $m[2];

            return new BackrefNode($ref, $startPos, $endPos);
        }

        return new BackrefNode($value, $startPos, $endPos);
    }

    private function parseCharClass(): CharClassNode
    {
        $this->consume(TokenType::T_CHAR_CLASS_OPEN, 'Expected "["');
        $startPos = $this->previous()->position;

        $negated = false;
        if ($this->match(TokenType::T_NEGATION)) {
            $negated = true;
        }

        $nodes = [];
        while (!$this->check(TokenType::T_CHAR_CLASS_CLOSE) && !$this->isAtEnd()) {
            $nodes[] = $this->parseCharClassRange();
        }

        $this->consume(TokenType::T_CHAR_CLASS_CLOSE, 'Expected "]" to close character class');
        $endPos = $this->previous()->position + 1;

        return new CharClassNode($nodes, $negated, $startPos, $endPos);
    }

    private function parseCharClassRange(): NodeInterface
    {
        $startNode = $this->parseCharClassPart();
        $startPos = $startNode->getStartPosition();

        if ($this->check(TokenType::T_RANGE) && !$this->check(TokenType::T_CHAR_CLASS_CLOSE)) {
            $this->advance();

            if ($this->check(TokenType::T_CHAR_CLASS_CLOSE)) {
                return $startNode;
            }

            $endNode = $this->parseCharClassPart();

            return new RangeNode($startNode, $endNode, $startPos, $endNode->getEndPosition());
        }

        return $startNode;
    }

    private function parseCharClassPart(): NodeInterface
    {
        $token = $this->current();
        $startPos = $token->position;

        if ($this->match(TokenType::T_CHAR_TYPE)) {
            return new CharTypeNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_UNICODE)) {
            return new UnicodeNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_UNICODE_PROP)) {
            return new UnicodePropNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_OCTAL)) {
            return new OctalNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_OCTAL_LEGACY)) {
            return new OctalLegacyNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_POSIX_CLASS)) {
            return new PosixClassNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_LITERAL) || $this->match(TokenType::T_LITERAL_ESCAPED)) {
            return new LiteralNode($token->value, $startPos, $startPos + strlen($token->value));
        }

        if ($this->match(TokenType::T_NEGATION)) {
            return new LiteralNode('^', $startPos, $startPos + 1);
        }

        throw new ParserException(sprintf('Unexpected token "%s" in character class at position %d', $token->type->value, $token->position));
    }

    private function parseGroup(): NodeInterface
    {
        $startPos = $this->current()->position;

        if ($this->match(TokenType::T_COMMENT_OPEN)) {
            return $this->parseCommentGroup($startPos);
        }

        if ($this->match(TokenType::T_GROUP_MODIFIER_OPEN)) {
            return $this->parseModifiedGroup($startPos);
        }

        $this->consume(TokenType::T_GROUP_OPEN, 'Expected "("');

        $child = $this->parseAlternation();

        $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")" to close group');
        $endPos = $this->previous()->position + 1;

        return new GroupNode(GroupType::CAPTURING, null, $child, $startPos, $endPos);
    }

    private function parseCommentGroup(int $startPos): NodeInterface
    {
        $content = '';
        while (!$this->check(TokenType::T_GROUP_CLOSE) && !$this->isAtEnd()) {
            $content .= $this->current()->value;
            $this->advance();
        }

        $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")" to close comment');
        $endPos = $this->previous()->position + 1;

        return new CommentNode($content, $startPos, $endPos);
    }

    private function parseModifiedGroup(int $startPos): NodeInterface
    {
        $token = $this->current();
        $value = $token->value;

        if (':' === $value) {
            $this->advance();
            $child = $this->parseAlternation();
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")" to close non-capturing group');

            return new GroupNode(GroupType::NON_CAPTURING, null, $child, $startPos, $this->previous()->position + 1);
        }

        if ('>' === $value) {
            $this->advance();
            $child = $this->parseAlternation();
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")" to close atomic group');

            return new GroupNode(GroupType::ATOMIC, null, $child, $startPos, $this->previous()->position + 1);
        }

        if ('|' === $value) {
            $this->advance();
            $child = $this->parseAlternation();
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")" to close branch reset group');

            return new GroupNode(GroupType::BRANCH_RESET, null, $child, $startPos, $this->previous()->position + 1);
        }

        if ($this->checkLiteral('P')) {
            return $this->parsePythonStyleGroup($startPos);
        }

        if ($this->checkLiteral('<')) {
            return $this->parseAngleBracketGroup($startPos);
        }

        if ($this->checkLiteral("'")) {
            return $this->parseQuotedNameGroup($startPos);
        }

        if ('=' === $value || '!' === $value) {
            return $this->parseLookahead($startPos, $value);
        }

        if ($this->matchLiteral('(')) {
            return $this->parseConditional($startPos);
        }

        if (preg_match('/^[imsxUXJ-]+/', $value)) {
            return $this->parseInlineModifiers($startPos);
        }

        if (preg_match('/^R$/', $value) || preg_match('/^\d+$/', $value) || preg_match('/^[+-]\d+$/', $value)) {
            return $this->parseSubroutine($startPos);
        }

        if ($this->checkLiteral('&')) {
            return $this->parseNamedSubroutine($startPos);
        }

        throw new ParserException(sprintf('Unknown group modifier "%s" at position %d', $value, $token->position));
    }

    private function parsePythonStyleGroup(int $startPos): NodeInterface
    {
        $this->consumeLiteral('P', 'Expected "P"');

        if ($this->matchLiteral('<')) {
            $name = $this->parseGroupName('>');
            $child = $this->parseAlternation();
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');

            return new GroupNode(GroupType::NAMED, $name, $child, $startPos, $this->previous()->position + 1);
        }

        if ($this->matchLiteral('=')) {
            $name = $this->parseSubroutineName();
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');

            return new BackrefNode($name, $startPos, $this->previous()->position + 1);
        }

        if ($this->matchLiteral('>')) {
            $name = $this->parseSubroutineName();
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');

            return new SubroutineNode($name, $startPos, $this->previous()->position + 1);
        }

        throw new ParserException('Invalid Python-style group syntax at position '.$startPos);
    }

    private function parseAngleBracketGroup(int $startPos): NodeInterface
    {
        $this->consumeLiteral('<', 'Expected "<"');

        $next = $this->current()->value;

        if ('=' === $next || '!' === $next) {
            return $this->parseLookbehind($startPos, $next);
        }

        $name = $this->parseGroupName('>');
        $child = $this->parseAlternation();
        $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');

        return new GroupNode(GroupType::NAMED, $name, $child, $startPos, $this->previous()->position + 1);
    }

    private function parseQuotedNameGroup(int $startPos): NodeInterface
    {
        $this->consumeLiteral("'", "Expected \"'\"");
        $name = $this->parseGroupName("'");
        $child = $this->parseAlternation();
        $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');

        return new GroupNode(GroupType::NAMED, $name, $child, $startPos, $this->previous()->position + 1);
    }

    private function parseLookahead(int $startPos, string $type): NodeInterface
    {
        $this->advance();
        $child = $this->parseAlternation();
        $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');
        $endPos = $this->previous()->position + 1;

        $assertionType = '=' === $type
            ? AssertionNode::typeFromName('lookahead')
            : AssertionNode::typeFromName('negative_lookahead');

        return new AssertionNode($assertionType, $child, $startPos, $endPos);
    }

    private function parseLookbehind(int $startPos, string $type): NodeInterface
    {
        $this->advance();
        $child = $this->parseAlternation();
        $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');
        $endPos = $this->previous()->position + 1;

        $assertionType = '=' === $type
            ? AssertionNode::typeFromName('lookbehind')
            : AssertionNode::typeFromName('negative_lookbehind');

        return new AssertionNode($assertionType, $child, $startPos, $endPos);
    }

    private function parseConditional(int $startPos): NodeInterface
    {
        if ($this->checkLiteral('?')) {
            $this->consumeLiteral('?', 'Expected "?"');

            $next = $this->current()->value;
            if ('=' === $next || '!' === $next) {
                $this->advance();
                $condition = $this->parseAlternation();
                $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');

                $yesNode = $this->parseAlternation();
                $noNode = null;
                if ($this->match(TokenType::T_ALTERNATION)) {
                    $noNode = $this->parseAlternation();
                }
                $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');

                $assertionType = '=' === $next
                    ? AssertionNode::typeFromName('lookahead')
                    : AssertionNode::typeFromName('negative_lookahead');

                $conditionNode = new AssertionNode($assertionType, $condition, $startPos, $this->previous()->position);

                return new ConditionalNode($conditionNode, $yesNode, $noNode, $startPos, $this->previous()->position + 1);
            }

            if ('<' === $next) {
                $this->consumeLiteral('<', 'Expected "<"');
                $lookType = $this->current()->value;
                if ('=' === $lookType || '!' === $lookType) {
                    $this->advance();
                    $condition = $this->parseAlternation();
                    $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');

                    $yesNode = $this->parseAlternation();
                    $noNode = null;
                    if ($this->match(TokenType::T_ALTERNATION)) {
                        $noNode = $this->parseAlternation();
                    }
                    $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');

                    $assertionType = '=' === $lookType
                        ? AssertionNode::typeFromName('lookbehind')
                        : AssertionNode::typeFromName('negative_lookbehind');

                    $conditionNode = new AssertionNode($assertionType, $condition, $startPos, $this->previous()->position);

                    return new ConditionalNode($conditionNode, $yesNode, $noNode, $startPos, $this->previous()->position + 1);
                }
            }
        }

        $condition = null;
        if ($this->check(TokenType::T_LITERAL)) {
            $refValue = $this->consumeWhile(fn ($c) => ctype_alnum($c) || '_' === $c || '-' === $c || '+' === $c);
            if ('' !== $refValue) {
                $condition = new BackrefNode($refValue, $startPos, $this->current()->position);
            }
        }

        $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")" for conditional condition');

        $yesNode = $this->parseAlternation();
        $noNode = null;
        if ($this->match(TokenType::T_ALTERNATION)) {
            $noNode = $this->parseAlternation();
        }

        $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")" to close conditional');

        return new ConditionalNode($condition, $yesNode, $noNode, $startPos, $this->previous()->position + 1);
    }

    private function parseInlineModifiers(int $startPos): NodeInterface
    {
        $mods = '';
        while ($this->check(TokenType::T_LITERAL) && preg_match('/^[imsxUXJ-]$/', $this->current()->value)) {
            $mods .= $this->current()->value;
            $this->advance();
        }

        if ($this->matchLiteral(':')) {
            $child = $this->parseAlternation();
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');

            return new GroupNode(GroupType::MODIFIER_SPAN, $mods, $child, $startPos, $this->previous()->position + 1);
        }

        $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');

        return new GroupNode(GroupType::MODIFIER, $mods, new LiteralNode('', $startPos, $startPos), $startPos, $this->previous()->position + 1);
    }

    private function parseSubroutine(int $startPos): NodeInterface
    {
        $refValue = '';
        while ($this->check(TokenType::T_LITERAL) && preg_match('/^[R0-9+-]$/', $this->current()->value)) {
            $refValue .= $this->current()->value;
            $this->advance();
        }

        $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');

        return new SubroutineNode($refValue, $startPos, $this->previous()->position + 1);
    }

    private function parseNamedSubroutine(int $startPos): NodeInterface
    {
        $this->consumeLiteral('&', 'Expected "&"');
        $name = $this->parseSubroutineName();
        $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ")"');

        return new SubroutineNode($name, $startPos, $this->previous()->position + 1);
    }

    private function parseGroupName(string $terminator): string
    {
        $name = '';
        while (!$this->checkLiteral($terminator) && !$this->isAtEnd()) {
            if ($this->check(TokenType::T_LITERAL) || $this->check(TokenType::T_LITERAL_ESCAPED)) {
                $char = $this->current()->value;
                if (!preg_match('/^[a-zA-Z0-9_]$/', $char)) {
                    throw new ParserException('Invalid character in group name: '.$char);
                }
                $name .= $char;
                $this->advance();
            } else {
                throw new ParserException('Unexpected token in group name: '.$this->current()->value);
            }
        }

        if ('' === $name) {
            throw new ParserException('Expected group name at position '.$this->current()->position);
        }

        $this->consumeLiteral($terminator, 'Expected "'.$terminator.'"');

        return $name;
    }

    private function parseSubroutineName(): string
    {
        $name = '';
        while (!$this->check(TokenType::T_GROUP_CLOSE) && !$this->isAtEnd()) {
            if ($this->check(TokenType::T_LITERAL) || $this->check(TokenType::T_LITERAL_ESCAPED)) {
                $char = $this->current()->value;
                if (!preg_match('/^[a-zA-Z0-9_]$/', $char)) {
                    throw new ParserException('Unexpected token in subroutine name: '.$char);
                }
                $name .= $char;
                $this->advance();
            } else {
                throw new ParserException('Unexpected token in subroutine name: '.$this->current()->value);
            }
        }
        if ('' === $name) {
            throw new ParserException('Expected subroutine name at position '.$this->current()->position);
        }

        return $name;
    }

    // ========================================================================
    // TOKEN NAVIGATION
    // ========================================================================

    private function match(TokenType $type): bool
    {
        if ($this->check($type)) {
            $this->advance();

            return true;
        }

        return false;
    }

    private function matchLiteral(string $value): bool
    {
        if ($this->checkLiteral($value)) {
            $this->advance();

            return true;
        }

        return false;
    }

    private function checkLiteral(string $value): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }
        $token = $this->current();

        return TokenType::T_LITERAL === $token->type && $token->value === $value;
    }

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
            $this->stream->next();
        }
    }

    private function isAtEnd(): bool
    {
        return TokenType::T_EOF === $this->current()->type;
    }

    private function current(): Token
    {
        return $this->stream->current();
    }

    private function previous(): Token
    {
        return $this->stream->peek(-1);
    }

    /**
     * Check and enforce recursion limit.
     *
     * @throws RecursionLimitException if recursion depth exceeds limit
     */
    private function checkRecursionLimit(): void
    {
        $this->recursionDepth++;

        if ($this->recursionDepth > $this->maxRecursionDepth) {
            throw new RecursionLimitException(
                sprintf(
                'Recursion limit of %d exceeded (current: %d). Pattern is too deeply nested.',
                $this->maxRecursionDepth,
                $this->recursionDepth,
            ));
        }
    }

    /**
     * End a recursion scope.
     */
    private function exitRecursionScope(): void
    {
        $this->recursionDepth--;
    }

    /**
     * Check and enforce node count limit.
     *
     * @throws ResourceLimitException if node count exceeds limit
     */
    private function checkNodeLimit(): void
    {
        $this->nodeCount++;

        if ($this->nodeCount > $this->maxNodes) {
            throw new ResourceLimitException(
                sprintf(
                'Node count limit of %d exceeded. Pattern is too complex.',
                $this->maxNodes,
            ));
        }
    }

    private function consumeWhile(callable $predicate): string
    {
        $value = '';
        while (!$this->isAtEnd() && $this->check(TokenType::T_LITERAL) && $predicate($this->current()->value)) {
            $value .= $this->current()->value;
            $this->advance();
        }

        return $value;
    }
}
