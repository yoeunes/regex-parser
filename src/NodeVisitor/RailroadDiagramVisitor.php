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
 * Renders an ASCII tree diagram of the regex AST.
 *
 * @extends AbstractNodeVisitor<string>
 */
final class RailroadDiagramVisitor extends AbstractNodeVisitor
{
    /**
     * @var array<string>
     */
    private array $lines = [];

    /**
     * @var array<bool>
     */
    private array $branchStack = [];

    #[\Override]
    public function visitRegex(Node\RegexNode $node): string
    {
        $this->lines = [];
        $this->branchStack = [];

        $label = 'Regex';
        if ('' !== $node->flags) {
            $label .= ' (flags: '.$node->flags.')';
        }

        $this->addLine($label);
        $this->visitChildren([$node->pattern]);

        return implode("\n", $this->lines);
    }

    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): string
    {
        $this->addLine('Alternation');
        $this->visitChildren(array_values($node->alternatives));

        return '';
    }

    #[\Override]
    public function visitSequence(Node\SequenceNode $node): string
    {
        $this->addLine('Sequence');
        $this->visitChildren(array_values($node->children));

        return '';
    }

    #[\Override]
    public function visitGroup(Node\GroupNode $node): string
    {
        $label = 'Group ('.$this->describeGroupType($node).')';
        if (Node\GroupType::T_GROUP_NAMED === $node->type && null !== $node->name) {
            $label .= ' name="'.$node->name.'"';
        }
        if (Node\GroupType::T_GROUP_INLINE_FLAGS === $node->type && null !== $node->flags && '' !== $node->flags) {
            $label .= ' flags="'.$node->flags.'"';
        }

        $this->addLine($label);
        $this->visitChildren([$node->child]);

        return '';
    }

    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): string
    {
        $label = 'Quantifier ('.$node->quantifier.', '.$node->type->value.')';
        $this->addLine($label);
        $this->visitChildren([$node->node]);

        return '';
    }

    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): string
    {
        $value = addcslashes($node->value, "\0..\37\177..\377");
        $this->addLine("Literal ('".$value."')");

        return '';
    }

    #[\Override]
    public function visitCharLiteral(Node\CharLiteralNode $node): string
    {
        $this->addLine('CharLiteral ('.$node->originalRepresentation.')');

        return '';
    }

    #[\Override]
    public function visitCharType(Node\CharTypeNode $node): string
    {
        $this->addLine('CharType (\\'.$node->value.')');

        return '';
    }

    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): string
    {
        $this->addLine('Unicode (\\x'.$node->code.')');

        return '';
    }

    #[\Override]
    public function visitDot(Node\DotNode $node): string
    {
        $this->addLine('Dot (.)');

        return '';
    }

    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): string
    {
        $this->addLine('Anchor ('.$node->value.')');

        return '';
    }

    #[\Override]
    public function visitAssertion(Node\AssertionNode $node): string
    {
        $this->addLine('Assertion (\\'.$node->value.')');

        return '';
    }

    #[\Override]
    public function visitKeep(Node\KeepNode $node): string
    {
        $this->addLine('Keep (\\K)');

        return '';
    }

    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): string
    {
        $label = $node->isNegated ? 'CharClass (negated)' : 'CharClass';
        $this->addLine($label);
        $this->visitChildren([$node->expression]);

        return '';
    }

    #[\Override]
    public function visitRange(Node\RangeNode $node): string
    {
        $this->addLine('Range');
        $this->visitChildren([$node->start, $node->end]);

        return '';
    }

    #[\Override]
    public function visitBackref(Node\BackrefNode $node): string
    {
        $this->addLine('Backref (\\'.$node->ref.')');

        return '';
    }

    #[\Override]
    public function visitClassOperation(Node\ClassOperationNode $node): string
    {
        $label = 'ClassOperation ('.$node->type->value.')';
        $this->addLine($label);
        $this->visitChildren([$node->left, $node->right]);

        return '';
    }

    #[\Override]
    public function visitControlChar(Node\ControlCharNode $node): string
    {
        $this->addLine('ControlChar (\\c'.$node->char.')');

        return '';
    }

    #[\Override]
    public function visitScriptRun(Node\ScriptRunNode $node): string
    {
        $this->addLine('ScriptRun ('.$node->script.')');

        return '';
    }

    #[\Override]
    public function visitVersionCondition(Node\VersionConditionNode $node): string
    {
        $this->addLine('VersionCondition ('.$node->operator.' '.$node->version.')');

        return '';
    }

    #[\Override]
    public function visitUnicodeProp(Node\UnicodePropNode $node): string
    {
        $inner = $node->hasBraces ? trim($node->prop, '{}') : $node->prop;
        $display = '{'.$inner.'}';
        $this->addLine('UnicodeProperty (\\p'.$display.')');

        return '';
    }

    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): string
    {
        $this->addLine('PosixClass ([:'.$node->class.':])');

        return '';
    }

    #[\Override]
    public function visitComment(Node\CommentNode $node): string
    {
        $this->addLine('Comment');

        return '';
    }

    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): string
    {
        $this->addLine('Conditional');
        $this->visitChildren([$node->condition, $node->yes, $node->no]);

        return '';
    }

    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): string
    {
        $this->addLine('Subroutine ('.$node->reference.')');

        return '';
    }

    #[\Override]
    public function visitPcreVerb(Node\PcreVerbNode $node): string
    {
        $this->addLine('PCREVerb (*'.$node->verb.')');

        return '';
    }

    #[\Override]
    public function visitDefine(Node\DefineNode $node): string
    {
        $this->addLine('Define');
        $this->visitChildren([$node->content]);

        return '';
    }

    #[\Override]
    public function visitLimitMatch(Node\LimitMatchNode $node): string
    {
        $this->addLine('LimitMatch (*LIMIT_MATCH='.$node->limit.')');

        return '';
    }

    #[\Override]
    public function visitCallout(Node\CalloutNode $node): string
    {
        if (null === $node->identifier) {
            $label = 'Callout (?C)';
        } elseif ($node->isStringIdentifier) {
            $label = 'Callout (?C="'.$node->identifier.'")';
        } else {
            $label = 'Callout (?C'.$node->identifier.')';
        }

        $this->addLine($label);

        return '';
    }

    /**
     * @param array<Node\NodeInterface> $children
     */
    private function visitChildren(array $children): void
    {
        $total = \count($children);
        foreach ($children as $index => $child) {
            $this->branchStack[] = $index === $total - 1;
            $child->accept($this);
            array_pop($this->branchStack);
        }
    }

    private function addLine(string $label): void
    {
        $depth = \count($this->branchStack);
        $prefix = '';
        for ($i = 0; $i < $depth - 1; $i++) {
            $prefix .= $this->branchStack[$i] ? '    ' : '|   ';
        }

        if ($depth > 0) {
            $prefix .= $this->branchStack[$depth - 1] ? '\\-- ' : '|-- ';
        }

        $this->lines[] = $prefix.$label;
    }

    private function describeGroupType(Node\GroupNode $node): string
    {
        return match ($node->type) {
            Node\GroupType::T_GROUP_CAPTURING => 'capturing',
            Node\GroupType::T_GROUP_NON_CAPTURING => 'non-capturing',
            Node\GroupType::T_GROUP_NAMED => 'named',
            Node\GroupType::T_GROUP_LOOKAHEAD_POSITIVE => 'positive lookahead',
            Node\GroupType::T_GROUP_LOOKAHEAD_NEGATIVE => 'negative lookahead',
            Node\GroupType::T_GROUP_LOOKBEHIND_POSITIVE => 'positive lookbehind',
            Node\GroupType::T_GROUP_LOOKBEHIND_NEGATIVE => 'negative lookbehind',
            Node\GroupType::T_GROUP_INLINE_FLAGS => 'inline flags',
            Node\GroupType::T_GROUP_ATOMIC => 'atomic',
            Node\GroupType::T_GROUP_BRANCH_RESET => 'branch reset',
        };
    }
}
