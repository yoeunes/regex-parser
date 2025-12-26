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

namespace RegexParser\NodeVisitor;

use RegexParser\Node;
use RegexParser\Node\AbstractNode;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CalloutNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LimitMatchNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\ReDoS\CharSetAnalyzer;

/**
 * Transforms the AST to apply optimizations, returning a new, simplified AST.
 *
 * @extends AbstractNodeVisitor<Node\NodeInterface>
 */
final class OptimizerNodeVisitor extends AbstractNodeVisitor
{
    private const CHAR_CLASS_META = [']' => true, '\\' => true, '^' => true, '-' => true];

    private string $flags = '';

    private CharSetAnalyzer $charSetAnalyzer;

    private bool $isInsideQuantifier = false;

    public function __construct(
        private readonly bool $optimizeDigits = true,
        private readonly bool $optimizeWord = true,
        private readonly bool $strictRanges = true,
        /**
         * Whether to automatically convert greedy quantifiers to possessive
         * when followed by a disjoint character set. This is safe in most cases
         * but can change semantics when backreferences are involved.
         * Default is false to ensure semantic preservation.
         */
        private readonly bool $autoPossessify = false,
        /**
         * Whether to perform string-based alternation factorization.
         * This can make verbose (/x) patterns harder to read.
         */
        private readonly bool $allowAlternationFactorization = true
    ) {
        $this->charSetAnalyzer = new CharSetAnalyzer();
    }

    #[\Override]
    public function visitRegex(RegexNode $node): NodeInterface
    {
        $this->flags = $node->flags;
        $this->charSetAnalyzer = new CharSetAnalyzer($this->flags);
        $optimizedPattern = $node->pattern->accept($this);

        if ($optimizedPattern === $node->pattern) {
            return $node;
        }

        return new RegexNode($optimizedPattern, $node->flags, $node->delimiter, $node->startPosition, $node->endPosition);
    }

    #[\Override]
    public function visitAlternation(AlternationNode $node): NodeInterface
    {
        $optimizedAlts = [];
        $hasChanged = false;

        foreach ($node->alternatives as $alt) {
            $optimizedAlt = $alt->accept($this);

            if ($optimizedAlt instanceof AlternationNode) {
                array_push($optimizedAlts, ...$optimizedAlt->alternatives);
                $hasChanged = true;
            } else {
                $optimizedAlts[] = $optimizedAlt;
            }

            if ($optimizedAlt !== $alt) {
                $hasChanged = true;
            }
        }

        // Try to merge adjacent character class nodes
        $mergedAlts = $this->mergeAdjacentCharClasses($optimizedAlts);
        if ($mergedAlts !== $optimizedAlts) {
            $hasChanged = true;
            $optimizedAlts = $mergedAlts;
        }

        $deduplicatedAlts = $this->deduplicateAlternation($optimizedAlts);
        if (\count($deduplicatedAlts) !== \count($optimizedAlts)) {
            $hasChanged = true;
            $optimizedAlts = $deduplicatedAlts;
        }

        if ($this->canAlternationBeCharClass($optimizedAlts)) {
            /* @var array<Node\LiteralNode> $optimizedAlts */
            $expression = new AlternationNode($optimizedAlts, $node->startPosition, $node->endPosition);

            return new CharClassNode($expression, false, $node->startPosition, $node->endPosition);
        }

        // Try to convert simple alternations to character classes
        $charClass = $this->tryConvertAlternationToCharClass($optimizedAlts, $node->startPosition, $node->endPosition);
        if (null !== $charClass) {
            return $charClass;
        }

        if (!$hasChanged) {
            return $node;
        }

        $deduplicatedAlts = $this->deduplicateAlternation($optimizedAlts);
        if (\count($deduplicatedAlts) !== \count($optimizedAlts)) {
            $hasChanged = true;
            $optimizedAlts = $deduplicatedAlts;
        }

        if ($this->allowAlternationFactorization) {
            $factoredAlts = $this->factorizeAlternation($optimizedAlts);

            if ($factoredAlts !== $optimizedAlts) {
                $hasChanged = true;
                $optimizedAlts = $factoredAlts;
            }

            $suffixFactoredAlts = $this->factorizeSuffix($optimizedAlts);

            if ($suffixFactoredAlts !== $optimizedAlts) {
                return new AlternationNode($suffixFactoredAlts, $node->startPosition, $node->endPosition);
            }
        }

        return new AlternationNode($optimizedAlts, $node->startPosition, $node->endPosition);
    }

    #[\Override]
    public function visitSequence(SequenceNode $node): NodeInterface
    {
        $optimizedChildren = [];
        $hasChanged = false;

        foreach ($node->children as $child) {
            $optimizedChild = $child->accept($this);

            if ($optimizedChild instanceof LiteralNode && \count($optimizedChildren) > 0) {
                $prevNode = $optimizedChildren[\count($optimizedChildren) - 1];
                if ($prevNode instanceof LiteralNode) {
                    $optimizedChildren[\count($optimizedChildren) - 1] = new LiteralNode(
                        $prevNode->value.$optimizedChild->value,
                        $prevNode->startPosition,
                        $optimizedChild->endPosition,
                    );
                    $hasChanged = true;

                    continue;
                }
            }

            if ($optimizedChild instanceof SequenceNode) {
                array_push($optimizedChildren, ...$optimizedChild->children);
                $hasChanged = true;

                continue;
            }

            if ($optimizedChild instanceof LiteralNode && '' === $optimizedChild->value) {
                $hasChanged = true;

                continue;
            }

            if ($optimizedChild !== $child) {
                $hasChanged = true;
            }

            $optimizedChildren[] = $optimizedChild;
        }

        // Compact repeated literal sequences
        foreach ($optimizedChildren as $i => $child) {
            if ($child instanceof LiteralNode && preg_match('/^(.)\1+$/', $child->value, $matches)) {
                $char = $matches[1];
                $count = \strlen($child->value);
                $baseNode = new LiteralNode($char, $child->startPosition, $child->endPosition);
                $optimizedChildren[$i] = new QuantifierNode($baseNode, '{'.$count.'}', QuantifierType::T_GREEDY, $child->startPosition, $child->endPosition);
                $hasChanged = true;
            }
        }

        // Sequence compaction
        $originalCount = \count($optimizedChildren);
        /** @var array<Node\NodeInterface> $optimizedChildren */
        $optimizedChildren = array_values($optimizedChildren);
        $optimizedChildren = $this->compactSequence($optimizedChildren);
        if (\count($optimizedChildren) !== $originalCount) {
            $hasChanged = true;
        }

        // Auto-possessivization (only for + to be conservative)
        // This optimization is opt-in because it can change semantics with backreferences
        if ($this->autoPossessify) {
            for ($i = 0; $i < \count($optimizedChildren) - 1; $i++) {
                $current = $optimizedChildren[$i];
                $next = $optimizedChildren[$i + 1];

                if ($current instanceof QuantifierNode && QuantifierType::T_GREEDY === $current->type && '+' === $current->quantifier) {
                    if ($this->areCharSetsDisjoint($current->node, $next)) {
                        $optimizedChildren[$i] = new QuantifierNode(
                            $current->node,
                            $current->quantifier,
                            QuantifierType::T_POSSESSIVE,
                            $current->startPosition,
                            $current->endPosition,
                        );
                        $hasChanged = true;
                    }
                }
            }
        }

        if (!$hasChanged) {
            return $node;
        }

        if (1 === \count($optimizedChildren)) {
            return $optimizedChildren[0];
        }

        if (0 === \count($optimizedChildren)) {
            return new LiteralNode('', $node->startPosition, $node->endPosition);
        }

        return new SequenceNode($optimizedChildren, $node->startPosition, $node->endPosition);
    }

    #[\Override]
    public function visitGroup(GroupNode $node): NodeInterface
    {
        $optimizedChild = $node->child->accept($this);

        // Enhanced Group Unwrapping: (?:x) -> x
        // If the group is non-capturing and contains a single atomic node, remove the group.
        // But do not unwrap if we are inside a quantifier and the original child was a sequence or alternation,
        // as unwrapping changes semantics when the group has a quantifier.
        if (
            GroupType::T_GROUP_NON_CAPTURING === $node->type
            && !($this->isInsideQuantifier && ($node->child instanceof SequenceNode || $node->child instanceof AlternationNode))
            && (
                $optimizedChild instanceof LiteralNode
                || $optimizedChild instanceof CharLiteralNode
                || $optimizedChild instanceof CharTypeNode
                || $optimizedChild instanceof DotNode
                || $optimizedChild instanceof CharClassNode
                || $optimizedChild instanceof AnchorNode
                || $optimizedChild instanceof AssertionNode
                || $optimizedChild instanceof UnicodePropNode
            )
        ) {
            return $optimizedChild;
        }

        if ($optimizedChild !== $node->child) {
            return new GroupNode($optimizedChild, $node->type, $node->name, $node->flags, $node->startPosition, $node->endPosition);
        }

        return $node;
    }

    #[\Override]
    public function visitQuantifier(QuantifierNode $node): NodeInterface
    {
        $this->isInsideQuantifier = true;
        $optimizedNode = $node->node->accept($this);
        $this->isInsideQuantifier = false;

        if ($optimizedNode !== $node->node) {
            $node = new QuantifierNode($optimizedNode, $node->quantifier, $node->type, $node->startPosition, $node->endPosition);
        }

        $normalized = $this->normalizeQuantifier($node);
        if ($normalized !== $node) {
            return $normalized;
        }

        return $node;
    }

    #[\Override]
    public function visitCharClass(CharClassNode $node): NodeInterface
    {
        $parts = $node->expression instanceof AlternationNode ? $node->expression->alternatives : [$node->expression];

        // We must check !str_contains($this->flags, 'u') because in Unicode mode,
        // \d matches more than just [0-9] (e.g. Arabic digits), so they are not equivalent.
        if ($this->optimizeDigits && !str_contains($this->flags, 'u') && !$node->isNegated && 1 === \count($parts)) {
            $part = $parts[0];
            if ($part instanceof RangeNode && $part->start instanceof LiteralNode && $part->end instanceof LiteralNode) {
                if ('0' === $part->start->value && '9' === $part->end->value) {
                    return new CharTypeNode('d', $node->startPosition, $node->endPosition);
                }
            }
        }

        if ($this->optimizeWord && !str_contains($this->flags, 'u') && !$node->isNegated && 4 === \count($parts)) {
            if ($this->isFullWordClass($parts)) {
                return new CharTypeNode('w', $node->startPosition, $node->endPosition);
            }
        }

        $optimizedParts = [];
        $hasChanged = false;
        foreach ($parts as $part) {
            $optimizedPart = $part->accept($this);
            $optimizedParts[] = $optimizedPart;
            if ($optimizedPart !== $part) {
                $hasChanged = true;
            }
        }

        [$optimizedParts, $normalizedChanged] = $this->normalizeCharClassParts($optimizedParts);
        $hasChanged = $hasChanged || $normalizedChanged;

        if ($hasChanged) {
            if (1 === \count($optimizedParts)) {
                $expression = $optimizedParts[0];
            } else {
                $expression = new AlternationNode($optimizedParts, $node->startPosition, $node->endPosition);
            }

            return new CharClassNode($expression, $node->isNegated, $node->startPosition, $node->endPosition);
        }

        return $node;
    }

    #[\Override]
    public function visitRange(RangeNode $node): NodeInterface
    {
        return $node;
    }

    #[\Override]
    public function visitConditional(ConditionalNode $node): NodeInterface
    {
        $optimizedCond = $node->condition->accept($this);
        $optimizedYes = $node->yes->accept($this);
        $optimizedNo = $node->no->accept($this);

        if ($optimizedCond !== $node->condition || $optimizedYes !== $node->yes || $optimizedNo !== $node->no) {
            return new ConditionalNode($optimizedCond, $optimizedYes, $optimizedNo, $node->startPosition, $node->endPosition);
        }

        return $node;
    }

    #[\Override]
    public function visitLiteral(LiteralNode $node): NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `CharTypeNode`.
     *
     * Purpose: Character types like `\d` are already in their most optimal form.
     *
     * @param Node\CharTypeNode $node the character type node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitCharType(CharTypeNode $node): NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `DotNode`.
     *
     * Purpose: The `.` wildcard is atomic and cannot be optimized.
     *
     * @param Node\DotNode $node the dot node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitDot(DotNode $node): NodeInterface
    {
        return $node;
    }

    /**
     * Visits an `AnchorNode`.
     *
     * Purpose: Anchors like `^` and `$` are atomic and cannot be optimized.
     *
     * @param Node\AnchorNode $node the anchor node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitAnchor(AnchorNode $node): NodeInterface
    {
        return $node;
    }

    /**
     * Visits an `AssertionNode`.
     *
     * Purpose: Assertions like `\b` are atomic and cannot be optimized.
     *
     * @param Node\AssertionNode $node the assertion node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitAssertion(AssertionNode $node): NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `KeepNode`.
     *
     * Purpose: The `\K` assertion is atomic and cannot be optimized.
     *
     * @param Node\KeepNode $node the keep node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitKeep(KeepNode $node): NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `BackrefNode`.
     *
     * Purpose: Backreferences are dynamic and cannot be optimized.
     *
     * @param Node\BackrefNode $node the backreference node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitBackref(BackrefNode $node): NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `UnicodePropNode`.
     *
     * Purpose: Unicode property escapes are atomic and cannot be optimized.
     *
     * @param Node\UnicodePropNode $node the Unicode property node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitUnicodeProp(UnicodePropNode $node): NodeInterface
    {
        return $node;
    }

    #[\Override]
    public function visitCharLiteral(CharLiteralNode $node): NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `PosixClassNode`.
     *
     * Purpose: POSIX classes are atomic and cannot be optimized further.
     *
     * @param Node\PosixClassNode $node the POSIX class node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitPosixClass(PosixClassNode $node): NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `CommentNode`.
     *
     * Purpose: Comments do not affect matching and are preserved as is.
     *
     * @param Node\CommentNode $node the comment node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitComment(CommentNode $node): NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `SubroutineNode`.
     *
     * Purpose: Subroutine calls are dynamic and cannot be optimized.
     *
     * @param Node\SubroutineNode $node the subroutine node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitSubroutine(SubroutineNode $node): NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `PcreVerbNode`.
     *
     * Purpose: PCRE verbs control the matching engine and are not optimized.
     *
     * @param Node\PcreVerbNode $node the PCRE verb node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitPcreVerb(PcreVerbNode $node): NodeInterface
    {
        return $node;
    }

    /**
     * Optimizes a `DefineNode`.
     *
     * Purpose: This method recursively optimizes the content within a `(?(DEFINE)...)` block.
     *
     * @param Node\DefineNode $node the define node to optimize
     *
     * @return Node\NodeInterface the new, optimized define node
     */
    #[\Override]
    public function visitDefine(DefineNode $node): NodeInterface
    {
        return new DefineNode(
            $node->content->accept($this),
            $node->startPosition,
            $node->endPosition,
        );
    }

    #[\Override]
    public function visitLimitMatch(LimitMatchNode $node): NodeInterface
    {
        return $node;
    }

    #[\Override]
    public function visitCallout(CalloutNode $node): NodeInterface
    {
        return $node;
    }

    /**
     * @param array<Node\NodeInterface> $alternatives
     */
    private function canAlternationBeCharClass(array $alternatives): bool
    {
        if (empty($alternatives)) {
            return false;
        }

        foreach ($alternatives as $alt) {
            if (!$alt instanceof LiteralNode) {
                return false;
            }
            if (1 !== \strlen($alt->value)) {  // Also excludes empty strings
                return false;
            }
            if (isset(self::CHAR_CLASS_META[$alt->value])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<NodeInterface> $parts
     */
    private function isFullWordClass(array $parts): bool
    {
        $partsFound = ['a-z' => false, 'A-Z' => false, '0-9' => false, '_' => false];
        foreach ($parts as $part) {
            if ($part instanceof RangeNode && $part->start instanceof LiteralNode && $part->end instanceof LiteralNode) {
                $range = $part->start->value.'-'.$part->end->value;
                if (isset($partsFound[$range])) {
                    $partsFound[$range] = true;
                }
            } elseif ($part instanceof LiteralNode && '_' === $part->value) {
                $partsFound['_'] = true;
            }
        }

        return !\in_array(false, $partsFound, true);
    }

    /**
     * Classify a character by its ASCII category for range merging.
     *
     * @param int $ord the ASCII ordinal of the character
     *
     * @return int category: 0=other, 1=digits, 2=uppercase, 3=lowercase
     */
    private function getCharCategory(int $ord): int
    {
        if ($ord >= 48 && $ord <= 57) { // 0-9
            return 1;
        }
        if ($ord >= 65 && $ord <= 90) { // A-Z
            return 2;
        }
        if ($ord >= 97 && $ord <= 122) { // a-z
            return 3;
        }

        return 0; // other
    }

    /**
     * @param array<Node\NodeInterface> $parts
     *
     * @return array{0: array<Node\NodeInterface>, 1: bool}
     */
    private function normalizeCharClassParts(array $parts): array
    {
        /** @var array<int, array{start: int, end: int}> $scalarChars */
        $scalarChars = [];
        $otherParts = [];
        $changed = false;

        foreach ($parts as $part) {
            if ($part instanceof LiteralNode && 1 === \strlen($part->value)) {
                $ord = mb_ord($part->value);
                if (isset($scalarChars[$ord])) {
                    $scalarChars[$ord]['start'] = min($scalarChars[$ord]['start'], $part->startPosition);
                    $scalarChars[$ord]['end'] = max($scalarChars[$ord]['end'], $part->endPosition);
                    $changed = true;
                } else {
                    $scalarChars[$ord] = ['start' => $part->startPosition, 'end' => $part->endPosition];
                }

                continue;
            }

            if ($part instanceof RangeNode
                && $part->start instanceof LiteralNode
                && $part->end instanceof LiteralNode
                && 1 === \strlen($part->start->value)
                && 1 === \strlen($part->end->value)
            ) {
                $startOrd = mb_ord($part->start->value);
                $endOrd = mb_ord($part->end->value);
                if ($startOrd > $endOrd) {
                    [$startOrd, $endOrd] = [$endOrd, $startOrd];
                }
                for ($ord = $startOrd; $ord <= $endOrd; $ord++) {
                    if (isset($scalarChars[$ord])) {
                        $scalarChars[$ord]['start'] = min($scalarChars[$ord]['start'], $part->startPosition);
                        $scalarChars[$ord]['end'] = max($scalarChars[$ord]['end'], $part->endPosition);
                    } else {
                        $scalarChars[$ord] = ['start' => $part->startPosition, 'end' => $part->endPosition];
                    }
                }
                $changed = true;

                continue;
            }

            $otherParts[] = $part;
        }

        if (empty($scalarChars)) {
            return [$parts, $changed];
        }

        ksort($scalarChars);

        $normalized = [];
        $hasRange = false;
        $rangeStart = 0;
        $rangeEnd = 0;
        $rangeStartPos = 0;
        $rangeEndPos = 0;

        foreach ($scalarChars as $ord => $pos) {
            $ord = (int) $ord;
            $posStart = (int) $pos['start'];
            $posEnd = (int) $pos['end'];

            if (!$hasRange) {
                $rangeStart = $ord;
                $rangeEnd = $ord;
                $rangeStartPos = $posStart;
                $rangeEndPos = $posEnd;
                $hasRange = true;

                continue;
            }

            if ($ord === $rangeEnd + 1 && (!$this->strictRanges || $this->getCharCategory($ord) === $this->getCharCategory($rangeEnd))) {
                $rangeEnd = $ord;
                $rangeEndPos = max($rangeEndPos, $posEnd);

                continue;
            }

            $normalized = array_merge($normalized, $this->buildRangeOrLiteral($rangeStart, $rangeEnd, $rangeStartPos, $rangeEndPos));
            $rangeStart = $ord;
            $rangeEnd = $ord;
            $rangeStartPos = $posStart;
            $rangeEndPos = $posEnd;
        }

        $normalized = array_merge($normalized, $this->buildRangeOrLiteral($rangeStart, $rangeEnd, $rangeStartPos, $rangeEndPos));

        $finalParts = array_merge($normalized, $otherParts);

        return [$finalParts, $changed || \count($finalParts) !== \count($parts)];
    }

    /**
     * @return array<Node\NodeInterface>
     */
    private function buildRangeOrLiteral(int $startOrd, int $endOrd, int $startPos, int $endPos): array
    {
        $startLiteral = new LiteralNode(mb_chr($startOrd), $startPos, $startPos + 1);

        if ($startOrd === $endOrd) {
            return [$startLiteral];
        }

        // Only create a range if it covers 3 or more characters to save space
        $coverage = $endOrd - $startOrd + 1;
        if ($coverage < 3) {
            // For 2 characters, return them as separate literals
            $endLiteral = new LiteralNode(mb_chr($endOrd), $endPos, $endPos + 1);

            return [$startLiteral, $endLiteral];
        }

        $endLiteral = new LiteralNode(mb_chr($endOrd), $endPos, $endPos + 1);

        return [new RangeNode($startLiteral, $endLiteral, $startPos, $endPos)];
    }

    /**
     * @param array<Node\NodeInterface> $children
     *
     * @return array<Node\NodeInterface>
     */
    private function compactSequence(array $children): array
    {
        if (empty($children)) {
            return $children;
        }

        $compacted = [];
        $currentNode = null;
        $currentCount = 0;

        foreach ($children as $child) {
            $baseNode = $child;
            $count = 1;

            if ($child instanceof QuantifierNode) {
                $baseNode = $child->node;
                $parsedCount = $this->parseQuantifierCount($child->quantifier);
                if (null === $parsedCount) {
                    // Variable quantifier, don't merge
                    if (null !== $currentNode) {
                        $compacted[] = $this->createQuantifiedNode($currentNode, $currentCount);
                        $currentNode = null;
                        $currentCount = 0;
                    }
                    $compacted[] = $child;

                    continue;
                }
                $count = $parsedCount;
            }

            if (null === $currentNode || !$this->areNodesEqual($currentNode, $baseNode)) {
                if (null !== $currentNode) {
                    $compacted[] = $this->createQuantifiedNode($currentNode, $currentCount);
                }
                $currentNode = $baseNode;
                $currentCount = $count;
            } else {
                $currentCount += $count;
            }
        }

        if (null !== $currentNode) {
            $compacted[] = $this->createQuantifiedNode($currentNode, $currentCount);
        }

        return $compacted;
    }

    private function parseQuantifierCount(string $quantifier): ?int
    {
        if (preg_match('/^\{(\d+)(?:,(\d*))?\}$/', $quantifier, $matches)) {
            $min = (int) $matches[1];
            $max = isset($matches[2]) ? ('' === $matches[2] ? \PHP_INT_MAX : (int) $matches[2]) : $min;
            if ($min === $max) {
                return $min;
            }

            // For ranges, don't merge
            return null;
        }

        // For * + ?, don't merge
        return null;
    }

    private function areNodesEqual(NodeInterface $a, NodeInterface $b): bool
    {
        // Simple equality: same type and same string representation
        if ($a::class !== $b::class) {
            return false;
        }

        return $this->nodeToString($a) === $this->nodeToString($b);
    }

    private function createQuantifiedNode(NodeInterface $node, int $count): NodeInterface
    {
        if (1 === $count) {
            return $node;
        }

        return new QuantifierNode(
            $node,
            '{'.$count.'}',
            QuantifierType::T_GREEDY,
            $node->getStartPosition(),
            $node->getEndPosition(),
        );
    }

    private function areCharSetsDisjoint(NodeInterface $node1, NodeInterface $node2): bool
    {
        try {
            $set1 = $this->charSetAnalyzer->lastChars($node1);
            $set2 = $this->charSetAnalyzer->firstChars($node2);

            return !$set1->intersects($set2);
        } catch (\Throwable) {
            return false; // If analysis fails, don't optimize
        }
    }

    private function normalizeQuantifier(QuantifierNode $node): NodeInterface
    {
        $quantifier = $node->quantifier;

        if ('{0,}' === $quantifier) {
            return new QuantifierNode($node->node, '*', $node->type, $node->startPosition, $node->endPosition);
        }

        if ('{1,}' === $quantifier) {
            return new QuantifierNode($node->node, '+', $node->type, $node->startPosition, $node->endPosition);
        }

        if ('{0,1}' === $quantifier) {
            return new QuantifierNode($node->node, '?', $node->type, $node->startPosition, $node->endPosition);
        }

        if ('{1}' === $quantifier || '{1,1}' === $quantifier) {
            return $node->node;
        }

        if ('{0}' === $quantifier || '{0,0}' === $quantifier) {
            return new LiteralNode('', $node->startPosition, $node->endPosition);
        }

        return $node;
    }

    /**
     * @param array<Node\NodeInterface> $alts
     *
     * @return array<Node\NodeInterface>
     */
    private function deduplicateAlternation(array $alts): array
    {
        $seen = [];
        $unique = [];

        foreach ($alts as $alt) {
            $key = $this->nodeToString($alt);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $alt;
            }
        }

        return $unique;
    }

    /**
     * @param array<Node\NodeInterface> $alts
     *
     * @return array<Node\NodeInterface>
     */
    private function factorizeAlternation(array $alts): array
    {
        if (\count($alts) < 2) {
            return $alts;
        }

        // Get string representations
        $strings = [];
        foreach ($alts as $alt) {
            $strings[] = $this->nodeToString($alt);
        }

        // Find common prefix
        $prefix = $this->findCommonPrefix($strings);
        if (empty($prefix) || str_starts_with($prefix, '[')) {
            return $alts;
        }

        // Split into with prefix and without
        $withPrefix = [];
        $withoutPrefix = [];
        foreach ($alts as $i => $alt) {
            if (str_starts_with($strings[$i], $prefix)) {
                $withPrefix[] = $alt;
            } else {
                $withoutPrefix[] = $alt;
            }
        }

        if (\count($withPrefix) < 2) {
            return $alts;
        }

        // Create suffixes
        $suffixes = [];
        $hasEmptySuffix = false;
        /**
         * @var AbstractNode $alt
         */
        foreach ($withPrefix as $alt) {
            $suffixStr = substr($this->nodeToString($alt), \strlen($prefix));
            if (empty($suffixStr)) {
                $suffixes[] = null;
                $hasEmptySuffix = true;
            } else {
                $suffixes[] = $this->stringToNode($suffixStr, $alt->startPosition + \strlen($prefix), $alt->endPosition);
            }
        }

        /** @var array<Node\NodeInterface> $nonNullSuffixes */
        $nonNullSuffixes = array_values(array_filter($suffixes, static fn ($suffix): bool => null !== $suffix));
        if (empty($nonNullSuffixes)) {
            // All are just the prefix
            /** @var AbstractNode $firstAlt */
            $firstAlt = $withPrefix[0];

            return [$this->stringToNode($prefix, $firstAlt->startPosition, $firstAlt->startPosition + \strlen($prefix))];
        }

        /** @var AbstractNode $firstSuffix */
        $firstSuffix = $nonNullSuffixes[0];
        /** @var AbstractNode $lastSuffix */
        $lastSuffix = $nonNullSuffixes[\count($nonNullSuffixes) - 1];
        $newAlt = 1 === \count($nonNullSuffixes)
            ? $firstSuffix
            : new AlternationNode($nonNullSuffixes, $firstSuffix->startPosition, $lastSuffix->endPosition);
        $group = new GroupNode($newAlt, GroupType::T_GROUP_NON_CAPTURING);
        if ($hasEmptySuffix) {
            $group = new QuantifierNode(
                $group,
                '?',
                QuantifierType::T_GREEDY,
                $firstSuffix->startPosition,
                $lastSuffix->endPosition,
            );
        }
        /** @var AbstractNode $firstAlt */
        $firstAlt = $withPrefix[0];
        $prefixNode = $this->stringToNode($prefix, $firstAlt->startPosition, $firstAlt->startPosition + \strlen($prefix));
        $factored = new SequenceNode([$prefixNode, $group], $firstAlt->startPosition, $firstAlt->endPosition);

        if (empty($withoutPrefix)) {
            return [$factored];
        }

        return array_merge([$factored], $withoutPrefix);

    }

    /**
     * @param array<Node\NodeInterface> $alts
     *
     * @return array<Node\NodeInterface>
     */
    private function factorizeSuffix(array $alts): array
    {
        if (\count($alts) < 2) {
            return $alts;
        }

        // Safety check: only factorize if all alternatives are LiteralNode
        foreach ($alts as $alt) {
            if (!$alt instanceof LiteralNode) {
                return $alts; // Too risky to factorize complex nodes based on string output.
            }
        }

        // Get string representations
        $strings = [];
        /** @var Node\LiteralNode $alt */
        foreach ($alts as $alt) {
            $strings[] = $this->nodeToString($alt);
        }

        // Find common suffix by reversing strings and finding common prefix
        $reversedStrings = array_map(strrev(...), $strings);
        $suffix = $this->findCommonPrefix($reversedStrings);
        if (empty($suffix) || \strlen($suffix) < 2 || str_starts_with($suffix, '[')) {
            return $alts;
        }

        // Reverse back to get the actual suffix
        $suffix = strrev($suffix);

        // Split into with suffix and without
        $withSuffix = [];
        $withoutSuffix = [];
        foreach ($alts as $i => $alt) {
            if (str_ends_with($strings[$i], $suffix)) {
                $withSuffix[] = $alt;
            } else {
                $withoutSuffix[] = $alt;
            }
        }

        if (\count($withSuffix) < 2) {
            return $alts;
        }

        // Create prefixes (everything before the suffix)
        $prefixes = [];
        /**
         * @var AbstractNode $alt
         */
        foreach ($withSuffix as $alt) {
            $prefixStr = substr($this->nodeToString($alt), 0, -\strlen($suffix));
            if (empty($prefixStr)) {
                $prefixes[] = null;
            } else {
                $prefixes[] = $this->stringToNode($prefixStr, $alt->startPosition, $alt->endPosition - \strlen($suffix));
            }
        }

        /** @var array<Node\NodeInterface> $nonNullPrefixes */
        $nonNullPrefixes = array_values(array_filter($prefixes, static fn ($prefix): bool => null !== $prefix));
        if (empty($nonNullPrefixes)) {
            // All are just the suffix
            /** @var AbstractNode $firstAlt */
            $firstAlt = $withSuffix[0];

            return [$this->stringToNode($suffix, $firstAlt->endPosition - \strlen($suffix), $firstAlt->endPosition)];
        }

        /** @var AbstractNode $firstPrefix */
        $firstPrefix = $nonNullPrefixes[0];
        /** @var AbstractNode $lastPrefix */
        $lastPrefix = $nonNullPrefixes[\count($nonNullPrefixes) - 1];
        $newAlt = 1 === \count($nonNullPrefixes)
            ? $firstPrefix
            : new AlternationNode($nonNullPrefixes, $firstPrefix->startPosition, $lastPrefix->endPosition);
        $group = new GroupNode($newAlt, GroupType::T_GROUP_NON_CAPTURING);
        /** @var AbstractNode $firstAlt */
        $firstAlt = $withSuffix[0];
        $suffixNode = $this->stringToNode($suffix, $firstAlt->endPosition - \strlen($suffix), $firstAlt->endPosition);
        $factored = new SequenceNode([$group, $suffixNode], $firstAlt->startPosition, $firstAlt->endPosition);

        if (empty($withoutSuffix)) {
            return [$factored];
        }

        return array_merge([$factored], $withoutSuffix);
    }

    private function nodeToString(NodeInterface $node): string
    {
        $compiler = new CompilerNodeVisitor();

        return $node->accept($compiler);
    }

    private function stringToNode(string $str, int $start, int $end): NodeInterface
    {
        if (1 === \strlen($str)) {
            return new LiteralNode($str, $start, $end);
        }
        $children = [];
        for ($i = 0; $i < \strlen($str); $i++) {
            $children[] = new LiteralNode($str[$i], $start + $i, $start + $i + 1);
        }

        return new SequenceNode($children, $start, $end);
    }

    /**
     * @param array<string> $strings
     */
    private function findCommonPrefix(array $strings): string
    {
        if (empty($strings)) {
            return '';
        }
        $prefix = $strings[0];
        foreach ($strings as $str) {
            while (!str_starts_with((string) $str, (string) $prefix)) {
                $prefix = substr((string) $prefix, 0, -1);
                if (empty($prefix)) {
                    return '';
                }
            }
        }

        return $prefix;
    }

    /**
     * Merges adjacent character class nodes in an alternation.
     * For example: [a-z]|[0-9] becomes [a-z0-9]
     *
     * @param array<Node\NodeInterface> $alternatives
     *
     * @return array<Node\NodeInterface>
     */
    private function mergeAdjacentCharClasses(array $alternatives): array
    {
        if (\count($alternatives) < 2) {
            return $alternatives;
        }

        $merged = [];
        $i = 0;

        while ($i < \count($alternatives)) {
            $current = $alternatives[$i];

            // Check if current is a character class or a char type that can be converted
            $isCharClass = $current instanceof CharClassNode && !$current->isNegated;
            $isCharType = $current instanceof CharTypeNode && $this->canConvertCharTypeToCharClass($current);

            if (!$isCharClass && !$isCharType) {
                $merged[] = $current;
                $i++;

                continue;
            }

            // Look ahead to find adjacent character classes or char types
            $nodesToMerge = [$current];
            $j = $i + 1;

            while ($j < \count($alternatives)) {
                $next = $alternatives[$j];
                $isNextCharClass = $next instanceof CharClassNode && !$next->isNegated;
                $isNextCharType = $next instanceof CharTypeNode && $this->canConvertCharTypeToCharClass($next);

                if ($isNextCharClass || $isNextCharType) {
                    $nodesToMerge[] = $next;
                    $j++;
                } else {
                    break;
                }
            }

            // If we found multiple adjacent character classes/char types, merge them
            if (\count($nodesToMerge) > 1) {
                $merged[] = $this->mergeCharClassesAndCharTypes($nodesToMerge);
                $i = $j;
            } else {
                $merged[] = $current;
                $i++;
            }
        }

        return $merged;
    }

    /**
     * Checks if a CharTypeNode can be converted to a CharClassNode.
     */
    private function canConvertCharTypeToCharClass(CharTypeNode $node): bool
    {
        // For now, only handle \d (digits) which can be converted to [0-9]
        return 'd' === $node->value;
    }

    /**
     * Converts a CharTypeNode to an equivalent CharClassNode.
     */
    private function convertCharTypeToCharClass(CharTypeNode $node): CharClassNode
    {
        $startPos = $node->startPosition;
        $endPos = $node->endPosition;

        return match ($node->value) {
            'd' => new CharClassNode(
                new RangeNode(
                    new LiteralNode('0', $startPos, $startPos + 1),
                    new LiteralNode('9', $startPos + 1, $startPos + 2),
                    $startPos,
                    $startPos + 2,
                ),
                false,
                $startPos,
                $endPos,
            ),
            default => throw new \InvalidArgumentException("Unsupported char type: {$node->value}"),
        };
    }

    /**
     * Merges character classes and char types into a single character class.
     *
     * @param array<Node\NodeInterface> $nodes
     */
    private function mergeCharClassesAndCharTypes(array $nodes): CharClassNode
    {
        $allParts = [];
        $startPos = $nodes[0]->getStartPosition();
        $endPos = $nodes[\count($nodes) - 1]->getEndPosition();

        foreach ($nodes as $node) {
            if ($node instanceof CharClassNode) {
                if ($node->expression instanceof AlternationNode) {
                    $allParts = array_merge($allParts, $node->expression->alternatives);
                } else {
                    $allParts[] = $node->expression;
                }
            } elseif ($node instanceof CharTypeNode) {
                // Convert char type to equivalent char class parts
                $charClass = $this->convertCharTypeToCharClass($node);
                if ($charClass->expression instanceof AlternationNode) {
                    $allParts = array_merge($allParts, $charClass->expression->alternatives);
                } else {
                    $allParts[] = $charClass->expression;
                }
            }
        }

        // Create a new alternation with all parts
        $mergedExpression = new AlternationNode($allParts, $startPos, $endPos);

        return new CharClassNode($mergedExpression, false, $startPos, $endPos);
    }

    /**
     * Tries to convert an alternation to a character class if it's beneficial.
     * Only converts when it's clearly safe (no special char class metacharacters).
     *
     * @param array<Node\NodeInterface> $alternatives
     */
    private function tryConvertAlternationToCharClass(array $alternatives, int $startPos, int $endPos): ?CharClassNode
    {
        if (\count($alternatives) < 3) {
            return null; // Not worth it for small alternations
        }

        $literals = [];
        $other = [];

        foreach ($alternatives as $alt) {
            if ($alt instanceof LiteralNode && 1 === \strlen($alt->value)) {
                $char = $alt->value;
                // Don't convert if it contains char class metacharacters that would change meaning
                if (!isset(self::CHAR_CLASS_META[$char])) {
                    $literals[] = $char;
                } else {
                    return null; // Contains metacharacter, don't convert
                }
            } else {
                $other[] = $alt;
            }
        }

        if (!empty($other)) {
            return null; // Can't convert if there are non-literal parts
        }

        // Only convert if we have enough literals that form a consecutive range
        if (\count($literals) >= 3) {
            $sortedLiterals = $literals;
            sort($sortedLiterals);

            // Check if they form a consecutive range
            $first = $sortedLiterals[0];
            $last = end($sortedLiterals);
            $expected = [];
            for ($i = \ord($first); $i <= \ord($last); $i++) {
                $expected[] = \chr($i);
            }

            if ($sortedLiterals === $expected) {
                // Create a range instead
                $startLiteral = new LiteralNode($first, $startPos, $startPos + 1);
                $endLiteral = new LiteralNode($last, $endPos - 1, $endPos);

                return new CharClassNode(
                    new RangeNode($startLiteral, $endLiteral, $startPos, $endPos),
                    false,
                    $startPos,
                    $endPos,
                );
            }
        }

        return null;
    }
}
