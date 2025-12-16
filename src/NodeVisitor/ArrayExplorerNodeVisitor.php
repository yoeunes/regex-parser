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

/**
 * Transforms the AST into a structured array tree suitable for UI visualization.
 *
 * @extends AbstractNodeVisitor<array<string, mixed>>
 */
final class ArrayExplorerNodeVisitor extends AbstractNodeVisitor
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitRegex(Node\RegexNode $node): array
    {
        return [
            'type' => 'Regex',
            'label' => 'Pattern',
            'detail' => $node->flags ? "Flags: {$node->flags}" : 'Global',
            'icon' => 'fa-solid fa-globe',
            'color' => 'text-indigo-600',
            'bg' => 'bg-indigo-50',
            'children' => [$node->pattern->accept($this)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitGroup(Node\GroupNode $node): array
    {
        [$label, $icon, $color, $bg] = match ($node->type) {
            GroupType::T_GROUP_CAPTURING => ['Capturing Group', 'fa-solid fa-brackets-round', 'text-green-600', 'bg-green-50'],
            GroupType::T_GROUP_NAMED => ["Named Group: <span class='font-mono'>{$node->name}</span>", 'fa-solid fa-tag', 'text-emerald-600', 'bg-emerald-50'],
            GroupType::T_GROUP_NON_CAPTURING => ['Non-Capturing Group', 'fa-solid fa-ban', 'text-slate-500', 'bg-slate-50'],
            GroupType::T_GROUP_ATOMIC => ['Atomic Group (?>...)', 'fa-solid fa-lock', 'text-red-500', 'bg-red-50'],
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE => ['Positive Lookahead (?=...)', 'fa-solid fa-eye', 'text-blue-600', 'bg-blue-50'],
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE => ['Negative Lookahead (?!...)', 'fa-solid fa-eye-slash', 'text-red-600', 'bg-red-50'],
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE => ['Positive Lookbehind (?<=...)', 'fa-solid fa-chevron-left', 'text-blue-600', 'bg-blue-50'],
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE => ['Negative Lookbehind (?<!...)', 'fa-solid fa-chevron-left', 'text-red-600', 'bg-red-50'],
            default => [ucfirst(str_replace('_', ' ', $node->type->value)), 'fa-solid fa-layer-group', 'text-blue-500', 'bg-blue-50'],
        };

        return [
            'type' => 'Group',
            'label' => $label,
            'icon' => $icon,
            'color' => $color,
            'bg' => $bg,
            'children' => [$node->child->accept($this)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): array
    {
        return [
            'type' => 'Quantifier',
            'label' => 'Quantifier',
            'detail' => "{$node->quantifier} (".ucfirst($node->type->value).')',
            'icon' => 'fa-solid fa-rotate-right',
            'color' => 'text-orange-600',
            'bg' => 'bg-orange-50',
            'children' => [$node->node->accept($this)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitSequence(Node\SequenceNode $node): array
    {
        return [
            'type' => 'Sequence',
            'label' => 'Sequence',
            'icon' => 'fa-solid fa-arrow-right-long',
            'color' => 'text-slate-400',
            'children' => array_map(fn ($child) => $child->accept($this), $node->children),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): array
    {
        return [
            'type' => 'Alternation',
            'label' => 'Alternation (OR)',
            'icon' => 'fa-solid fa-code-branch',
            'color' => 'text-purple-600',
            'bg' => 'bg-purple-50',
            'children' => array_map(fn ($child) => $child->accept($this), $node->alternatives),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): array
    {
        return [
            'type' => 'Literal',
            'label' => 'Literal',
            'detail' => $this->formatValue($node->value),
            'icon' => 'fa-solid fa-font',
            'color' => 'text-slate-700',
            'isLeaf' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): array
    {
        $label = $node->isNegated ? 'Negative Character Set [^...]' : 'Character Set [...]';
        $parts = $node->expression instanceof Node\AlternationNode ? $node->expression->alternatives : [$node->expression];

        return [
            'type' => 'CharClass',
            'label' => $label,
            'icon' => 'fa-solid fa-border-all',
            'color' => $node->isNegated ? 'text-red-600' : 'text-teal-600',
            'bg' => $node->isNegated ? 'bg-red-50' : 'bg-teal-50',
            'children' => array_map(fn ($child) => $child->accept($this), $parts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitRange(Node\RangeNode $node): array
    {
        return [
            'type' => 'Range',
            'label' => 'Range',
            'icon' => 'fa-solid fa-arrows-left-right',
            'color' => 'text-teal-600',
            'children' => [
                $node->start->accept($this),
                $node->end->accept($this),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitCharType(Node\CharTypeNode $node): array
    {
        $map = [
            'd' => 'Digit (0-9)', 'D' => 'Not Digit',
            'w' => 'Word Char', 'W' => 'Not Word Char',
            's' => 'Whitespace', 'S' => 'Not Whitespace',
        ];

        return [
            'type' => 'CharType',
            'label' => 'Character Type',
            'detail' => '\\'.$node->value.' ('.($map[$node->value] ?? 'Custom').')',
            'icon' => 'fa-solid fa-filter',
            'color' => 'text-blue-600',
            'isLeaf' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitDot(Node\DotNode $node): array
    {
        return [
            'type' => 'Dot',
            'label' => 'Wildcard (Dot)',
            'detail' => 'Any character',
            'icon' => 'fa-solid fa-circle',
            'color' => 'text-pink-600',
            'isLeaf' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): array
    {
        $map = ['^' => 'Start of Line', '$' => 'End of Line', '\A' => 'Start of String', '\z' => 'End of String'];

        return [
            'type' => 'Anchor',
            'label' => 'Anchor',
            'detail' => $node->value.' ('.($map[$node->value] ?? 'Custom').')',
            'icon' => 'fa-solid fa-anchor',
            'color' => 'text-rose-600',
            'isLeaf' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitAssertion(Node\AssertionNode $node): array
    {
        return [
            'type' => 'Assertion',
            'label' => 'Assertion',
            'detail' => '\\'.$node->value,
            'icon' => 'fa-solid fa-check-double',
            'color' => 'text-amber-600',
            'isLeaf' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitBackref(Node\BackrefNode $node): array
    {
        return [
            'type' => 'Backref',
            'label' => 'Backreference',
            'detail' => 'To group: '.$node->ref,
            'icon' => 'fa-solid fa-clock-rotate-left',
            'color' => 'text-cyan-600',
            'isLeaf' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): array
    {
        return [
            'type' => 'Unicode',
            'label' => 'Unicode Character',
            'detail' => $node->code,
            'icon' => 'fa-solid fa-language',
            'color' => 'text-violet-600',
            'isLeaf' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitUnicodeProp(Node\UnicodePropNode $node): array
    {
        return [
            'type' => 'UnicodeProp',
            'label' => 'Unicode Property',
            'detail' => '\p{'.$node->prop.'}',
            'icon' => 'fa-solid fa-globe-europe',
            'color' => 'text-violet-600',
            'isLeaf' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): array
    {
        return [
            'type' => 'PosixClass',
            'label' => 'POSIX Class',
            'detail' => '[:'.$node->class.':]',
            'icon' => 'fa-solid fa-box-archive',
            'color' => 'text-slate-600',
            'isLeaf' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitComment(Node\CommentNode $node): array
    {
        return [
            'type' => 'Comment',
            'label' => 'Comment',
            'detail' => $node->comment,
            'icon' => 'fa-solid fa-comment-slash',
            'color' => 'text-gray-400',
            'isLeaf' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): array
    {
        return [
            'type' => 'Conditional',
            'label' => 'Conditional (If-Then-Else)',
            'icon' => 'fa-solid fa-code-fork',
            'color' => 'text-fuchsia-600',
            'bg' => 'bg-fuchsia-50',
            'children' => [
                ['label' => 'Condition', 'children' => [$node->condition->accept($this)]],
                ['label' => 'If True', 'children' => [$node->yes->accept($this)]],
                ['label' => 'If False', 'children' => [$node->no->accept($this)]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): array
    {
        return [
            'type' => 'Subroutine',
            'label' => 'Subroutine',
            'detail' => 'Call: '.$node->reference,
            'icon' => 'fa-solid fa-recycle',
            'color' => 'text-cyan-600',
            'isLeaf' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitPcreVerb(Node\PcreVerbNode $node): array
    {
        return [
            'type' => 'PcreVerb',
            'label' => 'Control Verb',
            'detail' => '(*'.$node->verb.')',
            'icon' => 'fa-solid fa-gamepad',
            'color' => 'text-pink-500',
            'isLeaf' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitDefine(Node\DefineNode $node): array
    {
        return [
            'type' => 'Define',
            'label' => '(DEFINE) Block',
            'icon' => 'fa-solid fa-book',
            'color' => 'text-slate-500',
            'children' => [$node->content->accept($this)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function visitKeep(Node\KeepNode $node): array
    {
        return [
            'type' => 'Keep',
            'label' => 'Keep (\K)',
            'detail' => 'Reset match start',
            'icon' => 'fa-solid fa-scissors',
            'color' => 'text-orange-500',
            'isLeaf' => true,
        ];
    }

    #[\Override]
    public function visitLimitMatch(Node\LimitMatchNode $node): array
    {
        return [
            'type' => 'LimitMatch',
            'label' => 'Match Limit',
            'detail' => '(*LIMIT_MATCH='.$node->limit.')',
            'icon' => 'fa-solid fa-gauge-high',
            'color' => 'text-red-500',
            'isLeaf' => true,
        ];
    }

    #[\Override]
    public function visitCallout(Node\CalloutNode $node): array
    {
        $detail = match (true) {
            \is_int($node->identifier) => '(?C'.$node->identifier.')',
            $node->isStringIdentifier => '(?C"'.$node->identifier.'")',
            default => '(?C'.$node->identifier.')',
        };

        return [
            'type' => 'Callout',
            'label' => 'Callout',
            'detail' => $detail,
            'icon' => 'fa-solid fa-plug',
            'color' => 'text-amber-600',
            'isLeaf' => true,
        ];
    }

    private function formatValue(string $value): string
    {
        $map = ["\n" => '\n', "\r" => '\r', "\t" => '\t'];

        return '"'.strtr($value, $map).'"';
    }
}
