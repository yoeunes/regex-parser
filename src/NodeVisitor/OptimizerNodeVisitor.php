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
use RegexParser\Node\GroupType;
use RegexParser\ReDoS\CharSetAnalyzer;

/**
 * Transforms the AST to apply optimizations, returning a new, simplified AST.
 *
 * Purpose: This visitor is the engine behind `Regex::optimize()`. It traverses the
 * AST and applies a series of rules to simplify the regex without changing its
 * meaning. This can lead to more readable and sometimes more performant patterns.
 * For contributors, this class is a great example of AST-to-AST transformation.
 * Each `visit` method can return a new, modified node, effectively rewriting
 * parts of the tree.
 *
 * @extends AbstractNodeVisitor<Node\NodeInterface>
 */
final class OptimizerNodeVisitor extends AbstractNodeVisitor
{
    private const CHAR_CLASS_META = [']' => true, '\\' => true, '^' => true, '-' => true];

    private string $flags = '';

    private readonly CharSetAnalyzer $charSetAnalyzer;

    public function __construct()
    {
        $this->charSetAnalyzer = new CharSetAnalyzer();
    }

    /**
     * Optimizes the root `RegexNode`.
     *
     * Purpose: This is the entry point for the optimization. It stores the regex
     * flags for context-aware optimizations (like unicode-dependent rules) and then
     * recursively optimizes the main pattern.
     *
     * @param Node\RegexNode $node the root node of the AST
     *
     * @return Node\NodeInterface the new, optimized root node
     */
    #[\Override]
    public function visitRegex(Node\RegexNode $node): Node\NodeInterface
    {
        $this->flags = $node->flags;
        $optimizedPattern = $node->pattern->accept($this);

        if ($optimizedPattern === $node->pattern) {
            return $node;
        }

        return new Node\RegexNode($optimizedPattern, $node->flags, $node->delimiter, $node->startPosition, $node->endPosition);
    }

    /**
     * Optimizes an `AlternationNode`.
     *
     * Purpose: This method applies two key optimizations:
     * 1.  **Flattening:** It merges nested alternations (e.g., `a|(b|c)` becomes `a|b|c`).
     * 2.  **Character Class Conversion:** It converts simple alternations of single
     *     characters into a more efficient character class (e.g., `a|b|c` becomes `[abc]`).
     *
     * @param Node\AlternationNode $node the alternation node to optimize
     *
     * @return Node\NodeInterface the new, optimized node (which could be an `AlternationNode` or `CharClassNode`)
     */
    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): Node\NodeInterface
    {
        $optimizedAlts = [];
        $hasChanged = false;

        foreach ($node->alternatives as $alt) {
            $optimizedAlt = $alt->accept($this);

            if ($optimizedAlt instanceof Node\AlternationNode) {
                array_push($optimizedAlts, ...$optimizedAlt->alternatives);
                $hasChanged = true;
            } else {
                $optimizedAlts[] = $optimizedAlt;
            }

            if ($optimizedAlt !== $alt) {
                $hasChanged = true;
            }
        }

        $deduplicatedAlts = $this->deduplicateAlternation($optimizedAlts);
        if (\count($deduplicatedAlts) !== \count($optimizedAlts)) {
            $hasChanged = true;
            $optimizedAlts = $deduplicatedAlts;
        }

        if ($this->canAlternationBeCharClass($optimizedAlts)) {
            /* @var list<Node\LiteralNode> $optimizedAlts */
            $expression = new Node\AlternationNode($optimizedAlts, $node->startPosition, $node->endPosition);

            return new Node\CharClassNode($expression, false, $node->startPosition, $node->endPosition);
        }

        if (!$hasChanged) {
            return $node;
        }

        $deduplicatedAlts = $this->deduplicateAlternation($optimizedAlts);
        if (\count($deduplicatedAlts) !== \count($optimizedAlts)) {
            $hasChanged = true;
            $optimizedAlts = $deduplicatedAlts;
        }

        $factoredAlts = $this->factorizeAlternation($optimizedAlts);

        if ($factoredAlts !== $optimizedAlts) {
            return new Node\AlternationNode($factoredAlts, $node->startPosition, $node->endPosition);
        }

        return new Node\AlternationNode($optimizedAlts, $node->startPosition, $node->endPosition);
    }

    /**
     * Optimizes a `SequenceNode`.
     *
     * Purpose: This method applies several optimizations to sequences:
     * 1.  **Literal Merging:** It combines adjacent `LiteralNode`s into a single node
     *     (e.g., the sequence `('a', 'b')` becomes `('ab')`).
     * 2.  **Flattening:** It merges nested sequences into the parent sequence.
     * 3.  **Empty Node Removal:** It removes empty `LiteralNode`s that might result
     *     from other optimizations (like an empty group).
     *
     * @param Node\SequenceNode $node the sequence node to optimize
     *
     * @return Node\NodeInterface the new, optimized node (which could be a `SequenceNode` or a single child node)
     */
    #[\Override]
    public function visitSequence(Node\SequenceNode $node): Node\NodeInterface
    {
        $optimizedChildren = [];
        $hasChanged = false;

        foreach ($node->children as $child) {
            $optimizedChild = $child->accept($this);

            if ($optimizedChild instanceof Node\LiteralNode && \count($optimizedChildren) > 0) {
                $prevNode = $optimizedChildren[\count($optimizedChildren) - 1];
                if ($prevNode instanceof Node\LiteralNode) {
                    $optimizedChildren[\count($optimizedChildren) - 1] = new Node\LiteralNode(
                        $prevNode->value.$optimizedChild->value,
                        $prevNode->startPosition,
                        $optimizedChild->endPosition,
                    );
                    $hasChanged = true;

                    continue;
                }
            }

            if ($optimizedChild instanceof Node\SequenceNode) {
                array_push($optimizedChildren, ...$optimizedChild->children);
                $hasChanged = true;

                continue;
            }

            if ($optimizedChild instanceof Node\LiteralNode && '' === $optimizedChild->value) {
                $hasChanged = true;

                continue;
            }

            if ($optimizedChild !== $child) {
                $hasChanged = true;
            }

            $optimizedChildren[] = $optimizedChild;
        }

        // Sequence compaction
        $originalCount = \count($optimizedChildren);
        /** @var list<Node\NodeInterface> $optimizedChildren */
        $optimizedChildren = array_values($optimizedChildren);
        $optimizedChildren = $this->compactSequence($optimizedChildren);
        if (\count($optimizedChildren) !== $originalCount) {
            $hasChanged = true;
        }

        // Auto-possessivization
        for ($i = 0; $i < \count($optimizedChildren) - 1; $i++) {
            $current = $optimizedChildren[$i];
            $next = $optimizedChildren[$i + 1];

            if ($current instanceof Node\QuantifierNode && Node\QuantifierType::T_GREEDY === $current->type) {
                if ($this->areCharSetsDisjoint($current->node, $next)) {
                    $optimizedChildren[$i] = new Node\QuantifierNode(
                        $current->node,
                        $current->quantifier,
                        Node\QuantifierType::T_POSSESSIVE,
                        $current->startPosition,
                        $current->endPosition,
                    );
                    $hasChanged = true;
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
            return new Node\LiteralNode('', $node->startPosition, $node->endPosition);
        }

        return new Node\SequenceNode($optimizedChildren, $node->startPosition, $node->endPosition);
    }

    /**
     * Optimizes a `GroupNode`.
     *
     * Purpose: This method simplifies groups where possible. Its main optimization is
     * to "unwrap" redundant non-capturing groups. For example, `(?:a)` is simplified
     * to just `a`, and `(?:[a-z])` becomes `[a-z]`, as the group serves no purpose.
     *
     * @param Node\GroupNode $node the group node to optimize
     *
     * @return Node\NodeInterface the new, optimized node (which might be the unwrapped child node)
     */
    #[\Override]
    public function visitGroup(Node\GroupNode $node): Node\NodeInterface
    {
        $optimizedChild = $node->child->accept($this);

        // Enhanced Group Unwrapping: (?:x) -> x
        // If the group is non-capturing and contains a single atomic node, remove the group.
        if (
            GroupType::T_GROUP_NON_CAPTURING === $node->type
            && (
                $optimizedChild instanceof Node\LiteralNode
                || $optimizedChild instanceof Node\CharTypeNode
                || $optimizedChild instanceof Node\DotNode
                || $optimizedChild instanceof Node\CharClassNode
                || $optimizedChild instanceof Node\AnchorNode
                || $optimizedChild instanceof Node\AssertionNode
                || $optimizedChild instanceof Node\UnicodeNode
                || $optimizedChild instanceof Node\UnicodePropNode
            )
        ) {
            return $optimizedChild;
        }

        if ($optimizedChild !== $node->child) {
            return new Node\GroupNode($optimizedChild, $node->type, $node->name, $node->flags, $node->startPosition, $node->endPosition);
        }

        return $node;
    }

    /**
     * Optimizes a `QuantifierNode`.
     *
     * Purpose: This method recursively optimizes the node that the quantifier applies
     * to. Future optimizations could be added here, such as merging adjacent identical
     * quantifiers (e.g., `a?a?` -> `a{0,2}`) or simplifying nested quantifiers (e.g., `(a*)*` -> `a*`).
     *
     * @param Node\QuantifierNode $node the quantifier node to optimize
     *
     * @return Node\NodeInterface the new, optimized quantifier node
     */
    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): Node\NodeInterface
    {
        $optimizedNode = $node->node->accept($this);

        if ($optimizedNode !== $node->node) {
            $node = new Node\QuantifierNode($optimizedNode, $node->quantifier, $node->type, $node->startPosition, $node->endPosition);
        }

        $normalized = $this->normalizeQuantifier($node);
        if ($normalized !== $node) {
            return $normalized;
        }

        return $node;
    }

    /**
     * Optimizes a `CharClassNode`.
     *
     * Purpose: This method simplifies character classes. For example, it can convert
     * a class containing a full digit range `[0-9]` into the more concise and efficient
     * `\d` token. It can also perform the same optimization for `\w`.
     *
     * @param Node\CharClassNode $node the character class node to optimize
     *
     * @return Node\NodeInterface the new, optimized node (which could be a `CharTypeNode`)
     */
    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): Node\NodeInterface
    {
        $isUnicode = str_contains($this->flags, 'u');
        $parts = $node->expression instanceof Node\AlternationNode ? $node->expression->alternatives : [$node->expression];

        if (!$isUnicode && !$node->isNegated && 1 === \count($parts)) {
            $part = $parts[0];
            if ($part instanceof Node\RangeNode && $part->start instanceof Node\LiteralNode && $part->end instanceof Node\LiteralNode) {
                if ('0' === $part->start->value && '9' === $part->end->value) {
                    return new Node\CharTypeNode('d', $node->startPosition, $node->endPosition);
                }
            }
        }

        if (!$isUnicode && !$node->isNegated && 4 === \count($parts)) {
            if ($this->isFullWordClass($parts)) {
                return new Node\CharTypeNode('w', $node->startPosition, $node->endPosition);
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
                $expression = new Node\AlternationNode($optimizedParts, $node->startPosition, $node->endPosition);
            }

            return new Node\CharClassNode($expression, $node->isNegated, $node->startPosition, $node->endPosition);
        }

        return $node;
    }

    /**
     * Visits a `RangeNode`.
     *
     * Purpose: Ranges are considered atomic and are not changed by the optimizer.
     * This method simply returns the node as is.
     *
     * @param Node\RangeNode $node the range node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitRange(Node\RangeNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Optimizes a `ConditionalNode`.
     *
     * Purpose: This method recursively optimizes the three branches of a conditional
     * node: the condition, the "yes" pattern, and the "no" pattern.
     *
     * @param Node\ConditionalNode $node the conditional node to optimize
     *
     * @return Node\NodeInterface the new, optimized conditional node
     */
    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): Node\NodeInterface
    {
        $optimizedCond = $node->condition->accept($this);
        $optimizedYes = $node->yes->accept($this);
        $optimizedNo = $node->no->accept($this);

        if ($optimizedCond !== $node->condition || $optimizedYes !== $node->yes || $optimizedNo !== $node->no) {
            return new Node\ConditionalNode($optimizedCond, $optimizedYes, $optimizedNo, $node->startPosition, $node->endPosition);
        }

        return $node;
    }

    /**
     * Visits a `LiteralNode`.
     *
     * Purpose: Literals are atomic and cannot be optimized further by this visitor.
     * The merging of adjacent literals is handled by the `visitSequence` method.
     *
     * @param Node\LiteralNode $node the literal node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): Node\NodeInterface
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
    public function visitCharType(Node\CharTypeNode $node): Node\NodeInterface
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
    public function visitDot(Node\DotNode $node): Node\NodeInterface
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
    public function visitAnchor(Node\AnchorNode $node): Node\NodeInterface
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
    public function visitAssertion(Node\AssertionNode $node): Node\NodeInterface
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
    public function visitKeep(Node\KeepNode $node): Node\NodeInterface
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
    public function visitBackref(Node\BackrefNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `UnicodeNode`.
     *
     * Purpose: Unicode character escapes are atomic and cannot be optimized.
     *
     * @param Node\UnicodeNode $node the Unicode node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): Node\NodeInterface
    {
        return $node;
    }

    #[\Override]
    public function visitUnicodeNamed(Node\UnicodeNamedNode $node): Node\NodeInterface
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
    public function visitUnicodeProp(Node\UnicodePropNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits an `OctalNode`.
     *
     * Purpose: Octal character escapes are atomic and cannot be optimized.
     *
     * @param Node\OctalNode $node the octal node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitOctal(Node\OctalNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits an `OctalLegacyNode`.
     *
     * Purpose: Legacy octal escapes are atomic and cannot be optimized.
     *
     * @param Node\OctalLegacyNode $node the legacy octal node
     *
     * @return Node\NodeInterface the unchanged node
     */
    #[\Override]
    public function visitOctalLegacy(Node\OctalLegacyNode $node): Node\NodeInterface
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
    public function visitPosixClass(Node\PosixClassNode $node): Node\NodeInterface
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
    public function visitComment(Node\CommentNode $node): Node\NodeInterface
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
    public function visitSubroutine(Node\SubroutineNode $node): Node\NodeInterface
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
    public function visitPcreVerb(Node\PcreVerbNode $node): Node\NodeInterface
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
    public function visitDefine(Node\DefineNode $node): Node\NodeInterface
    {
        return new Node\DefineNode(
            $node->content->accept($this),
            $node->startPosition,
            $node->endPosition,
        );
    }

    #[\Override]
    public function visitLimitMatch(Node\LimitMatchNode $node): Node\NodeInterface
    {
        return $node;
    }

    #[\Override]
    public function visitCallout(Node\CalloutNode $node): Node\NodeInterface
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
            if (!$alt instanceof Node\LiteralNode) {
                return false;
            }
            if (\strlen($alt->value) > 1) {
                return false;
            }
            if (isset(self::CHAR_CLASS_META[$alt->value])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<\RegexParser\Node\NodeInterface> $parts
     */
    private function isFullWordClass(array $parts): bool
    {
        $partsFound = ['a-z' => false, 'A-Z' => false, '0-9' => false, '_' => false];
        foreach ($parts as $part) {
            if ($part instanceof Node\RangeNode && $part->start instanceof Node\LiteralNode && $part->end instanceof Node\LiteralNode) {
                $range = $part->start->value.'-'.$part->end->value;
                if (isset($partsFound[$range])) {
                    $partsFound[$range] = true;
                }
            } elseif ($part instanceof Node\LiteralNode && '_' === $part->value) {
                $partsFound['_'] = true;
            }
        }

        return !\in_array(false, $partsFound, true);
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
            if ($part instanceof Node\LiteralNode && 1 === \strlen($part->value)) {
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

            if ($part instanceof Node\RangeNode
                && $part->start instanceof Node\LiteralNode
                && $part->end instanceof Node\LiteralNode
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

            if ($ord === $rangeEnd + 1) {
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
        $startLiteral = new Node\LiteralNode(mb_chr($startOrd), $startPos, $startPos + 1);

        if ($startOrd === $endOrd) {
            return [$startLiteral];
        }

        // Only create a range if it covers 3 or more characters to save space
        $coverage = $endOrd - $startOrd + 1;
        if ($coverage < 3) {
            // For 2 characters, return them as separate literals
            $endLiteral = new Node\LiteralNode(mb_chr($endOrd), $endPos, $endPos + 1);

            return [$startLiteral, $endLiteral];
        }

        $endLiteral = new Node\LiteralNode(mb_chr($endOrd), $endPos, $endPos + 1);

        return [new Node\RangeNode($startLiteral, $endLiteral, $startPos, $endPos)];
    }

    /**
     * @param list<Node\NodeInterface> $children
     *
     * @return list<Node\NodeInterface>
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

            if ($child instanceof Node\QuantifierNode) {
                $baseNode = $child->node;
                $count = $this->parseQuantifierCount($child->quantifier);
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

    private function parseQuantifierCount(string $quantifier): int
    {
        if (preg_match('/^\{(\d+)(?:,(\d*))?\}$/', $quantifier, $matches)) {
            $min = (int) $matches[1];
            $max = isset($matches[2]) ? ('' === $matches[2] ? \PHP_INT_MAX : (int) $matches[2]) : $min;
            if ($min === $max) {
                return $min;
            }

            // For ranges, don't merge
            return 1;
        }

        // For * + ?, count as 1
        return 1;
    }

    private function areNodesEqual(Node\NodeInterface $a, Node\NodeInterface $b): bool
    {
        // Simple equality: same type and same string representation
        if ($a::class !== $b::class) {
            return false;
        }

        return $this->nodeToString($a) === $this->nodeToString($b);
    }

    private function createQuantifiedNode(Node\NodeInterface $node, int $count): Node\NodeInterface
    {
        if (1 === $count) {
            return $node;
        }

        return new Node\QuantifierNode(
            $node,
            '{'.$count.'}',
            Node\QuantifierType::T_GREEDY,
            $node->getStartPosition(),
            $node->getEndPosition(),
        );
    }

    private function areCharSetsDisjoint(Node\NodeInterface $node1, Node\NodeInterface $node2): bool
    {
        try {
            $set1 = $this->charSetAnalyzer->lastChars($node1);
            $set2 = $this->charSetAnalyzer->firstChars($node2);

            return !$set1->intersects($set2);
        } catch (\Throwable) {
            return false; // If analysis fails, don't optimize
        }
    }

    private function normalizeQuantifier(Node\QuantifierNode $node): Node\NodeInterface
    {
        $quantifier = $node->quantifier;

        if ('{0,}' === $quantifier) {
            return new Node\QuantifierNode($node->node, '*', $node->type, $node->startPosition, $node->endPosition);
        }

        if ('{1,}' === $quantifier) {
            return new Node\QuantifierNode($node->node, '+', $node->type, $node->startPosition, $node->endPosition);
        }

        if ('{0,1}' === $quantifier) {
            return new Node\QuantifierNode($node->node, '?', $node->type, $node->startPosition, $node->endPosition);
        }

        if ('{1}' === $quantifier || '{1,1}' === $quantifier) {
            return $node->node;
        }

        if ('{0}' === $quantifier || '{0,0}' === $quantifier) {
            return new Node\LiteralNode('', $node->startPosition, $node->endPosition);
        }

        return $node;
    }

    /**
     * @param list<Node\NodeInterface> $alts
     *
     * @return list<Node\NodeInterface>
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
     * @param list<Node\NodeInterface> $alts
     *
     * @return list<Node\NodeInterface>
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
        if (empty($prefix)) {
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
        /**
         * @var \RegexParser\Node\AbstractNode $alt
         */
        foreach ($withPrefix as $alt) {
            $suffixStr = substr($this->nodeToString($alt), \strlen($prefix));
            if (empty($suffixStr)) {
                $suffixes[] = null;
            } else {
                $suffixes[] = $this->stringToNode($suffixStr, $alt->startPosition + \strlen($prefix), $alt->endPosition);
            }
        }

        $nonNullSuffixes = array_filter($suffixes, fn ($s) => null !== $s);
        if (empty($nonNullSuffixes)) {
            // All are just the prefix
            /** @var \RegexParser\Node\AbstractNode $firstAlt */
            $firstAlt = $withPrefix[0];

            return [$this->stringToNode($prefix, $firstAlt->startPosition, $firstAlt->startPosition + \strlen($prefix))];
        }

        /** @var \RegexParser\Node\AbstractNode $firstSuffix */
        $firstSuffix = $nonNullSuffixes[0];
        /** @var \RegexParser\Node\AbstractNode $lastSuffix */
        $lastSuffix = end($nonNullSuffixes);
        $newAlt = 1 === \count($nonNullSuffixes) ? $nonNullSuffixes[0] : new Node\AlternationNode($nonNullSuffixes, $firstSuffix->startPosition, $lastSuffix->endPosition);
        $group = new Node\GroupNode($newAlt, Node\GroupType::T_GROUP_NON_CAPTURING);
        /** @var \RegexParser\Node\AbstractNode $firstAlt */
        $firstAlt = $withPrefix[0];
        $prefixNode = $this->stringToNode($prefix, $firstAlt->startPosition, $firstAlt->startPosition + \strlen($prefix));
        $factored = new Node\SequenceNode([$prefixNode, $group], $firstAlt->startPosition, $firstAlt->endPosition);

        if (empty($withoutPrefix)) {
            return [$factored];
        }

        return array_merge([$factored], $withoutPrefix);

    }

    private function nodeToString(Node\NodeInterface $node): string
    {
        // Simple string representation, assuming Literal or Sequence of Literals
        if ($node instanceof Node\LiteralNode) {
            return $node->value;
        }
        if ($node instanceof Node\SequenceNode) {
            $str = '';
            foreach ($node->children as $child) {
                if ($child instanceof Node\LiteralNode) {
                    $str .= $child->value;
                } else {
                    return ''; // Can't handle
                }
            }

            return $str;
        }

        return '';
    }

    private function stringToNode(string $str, int $start, int $end): Node\NodeInterface
    {
        if (1 === \strlen($str)) {
            return new Node\LiteralNode($str, $start, $end);
        }
        $children = [];
        for ($i = 0; $i < \strlen($str); $i++) {
            $children[] = new Node\LiteralNode($str[$i], $start + $i, $start + $i + 1);
        }

        return new Node\SequenceNode($children, $start, $end);
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
}
