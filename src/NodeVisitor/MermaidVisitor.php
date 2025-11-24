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

use RegexParser\Node\AnchorNode;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\OctalLegacyNode;
use RegexParser\Node\OctalNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;

/**
 * Generates a Mermaid.js flowchart visualization of a regex AST.
 *
 * Provides visual debugging and documentation capabilities inspired by Python's `re`
 * and JavaScript's `regexp-tree` libraries.
 *
 * @implements NodeVisitorInterface<string>
 */
class MermaidVisitor implements NodeVisitorInterface
{
    /**
     * @var int Counter for unique node IDs
     */
    private int $nodeCounter = 0;

    /**
     * @var list<string> Accumulated lines of Mermaid syntax
     */
    private array $lines = [];

    /**
     * @var array<int, string> Map of node object IDs to Mermaid node IDs
     */
    private array $nodeIdMap = [];

    public function visit(NodeInterface $node): string
    {
        // Reset state for new visualization
        $this->nodeCounter = 0;
        $this->lines = [];
        $this->nodeIdMap = [];

        // Start the Mermaid graph
        $this->lines[] = 'graph TD;';

        // Visit the node
        $nodeId = $this->visitNode($node);

        // Join all lines with newline
        return implode("\n", $this->lines);
    }

    private function visitNode(NodeInterface $node): string
    {
        $nodeId = $this->nextNodeId();
        $spl = spl_object_id($node);
        $this->nodeIdMap[$spl] = $nodeId;

        return match ($node::class) {
            RegexNode::class => $this->visitRegexNode($node, $nodeId),
            SequenceNode::class => $this->visitSequenceNode($node, $nodeId),
            AlternationNode::class => $this->visitAlternationNode($node, $nodeId),
            GroupNode::class => $this->visitGroupNode($node, $nodeId),
            QuantifierNode::class => $this->visitQuantifierNode($node, $nodeId),
            CharClassNode::class => $this->visitCharClassNode($node, $nodeId),
            AnchorNode::class => $this->visitAnchorNode($node, $nodeId),
            AssertionNode::class => $this->visitAssertionNode($node, $nodeId),
            ConditionalNode::class => $this->visitConditionalNode($node, $nodeId),
            LiteralNode::class => $this->visitLiteralNode($node, $nodeId),
            DotNode::class => $this->visitDotNode($node, $nodeId),
            CharTypeNode::class => $this->visitCharTypeNode($node, $nodeId),
            BackrefNode::class => $this->visitBackrefNode($node, $nodeId),
            UnicodeNode::class => $this->visitUnicodeNode($node, $nodeId),
            UnicodePropNode::class => $this->visitUnicodePropNode($node, $nodeId),
            RangeNode::class => $this->visitRangeNode($node, $nodeId),
            PosixClassNode::class => $this->visitPosixClassNode($node, $nodeId),
            OctalNode::class => $this->visitOctalNode($node, $nodeId),
            OctalLegacyNode::class => $this->visitOctalLegacyNode($node, $nodeId),
            PcreVerbNode::class => $this->visitPcreVerbNode($node, $nodeId),
            SubroutineNode::class => $this->visitSubroutineNode($node, $nodeId),
            KeepNode::class => $this->visitKeepNode($node, $nodeId),
            CommentNode::class => $this->visitCommentNode($node, $nodeId),
            default => $nodeId,
        };
    }

    private function visitRegexNode(RegexNode $node, string $nodeId): string
    {
        $flags = $node->flags ?: 'none';
        $this->lines[] = \sprintf('    %s["Regex: %s"]', $nodeId, htmlspecialchars($flags));
        $childId = $this->visitNode($node->pattern);
        $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);

        return $nodeId;
    }

    private function visitSequenceNode(SequenceNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["Sequence"]', $nodeId);
        foreach ($node->nodes as $child) {
            $childId = $this->visitNode($child);
            $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);
        }

        return $nodeId;
    }

    private function visitAlternationNode(AlternationNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s{"Alternation"}', $nodeId);
        foreach ($node->nodes as $child) {
            $childId = $this->visitNode($child);
            $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);
        }

        return $nodeId;
    }

    private function visitGroupNode(GroupNode $node, string $nodeId): string
    {
        $label = \sprintf('Group: %s', $node->type->name);
        $name = $node->name ? ' ('.$node->name.')' : '';
        $this->lines[] = \sprintf('    %s("\\%s\\%s")', $nodeId, $label, htmlspecialchars($name));
        $childId = $this->visitNode($node->node);
        $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);

        return $nodeId;
    }

    private function visitQuantifierNode(QuantifierNode $node, string $nodeId): string
    {
        $label = \sprintf('Quantifier: %s{%d,%d}', $node->type->name, $node->min, $node->max);
        $this->lines[] = \sprintf('    %s["%s"]', $nodeId, htmlspecialchars($label));
        $childId = $this->visitNode($node->node);
        $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);

        return $nodeId;
    }

    private function visitCharClassNode(CharClassNode $node, string $nodeId): string
    {
        $label = 'CharClass'.($node->negated ? ' [NOT]' : '');
        $this->lines[] = \sprintf('    %s["%s"]', $nodeId, $label);
        foreach ($node->nodes as $child) {
            $childId = $this->visitNode($child);
            $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);
        }

        return $nodeId;
    }

    private function visitAnchorNode(AnchorNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s(("Anchor: %s"))', $nodeId, htmlspecialchars($node->value));

        return $nodeId;
    }

    private function visitAssertionNode(AssertionNode $node, string $nodeId): string
    {
        $label = \sprintf('Assertion: %s', $node->type->name);
        $this->lines[] = \sprintf('    %s["%s"]', $nodeId, htmlspecialchars($label));
        if (null !== $node->node) {
            $childId = $this->visitNode($node->node);
            $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);
        }

        return $nodeId;
    }

    private function visitConditionalNode(ConditionalNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s{{"Conditional"}}', $nodeId);
        if (null !== $node->condition) {
            $condId = $this->visitNode($node->condition);
            $this->lines[] = \sprintf('    %s -->|condition| %s', $nodeId, $condId);
        }
        if (null !== $node->yesNode) {
            $yesId = $this->visitNode($node->yesNode);
            $this->lines[] = \sprintf('    %s -->|yes| %s', $nodeId, $yesId);
        }
        if (null !== $node->noNode) {
            $noId = $this->visitNode($node->noNode);
            $this->lines[] = \sprintf('    %s -->|no| %s', $nodeId, $noId);
        }

        return $nodeId;
    }

    private function visitLiteralNode(LiteralNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["%s"]', $nodeId, htmlspecialchars($node->value));

        return $nodeId;
    }

    private function visitDotNode(DotNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["Dot: any char"]', $nodeId);

        return $nodeId;
    }

    private function visitCharTypeNode(CharTypeNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["CharType: %s"]', $nodeId, htmlspecialchars($node->value));

        return $nodeId;
    }

    private function visitBackrefNode(BackrefNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["Backref: %s"]', $nodeId, htmlspecialchars($node->value));

        return $nodeId;
    }

    private function visitUnicodeNode(UnicodeNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["Unicode: %s"]', $nodeId, htmlspecialchars($node->value));

        return $nodeId;
    }

    private function visitUnicodePropNode(UnicodePropNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["UnicodeProp: %s"]', $nodeId, htmlspecialchars($node->value));

        return $nodeId;
    }

    private function visitRangeNode(RangeNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["Range"]', $nodeId);
        $startId = $this->visitNode($node->start);
        $endId = $this->visitNode($node->end);
        $this->lines[] = \sprintf('    %s -->|from| %s', $nodeId, $startId);
        $this->lines[] = \sprintf('    %s -->|to| %s', $nodeId, $endId);

        return $nodeId;
    }

    private function visitPosixClassNode(PosixClassNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["PosixClass: %s"]', $nodeId, htmlspecialchars($node->value));

        return $nodeId;
    }

    private function visitOctalNode(OctalNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["Octal: %s"]', $nodeId, htmlspecialchars($node->value));

        return $nodeId;
    }

    private function visitOctalLegacyNode(OctalLegacyNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["OctalLegacy: %s"]', $nodeId, htmlspecialchars($node->value));

        return $nodeId;
    }

    private function visitPcreVerbNode(PcreVerbNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["PcreVerb: %s"]', $nodeId, htmlspecialchars($node->value));

        return $nodeId;
    }

    private function visitSubroutineNode(SubroutineNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["Subroutine: %s"]', $nodeId, htmlspecialchars($node->name ?? 'recursive'));

        return $nodeId;
    }

    private function visitKeepNode(KeepNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["Keep: \\K"]', $nodeId);

        return $nodeId;
    }

    private function visitCommentNode(CommentNode $node, string $nodeId): string
    {
        $this->lines[] = \sprintf('    %s["Comment: %s"]', $nodeId, htmlspecialchars(substr($node->value, 0, 20)));

        return $nodeId;
    }

    private function nextNodeId(): string
    {
        return 'node'.($this->nodeCounter++);
    }
}
