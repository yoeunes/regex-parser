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

/**
 * Collects structural metrics about an AST (node counts and depth).
 *
 * @extends AbstractNodeVisitor<array{counts: array<string, int>, total: int, maxDepth: int}>
 */
final class MetricsNodeVisitor extends AbstractNodeVisitor
{
    /**
     * @var array<string, int>
     */
    private array $counts = [];

    private int $total = 0;

    private int $maxDepth = 0;

    private int $currentDepth = 0;

    public function visitRegex(Node\RegexNode $node): array
    {
        return $this->record($node, function () use ($node): void {
            $this->visitChild($node->pattern);
        });
    }

    public function visitAlternation(Node\AlternationNode $node): array
    {
        return $this->record($node, function () use ($node): void {
            $this->visitChildren($node->alternatives);
        });
    }

    public function visitSequence(Node\SequenceNode $node): array
    {
        return $this->record($node, function () use ($node): void {
            $this->visitChildren($node->children);
        });
    }

    public function visitGroup(Node\GroupNode $node): array
    {
        return $this->record($node, function () use ($node): void {
            $this->visitChild($node->child);
        });
    }

    public function visitQuantifier(Node\QuantifierNode $node): array
    {
        return $this->record($node, function () use ($node): void {
            $this->visitChild($node->node);
        });
    }

    public function visitLiteral(Node\LiteralNode $node): array
    {
        return $this->record($node);
    }

    public function visitCharType(Node\CharTypeNode $node): array
    {
        return $this->record($node);
    }

    public function visitDot(Node\DotNode $node): array
    {
        return $this->record($node);
    }

    public function visitAnchor(Node\AnchorNode $node): array
    {
        return $this->record($node);
    }

    public function visitAssertion(Node\AssertionNode $node): array
    {
        return $this->record($node);
    }

    public function visitKeep(Node\KeepNode $node): array
    {
        return $this->record($node);
    }

    public function visitCharClass(Node\CharClassNode $node): array
    {
        return $this->record($node, function () use ($node): void {
            $this->visitChildren($node->parts);
        });
    }

    public function visitRange(Node\RangeNode $node): array
    {
        return $this->record($node, function () use ($node): void {
            $this->visitChild($node->start);
            $this->visitChild($node->end);
        });
    }

    public function visitBackref(Node\BackrefNode $node): array
    {
        return $this->record($node);
    }

    public function visitUnicode(Node\UnicodeNode $node): array
    {
        return $this->record($node);
    }

    public function visitUnicodeProp(Node\UnicodePropNode $node): array
    {
        return $this->record($node);
    }

    public function visitOctal(Node\OctalNode $node): array
    {
        return $this->record($node);
    }

    public function visitOctalLegacy(Node\OctalLegacyNode $node): array
    {
        return $this->record($node);
    }

    public function visitPosixClass(Node\PosixClassNode $node): array
    {
        return $this->record($node);
    }

    public function visitComment(Node\CommentNode $node): array
    {
        return $this->record($node);
    }

    public function visitConditional(Node\ConditionalNode $node): array
    {
        return $this->record($node, function () use ($node): void {
            $this->visitChild($node->condition);
            $this->visitChild($node->yes);
            $this->visitChild($node->no);
        });
    }

    public function visitSubroutine(Node\SubroutineNode $node): array
    {
        return $this->record($node);
    }

    public function visitPcreVerb(Node\PcreVerbNode $node): array
    {
        return $this->record($node);
    }

    public function visitDefine(Node\DefineNode $node): array
    {
        return $this->record($node, function () use ($node): void {
            $this->visitChild($node->content);
        });
    }

    public function visitLimitMatch(Node\LimitMatchNode $node): array
    {
        return $this->record($node);
    }

    public function visitCallout(Node\CalloutNode $node): array
    {
        return $this->record($node);
    }

    /**
     * @return array{counts: array<string, int>, total: int, maxDepth: int}
     */
    private function record(Node\NodeInterface $node, ?callable $traverse = null): array
    {
        $this->total++;
        $type = $this->shortName($node::class);
        $this->counts[$type] = ($this->counts[$type] ?? 0) + 1;

        $this->currentDepth++;
        $this->maxDepth = max($this->maxDepth, $this->currentDepth);

        if (null !== $traverse) {
            $traverse();
        }

        $this->currentDepth--;

        return $this->snapshot();
    }

    private function visitChild(Node\NodeInterface $child): void
    {
        $child->accept($this);
    }

    /**
     * @param array<Node\NodeInterface> $children
     */
    private function visitChildren(array $children): void
    {
        foreach ($children as $child) {
            $this->visitChild($child);
        }
    }

    private function shortName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return false === $pos ? $class : substr($class, $pos + 1);
    }

    /**
     * @return array{counts: array<string, int>, total: int, maxDepth: int}
     */
    private function snapshot(): array
    {
        return [
            'counts' => $this->counts,
            'total' => $this->total,
            'maxDepth' => $this->maxDepth,
        ];
    }
}
