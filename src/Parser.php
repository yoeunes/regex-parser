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
use RegexParser\Exception\SyntaxErrorException;

/**
 * High-performance recursive descent parser for regex patterns.
 *
 * This optimized parser uses intelligent caching, reduced method calls, and
 * streamlined parsing logic for maximum performance while maintaining full
 * compatibility with PCRE syntax.
 */
final class Parser
{
    private const INLINE_FLAG_CHARS = 'imsxUJn-';
    private const MAX_RECURSION_DEPTH = 1024;

    private TokenStream $stream;

    private string $pattern = '';

    private string $flags = '';

    private bool $JModifier = false;

    private bool $inQuoteMode = false;

    /**
     * @var array<string, bool>
     */
    private array $groupNames = [];

    // Performance optimizations
    private ?Token $currentToken = null;

    private bool $currentTokenValid = false;

    private int $lastPosition = -1;

    // State tracking
    private bool $lastTokenWasAlternation = false;

    private int $lastInlineFlagsLength = 0;

    private int $recursionDepth = 0;

    /**
     * @var array<int|string, bool>
     */
    private static array $supportsInlineModifierR = [];

    private readonly int $maxRecursionDepth;

    private readonly int $phpVersionId;

    private readonly bool $useRuntimePcreDetection;

    public function __construct(?int $maxRecursionDepth = null, ?int $phpVersionId = null)
    {
        $this->maxRecursionDepth = $maxRecursionDepth ?? self::MAX_RECURSION_DEPTH;
        $this->phpVersionId = $phpVersionId ?? \PHP_VERSION_ID;
        $this->useRuntimePcreDetection = null === $phpVersionId;
    }

    public function parse(TokenStream $stream, string $flags = '', string $delimiter = '/', int $patternLength = 0): Node\RegexNode
    {
        $this->stream = $stream;
        $this->pattern = $stream->getPattern();
        $this->flags = $flags;
        $this->JModifier = str_contains($flags, 'J');
        $this->inQuoteMode = false;
        $this->groupNames = [];
        $this->lastTokenWasAlternation = false;
        $this->lastInlineFlagsLength = 0;
        $this->recursionDepth = 0;

        // Reset performance caches
        $this->currentToken = null;
        $this->currentTokenValid = false;
        $this->lastPosition = -1;

        $patternNode = $this->parseAlternation();
        $this->consume(TokenType::T_EOF, 'Unexpected content at end of pattern');

        return new Node\RegexNode($patternNode, $flags, $delimiter, 0, $patternLength);
    }

    private function parseAlternation(): Node\NodeInterface
    {
        $this->guardRecursionDepth($this->current()->position);
        $this->recursionDepth++;

        try {
            $startPosition = $this->current()->position;
            $nodes = [$this->parseSequence()];

            while ($this->match(TokenType::T_ALTERNATION)) {
                $this->lastTokenWasAlternation = true;
                $nodes[] = $this->parseSequence();
            }

            if (1 === \count($nodes)) {
                return $nodes[0];
            }

            $endPosition = end($nodes)->getEndPosition();

            return new Node\AlternationNode($nodes, $startPosition, $endPosition);
        } finally {
            $this->recursionDepth--;
        }
    }

    private function parseSequence(): Node\NodeInterface
    {
        $nodes = [];
        $startPosition = $this->current()->position;

        while (!$this->isAtEnd() && !$this->check(TokenType::T_GROUP_CLOSE) && !$this->check(TokenType::T_ALTERNATION)) {
            if ($this->match(TokenType::T_QUOTE_MODE_START)) {
                $this->inQuoteMode = true;

                continue;
            }
            if ($this->match(TokenType::T_QUOTE_MODE_END)) {
                $this->inQuoteMode = false;

                continue;
            }

            // In extended (/x) mode, consume whitespace and line comments as
            // explicit nodes where appropriate so we can preserve them when
            // reconstructing the pattern.
            if ($this->consumeExtendedModeContent($nodes)) {
                continue;
            }

            $nodes[] = $this->parseQuantifiedAtom();
        }

        if (empty($nodes)) {
            return $this->createEmptyLiteralNodeAt($startPosition);
        }

        if (1 === \count($nodes)) {
            return $nodes[0];
        }

        $endPosition = end($nodes)->getEndPosition();

        return new Node\SequenceNode($nodes, $startPosition, $endPosition);
    }

    /**
     * Consume extended-mode (/x) whitespace and comments at the current
     * position, adding any comments as CommentNode instances into the
     * provided node list. This is used at the sequence level so that /x
     * comments are preserved in the AST with accurate positions.
     *
     * @param list<Node\NodeInterface> $nodes
     */
    private function consumeExtendedModeContent(array &$nodes): bool
    {
        if (!str_contains($this->flags, 'x') || $this->inQuoteMode) {
            return false;
        }

        $skipped = false;
        while (!$this->isAtEnd() && !$this->check(TokenType::T_GROUP_CLOSE) && !$this->check(TokenType::T_ALTERNATION)) {
            $token = $this->current();
            if (TokenType::T_LITERAL !== $token->type) {
                break;
            }

            // Skip pure whitespace silently; comments will be explicit nodes.
            if (ctype_space($token->value)) {
                $this->advance();
                $skipped = true;

                continue;
            }

            // Line comment starting with # until end-of-line.
            if ('#' === $token->value) {
                $nodes[] = $this->parseExtendedComment();
                $skipped = true;

                continue;
            }

            break;
        }

        return $skipped;
    }

    /**
     * Parse an extended-mode line comment (starting at '#') into a CommentNode,
     * preserving the exact text and byte offsets.
     */
    private function parseExtendedComment(): Node\CommentNode
    {
        $startToken = $this->current(); // '#'
        $startPosition = $startToken->position;

        $comment = $this->reconstructTokenValue($startToken);
        $this->advance();

        while (!$this->isAtEnd()) {
            $token = $this->current();

            // Comment ends at newline (included) or at end of pattern.
            if (TokenType::T_LITERAL === $token->type && "\n" === $token->value) {
                $comment .= $this->reconstructTokenValue($token);
                $this->advance();

                break;
            }

            $comment .= $this->reconstructTokenValue($token);
            $this->advance();
        }

        $endPosition = $startPosition + \strlen($comment);

        return new Node\CommentNode($comment, $startPosition, $endPosition);
    }

    /**
     * Skip extended-mode (/x) whitespace and comments *without* producing
     * nodes. This is used where the parser needs to see through trivia,
     * for example between an atom and its following quantifier.
     */
    private function skipExtendedModeContent(): int
    {
        if (!str_contains($this->flags, 'x') || $this->inQuoteMode) {
            return 0;
        }

        $skipped = 0;
        while (!$this->isAtEnd() && !$this->check(TokenType::T_GROUP_CLOSE) && !$this->check(TokenType::T_ALTERNATION)) {
            $token = $this->current();
            if (TokenType::T_LITERAL !== $token->type) {
                break;
            }

            if (ctype_space($token->value)) {
                $this->advance();
                $skipped++;

                continue;
            }

            if ('#' === $token->value) {
                $this->advance();
                $skipped++;
                while (!$this->isAtEnd() && "\n" !== $this->current()->value) {
                    $this->advance();
                    $skipped++;
                }
                if (!$this->isAtEnd() && "\n" === $this->current()->value) {
                    $this->advance();
                    $skipped++;
                }

                continue;
            }

            break;
        }

        return $skipped;
    }

    private function parseQuantifiedAtom(): Node\NodeInterface
    {
        $node = $this->parseAtom();

        $skipped = $this->skipExtendedModeContent();

        if ($this->match(TokenType::T_QUANTIFIER)) {
            $token = $this->previous();

            $this->assertQuantifierCanApply($node, $token);

            [$quantifier, $type] = $this->parseQuantifierValue($token->value);

            $startPosition = $node->getStartPosition();
            $endPosition = $token->position + \strlen($token->value);

            return new Node\QuantifierNode($node, $quantifier, $type, $startPosition, $endPosition);
        }

        if ($skipped > 0) {
            $this->stream->rewind($skipped);
            $this->currentTokenValid = false;
        }

        return $node;
    }

    /**
     * @return array{0: string, 1: Node\QuantifierType}
     */
    private function parseQuantifierValue(string $value): array
    {
        $lastChar = substr($value, -1);
        $baseValue = substr($value, 0, -1);

        if ('?' === $lastChar && \strlen($value) > 1) {
            return [$baseValue, Node\QuantifierType::T_LAZY];
        }

        if ('+' === $lastChar && \strlen($value) > 1) {
            return [$baseValue, Node\QuantifierType::T_POSSESSIVE];
        }

        return [$value, Node\QuantifierType::T_GREEDY];
    }

    private function assertQuantifierCanApply(Node\NodeInterface $node, Token $token): void
    {
        if ($this->isEmptyNode($node)) {
            throw $this->parserException(
                \sprintf('Quantifier without target at position %d', $token->position),
                $token->position,
            );
        }

        if ($this->isAssertionNode($node)) {
            $nodeName = $this->getAssertionNodeName($node);

            throw $this->parserException(
                \sprintf('Quantifier "%s" cannot be applied to assertion or verb "%s" at position %d',
                    $token->value, $nodeName, $node->getStartPosition()),
                $token->position,
            );
        }
    }

    private function isEmptyNode(Node\NodeInterface $node): bool
    {
        return ($node instanceof Node\LiteralNode && '' === $node->value)
            || ($node instanceof Node\GroupNode && $this->isEmptyGroup($node))
            || ($node instanceof Node\SequenceNode && empty($node->children));
    }

    private function isAssertionNode(Node\NodeInterface $node): bool
    {
        return $node instanceof Node\AnchorNode
            || $node instanceof Node\AssertionNode
            || $node instanceof Node\PcreVerbNode
            || $node instanceof Node\KeepNode;
    }

    private function getAssertionNodeName(Node\NodeInterface $node): string
    {
        return match (true) {
            $node instanceof Node\AnchorNode => $node->value,
            $node instanceof Node\AssertionNode => '\\'.$node->value,
            $node instanceof Node\PcreVerbNode => '(*'.$node->verb.')',
            default => '\K',
        };
    }

    private function isEmptyGroup(Node\GroupNode $node): bool
    {
        $child = $node->child;

        return ($child instanceof Node\LiteralNode && '' === $child->value)
            || ($child instanceof Node\SequenceNode && empty($child->children));
    }

    private function parseAtom(): Node\NodeInterface
    {
        $token = $this->current();
        $startPosition = $token->position;

        if ($this->match(TokenType::T_COMMENT_OPEN)) {
            return $this->parseComment();
        }

        if ($this->match(TokenType::T_CALLOUT)) {
            return $this->parseCallout();
        }

        if ($this->match(TokenType::T_QUOTE_MODE_START)) {
            $this->inQuoteMode = true;

            return $this->parseAtom();
        }
        if ($this->match(TokenType::T_QUOTE_MODE_END)) {
            $this->inQuoteMode = false;

            return $this->parseAtom();
        }

        if (null !== $node = $this->parseSimpleAtom($startPosition)) {
            return $node;
        }

        if (null !== $node = $this->parseGroupOrCharClassAtom()) {
            return $node;
        }

        if (null !== $node = $this->parseVerbAtom($startPosition)) {
            return $node;
        }

        if ($this->check(TokenType::T_QUANTIFIER)) {
            throw $this->parserException(
                \sprintf('Quantifier without target at position %d', $this->current()->position),
                $this->current()->position,
            );
        }

        $val = $this->current()->value;
        $type = $this->current()->type->value;

        throw $this->parserException(
            \sprintf('Unexpected token "%s" (%s) at position %d.', $val, $type, $startPosition),
            $startPosition,
        );
    }

    private function parseSimpleAtom(int $startPosition): ?Node\NodeInterface
    {
        if ($this->match(TokenType::T_LITERAL)) {
            $token = $this->previous();
            $endPosition = $startPosition + \strlen($token->value);

            return new Node\LiteralNode($token->value, $startPosition, $endPosition);
        }

        if ($this->match(TokenType::T_LITERAL_ESCAPED)) {
            $token = $this->previous();
            $endPosition = $startPosition + \strlen($token->value) + 1; // +1 for the backslash

            return new Node\LiteralNode($token->value, $startPosition, $endPosition);
        }

        if ($this->match(TokenType::T_CHAR_TYPE)) {
            $token = $this->previous();
            $endPosition = $startPosition + \strlen($token->value) + 1; // +1 for the backslash

            return new Node\CharTypeNode($token->value, $startPosition, $endPosition);
        }

        if ($this->match(TokenType::T_DOT)) {
            return new Node\DotNode($startPosition, $startPosition + 1);
        }

        if ($this->match(TokenType::T_ANCHOR)) {
            $token = $this->previous();
            $endPosition = $startPosition + \strlen($token->value);

            return new Node\AnchorNode($token->value, $startPosition, $endPosition);
        }

        if ($this->match(TokenType::T_ASSERTION)) {
            $token = $this->previous();
            $endPosition = $startPosition + \strlen($token->value) + 1;

            return new Node\AssertionNode($token->value, $startPosition, $endPosition);
        }

        if ($this->match(TokenType::T_BACKREF)) {
            $token = $this->previous();
            $endPosition = $startPosition + \strlen($token->value);

            return new Node\BackrefNode($token->value, $startPosition, $endPosition);
        }

        if ($this->match(TokenType::T_G_REFERENCE)) {
            return $this->parseGReference($startPosition);
        }

        if ($this->match(TokenType::T_UNICODE)) {
            return $this->createCharLiteralNodeFromToken($this->previous(), TokenType::T_UNICODE, $startPosition);
        }

        if ($this->match(TokenType::T_UNICODE_NAMED)) {
            return $this->createCharLiteralNodeFromToken(
                $this->previous(),
                TokenType::T_UNICODE_NAMED,
                $startPosition,
            );
        }

        if ($this->match(TokenType::T_CONTROL_CHAR)) {
            $token = $this->previous();
            $endPosition = $startPosition + 2 + \strlen($token->value); // \cX (single codepoint)
            $codePoint = $this->parseControlCharCodePoint($token->value);

            return new Node\ControlCharNode($token->value, $codePoint, $startPosition, $endPosition);
        }

        if ($this->match(TokenType::T_OCTAL)) {
            return $this->createCharLiteralNodeFromToken($this->previous(), TokenType::T_OCTAL, $startPosition);
        }

        if ($this->match(TokenType::T_OCTAL_LEGACY)) {
            return $this->createCharLiteralNodeFromToken(
                $this->previous(),
                TokenType::T_OCTAL_LEGACY,
                $startPosition,
            );
        }

        if ($this->match(TokenType::T_UNICODE_PROP)) {
            $token = $this->previous();
            // Calculate end pos based on original syntax (\p{L} vs \pL)
            $len = 2 + \strlen($token->value); // \p or \P + value
            if (\strlen($token->value) > 1 || str_starts_with($token->value, '^')) {
                $len += 2; // for {}
            }
            $endPosition = $startPosition + $len;

            return new Node\UnicodePropNode($token->value, str_starts_with($token->value, '{'), $startPosition, $endPosition);
        }

        if ($this->match(TokenType::T_KEEP)) {
            return new Node\KeepNode($startPosition, $startPosition + 2); // \K
        }

        return null;
    }

    /**
     * Transforms a stream of Tokens into an Abstract Syntax Tree (AST).
     * Implements a Recursive Descent Parser based on PCRE grammar.
     */
    private function parseGroupOrCharClassAtom(): ?Node\NodeInterface
    {
        if ($this->match(TokenType::T_GROUP_OPEN)) {
            $startToken = $this->previous();
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return $this->createGroupNode(
                $expr,
                Node\GroupType::T_GROUP_CAPTURING,
                $startToken->position,
                $endToken,
            );
        }

        if ($this->match(TokenType::T_GROUP_MODIFIER_OPEN)) {
            return $this->parseGroupModifier();
        }

        if ($this->match(TokenType::T_CHAR_CLASS_OPEN)) {
            return $this->parseCharClass();
        }

        return null;
    }

    private function parseVerbAtom(int $startPosition): ?Node\NodeInterface
    {
        if (!$this->match(TokenType::T_PCRE_VERB)) {
            return null;
        }

        $token = $this->previous();
        $endPosition = $startPosition + \strlen($token->value) + 3; // +3 for "(*)"

        return new Node\PcreVerbNode($token->value, $startPosition, $endPosition);
    }

    /**
     * parses callouts like (?C), (?C1), (?C"name"), (?C"string"), and (?Cname)
     */
    private function parseCallout(): Node\CalloutNode
    {
        $token = $this->previous();
        $startPosition = $token->position;
        $value = $token->value;
        $endPosition = $startPosition + \strlen($token->value) + 4; // for (?C)

        if ('' === $value) {
            return new Node\CalloutNode(null, false, $startPosition, $endPosition);
        }

        $isStringIdentifier = false;
        $identifier = null;
        if (preg_match('/^"([^"]*+)"$/', $value, $matches)) {
            $identifier = $matches[1];
            $isStringIdentifier = true;
        } elseif (ctype_digit($value)) {
            $identifier = (int) $value;
        } elseif (preg_match('/^[A-Z_a-z]\w*+$/', $value)) {
            $identifier = $value;
        } else {
            throw $this->parserException(
                \sprintf('Invalid callout argument: %s at position %d', $value, $startPosition),
                $startPosition,
            );
        }

        return new Node\CalloutNode($identifier, $isStringIdentifier, $startPosition, $endPosition);
    }

    /**
     * parses \g references (backreferences and subroutines)
     */
    private function parseGReference(int $startPosition): Node\NodeInterface
    {
        $token = $this->previous();
        $value = $token->value;
        $endPosition = $startPosition + \strlen($value);

        // \g{N} or \gN (numeric, incl. relative) -> Backreference
        if (preg_match('/^\\\\g\{?([0-9+-]++)\}?$/', $value, $m)) {
            return new Node\BackrefNode($value, $startPosition, $endPosition);
        }

        // \g<name> or \g{name} (non-numeric) -> Subroutine
        if (
            preg_match('/^\\\\g<(\w++)>$/', $value, $m)
            || preg_match('/^\\\\g\{(\w++)\}$/', $value, $m)
        ) {
            return new Node\SubroutineNode($m[1], 'g', $startPosition, $endPosition);
        }

        throw $this->parserException(
            \sprintf('Invalid \\g reference syntax: %s at position %d', $value, $token->position),
            $token->position,
        );
    }

    /**
     * parses comments like (?# this is a comment )
     */
    private function parseComment(): Node\CommentNode
    {
        $startToken = $this->previous(); // (?#
        $startPosition = $startToken->position;

        $comment = '';
        while (
            !$this->isAtEnd()
            && !$this->check(TokenType::T_GROUP_CLOSE)
        ) {
            $token = $this->current();
            $comment .= $this->reconstructTokenValue($token);
            $this->advance();
        }

        $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) to close comment');
        $endPosition = $endToken->position + 1;

        return new Node\CommentNode($comment, $startPosition, $endPosition);
    }

    /**
     * Reconstructs the original string representation of a token.
     */
    private function reconstructTokenValue(Token $token): string
    {
        return match ($token->type) {
            // Simple literals
            TokenType::T_LITERAL,
            TokenType::T_NEGATION,
            TokenType::T_RANGE,
            TokenType::T_DOT,
            TokenType::T_GROUP_OPEN,
            TokenType::T_GROUP_CLOSE,
            TokenType::T_CHAR_CLASS_OPEN,
            TokenType::T_CHAR_CLASS_CLOSE,
            TokenType::T_QUANTIFIER,
            TokenType::T_ALTERNATION,
            TokenType::T_ANCHOR => $token->value,

            // Types that had a \ stripped
            TokenType::T_CHAR_TYPE,
            TokenType::T_ASSERTION,
            TokenType::T_KEEP,
            TokenType::T_OCTAL_LEGACY,
            TokenType::T_LITERAL_ESCAPED => '\\'.$token->value,

            // Types that kept their \
            TokenType::T_BACKREF,
            TokenType::T_G_REFERENCE,
            TokenType::T_UNICODE => $token->value,
            TokenType::T_UNICODE_NAMED => '\\N{'.$token->value.'}',
            TokenType::T_OCTAL => $token->value,

            // Complex re-assembly
            TokenType::T_CALLOUT => '(?C'.$token->value.')',
            TokenType::T_UNICODE_PROP => str_starts_with($token->value, '{')
                ? '\p'.$token->value
                : ((\strlen($token->value) > 1 || str_starts_with($token->value, '^'))
                    ? '\p{'.$token->value.'}'
                    : '\p'.$token->value),
            TokenType::T_POSIX_CLASS => '[[:'.$token->value.':]]',
            TokenType::T_PCRE_VERB => '(*'.$token->value.')',
            TokenType::T_GROUP_MODIFIER_OPEN => '(?',
            TokenType::T_COMMENT_OPEN => '(?#',
            TokenType::T_QUOTE_MODE_START => '\Q',
            TokenType::T_QUOTE_MODE_END => '\E',
            TokenType::T_CONTROL_CHAR => '\\c'.$token->value,
            TokenType::T_CLASS_INTERSECTION => '&&',
            TokenType::T_CLASS_SUBTRACTION => '--',

            // Should not be encountered here
            TokenType::T_EOF => '',
        };
    }

    private function createCharLiteralNodeFromToken(Token $token, TokenType $type, int $startPosition): Node\CharLiteralNode
    {
        [$representation, $charType] = match ($type) {
            TokenType::T_UNICODE => [$token->value, Node\CharLiteralType::UNICODE],
            TokenType::T_UNICODE_NAMED => ['\\N{'.$token->value.'}', Node\CharLiteralType::UNICODE_NAMED],
            TokenType::T_OCTAL => [$token->value, Node\CharLiteralType::OCTAL],
            TokenType::T_OCTAL_LEGACY => ['\\'.$token->value, Node\CharLiteralType::OCTAL_LEGACY],
            default => throw new \InvalidArgumentException('Unsupported character literal token type.'),
        };

        return new Node\CharLiteralNode(
            $representation,
            $this->parseCharLiteralCodePoint($representation, $charType),
            $charType,
            $startPosition,
            $startPosition + \strlen($representation),
        );
    }

    private function parseCharLiteralCodePoint(string $representation, Node\CharLiteralType $type): int
    {
        return match ($type) {
            Node\CharLiteralType::UNICODE => $this->parseUnicodeCodePoint($representation),
            Node\CharLiteralType::UNICODE_NAMED => $this->parseNamedUnicodeCodePoint($representation),
            Node\CharLiteralType::OCTAL,
            Node\CharLiteralType::OCTAL_LEGACY => $this->parseOctalCodePoint($representation),
        };
    }

    private function parseUnicodeCodePoint(string $representation): int
    {
        if (preg_match('/^\\\\x([0-9a-fA-F]{2})$/', $representation, $matches)) {
            return (int) hexdec($matches[1]);
        }

        if (preg_match('/^\\\\[xu]\\{([0-9a-fA-F]++)\\}$/', $representation, $matches)) {
            return (int) hexdec($matches[1]);
        }

        return -1;
    }

    private function parseNamedUnicodeCodePoint(string $representation): int
    {
        if (!preg_match('/^\\\\N\\{(.+)}$/', $representation, $matches)) {
            return -1;
        }

        $name = $matches[1];
        if (class_exists(\IntlChar::class)) {
            $char = \IntlChar::charFromName($name);
            if (null !== $char) {
                return (int) \IntlChar::ord($char);
            }
        }

        return -1;
    }

    private function parseOctalCodePoint(string $representation): int
    {
        if (preg_match('/^\\\\o\\{([0-7]++)\\}$/', $representation, $matches)) {
            return (int) octdec($matches[1]);
        }

        if (preg_match('/^\\\\([0-7]{1,3})$/', $representation, $matches)) {
            return (int) octdec($matches[1]);
        }

        return -1;
    }

    private function parseControlCharCodePoint(string $char): int
    {
        if ('' === $char) {
            return -1;
        }

        return \ord(strtoupper($char)) ^ 64;
    }

    /**
     * parses group modifiers like (?=...), (?!...), (?<=...), (?<!...), (?P<name>...), (?P'name'...), (?'name'...),
     * (?P=name), (?:...), (?(...)), (?&name), (?R), (?1), (?-1), (?0), and inline flags.
     */
    private function parseGroupModifier(): Node\NodeInterface
    {
        $startToken = $this->previous(); // (?
        $startPosition = $startToken->position;

        // 1. Check for Python-style 'P' groups
        $pPos = $this->current()->position;
        if ($this->matchLiteral('P')) {
            return $this->parsePythonGroup($startPosition, $pPos);
        }

        // 2. Check for PCRE verbs: (*...)
        if ($this->matchLiteral('*')) {
            return $this->parsePcreVerbInGroup($startPosition);
        }

        // 2.1 PCRE verbs already tokenized inside modifier groups: (?(*VERB)...)
        if ($this->match(TokenType::T_PCRE_VERB)) {
            return $this->parsePcreVerbTokenInGroup($startPosition, $this->previous());
        }

        // 3. PCRE-style quoted named groups (?'name'...)
        if ($this->checkLiteral("'")) {
            $name = $this->parseGroupName($startPosition);
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return $this->createGroupNode(
                $expr,
                Node\GroupType::T_GROUP_NAMED,
                $startPosition,
                $endToken,
                $name,
            );
        }

        // 4. Check for standard lookarounds and named groups
        if ($this->matchLiteral('<')) {
            return $this->parseStandardGroup($startPosition);
        }

        // 5. Check for conditional (?(...)
        $isConditionalWithModifier = null;
        if ($this->match(TokenType::T_GROUP_MODIFIER_OPEN)) {
            $isConditionalWithModifier = true;
        } elseif ($this->match(TokenType::T_GROUP_OPEN)) {
            $isConditionalWithModifier = false;
        }

        if (null !== $isConditionalWithModifier) {
            return $this->parseConditional($startPosition, $isConditionalWithModifier);
        }

        // 6. Check for Subroutines
        if ($this->matchLiteral('&')) { // (?&name)
            $name = $this->parseSubroutineName();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) to close subroutine call');

            return new Node\SubroutineNode($name, '&', $startPosition, $endToken->position + 1);
        }

        if ($this->matchLiteral('R')) { // (?R)
            if ($this->check(TokenType::T_GROUP_CLOSE)) {
                $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

                return new Node\SubroutineNode('R', '', $startPosition, $endToken->position + 1);
            }
            $this->stream->rewind(1); // Rewind 'R'
        }

        // Check for (?1), (?-1), (?0)
        if ($subroutine = $this->parseNumericSubroutine($startPosition)) {
            return $subroutine;
        }

        // 7. Check for simple non-capturing, lookaheads, atomic, branch reset
        if ($this->matchLiteral(':')) {
            return $this->parseSimpleGroup($startPosition, Node\GroupType::T_GROUP_NON_CAPTURING);
        }

        if ($this->matchLiteral('=')) {
            return $this->parseSimpleGroup($startPosition, Node\GroupType::T_GROUP_LOOKAHEAD_POSITIVE);
        }

        if ($this->matchLiteral('!')) {
            return $this->parseSimpleGroup($startPosition, Node\GroupType::T_GROUP_LOOKAHEAD_NEGATIVE);
        }

        if ($this->matchLiteral('>')) {
            return $this->parseSimpleGroup($startPosition, Node\GroupType::T_GROUP_ATOMIC);
        }

        if ($this->match(TokenType::T_ALTERNATION)) {
            // Branch reset group (?|...)
            return $this->parseSimpleGroup($startPosition, Node\GroupType::T_GROUP_BRANCH_RESET);
        }

        // 8. Inline flags
        return $this->parseInlineFlags($startPosition);
    }

    /**
     * Parses PCRE verbs in group context: (?(*VERB)...)
     */
    private function parsePcreVerbInGroup(int $startPosition): Node\NodeInterface
    {
        $verb = '';
        $verbStartPosition = $this->current()->position;

        // Collect verb name characters until we hit : or )
        while (
            !$this->isAtEnd()
            && !$this->check(TokenType::T_GROUP_CLOSE)
            && !$this->checkLiteral(':')
        ) {
            if ($this->check(TokenType::T_LITERAL)) {
                $verb .= $this->current()->value;
                $this->advance();
            } else {
                break;
            }
        }

        // Check for verbs with arguments like MARK:name
        $argument = '';
        if ($this->matchLiteral(':')) {
            while (
                !$this->isAtEnd()
                && !$this->check(TokenType::T_GROUP_CLOSE)
            ) {
                if ($this->check(TokenType::T_LITERAL)) {
                    $argument .= $this->current()->value;
                    $this->advance();
                } else {
                    break;
                }
            }
        }

        $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) to close PCRE verb');
        $endPosition = $endToken->position + 1;

        // Parse the rest of the pattern after the verb group
        $expr = null;
        if (!$this->isAtEnd()) {
            $expr = $this->parseAlternation();
        } else {
            $expr = $this->createEmptyLiteralNodeAt($endPosition);
        }

        // Create a group node containing the verb and the following expression
        $verbNode = new Node\PcreVerbNode(
            '' !== $argument ? $verb.':'.$argument : $verb,
            $verbStartPosition,
            $endPosition,
        );

        // Create a sequence with the verb and the expression
        return new Node\SequenceNode(
            [$verbNode, $expr],
            $startPosition,
            $expr->getEndPosition(),
        );
    }

    /**
     * Parses a PCRE verb token inside a modifier group: (?(*VERB)...)
     */
    private function parsePcreVerbTokenInGroup(int $startPosition, Token $verbToken): Node\NodeInterface
    {
        $verbStartPosition = $verbToken->position;
        $verbEndPosition = $verbStartPosition + \strlen($verbToken->value) + 3; // +3 for "(*)"

        $verbNode = new Node\PcreVerbNode($verbToken->value, $verbStartPosition, $verbEndPosition);

        $expr = $this->parseAlternation();
        $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) to close PCRE verb group');

        return new Node\SequenceNode(
            [$verbNode, $expr],
            $startPosition,
            $expr->getEndPosition(),
        );
    }

    /**
     * Parses Python-style named groups and subroutines like
     * (?P'name'...), (?P"name"...), (?P<name>...), (?P>name), and (?P=name).
     */
    private function parsePythonGroup(int $startPos, int $pPos): Node\NodeInterface
    {
        // Check for (?P'name'...) or (?P"name"...)
        if ($this->checkLiteral("'") || $this->checkLiteral('"')) {
            $quote = $this->current()->value;
            $this->advance();

            // Consume T_LITERAL tokens to build the name character by character
            $name = '';
            while (
                !$this->isAtEnd()
                && !$this->checkLiteral($quote)
            ) {
                if ($this->check(TokenType::T_LITERAL)) {
                    $name .= $this->current()->value;
                    $this->advance();
                } else {
                    if ($this->check(TokenType::T_GROUP_CLOSE)) {
                        break;
                    }

                    throw $this->parserException(
                        \sprintf('Unexpected token in group name at position %d', $this->current()->position),
                        $this->current()->position,
                    );
                }
            }

            if ('' === $name) {
                throw $this->parserException(
                    \sprintf('Expected group name at position %d', $this->current()->position),
                    $this->current()->position,
                );
            }

            if (!$this->checkLiteral($quote)) {
                throw $this->parserException(
                    \sprintf('Expected closing quote %s at position %d', $quote, $this->current()->position),
                    $this->current()->position,
                );
            }
            $this->advance();

            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return $this->createGroupNode(
                $expr,
                Node\GroupType::T_GROUP_NAMED,
                $startPos,
                $endToken,
                $name,
            );
        }

        if ($this->matchLiteral('<')) { // (?P<name>...)
            $name = $this->parseGroupName($pPos);
            $this->consumeLiteral('>', 'Expected > after group name');
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return $this->createGroupNode(
                $expr,
                Node\GroupType::T_GROUP_NAMED,
                $startPos,
                $endToken,
                $name,
            );
        }

        if ($this->matchLiteral('>')) { // (?P>name) subroutine
            $name = $this->parseSubroutineName();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected ) to close subroutine call');

            return new Node\SubroutineNode($name, 'P>', $startPos, $endToken->position + 1);
        }

        if ($this->matchLiteral('=')) {
            $name = $this->parseGroupName($this->current()->position, false);
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return new Node\BackrefNode('\\k<'.$name.'>', $startPos, $endToken->position + 1);
        }

        throw $this->parserException(
            \sprintf('Invalid syntax after (?P at position %d', $pPos),
            $pPos,
        );
    }

    /**
     * Parses standard groups like (?<=...), (?<!...), and (?<name>...).
     */
    private function parseStandardGroup(int $startPos): Node\NodeInterface
    {
        if ($this->matchLiteral('=')) { // (?<=...)
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return $this->createGroupNode(
                $expr,
                Node\GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
                $startPos,
                $endToken,
            );
        }

        if ($this->matchLiteral('!')) { // (?<!...)
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return $this->createGroupNode(
                $expr,
                Node\GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
                $startPos,
                $endToken,
            );
        }

        // (?<name>...)
        $name = $this->parseGroupName($startPos);
        $this->consumeLiteral('>', 'Expected > after group name');
        $expr = $this->parseAlternation();
        $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

        return $this->createGroupNode(
            $expr,
            Node\GroupType::T_GROUP_NAMED,
            $startPos,
            $endToken,
            $name,
        );
    }

    /**
     * Parses numeric subroutine calls like (?1), (?-1), (?0).
     */
    private function parseNumericSubroutine(int $startPos): ?Node\SubroutineNode
    {
        $tokensConsumed = 0;
        $num = '';

        if ($this->matchLiteral('-')) {
            $num = '-';
            $tokensConsumed++;
        }

        if ($this->isLiteralDigitToken()) {
            $num .= $this->current()->value;
            $this->advance();
            $tokensConsumed++;

            // Consume additional digits
            while ($this->check(TokenType::T_LITERAL) && ctype_digit($this->current()->value)) {
                $num .= $this->current()->value;
                $this->advance();
                $tokensConsumed++;
            }

            if ($this->check(TokenType::T_GROUP_CLOSE)) {
                $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

                return new Node\SubroutineNode($num, '', $startPos, $endToken->position + 1);
            }

            // Not a valid subroutine, rewind all consumed tokens
            $this->stream->rewind($tokensConsumed);
            $this->currentTokenValid = false;
        } elseif ('-' === $num) {
            // Only consumed the minus sign, rewind it
            $this->stream->rewind(1);
            $this->currentTokenValid = false;
        }

        return null;
    }

    /**
     * Parses inline flags and optional sub-expressions (?(?flags:...)).
     */
    private function parseInlineFlags(int $startPosition): Node\NodeInterface
    {
        // Support PHP/PCRE2 inline flags (imsxUJn) plus ^ (unset) and - toggles.
        // Handle ^ (T_ANCHOR) at the start - it means "unset all flags" in PCRE2
        $flags = '';
        if ($this->check(TokenType::T_ANCHOR) && '^' === $this->current()->value) {
            $flags = '^';
            $this->advance();
        }
        $inlineFlagChars = self::INLINE_FLAG_CHARS;
        $allFlags = 'imsxUJn';
        if ($this->supportsInlineModifierR()) {
            $inlineFlagChars .= 'r';
            $allFlags .= 'r';
        }

        $flags .= $this->consumeWhile(
            static fn (string $c): bool => str_contains($inlineFlagChars, $c),
        );

        if ('' !== $flags) {
            [$setFlags, $unsetFlags] = str_contains($flags, '-')
                ? explode('-', $flags, 2)
                : [$flags, ''];

            // Handle ^ (unset all flags)
            if (str_starts_with($setFlags, '^')) {
                $setFlagsAfter = substr($setFlags, 1);
                $unsetFlags = implode('', array_diff(str_split($allFlags), str_split($setFlagsAfter))).$unsetFlags;
                $setFlags = $setFlagsAfter;
            }

            // Validate no conflicting flags
            $setChars = str_split($setFlags);
            $unsetChars = str_split($unsetFlags);
            $overlap = array_intersect($setChars, $unsetChars);
            if (!empty($overlap)) {
                throw $this->parserException(
                    \sprintf('Conflicting flags: %s cannot be both set and unset at position %d', implode('', $overlap), $startPosition),
                    $startPosition,
                );
            }

            if (str_contains($setFlags, 'J')) {
                $this->JModifier = true;
            }
            if (str_contains($unsetFlags, 'J')) {
                $this->JModifier = false;
            }

            $expr = null;
            if ($this->matchLiteral(':')) {
                $expr = $this->parseAlternation();
            }
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            if (null === $expr) {
                $expr = $this->createEmptyLiteralNodeAt($this->previous()->position);
            }

            $this->lastInlineFlagsLength = ($endToken->position + 1) - $startPosition;

            return $this->createGroupNode(
                $expr,
                Node\GroupType::T_GROUP_INLINE_FLAGS,
                $startPosition,
                $endToken,
                null,
                $flags,
            );
        }

        throw $this->parserException(
            \sprintf('Invalid group modifier syntax at position %d', $startPosition),
            $startPosition,
        );
    }

    // Checks if the 'r' inline modifier is supported by the current PCRE/PHP version
    // The 'r' modifier was added in PCRE2 10.43 and PHP 8.4
    private function supportsInlineModifierR(): bool
    {
        $cacheKey = $this->useRuntimePcreDetection ? 'runtime' : $this->phpVersionId;
        if (\array_key_exists($cacheKey, self::$supportsInlineModifierR)) {
            return self::$supportsInlineModifierR[$cacheKey];
        }

        $supports = $this->phpVersionId >= 80400;

        if (!$supports && $this->useRuntimePcreDetection) {
            // For runtime detection, check the PCRE library version directly
            $pcreVersion = \defined('PCRE_VERSION') ? explode(' ', \PCRE_VERSION)[0] : '0';
            $supports = version_compare($pcreVersion, '10.43', '>=');
        }

        self::$supportsInlineModifierR[$cacheKey] = $supports;

        return $supports;
    }

    /**
     * Parses conditional constructs (?(condition)...).
     */
    private function parseConditional(int $startPosition, bool $isModifier): Node\ConditionalNode|Node\DefineNode
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

        // Special case: (?(DEFINE)...) creates a DefineNode instead of ConditionalNode
        if ($condition instanceof Node\AssertionNode && 'DEFINE' === $condition->value) {
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
            $endPosition = $endToken->position + 1;

            return new Node\DefineNode($yes, $startPosition, $endPosition);
        }

        $no = null;
        $yesBranch = $yes;
        if ($yes instanceof Node\AlternationNode && \count($yes->alternatives) > 1) {
            $yesBranch = $yes->alternatives[0];
            $noAlternatives = \array_slice($yes->alternatives, 1);
            if (1 === \count($noAlternatives)) {
                $no = $noAlternatives[0];
            } else {
                $lastAlt = $noAlternatives[\count($noAlternatives) - 1];
                $no = new Node\AlternationNode(
                    $noAlternatives,
                    $noAlternatives[0]->getStartPosition(),
                    $lastAlt->getEndPosition(),
                );
            }
        }

        if (null === $no) {
            $no = $this->createEmptyLiteralNodeAt($this->current()->position);
        }

        $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');
        $endPosition = $endToken->position + 1;

        return new Node\ConditionalNode($condition, $yesBranch, $no, $startPosition, $endPosition);
    }

    /**
     * Parses lookaround conditions inside conditional constructs (?(?=...)...).
     */
    private function parseLookaroundCondition(int $startPosition): Node\NodeInterface
    {
        if ($this->matchLiteral('=')) {
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return $this->createGroupNode(
                $expr,
                Node\GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
                $startPosition,
                $endToken,
            );
        }

        if ($this->matchLiteral('!')) {
            $expr = $this->parseAlternation();
            $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

            return $this->createGroupNode(
                $expr,
                Node\GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
                $startPosition,
                $endToken,
            );
        }

        if ($this->matchLiteral('<')) {
            // @phpstan-ignore-next-line if.alwaysFalse (false positive: position advanced after matching '<')
            if ($this->matchLiteral('=')) {
                $expr = $this->parseAlternation();
                $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

                return $this->createGroupNode(
                    $expr,
                    Node\GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
                    $startPosition,
                    $endToken,
                );
            }
            // @phpstan-ignore-next-line if.alwaysFalse (false positive: position advanced after matching '<')
            if ($this->matchLiteral('!')) {
                $expr = $this->parseAlternation();
                $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

                return $this->createGroupNode(
                    $expr,
                    Node\GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
                    $startPosition,
                    $endToken,
                );
            }
        }

        throw $this->parserException(
            'Invalid conditional condition at position '.$startPosition,
            $startPosition,
        );
    }

    /**
     * Parses the condition part of a conditional construct (?(condition)...).
     */
    private function parseConditionalCondition(): Node\NodeInterface
    {
        $startPosition = $this->current()->position;

        // This handles the PCRE feature where (?(DEFINE)...) allows defining subroutines
        // without matching them immediately.
        // We need to check for 'DEFINE' by peeking at multiple tokens since the lexer
        // tokenizes each character separately.
        if ($this->check(TokenType::T_LITERAL) && 'D' === $this->current()->value) {
            $savedPos = $this->stream->getPosition();
            $word = '';
            while ($this->isLiteralAlphaToken()) {
                $word .= $this->current()->value;
                $this->advance();
            }
            if ('DEFINE' === $word && $this->check(TokenType::T_GROUP_CLOSE)) {
                return new Node\AssertionNode('DEFINE', $startPosition, $this->current()->position);
            }
            // Not DEFINE, restore position
            $this->stream->setPosition($savedPos);
        }

        if ($this->isLiteralDigitToken()) {
            $this->advance();
            $num = (string) ($this->previous()->value.$this->consumeWhile(
                static fn (string $c): bool => ctype_digit($c),
            ));

            return new Node\BackrefNode($num, $startPosition, $this->current()->position);
        }

        if ($this->matchLiteral('<') || $this->matchLiteral('{')) {
            $open = $this->previous()->value;
            $name = $this->parseGroupName($startPosition, false);
            $close = '<' === $open ? '>' : '}';
            $this->consumeLiteral($close, "Expected $close after condition name");

            return new Node\BackrefNode($name, $startPosition, $this->current()->position);
        }

        if ($this->matchLiteral('R')) {
            $endPosition = $this->previous()->position;
            $numericPart = '';
            $sawMinus = false;

            if ($this->checkLiteral('-')) {
                $sawMinus = true;
                $this->advance();
            }

            $digits = $this->consumeWhile(static fn (string $c): bool => ctype_digit($c));
            if ('' !== $digits) {
                $numericPart = ($sawMinus ? '-' : '').$digits;
                $endPosition = $this->previous()->position;
            } elseif ($sawMinus) {
                $this->stream->rewind(1);
            }

            $reference = 'R'.$numericPart;

            return new Node\SubroutineNode($reference, '', $startPosition, $endPosition);
        }

        if ($this->matchLiteral('?')) {
            // Lookaround condition inside (?(...))
            return $this->parseLookaroundCondition($startPosition);
        }

        // Bare name check (for conditions like (?(name)...))
        if ($this->check(TokenType::T_LITERAL)) {
            $savedPos = $this->stream->getPosition();
            $name = '';
            while (
                $this->check(TokenType::T_LITERAL)
                && !$this->checkLiteral(')')
                && !$this->isAtEnd()
            ) {
                $name .= $this->current()->value;
                $this->advance();
            }
            if ('' !== $name && $this->check(TokenType::T_GROUP_CLOSE)) {
                return new Node\BackrefNode($name, $startPosition, $this->current()->position);
            }
            $this->stream->setPosition($savedPos);
        }

        $condition = $this->parseAtom();

        if (
            !(
                $condition instanceof Node\BackrefNode
                || $condition instanceof Node\GroupNode
                || $condition instanceof Node\AssertionNode
                || $condition instanceof Node\SubroutineNode
            )
        ) {
            throw $this->parserException(
                \sprintf(
                    'Invalid conditional construct at position %d. Condition must be a group reference, lookaround, or (DEFINE).',
                    $startPosition,
                ),
                $startPosition,
            );
        }

        return $condition;
    }

    /**
     * checks for duplicate group names and registers the name
     */
    private function checkAndRegisterGroupName(string $name, int $position): void
    {
        if (isset($this->groupNames[$name]) && !$this->JModifier) {
            throw $this->parserException(
                \sprintf('Duplicate group name "%s" at position %d.', $name, $position),
                $position,
            );
        }
        $this->groupNames[$name] = true;
    }

    /**
     * parses a group name, handling quoted names and validating characters
     */
    private function parseGroupName(?int $errorPosition = null, bool $register = true): string
    {
        $quote = null;
        $nameStartPosition = $errorPosition ?? $this->current()->position;

        $adjustment = 0;
        if ($this->lastInlineFlagsLength > 0) {
            $adjustment = max(0, $this->lastInlineFlagsLength - 2);
        } elseif ($this->lastTokenWasAlternation) {
            $adjustment = 1;
        }
        $nameStartPosition = max(0, $nameStartPosition - $adjustment);
        $this->lastTokenWasAlternation = false;
        $this->lastInlineFlagsLength = 0;

        // Check for quoted group name (Python-style: 'name' or "name")
        if ($this->checkLiteral("'") || $this->checkLiteral('"')) {
            $quote = $this->current()->value;
            $this->advance();
        }

        $name = '';
        while (
            !$this->checkLiteral('>')
            && !$this->checkLiteral('}')
            && !$this->isAtEnd()
        ) {
            // If we're in quoted mode and hit the closing quote, stop collecting
            if (null !== $quote && $this->checkLiteral($quote)) {
                break;
            }

            if ($this->check(TokenType::T_GROUP_CLOSE)) {
                break;
            }

            if ($this->check(TokenType::T_LITERAL) || $this->check(TokenType::T_LITERAL_ESCAPED)) {
                $name .= $this->current()->value;
                $this->advance();
            } else {
                throw $this->parserException(
                    \sprintf('Unexpected token "%s" in group name', $this->current()->value),
                    $this->current()->position,
                );
            }
        }

        // If quoted, expect the closing quote
        if (null !== $quote) {
            if (!$this->checkLiteral($quote)) {
                throw $this->parserException(
                    \sprintf(
                        'Expected closing quote "%s" for group name at position %d',
                        $quote,
                        $this->current()->position,
                    ),
                    $this->current()->position,
                );
            }
            $this->advance();
        }

        if ('' === $name) {
            throw $this->parserException(
                \sprintf('Expected group name at position %d', $nameStartPosition),
                $nameStartPosition,
            );
        }

        if ($register) {
            $this->checkAndRegisterGroupName($name, $nameStartPosition);
        }

        return $name;
    }

    /**
     * parses a character class, including its parts and negation
     */
    private function parseCharClass(): Node\CharClassNode
    {
        $startToken = $this->previous();
        $startPosition = $startToken->position;
        $isNegated = $this->match(TokenType::T_NEGATION);
        $parts = $this->parseClassExpression();

        $endToken = $this->consume(TokenType::T_CHAR_CLASS_CLOSE, 'Expected "]" to close character class');

        return new Node\CharClassNode($parts, $isNegated, $startPosition, $endToken->position + 1);
    }

    /**
     * Parses a character class expression with intersection (&&) and subtraction (--) operations.
     */
    private function parseClassExpression(): Node\NodeInterface
    {
        $left = $this->parseCharClassAlternation();

        while ($this->check(TokenType::T_CLASS_INTERSECTION) || $this->check(TokenType::T_CLASS_SUBTRACTION)) {
            $type = TokenType::T_CLASS_INTERSECTION === $this->current()->type ? Node\ClassOperationType::INTERSECTION : Node\ClassOperationType::SUBTRACTION;
            $this->advance();
            $right = $this->parseCharClassAlternation();
            $left = new Node\ClassOperationNode($type, $left, $right, $left->getStartPosition(), $right->getEndPosition());
        }

        return $left;
    }

    /**
     * Parses the alternation of character class parts (without operations).
     */
    private function parseCharClassAlternation(): Node\NodeInterface
    {
        $parts = [];

        while (
            !$this->check(TokenType::T_CHAR_CLASS_CLOSE)
            && !$this->check(TokenType::T_CLASS_INTERSECTION)
            && !$this->check(TokenType::T_CLASS_SUBTRACTION)
            && !$this->isAtEnd()
        ) {
            // Silent tokens inside char class
            if ($this->match(TokenType::T_QUOTE_MODE_START)) {
                $this->inQuoteMode = true;

                continue;
            }
            if ($this->match(TokenType::T_QUOTE_MODE_END)) {
                $this->inQuoteMode = false;

                continue;
            }
            $parts[] = $this->parseCharClassPart();
        }

        if (empty($parts)) {
            return $this->createEmptyLiteralNodeAt($this->current()->position);
        }

        if (1 === \count($parts)) {
            return $parts[0];
        }

        $start = $parts[0]->getStartPosition();
        $end = $parts[\count($parts) - 1]->getEndPosition();

        return new Node\AlternationNode($parts, $start, $end);
    }

    /**
     * parses a part of a character class, which can be a literal, range, char type, unicode property, etc
     */
    private function parseCharClassPart(): Node\NodeInterface
    {
        $startToken = $this->current();
        $startPosition = $startToken->position;
        $startNode = null;

        // Simplified matching logic for char class parts
        if ($this->match(TokenType::T_LITERAL) || $this->match(TokenType::T_LITERAL_ESCAPED)) {
            $token = $this->previous();
            // Check for range validity
            // +1 if escaped
            $endPosition = $startPosition + \strlen($token->value)
                + (TokenType::T_LITERAL_ESCAPED === $token->type ? 1 : 0);
            $startNode = new Node\LiteralNode($token->value, $startPosition, $endPosition);
        } elseif ($this->match(TokenType::T_CHAR_TYPE)) {
            $token = $this->previous();
            $startNode = new Node\CharTypeNode(
                $token->value,
                $startPosition,
                $startPosition + \strlen($token->value) + 1,
            );
        } elseif ($this->match(TokenType::T_UNICODE_PROP)) {
            $token = $this->previous();
            // Basic length calc - Parser logic from original
            $len = 2 + \strlen($token->value)
                + ((\strlen($token->value) > 1 || str_starts_with($token->value, '^')) ? 2 : 0);
            $startNode = new Node\UnicodePropNode($token->value, str_starts_with($token->value, '{'), $startPosition, $startPosition + $len);
        } elseif ($this->match(TokenType::T_UNICODE)) {
            $startNode = $this->createCharLiteralNodeFromToken(
                $this->previous(),
                TokenType::T_UNICODE,
                $startPosition,
            );
        } elseif ($this->match(TokenType::T_CONTROL_CHAR)) {
            $token = $this->previous();
            $endPosition = $startPosition + 2 + \strlen($token->value);
            $startNode = new Node\ControlCharNode(
                $token->value,
                $this->parseControlCharCodePoint($token->value),
                $startPosition,
                $endPosition,
            );
        } elseif ($this->match(TokenType::T_OCTAL)) {
            $startNode = $this->createCharLiteralNodeFromToken(
                $this->previous(),
                TokenType::T_OCTAL,
                $startPosition,
            );
        } elseif ($this->match(TokenType::T_OCTAL_LEGACY)) {
            $startNode = $this->createCharLiteralNodeFromToken(
                $this->previous(),
                TokenType::T_OCTAL_LEGACY,
                $startPosition,
            );
        } elseif ($this->match(TokenType::T_RANGE)) {
            // Literal hyphen at start
            return new Node\LiteralNode($this->previous()->value, $startPosition, $startPosition + 1);
        } elseif ($this->match(TokenType::T_POSIX_CLASS)) {
            $token = $this->previous();
            $startNode = new Node\PosixClassNode(
                $token->value,
                $startPosition,
                $startPosition + \strlen($token->value) + 4,
            );
        } else {
            throw $this->parserException(
                \sprintf(
                    'Unexpected token "%s" in character class at position %d.',
                    $this->current()->value,
                    $this->current()->position,
                ),
                $this->current()->position,
            );
        }

        // Check for Range
        if ($this->match(TokenType::T_RANGE)) {
            if ($this->check(TokenType::T_CHAR_CLASS_CLOSE)) {
                // Trailing hyphen
                $this->stream->rewind(1);

                return $startNode;
            }

            // Parse end node without allowing nested ranges
            $endToken = $this->current();
            $endPosition = $endToken->position;
            $endNode = null;

            if ($this->match(TokenType::T_LITERAL) || $this->match(TokenType::T_LITERAL_ESCAPED)) {
                $token = $this->previous();
                $endPosition = $endPosition + \strlen($token->value)
                    + (TokenType::T_LITERAL_ESCAPED === $token->type ? 1 : 0);
                $endNode = new Node\LiteralNode($token->value, $endPosition - \strlen($token->value), $endPosition);
            } elseif ($this->match(TokenType::T_CHAR_TYPE)) {
                $token = $this->previous();
                $endNode = new Node\CharTypeNode(
                    $token->value,
                    $endPosition,
                    $endPosition + \strlen($token->value) + 1,
                );
            } elseif ($this->match(TokenType::T_UNICODE_PROP)) {
                $token = $this->previous();
                $len = 2 + \strlen($token->value)
                    + ((\strlen($token->value) > 1 || str_starts_with($token->value, '^')) ? 2 : 0);
                $endNode = new Node\UnicodePropNode($token->value, str_starts_with($token->value, '{'), $endPosition, $endPosition + $len);
            } elseif ($this->match(TokenType::T_UNICODE)) {
                $endNode = $this->createCharLiteralNodeFromToken(
                    $this->previous(),
                    TokenType::T_UNICODE,
                    $endPosition,
                );
            } elseif ($this->match(TokenType::T_CONTROL_CHAR)) {
                $token = $this->previous();
                $endNode = new Node\ControlCharNode(
                    $token->value,
                    $this->parseControlCharCodePoint($token->value),
                    $endPosition,
                    $endPosition + 2 + \strlen($token->value),
                );
            } elseif ($this->match(TokenType::T_OCTAL)) {
                $endNode = $this->createCharLiteralNodeFromToken(
                    $this->previous(),
                    TokenType::T_OCTAL,
                    $endPosition,
                );
            } elseif ($this->match(TokenType::T_OCTAL_LEGACY)) {
                $endNode = $this->createCharLiteralNodeFromToken(
                    $this->previous(),
                    TokenType::T_OCTAL_LEGACY,
                    $endPosition,
                );
            } elseif ($this->match(TokenType::T_POSIX_CLASS)) {
                $token = $this->previous();
                $endNode = new Node\PosixClassNode(
                    $token->value,
                    $endPosition,
                    $endPosition + \strlen($token->value) + 4,
                );
            } else {
                throw $this->parserException(
                    \sprintf(
                        'Unexpected token "%s" in character class range at position %d.',
                        $this->current()->value,
                        $this->current()->position,
                    ),
                    $this->current()->position,
                );
            }

            return new Node\RangeNode($startNode, $endNode, $startPosition, $endNode->getEndPosition());
        }

        return $startNode;
    }

    /**
     * parses a subroutine name consisting of alphanumeric characters and underscores
     */
    private function parseSubroutineName(): string
    {
        $name = '';
        while (
            !$this->check(TokenType::T_GROUP_CLOSE)
            && !$this->isAtEnd()
        ) {
            if ($this->check(TokenType::T_LITERAL) || $this->check(TokenType::T_LITERAL_ESCAPED)) {
                $char = $this->current()->value;
                if (!preg_match('/^\w$/', $char)) {
                    throw $this->parserException(
                        'Unexpected token in subroutine name: '.$char,
                        $this->current()->position,
                    );
                }
                $name .= $char;
                $this->advance();
            } else {
                throw $this->parserException(
                    'Unexpected token in subroutine name: '.$this->current()->value,
                    $this->current()->position,
                );
            }
        }
        if ('' === $name) {
            throw $this->parserException(
                'Expected subroutine name at position '.$this->current()->position,
                $this->current()->position,
            );
        }

        return $name;
    }

    /**
     * creates a ParserException with context about the pattern being parsed
     */
    private function parserException(string $message, int $position): ParserException
    {
        return SyntaxErrorException::withContext($message, $position, $this->pattern);
    }

    private function guardRecursionDepth(int $position): void
    {
        if ($this->recursionDepth >= $this->maxRecursionDepth) {
            throw RecursionLimitException::withContext(
                \sprintf('Recursion limit of %d exceeded', $this->maxRecursionDepth),
                $position,
                $this->pattern,
            );
        }
    }

    /**
     * @return bool true if the current token is a T_LITERAL and its value matches the given value
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
     * @return bool true if the current token is a T_LITERAL and its value matches the given value
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
     * @return Token the consumed token
     */
    private function consume(TokenType $type, string $error): Token
    {
        if ($this->check($type)) {
            $token = $this->current();
            $this->advance();

            return $token;
        }
        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;

        throw $this->parserException(
            $error.' at '.$at.' (found '.$this->current()->type->value.')',
            $this->current()->position,
        );
    }

    /**
     * @return Token the consumed token
     */
    private function consumeLiteral(string $value, string $error): Token
    {
        if ($this->checkLiteral($value)) {
            $token = $this->current();
            $this->advance();

            return $token;
        }
        $at = $this->isAtEnd() ? 'end of input' : 'position '.$this->current()->position;

        throw $this->parserException(
            $error.' at '.$at.' (found '.$this->current()->type->value.' with value '.$this->current()->value.')',
            $this->current()->position,
        );
    }

    /**
     * Creates an empty literal node (epsilon) at a given position.
     */
    private function createEmptyLiteralNodeAt(int $position): Node\LiteralNode
    {
        return new Node\LiteralNode('', $position, $position);
    }

    /**
     * Small factory for group nodes to keep argument ordering and end positions consistent.
     */
    private function createGroupNode(
        Node\NodeInterface $expr,
        Node\GroupType $type,
        int $startPosition,
        Token $endToken,
        ?string $name = null,
        ?string $flags = null
    ): Node\GroupNode {
        return new Node\GroupNode($expr, $type, $name, $flags, $startPosition, $endToken->position + 1);
    }

    /**
     * Parses a simple group: alternation content followed by closing paren.
     * Used for non-capturing groups, lookaheads, atomic groups, etc.
     */
    private function parseSimpleGroup(int $startPosition, Node\GroupType $type): Node\GroupNode
    {
        $expr = $this->parseAlternation();
        $endToken = $this->consume(TokenType::T_GROUP_CLOSE, 'Expected )');

        return $this->createGroupNode($expr, $type, $startPosition, $endToken);
    }

    /**
     * Optimized current token access with caching.
     */
    private function current(): Token
    {
        $currentPos = $this->stream->getPosition();

        if ($this->currentTokenValid && $this->lastPosition === $currentPos) {
            return $this->currentToken ?? $this->stream->current();
        }

        $this->currentToken = $this->stream->current();
        $this->currentTokenValid = true;
        $this->lastPosition = $currentPos;

        return $this->currentToken;
    }

    /**
     * Optimized end-of-stream check.
     */
    private function isAtEnd(): bool
    {
        return $this->stream->isAtEnd();
    }

    /**
     * Optimized token type checking.
     */
    private function check(TokenType $type): bool
    {
        if ($this->isAtEnd()) {
            return TokenType::T_EOF === $type;
        }

        return $this->current()->type === $type;
    }

    /**
     * Optimized token consumption with caching invalidation.
     */
    private function match(TokenType $type): bool
    {
        if (!$this->check($type)) {
            return false;
        }

        $this->advance();

        return true;
    }

    /**
     * Advance to next token and invalidate cache.
     */
    private function advance(): void
    {
        if (!$this->isAtEnd()) {
            $this->stream->next();
            $this->currentTokenValid = false;
        }
    }

    /**
     * Check if current token is a literal digit.
     */
    private function isLiteralDigitToken(): bool
    {
        return $this->check(TokenType::T_LITERAL) && ctype_digit($this->current()->value);
    }

    /**
     * Get previous token with position management.
     */
    private function previous(): Token
    {
        if (0 === $this->stream->getPosition()) {
            return new Token(TokenType::T_EOF, '', 0);
        }

        $savedPos = $this->stream->getPosition();
        $this->stream->setPosition($savedPos - 1);
        $token = $this->stream->current();
        $this->stream->setPosition($savedPos);

        return $token;
    }

    /**
     * @return bool true if the current token is a T_LITERAL and its value is an alphabetic character (a-z, A-Z)
     */
    private function isLiteralAlphaToken(): bool
    {
        return $this->check(TokenType::T_LITERAL) && ctype_alpha($this->current()->value);
    }

    /**
     * Consumes tokens while the predicate returns true, concatenating their values.
     */
    private function consumeWhile(callable $predicate): string
    {
        $value = '';

        while (
            !$this->isAtEnd()
            && $this->check(TokenType::T_LITERAL)
            && $predicate($this->current()->value)
        ) {
            $value .= $this->current()->value;
            $this->advance();
        }

        return $value;
    }
}
