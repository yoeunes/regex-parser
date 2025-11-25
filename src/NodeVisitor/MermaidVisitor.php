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
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
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
 * Provides visual debugging and documentation capabilities.
 *
 * @implements NodeVisitorInterface<string>
 */
class MermaidVisitor implements NodeVisitorInterface
{
    private int $nodeCounter = 0;

    /** @var list<string> */
    private array $lines = [];

    public function visitRegex(RegexNode $node): string
    {
        $this->nodeCounter = 0;
        $this->lines = [];
        $this->lines[] = 'graph TD;';

        $nodeId = $this->nextNodeId();
        $flags = $node->flags ?: 'none';
        $this->lines[] = \sprintf('    %s["Regex: %s"]', $nodeId, $this->escape($flags));

        $childId = $node->pattern->accept($this);
        $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);

        return implode("\n", $this->lines);
    }

    public function visitAlternation(AlternationNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s{"Alternation"}', $nodeId);

        foreach ($node->nodes as $child) {
            $childId = $child->accept($this);
            $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);
        }

        return $nodeId;
    }

    public function visitSequence(SequenceNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Sequence"]', $nodeId);

        foreach ($node->children as $child) {
            $childId = $child->accept($this);
            $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);
        }

        return $nodeId;
    }

    public function visitGroup(GroupNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $label = \sprintf('Group: %s', $node->type->value);
        $name = $node->name ? ' ('.$node->name.')' : '';
        $this->lines[] = \sprintf('    %s("%s%s")', $nodeId, $this->escape($label), $this->escape($name));

        $childId = $node->child->accept($this);
        $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);

        return $nodeId;
    }

    public function visitQuantifier(QuantifierNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $label = \sprintf('Quantifier: %s', $node->quantifier);
        $this->lines[] = \sprintf('    %s["%s"]', $nodeId, $this->escape($label));

        $childId = $node->node->accept($this);
        $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);

        return $nodeId;
    }

    public function visitLiteral(LiteralNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $value = '' === $node->value ? '(empty)' : $node->value;
        $this->lines[] = \sprintf('    %s["Literal: %s"]', $nodeId, $this->escape($value));

        return $nodeId;
    }

    public function visitCharType(CharTypeNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["CharType: \\%s"]', $nodeId, $this->escape($node->value));

        return $nodeId;
    }

    public function visitDot(DotNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Dot: any char"]', $nodeId);

        return $nodeId;
    }

    public function visitAnchor(AnchorNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s(("Anchor: %s"))', $nodeId, $this->escape($node->value));

        return $nodeId;
    }

    public function visitAssertion(AssertionNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Assertion: %s"]', $nodeId, $this->escape($node->value));

        return $nodeId;
    }

    public function visitKeep(KeepNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Keep: \\K"]', $nodeId);

        return $nodeId;
    }

    public function visitCharClass(CharClassNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $label = 'CharClass'.($node->isNegated ? ' [NOT]' : '');
        $this->lines[] = \sprintf('    %s["%s"]', $nodeId, $label);

        foreach ($node->parts as $child) {
            $childId = $child->accept($this);
            $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);
        }

        return $nodeId;
    }

    public function visitRange(RangeNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Range"]', $nodeId);

        $startId = $node->start->accept($this);
        $endId = $node->end->accept($this);
        $this->lines[] = \sprintf('    %s -->|from| %s', $nodeId, $startId);
        $this->lines[] = \sprintf('    %s -->|to| %s', $nodeId, $endId);

        return $nodeId;
    }

    public function visitBackref(BackrefNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Backref: %s"]', $nodeId, $this->escape($node->value));

        return $nodeId;
    }

    public function visitUnicode(UnicodeNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Unicode: %s"]', $nodeId, $this->escape($node->value));

        return $nodeId;
    }

    public function visitUnicodeProp(UnicodePropNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["UnicodeProp: %s"]', $nodeId, $this->escape($node->value));

        return $nodeId;
    }

    public function visitOctal(OctalNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Octal: %s"]', $nodeId, $this->escape($node->value));

        return $nodeId;
    }

    public function visitOctalLegacy(OctalLegacyNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["OctalLegacy: %s"]', $nodeId, $this->escape($node->value));

        return $nodeId;
    }

    public function visitPosixClass(PosixClassNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["PosixClass: %s"]', $nodeId, $this->escape($node->value));

        return $nodeId;
    }

    public function visitComment(CommentNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $comment = substr($node->value, 0, 20);
        $this->lines[] = \sprintf('    %s["Comment: %s"]', $nodeId, $this->escape($comment));

        return $nodeId;
    }

    public function visitConditional(ConditionalNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s{{"Conditional"}}', $nodeId);

        if (null !== $node->condition) {
            $condId = $node->condition->accept($this);
            $this->lines[] = \sprintf('    %s -->|condition| %s', $nodeId, $condId);
        }
        if (null !== $node->yesNode) {
            $yesId = $node->yesNode->accept($this);
            $this->lines[] = \sprintf('    %s -->|yes| %s', $nodeId, $yesId);
        }
        if (null !== $node->noNode) {
            $noId = $node->noNode->accept($this);
            $this->lines[] = \sprintf('    %s -->|no| %s', $nodeId, $noId);
        }

        return $nodeId;
    }

    public function visitSubroutine(SubroutineNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $name = $node->name ?? 'recursive';
        $this->lines[] = \sprintf('    %s["Subroutine: %s"]', $nodeId, $this->escape($name));

        return $nodeId;
    }

    public function visitPcreVerb(PcreVerbNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["PcreVerb: %s"]', $nodeId, $this->escape($node->value));

        return $nodeId;
    }

    private function nextNodeId(): string
    {
        return 'node'.($this->nodeCounter++);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }
}
