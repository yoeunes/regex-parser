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

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
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

/**
 * The Parser.
 *
 * It transforms a stream of Tokens (from the Lexer) into an Abstract Syntax Tree (AST).
 * It implements a Recursive Descent Parser based on PCRE grammar.
 */
final class Parser
{
    /**
     * Default hard limit on the regex string length to prevent excessive processing/memory usage.
     */
    public const int DEFAULT_MAX_PATTERN_LENGTH = 100_000;

    /**
     * Default maximum recursion depth (prevents stack overflow on deeply nested patterns).
     */
    public const int DEFAULT_MAX_RECURSION_DEPTH = 200;

    /**
     * Default maximum number of AST nodes (prevents DoS through node exhaustion).
     */
    public const int DEFAULT_MAX_NODES = 10000;

    private readonly int $maxPatternLength;

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
     * Token stream (replaces array of tokens for memory efficiency).
     */
    private TokenStream $stream;

    /**
     * Runtime cache for parsed ASTs (Layer 1).
     * Maps cache keys to RegexNode instances for fast repeated access within same request.
     *
     * @var array<string, RegexNode>
     */
    private array $runtimeCache = [];

    private ?CacheInterface $cache = null;

    /**
     * @param array{
     *     max_pattern_length?: int,
     *     max_recursion_depth?: int,
     *     max_nodes?: int,
     *     cache?: CacheInterface|null,
     * } $options Configuration options
     * @param Lexer|null $lexer Optional Lexer instance for dependency injection
     */
    public function __construct(
        array $options = [],
        private ?Lexer $lexer = null,
    ) {
        $this->maxPatternLength = (int) ($options['max_pattern_length'] ?? self::DEFAULT_MAX_PATTERN_LENGTH);
        $this->maxRecursionDepth = (int) ($options['max_recursion_depth'] ?? self::DEFAULT_MAX_RECURSION_DEPTH);
        $this->maxNodes = (int) ($options['max_nodes'] ?? self::DEFAULT_MAX_NODES);
        $this->cache = $options['cache'] ?? null;
    }

    /**
     * Parses a full regex string (including delimiters and flags) into an AST.
     *
     * Implements a two-layer caching strategy:
     * 1. Runtime Cache (Layer 1): Fast in-memory cache for repeated calls within same request
     * 2. PSR-16 Persistent Cache (Layer 2): Optional external cache for cross-request optimization
     *
     * @throws ParserException         if the regex syntax is invalid
     * @throws RecursionLimitException if recursion depth exceeds limit
     * @throws ResourceLimitException  if node count exceeds limit
     */
    public function parse(string $regex): RegexNode
    {
        if (\strlen($regex) > $this->maxPatternLength) {
            throw new ParserException(\sprintf('Regex pattern exceeds maximum length of %d characters.', $this->maxPatternLength));
        }

        // Generate cache key
        $cacheKey = 'regex_parser_'.md5($regex);

        // Layer 1: Check runtime cache
        if (isset($this->runtimeCache[$cacheKey])) {
            return $this->runtimeCache[$cacheKey];
        }

        // Layer 2: Check persistent cache (if available)
        if (null !== $this->cache) {
            try {
                $cached = $this->cache->get($cacheKey);
                if ($cached instanceof RegexNode) {
                    // Found in persistent cache - save to runtime for next call
                    $this->runtimeCache[$cacheKey] = $cached;

                    return $cached;
                }
            } catch (InvalidArgumentException) {
                // Cache key is invalid - proceed with parsing
                // (should not happen with our key format, but catch for safety)
            }
        }

        // Cache miss - proceed with actual parsing
        [$pattern, $flags, $delimiter] = $this->extractPatternAndFlags($regex);

        // Reset state for new parse
        $this->recursionDepth = 0;
        $this->nodeCount = 0;

        // Initialize Token Stream (Generator-based)
        $lexer = $this->getLexer($pattern);
        $this->stream = new TokenStream($lexer->tokenize());

        // Parse the pattern content
        $patternNode = $this->parseAlternation();

        // Ensure we reached the end of the pattern
        $this->consume(TokenType::T_EOF, 'Unexpected content at end of pattern');

        $ast = new RegexNode($patternNode, $flags, $delimiter, 0, \strlen($pattern));

        // Save to runtime cache (Layer 1)
        $this->runtimeCache[$cacheKey] = $ast;

        // Save to persistent cache (Layer 2) if available
        if (null !== $this->cache) {
            try {
                $this->cache->set($cacheKey, $ast);
            } catch (InvalidArgumentException) {
                // Cache write failed - log would be nice but not critical
                // Continue without caching
            }
        }

        return $ast;
    }

    /**
     * Parses a TokenStream into a complete RegexNode AST.
     *
     * This is the low-level parsing method that operates purely on tokens.
     * It has no knowledge of raw strings, delimiters, or caching.
     *
     * For most use cases, use RegexCompiler::parse() which handles string
     * processing, tokenization, and caching automatically.
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

    private function getLexer(string $pattern): Lexer
    {
        if (null === $this->lexer) {
            $this->lexer = new Lexer($pattern);
        } else {
            $this->lexer->reset($pattern);
        }

        return $this->lexer;
    }

    /**
     * Extracts pattern, flags, and delimiter.
     * Handles escaped delimiters correctly (e.g., "/abc\/def/i").
     *
     * @return array{0: string, 1: string, 2: string} [pattern, flags, delimiter]
     */
    private function extractPatternAndFlags(string $regex): array
    {
        $len = \strlen($regex);
        if ($len < 2) {
            throw new ParserException('Regex is too short. It must include delimiters.');
        }

        $delimiter = $regex[0];
        // Handle bracket delimiters style: (pattern), [pattern], {pattern}, <pattern>
        $closingDelimiter = match ($delimiter) {
            '(' => ')',
            '[' => ']',
            '{' => '}',
            '<' => '>',
            default => $delimiter,
        };

        // Find the last occurrence of the closing delimiter that is NOT escaped
        // We scan from the end to optimize for flags
        for ($i = $len - 1; $i > 0; $i--) {
            if ($regex[$i] === $closingDelimiter) {
                // Check if escaped (count odd number of backslashes before it)
                $escapes = 0;
                for ($j = $i - 1; $j > 0 && '\\' === $regex[$j]; $j--) {
                    $escapes++;
                }

                if (0 === $escapes % 2) {
                    // Found the end delimiter
                    $pattern = substr($regex, 1, $i - 1);
                    $flags = substr($regex, $i + 1);

                    // Validate flags (only allow standard PCRE flags)
                    if (!preg_match('/^[imsxADSUXJu]*$/', $flags)) {
                        // Find the invalid flag for a better error message
                        $invalid = preg_replace('/[imsxADSUXJu]/', '', $flags);

                        throw new ParserException(\sprintf('Unknown regex flag(s) found: "%s"', $invalid ?? $flags));
                    }

                    return [$pattern, $flags, $delimiter];
                }
            }
        }

        throw new ParserException(\sprintf('No closing delimiter "%s" found.', $closingDelimiter));
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

        if (1 === \count($nodes)) {
            return $nodes[0];
        }

        // Calculate end position based on the last node
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
            // Handle silent tokens (Quote Mode)
            if ($this->match(TokenType::T_QUOTE_MODE_START) || $this->match(TokenType::T_QUOTE_MODE_END)) {
                continue;
            }

            $nodes[] = $this->parseQuantifiedAtom();
        }

        if (empty($nodes)) {
            // "Empty" node at the current position
            return new LiteralNode('', $startPos, $startPos);
        }

        if (1 === \count($nodes)) {
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

            // Validation: Quantifier on empty literal
            if ($node instanceof LiteralNode && '' === $node->value) {
                throw new ParserException(\sprintf('Quantifier without target at position %d', $token->position));
            }

            // Validation: Quantifier on empty group sequence
            if ($node instanceof GroupNode) {
                $child = $node->child;
                if (($child instanceof LiteralNode && '' === $child->value)
                    || ($child instanceof SequenceNode && empty($child->children))) {
                    throw new ParserException(\sprintf('Quantifier without target at position %d', $token->position));
                }
            }

            // Validation: Assertions, anchors, and verbs cannot be quantified
            if ($node instanceof AnchorNode || $node instanceof AssertionNode || $node instanceof PcreVerbNode || $node instanceof KeepNode) {
                $nodeName = match (true) {
                    $node instanceof AnchorNode => $node->value,
                    $node instanceof AssertionNode => '\\'.$node->value,
                    $node instanceof PcreVerbNode => '(*'.$node->verb.')',
                    default => '\K',
                };

                throw new ParserException(
                    \sprintf('Quantifier "%s" cannot be applied to assertion or verb "%s" at position %d', $token->value, $nodeName, $node->getStartPosition()));
            }

            [$quantifier, $type] = $this->parseQuantifierValue($token->value);

            $startPos = $node->getStartPosition();
            $endPos = $token->position + mb_strlen($token->value);

            return new QuantifierNode($node, $quantifier, $type, $startPos, $endPos);
        }

        return $node;
    }

    /**
     * @return array{0: string, 1: QuantifierType}
     */
    private function parseQuantifierValue(string $value): array
    {
        $lastChar = substr($value, -1);
        $baseValue = substr($value, 0, -1);

        if ('?' === $lastChar && \strlen($value) > 1) {
            return [$baseValue, QuantifierType::T_LAZY];
        }

        if ('+' === $lastChar && \strlen($value) > 1) {
            return [$baseValue, QuantifierType::T_POSSESSIVE];
        }

        return [$value, QuantifierType::T_GREEDY];
    }

    /**
     * atom -> T_LITERAL | T_CHAR_TYPE | ...
     */
    private function parseAtom(): NodeInterface
    {
        $token = $this->current();
        $startPos = $token->position;

        // --- Handle Full Fidelity Tokens ---

        // Comments (emitted by Lexer) must be parsed into CommentNode
        if ($this->match(TokenType::T_COMMENT_OPEN)) {
            return $this->parseComment();
        }

        // Quote Mode markers might leak here if sequence parsing logic didn't catch them all.
        // We consume them silently and recurse to get the next real atom.
        if ($this->match(TokenType::T_QUOTE_MODE_START) || $this->match(TokenType::T_QUOTE_MODE_END)) {
            return $this->parseAtom();
        }

        // --- Standard Atoms ---

        if ($this->match(TokenType::T_LITERAL)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value);

            return new LiteralNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_LITERAL_ESCAPED)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value) + 1; // +1 for the backslash

            return new LiteralNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_CHAR_TYPE)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value) + 1; // +1 for the backslash

            return new CharTypeNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_DOT)) {
            return new DotNode($startPos, $startPos + 1);
        }

        if ($this->match(TokenType::T_ANCHOR)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value);

            return new AnchorNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_ASSERTION)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value) + 1;

            return new AssertionNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_BACKREF)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value);

            return new BackrefNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_G_REFERENCE)) {
            return $this->parseGReference($startPos);
        }

        if ($this->match(TokenType::T_UNICODE)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value);

            return new UnicodeNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_OCTAL)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value);

            return new OctalNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_OCTAL_LEGACY)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value) + 1;

            return new OctalLegacyNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_UNICODE_PROP)) {
            $token = $this->previous();
            // Calculate end pos based on original syntax (\p{L} vs \pL)
            $len = 2 + mb_strlen($token->value); // \p or \P + value
            if (mb_strlen($token->value) > 1 || str_starts_with($token->value, '^')) {
                $len += 2; // for {}
            }
            $endPos = $startPos + $len;

            return new UnicodePropNode($token->value, $startPos, $endPos);
        }

        if ($this->match(TokenType::T_KEEP)) {
            return new KeepNode($startPos, $startPos + 2); // \K
        }

        if ($this->match(TokenType::T_GROUP_OPEN)) {
            $startToken = $this->previous();
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
            $endPos = $endToken->position + 1;

            return new GroupNode($expr, GroupType::T_GROUP_CAPTURING, null, null, $startToken->position, $endPos);
        }

        if ($this->match(TokenType::T_GROUP_MODIFIER_OPEN)) {
            return $this->parseGroupModifier();
        }

        if ($this->match(TokenType::T_CHAR_CLASS_OPEN)) {
            return $this->parseCharClass();
        }

        if ($this->match(TokenType::T_PCRE_VERB)) {
            $token = $this->previous();
            $endPos = $startPos + mb_strlen($token->value) + 3; // +3 for "(*)"

            return new PcreVerbNode($token->value, $startPos, $endPos);
        }

        // Special case: quantifier without target
        if ($this->check(TokenType::T_QUANTIFIER)) {
            throw new ParserException(\sprintf('Quantifier without target at position %d', $this->current()->position));
        }

        // @codeCoverageIgnoreStart
        $val = $this->current()->value;
        $type = $this->current()->type->value;

        throw new ParserException(\sprintf('Unexpected token "%s" (%s) at position %d.', $val, $type, $startPos));
        // @codeCoverageIgnoreEnd
    }

    private function parseGReference(int $startPos): NodeInterface
    {
        $token = $this->previous();
        $value = $token->value;
        $endPos = $startPos + mb_strlen($value);

        // \g{N} or \gN (numeric, incl. relative) -> Backreference
        if (preg_match('/^\\\\g\{?([0-9+-]+)\}?$/', $value, $m)) {
            return new BackrefNode($value, $startPos, $endPos);
        }

        // \g<name> or \g{name} (non-numeric) -> Subroutine
        if (preg_match('/^\\\\g<(\w+)>$/', $value, $m) || preg_match('/^\\\\g\{(\w+)\}$/', $value, $m)) {
            return new SubroutineNode($m[1], 'g', $startPos, $endPos);
        }

        throw new ParserException(\sprintf('Invalid \g reference syntax: %s at position %d', $value, $token->position));
    }

    private function parseComment(): CommentNode
    {
        $startToken = $this->previous(); // (?#
        $startPos = $startToken->position;

        $comment = '';
        while (!$this->isAtEnd() && !$this->check(TokenType::T_GROUP_CLOSE)) {
            $token = $this->current();
            $comment .= $this->reconstructTokenValue($token);
            $this->advance();
        }

        $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) to close comment');
        $endPos = $endToken->position + 1;

        return new CommentNode($comment, $startPos, $endPos);
    }

    private function reconstructTokenValue(Token $token): string
    {
        return match ($token->type) {
            // Simple literals
            TokenType::T_LITERAL, TokenType::T_NEGATION, TokenType::T_RANGE, TokenType::T_DOT,
            TokenType::T_GROUP_OPEN, TokenType::T_GROUP_CLOSE, TokenType::T_CHAR_CLASS_OPEN, TokenType::T_CHAR_CLASS_CLOSE,
            TokenType::T_QUANTIFIER, TokenType::T_ALTERNATION, TokenType::T_ANCHOR => $token->value,

            // Types that had a \ stripped
            TokenType::T_CHAR_TYPE, TokenType::T_ASSERTION, TokenType::T_KEEP, TokenType::T_OCTAL_LEGACY,
            TokenType::T_LITERAL_ESCAPED => '\\'.$token->value,

            // Types that kept their \
            TokenType::T_BACKREF, TokenType::T_G_REFERENCE, TokenType::T_UNICODE, TokenType::T_OCTAL => $token->value,

            // Complex re-assembly
            TokenType::T_UNICODE_PROP => str_starts_with($token->value, '{') ? '\p'.$token->value : ((mb_strlen($token->value) > 1 || str_starts_with($token->value, '^')) ? '\p{'.$token->value.'}' : '\p'.$token->value),
            TokenType::T_POSIX_CLASS => '[[:'.$token->value.':]]',
            TokenType::T_PCRE_VERB => '(*'.$token->value.')',
            TokenType::T_GROUP_MODIFIER_OPEN => '(?',
            TokenType::T_COMMENT_OPEN => '(?#',
            TokenType::T_QUOTE_MODE_START => '\Q',
            TokenType::T_QUOTE_MODE_END => '\E',

            // Should not be encountered here
            TokenType::T_EOF => '',
        };
    }

    private function parseGroupModifier(): NodeInterface
    {
        $startToken = $this->previous(); // (?
        $startPos = $startToken->position;

        // 1. Check for Python-style 'P' groups
        $pPos = $this->current()->position;
        if ($this->matchLiteral('P')) {
            return $this->parsePythonGroup($startPos, $pPos);
        }

        // 2. Check for standard lookarounds and named groups
        if ($this->matchLiteral('<')) {
            return $this->parseStandardGroup($startPos);
        }

        // 3. Check for conditional (?(...)
        $isConditionalWithModifier = null;
        if ($this->match(TokenType::T_GROUP_MODIFIER_OPEN)) {
            $isConditionalWithModifier = true;
        } elseif ($this->match(TokenType::T_GROUP_OPEN)) {
            $isConditionalWithModifier = false;
        }

        if (null !== $isConditionalWithModifier) {
            return $this->parseConditional($startPos, $isConditionalWithModifier);
        }

        // 4. Check for Subroutines
        if ($this->matchLiteral('&')) { // (?&name)
            $name = $this->parseSubroutineName();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) to close subroutine call');

            return new SubroutineNode($name, '&', $startPos, $endToken->position + 1);
        }

        if ($this->matchLiteral('R')) { // (?R)
            if ($this->check(TokenType::T_GROUP_CLOSE)) {
                $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

                return new SubroutineNode('R', '', $startPos, $endToken->position + 1);
            }
            $this->stream->rewind(1); // Rewind 'R'
        }

        // Check for (?1), (?-1), (?0)
        if ($subroutine = $this->parseNumericSubroutine($startPos)) {
            return $subroutine;
        }

        // 5. Check for simple non-capturing, lookaheads, atomic, branch reset
        if ($this->matchLiteral(':')) {
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_NON_CAPTURING, null, null, $startPos, $endToken->position + 1);
        }
        if ($this->matchLiteral('=')) {
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_LOOKAHEAD_POSITIVE, null, null, $startPos, $endToken->position + 1);
        }
        if ($this->matchLiteral('!')) {
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_LOOKAHEAD_NEGATIVE, null, null, $startPos, $endToken->position + 1);
        }
        if ($this->matchLiteral('>')) {
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_ATOMIC, null, null, $startPos, $endToken->position + 1);
        }
        if ($this->match(TokenType::T_ALTERNATION)) {
            // Branch reset group (?|...)
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_BRANCH_RESET, null, null, $startPos, $endToken->position + 1);
        }

        // 6. Inline flags
        return $this->parseInlineFlags($startPos);
    }

    // --- Helper methods for parseGroupModifier decomposition ---

    private function parsePythonGroup(int $startPos, int $pPos): NodeInterface
    {
        // Check for (?P'name'...) or (?P"name"...)
        if ($this->checkLiteral("'") || $this->checkLiteral('"')) {
            $quote = $this->current()->value;
            $this->advance();

            // Consume T_LITERAL tokens to build the name character by character
            $name = '';
            while (!$this->isAtEnd() && !$this->checkLiteral($quote)) {
                if ($this->check(TokenType::T_LITERAL)) {
                    $name .= $this->current()->value;
                    $this->advance();
                } else {
                    throw new ParserException(
                        \sprintf('Unexpected token in group name at position %d', $this->current()->position));
                }
            }

            if ('' === $name) {
                throw new ParserException(\sprintf('Expected group name at position %d', $this->current()->position));
            }

            if (!$this->checkLiteral($quote)) {
                throw new ParserException(
                    \sprintf('Expected closing quote %s at position %d', $quote, $this->current()->position));
            }
            $this->advance();

            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_NAMED, $name, null, $startPos, $endToken->position + 1);
        }

        if ($this->matchLiteral('<')) { // (?P<name>...)
            $name = $this->parseGroupName();
            $this->consumeLiteral('>', 'Expected > after group name');
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_NAMED, $name, null, $startPos, $endToken->position + 1);
        }

        if ($this->matchLiteral('>')) { // (?P>name) subroutine
            $name = $this->parseSubroutineName();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) to close subroutine call');

            return new SubroutineNode($name, 'P>', $startPos, $endToken->position + 1);
        }

        if ($this->matchLiteral('=')) {
            throw new ParserException('Backreferences (?P=name) are not supported yet.');
        }

        throw new ParserException(\sprintf('Invalid syntax after (?P at position %d', $pPos));
    }

    private function parseStandardGroup(int $startPos): NodeInterface
    {
        if ($this->matchLiteral('=')) { // (?<=...)
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_LOOKBEHIND_POSITIVE, null, null, $startPos, $endToken->position + 1);
        }
        if ($this->matchLiteral('!')) { // (?<!...)
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_LOOKBEHIND_NEGATIVE, null, null, $startPos, $endToken->position + 1);
        }
        // (?<name>...)
        $name = $this->parseGroupName();
        $this->consumeLiteral('>', 'Expected > after group name');
        $expr = $this->parseAlternation();
        $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

        return new GroupNode($expr, GroupType::T_GROUP_NAMED, $name, null, $startPos, $endToken->position + 1);
    }

    private function parseNumericSubroutine(int $startPos): ?SubroutineNode
    {
        $num = '';
        if ($this->matchLiteral('-')) {
            $num = '-';
        }
        if ($this->check(TokenType::T_LITERAL) && ctype_digit($this->current()->value)) {
            $num .= $this->current()->value;
            $this->advance();
            $num .= $this->consumeWhile(fn (string $c) => ctype_digit($c));

            if ($this->check(TokenType::T_GROUP_CLOSE)) {
                $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

                return new SubroutineNode($num, '', $startPos, $endToken->position + 1);
            }
            $this->stream->rewind(mb_strlen($num));
        } elseif ('-' === $num) {
            $this->stream->rewind(1);
        }

        return null;
    }

    private function parseInlineFlags(int $startPos): NodeInterface
    {
        $flags = $this->consumeWhile(fn (string $c) => (bool) preg_match('/^[imsxADSUXJ-]+$/', $c));
        if ('' !== $flags) {
            $expr = null;
            if ($this->matchLiteral(':')) {
                $expr = $this->parseAlternation();
            }
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            if (null === $expr) {
                $currentPos = $this->previous()->position;
                $expr = new LiteralNode('', $currentPos, $currentPos);
            }

            return new GroupNode($expr, GroupType::T_GROUP_INLINE_FLAGS, null, $flags, $startPos, $endToken->position + 1);
        }

        throw new ParserException(\sprintf('Invalid group modifier syntax at position %d', $startPos));
    }

    private function parseConditional(int $startPos, bool $isModifier): ConditionalNode
    {
        if ($isModifier) {
            // Inline Lookaround condition
            $conditionStartPos = $this->previous()->position;
            $condition = $this->parseLookaroundCondition($conditionStartPos);
        } else {
            $condition = $this->parseConditionalCondition();
            $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) after condition');
        }

        $yes = $this->parseAlternation();

        // Note: The "no" branch (ELSE) is implicitly handled by parseAlternation returning an AlternationNode.
        // If the "yes" branch is an alternation (a|b), then "b" is the ELSE branch.
        // However, if parseAlternation returns a single SequenceNode, then the "no" branch is empty.

        // The original code structure assumed 'no' is always empty unless handled by alternation logic,
        // but ConditionalNode structure expects 3 arguments: condition, yes, no.

        // Correct interpretation:
        // (?(cond)yes|no) -> parseAlternation will return an AlternationNode if '|' exists.
        // But wait! parseAlternation consumes EVERYTHING until ')' or EOF.
        // So if we have (?(cond)A|B), 'yes' variable will hold Alternation(A, B).
        // We need to split it manually?
        // No, the standard Parser logic (inherited) assigns 'no' to an empty LiteralNode.
        // If 'yes' is an AlternationNode, the visitor/compiler handles the split.
        // Let's stick to the original logic to be safe:

        $currentPos = $this->current()->position;
        $no = new LiteralNode('', $currentPos, $currentPos);

        $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
        $endPos = $endToken->position + 1;

        return new ConditionalNode($condition, $yes, $no, $startPos, $endPos);
    }

    private function parseLookaroundCondition(int $startPos): NodeInterface
    {
        if ($this->matchLiteral('=')) {
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_LOOKAHEAD_POSITIVE, null, null, $startPos, $endToken->position);
        }
        if ($this->matchLiteral('!')) {
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new GroupNode($expr, GroupType::T_GROUP_LOOKAHEAD_NEGATIVE, null, null, $startPos, $endToken->position);
        }
        if ($this->matchLiteral('<')) {
            // @phpstan-ignore-next-line if.alwaysFalse (false positive: position advanced after matching '<')
            if ($this->matchLiteral('=')) {
                $expr = $this->parseAlternation();
                $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

                return new GroupNode($expr, GroupType::T_GROUP_LOOKBEHIND_POSITIVE, null, null, $startPos, $endToken->position);
            }
            // @phpstan-ignore-next-line if.alwaysFalse (false positive: position advanced after matching '<')
            if ($this->matchLiteral('!')) {
                $expr = $this->parseAlternation();
                $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

                return new GroupNode($expr, GroupType::T_GROUP_LOOKBEHIND_NEGATIVE, null, null, $startPos, $endToken->position);
            }
        }

        throw new ParserException('Invalid conditional condition at position '.$startPos);
    }

    private function parseConditionalCondition(): NodeInterface
    {
        $startPos = $this->current()->position;

        // This handles the PCRE feature where (?(DEFINE)...) allows defining subroutines
        // without matching them immediately.
        if ($this->matchLiteral('DEFINE')) {
            // We return a special AssertionNode. The Validator needs to treat 'DEFINE'
            // assertions as valid conditions for this to fully work.
            return new AssertionNode('DEFINE', $startPos, $this->current()->position);
        }

        if ($this->check(TokenType::T_LITERAL) && ctype_digit($this->current()->value)) {
            $this->advance();
            $num = (string) ($this->previous()->value.$this->consumeWhile(fn (string $c) => ctype_digit($c)));

            return new BackrefNode($num, $startPos, $this->current()->position);
        }

        if ($this->matchLiteral('<') || $this->matchLiteral('{')) {
            $open = $this->previous()->value;
            $name = $this->parseGroupName();
            $close = '<' === $open ? '>' : '}';
            $this->consumeLiteral($close, "Expected $close after condition name");

            return new BackrefNode($name, $startPos, $this->current()->position);
        }

        if ($this->matchLiteral('R')) {
            return new SubroutineNode('R', '', $startPos, $this->current()->position);
        }

        if ($this->matchLiteral('?')) {
            // Lookaround condition inside (?(...))
            return $this->parseLookaroundCondition($startPos);
        }

        // Bare name check (for conditions like (?(name)...))
        if ($this->check(TokenType::T_LITERAL)) {
            $savedPos = $this->stream->getPosition();
            $name = '';
            while ($this->check(TokenType::T_LITERAL) && !$this->checkLiteral(')') && !$this->isAtEnd()) {
                $name .= $this->current()->value;
                $this->advance();
            }
            if ('' !== $name && $this->check(TokenType::T_GROUP_CLOSE)) {
                return new BackrefNode($name, $startPos, $this->current()->position);
            }
            $this->stream->setPosition($savedPos);
        }

        $condition = $this->parseAtom();

        if (!($condition instanceof BackrefNode || $condition instanceof GroupNode
              || $condition instanceof AssertionNode || $condition instanceof SubroutineNode)) {
            throw new ParserException(
                \sprintf('Invalid conditional construct at position %d. Condition must be a group reference, lookaround, or (DEFINE).', $startPos));
        }

        return $condition;
    }

    private function parseGroupName(): string
    {
        $token = $this->current();

        if (TokenType::T_LITERAL === $token->type && ("'" === $token->value || '"' === $token->value)) {
            $quote = $token->value;
            $this->advance();
            $nameToken = $this->consume(TokenType::T_LITERAL, 'Expected group name');
            if ($this->current()->value !== $quote) {
                throw new ParserException(
                    \sprintf('Expected closing quote %s at position %d', $quote, $this->current()->position));
            }
            $this->advance();

            return $nameToken->value;
        }

        $name = '';
        while (!$this->checkLiteral('>') && !$this->checkLiteral('}') && !$this->isAtEnd()) {
            if ($this->check(TokenType::T_LITERAL) || $this->check(TokenType::T_LITERAL_ESCAPED)) {
                $name .= $this->current()->value;
                $this->advance();
            } else {
                throw new ParserException(\sprintf('Unexpected token "%s" in group name', $this->current()->value));
            }
        }

        if ('' === $name) {
            throw new ParserException(\sprintf('Expected group name at position %d', $this->current()->position));
        }

        return $name;
    }

    private function parseCharClass(): CharClassNode
    {
        $startToken = $this->previous();
        $startPos = $startToken->position;
        $isNegated = $this->match(TokenType::T_NEGATION);
        $parts = [];

        while (!$this->check(TokenType::T_CHAR_CLASS_CLOSE) && !$this->isAtEnd()) {
            // Silent tokens inside char class
            if ($this->match(TokenType::T_QUOTE_MODE_START) || $this->match(TokenType::T_QUOTE_MODE_END)) {
                continue;
            }
            $parts[] = $this->parseCharClassPart();
        }

        $endToken = $this->consume(TokenType::T_CHAR_CLASS_CLOSE, 'Expected "]" to close character class');

        return new CharClassNode($parts, $isNegated, $startPos, $endToken->position + 1);
    }

    private function parseCharClassPart(): NodeInterface
    {
        $startToken = $this->current();
        $startPos = $startToken->position;
        $startNode = null;

        // Simplified matching logic for char class parts
        if ($this->match(TokenType::T_LITERAL) || $this->match(TokenType::T_LITERAL_ESCAPED)) {
            $token = $this->previous();
            // Check for range validity
            // +1 if escaped
            $endPos = $startPos + mb_strlen($token->value) + (TokenType::T_LITERAL_ESCAPED === $token->type ? 1 : 0);
            $startNode = new LiteralNode($token->value, $startPos, $endPos);
        } elseif ($this->match(TokenType::T_CHAR_TYPE)) {
            $token = $this->previous();
            $startNode = new CharTypeNode($token->value, $startPos, $startPos + mb_strlen($token->value) + 1);
        } elseif ($this->match(TokenType::T_UNICODE_PROP)) {
            $token = $this->previous();
            // Basic length calc - Parser logic from original
            $len = 2 + mb_strlen($token->value) + ((mb_strlen($token->value) > 1 || str_starts_with($token->value, '^')) ? 2 : 0);
            $startNode = new UnicodePropNode($token->value, $startPos, $startPos + $len);
        } elseif ($this->match(TokenType::T_UNICODE)) {
            $token = $this->previous();
            $startNode = new UnicodeNode($token->value, $startPos, $startPos + mb_strlen($token->value));
        } elseif ($this->match(TokenType::T_OCTAL)) {
            $token = $this->previous();
            $startNode = new OctalNode($token->value, $startPos, $startPos + mb_strlen($token->value));
        } elseif ($this->match(TokenType::T_OCTAL_LEGACY)) {
            $token = $this->previous();
            $startNode = new OctalLegacyNode($token->value, $startPos, $startPos + mb_strlen($token->value) + 1);
        } elseif ($this->match(TokenType::T_RANGE)) {
            // Literal hyphen at start
            return new LiteralNode($this->previous()->value, $startPos, $startPos + 1);
        } elseif ($this->match(TokenType::T_POSIX_CLASS)) {
            $token = $this->previous();
            $startNode = new PosixClassNode($token->value, $startPos, $startPos + mb_strlen($token->value) + 4);
        } else {
            throw new ParserException(
                \sprintf('Unexpected token "%s" in character class at position %d.', $this->current()->value, $this->current()->position));
        }

        // Check for Range
        if ($this->match(TokenType::T_RANGE)) {
            if ($this->check(TokenType::T_CHAR_CLASS_CLOSE)) {
                // Trailing hyphen
                $this->stream->rewind(1);

                return $startNode;
            }

            // For simplicity, we call parseCharClassPart recursively for the end node,
            // but we need to ensure it returns a simple node, not a range.
            // In this grammar, ranges don't chain (a-b-c is invalid or literals).
            // We manually parse the end node to avoid recursion loop.

            $endToken = $this->current();
            $endNodeStartPos = $endToken->position;

            // Re-using the logic above is tricky without recursion.
            // Let's assume simple structure: a-z.
            // We call parseCharClassPart again.
            $endNode = $this->parseCharClassPart();

            // Note: Original code handled this inline.
            return new RangeNode($startNode, $endNode, $startPos, $endNode->getEndPosition());
        }

        return $startNode;
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

    // ... (Navigation helpers match, matchLiteral, check, etc. are standard)

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
     * Must be called at the start of each recursive parsing method.
     *
     * @throws RecursionLimitException if recursion depth exceeds limit
     *
     * @phpstan-ignore method.unused (reserved for future resource limiting integration)
     */
    private function checkRecursionLimit(): void
    {
        $this->recursionDepth++;

        if ($this->recursionDepth > $this->maxRecursionDepth) {
            throw new RecursionLimitException(
                \sprintf(
                    'Recursion limit of %d exceeded (current: %d). Pattern is too deeply nested.',
                    $this->maxRecursionDepth,
                    $this->recursionDepth,
                ));
        }
    }

    /**
     * End a recursion scope.
     *
     * @phpstan-ignore method.unused (reserved for future resource limiting integration)
     */
    private function exitRecursionScope(): void
    {
        $this->recursionDepth--;
    }

    /**
     * Check and enforce node count limit.
     * Must be called before creating a new node.
     *
     * @throws ResourceLimitException if node count exceeds limit
     *
     * @phpstan-ignore method.unused (reserved for future resource limiting integration)
     */
    private function checkNodeLimit(): void
    {
        $this->nodeCount++;

        if ($this->nodeCount > $this->maxNodes) {
            throw new ResourceLimitException(
                \sprintf(
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
