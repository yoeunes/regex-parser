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
use RegexParser\Node\DefineNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
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
 * Transforms the AST into a structured array tree suitable for UI visualization.
 *
 * This visitor is used by the interactive playground to render the AST
 * in a hierarchical, collapsible format (similar to regexr.com).
 *
 * @implements NodeVisitorInterface<array<string, mixed>>
 */
final class ArrayExplorerVisitor implements NodeVisitorInterface
{
    public function visitRegex(RegexNode $node): array
    {
        return [
            'type' => 'Regex',
            'label' => 'Pattern',
            'detail' => $node->flags ? "Flags: {$node->flags}" : 'Global',
            'icon' => 'fa-solid fa-globe',
            'color' => 'text-indigo-600',
            'bg' => 'bg-indigo-50',
            'border' => 'border-indigo-200',
            'children' => [$node->pattern->accept($this)],
        ];
    }

    public function visitGroup(GroupNode $node): array
    {
        // Determine visual style based on group type
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

    public function visitQuantifier(QuantifierNode $node): array
    {
        return [
            'type' => 'Quantifier',
            'label' => 'Quantifier',
            'detail' => "{$node->quantifier} (" . ucfirst($node->type->value) . ')',
            'icon' => 'fa-solid fa-rotate-right',
            'color' => 'text-orange-600',
            'bg' => 'bg-orange-50',
            'children' => [$node->node->accept($this)],
        ];
    }

    public function visitSequence(SequenceNode $node): array
    {
        // Map all children
        $children = array_map(fn ($child) => $child->accept($this), $node->children);

        return [
            'type' => 'Sequence',
            'label' => 'Sequence',
            'icon' => 'fa-solid fa-arrow-right-long',
            'color' => 'text-slate-400',
            'children' => $children,
        ];
    }

    public function visitAlternation(AlternationNode $node): array
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

    public function visitLiteral(LiteralNode $node): array
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

    public function visitCharClass(CharClassNode $node): array
    {
        $label = $node->isNegated ? 'Negative Character Set [^...]' : 'Character Set [...]';

        return [
            'type' => 'CharClass',
            'label' => $label,
            'icon' => 'fa-solid fa-border-all',
            'color' => $node->isNegated ? 'text-red-600' : 'text-teal-600',
            'bg' => $node->isNegated ? 'bg-red-50' : 'bg-teal-50',
            'children' => array_map(fn ($child) => $child->accept($this), $node->parts),
        ];
    }

    public function visitRange(RangeNode $node): array
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

    public function visitCharType(CharTypeNode $node): array
    {
        $map = [
            'd' => 'Digit (0-9)', 'D' => 'Not Digit',
            'w' => 'Word Char', 'W' => 'Not Word Char',
            's' => 'Whitespace', 'S' => 'Not Whitespace',
        ];

        return [
            'type' => 'CharType',
            'label' => 'Character Type',
            'detail' => '\\' . $node->value . ' (' . ($map[$node->value] ?? 'Custom') . ')',
            'icon' => 'fa-solid fa-filter',
            'color' => 'text-blue-600',
            'isLeaf' => true,
        ];
    }

    public function visitDot(DotNode $node): array
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

    public function visitAnchor(AnchorNode $node): array
    {
        $map = ['^' => 'Start of Line', '$' => 'End of Line', '\A' => 'Start of String', '\z' => 'End of String'];

        return [
            'type' => 'Anchor',
            'label' => 'Anchor',
            'detail' => $node->value . ' (' . ($map[$node->value] ?? 'Custom') . ')',
            'icon' => 'fa-solid fa-anchor',
            'color' => 'text-rose-600',
            'isLeaf' => true,
        ];
    }

    public function visitAssertion(AssertionNode $node): array
    {
        return [
            'type' => 'Assertion',
            'label' => 'Assertion',
            'detail' => '\\' . $node->value,
            'icon' => 'fa-solid fa-check-double',
            'color' => 'text-amber-600',
            'isLeaf' => true,
        ];
    }

    public function visitBackref(BackrefNode $node): array
    {
        return [
            'type' => 'Backref',
            'label' => 'Backreference',
            'detail' => 'To group: ' . $node->ref,
            'icon' => 'fa-solid fa-clock-rotate-left',
            'color' => 'text-cyan-600',
            'isLeaf' => true,
        ];
    }

    public function visitUnicode(UnicodeNode $node): array
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

    public function visitUnicodeProp(UnicodePropNode $node): array
    {
        return [
            'type' => 'UnicodeProp',
            'label' => 'Unicode Property',
            'detail' => '\p{' . $node->prop . '}',
            'icon' => 'fa-solid fa-globe-europe',
            'color' => 'text-violet-600',
            'isLeaf' => true,
        ];
    }

    public function visitPosixClass(PosixClassNode $node): array
    {
        return [
            'type' => 'PosixClass',
            'label' => 'POSIX Class',
            'detail' => '[:' . $node->class . ':]',
            'icon' => 'fa-solid fa-box-archive',
            'color' => 'text-slate-600',
            'isLeaf' => true,
        ];
    }

    public function visitComment(CommentNode $node): array
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

    public function visitConditional(ConditionalNode $node): array
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

    public function visitSubroutine(SubroutineNode $node): array
    {
        return [
            'type' => 'Subroutine',
            'label' => 'Subroutine',
            'detail' => 'Call: ' . $node->reference,
            'icon' => 'fa-solid fa-recycle',
            'color' => 'text-cyan-600',
            'isLeaf' => true,
        ];
    }

    public function visitPcreVerb(PcreVerbNode $node): array
    {
        return [
            'type' => 'PcreVerb',
            'label' => 'Control Verb',
            'detail' => '(*' . $node->verb . ')',
            'icon' => 'fa-solid fa-gamepad',
            'color' => 'text-pink-500',
            'isLeaf' => true,
        ];
    }

    public function visitDefine(DefineNode $node): array
    {
        return [
            'type' => 'Define',
            'label' => '(DEFINE) Block',
            'icon' => 'fa-solid fa-book',
            'color' => 'text-slate-500',
            'children' => [$node->content->accept($this)],
        ];
    }

    public function visitKeep(KeepNode $node): array
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

    public function visitOctal(OctalNode $node): array
    {
        return $this->genericLeaf('Octal', $node->code);
    }

    public function visitOctalLegacy(OctalLegacyNode $node): array
    {
        return $this->genericLeaf('Legacy Octal', $node->code);
    }

    private function genericLeaf(string $label, string $detail): array
    {
        return [
            'type' => 'Generic',
            'label' => $label,
            'detail' => $detail,
            'icon' => 'fa-solid fa-cube',
            'color' => 'text-gray-500',
            'isLeaf' => true,
        ];
    }

    private function formatValue(string $value): string
    {
        // Format non-printable chars for display
        $map = ["\n" => '\n', "\r" => '\r', "\t" => '\t'];
        return '"' . strtr($value, $map) . '"';
    }
}
