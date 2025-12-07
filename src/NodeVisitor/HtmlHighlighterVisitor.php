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
 * Highlights regex syntax for HTML output using span tags with classes.
 *
 * @extends AbstractNodeVisitor<string>
 */
final class HtmlHighlighterVisitor extends AbstractNodeVisitor
{
    private const CLASSES = [
        'meta' => 'regex-meta',
        'quantifier' => 'regex-quantifier',
        'type' => 'regex-type',
        'anchor' => 'regex-anchor',
        'literal' => 'regex-literal',
    ];

    public function visitRegex(Node\RegexNode $node): string
    {
        return $node->pattern->accept($this);
    }

    public function visitAlternation(Node\AlternationNode $node): string
    {
        $parts = [];
        foreach ($node->alternatives as $alt) {
            $parts[] = $alt->accept($this);
        }
        return implode('<span class="' . self::CLASSES['meta'] . '">|</span>', $parts);
    }

    public function visitSequence(Node\SequenceNode $node): string
    {
        $parts = [];
        foreach ($node->children as $child) {
            $parts[] = $child->accept($this);
        }
        return implode('', $parts);
    }

    public function visitGroup(Node\GroupNode $node): string
    {
        $inner = $node->child->accept($this);
        $prefix = match ($node->type) {
            Node\GroupType::T_GROUP_NON_CAPTURING => '?:',
            Node\GroupType::T_GROUP_LOOKAHEAD_POSITIVE => '?=',
            Node\GroupType::T_GROUP_LOOKAHEAD_NEGATIVE => '?!',
            Node\GroupType::T_GROUP_LOOKBEHIND_POSITIVE => '?<=',
            Node\GroupType::T_GROUP_LOOKBEHIND_NEGATIVE => '?<!',
            Node\GroupType::T_GROUP_ATOMIC => '?>',
            Node\GroupType::T_GROUP_NAMED => "?&lt;{$node->name}&gt;",
            default => '',
        };
        $opening = '<span class="' . self::CLASSES['meta'] . '">(' . htmlspecialchars($prefix) . '</span>';
        $closing = '<span class="' . self::CLASSES['meta'] . '">)</span>';
        return $opening . $inner . $closing;
    }

    public function visitQuantifier(Node\QuantifierNode $node): string
    {
        $inner = $node->node->accept($this);
        $quant = $node->quantifier;
        if ($node->type === Node\QuantifierType::T_LAZY) {
            $quant .= '?';
        } elseif ($node->type === Node\QuantifierType::T_POSSESSIVE) {
            $quant .= '+';
        }
        return $inner . '<span class="' . self::CLASSES['quantifier'] . '">' . htmlspecialchars($quant) . '</span>';
    }

    public function visitLiteral(Node\LiteralNode $node): string
    {
        return '<span class="' . self::CLASSES['literal'] . '">' . htmlspecialchars($node->value) . '</span>';
    }

    public function visitCharType(Node\CharTypeNode $node): string
    {
        return '<span class="' . self::CLASSES['type'] . '">\\' . $node->value . '</span>';
    }

    public function visitDot(Node\DotNode $node): string
    {
        return '<span class="' . self::CLASSES['meta'] . '">.</span>';
    }

    public function visitAnchor(Node\AnchorNode $node): string
    {
        return '<span class="' . self::CLASSES['anchor'] . '">' . htmlspecialchars($node->value) . '</span>';
    }

    public function visitAssertion(Node\AssertionNode $node): string
    {
        return '<span class="' . self::CLASSES['type'] . '">\\' . $node->value . '</span>';
    }

    public function visitCharClass(Node\CharClassNode $node): string
    {
        $parts = $node->expression instanceof Node\AlternationNode
            ? $node->expression->alternatives
            : [$node->expression];
        $inner = '';
        foreach ($parts as $part) {
            $inner .= $part->accept($this);
        }
        $neg = $node->isNegated ? '^' : '';
        return '<span class="' . self::CLASSES['meta'] . '>[' . htmlspecialchars($neg) . '</span>' . $inner . '<span class="' . self::CLASSES['meta'] . '">]</span>';
    }

    public function visitRange(Node\RangeNode $node): string
    {
        $start = $node->start->accept($this);
        $end = $node->end->accept($this);
        return $start . '<span class="' . self::CLASSES['meta'] . '">-</span>' . $end;
    }

    // Implement other visit methods
    public function visitBackref(Node\BackrefNode $node): string
    {
        return '<span class="' . self::CLASSES['type'] . '">\\' . htmlspecialchars($node->ref) . '</span>';
    }

    public function visitUnicode(Node\UnicodeNode $node): string
    {
        return '<span class="' . self::CLASSES['type'] . '">\\x' . $node->code . '</span>';
    }

    public function visitUnicodeNamed(Node\UnicodeNamedNode $node): string
    {
        return '<span class="' . self::CLASSES['type'] . '">\\N{' . htmlspecialchars($node->name) . '}</span>';
    }

    public function visitUnicodeProp(Node\UnicodePropNode $node): string
    {
        $prop = $node->prop;
        if (strlen($prop) > 1 || str_starts_with($prop, '^')) {
            $prop = '{' . $prop . '}';
        }
        return '<span class="' . self::CLASSES['type'] . '">\\p' . htmlspecialchars($prop) . '</span>';
    }

    public function visitOctal(Node\OctalNode $node): string
    {
        return '<span class="' . self::CLASSES['type'] . '">\\o{' . $node->code . '}</span>';
    }

    public function visitOctalLegacy(Node\OctalLegacyNode $node): string
    {
        return '<span class="' . self::CLASSES['type'] . '">\\' . $node->code . '</span>';
    }

    public function visitPosixClass(Node\PosixClassNode $node): string
    {
        return '<span class="' . self::CLASSES['type'] . '">[:' . htmlspecialchars($node->class) . ':]</span>';
    }

    public function visitComment(Node\CommentNode $node): string
    {
        return '<span class="' . self::CLASSES['meta'] . '">(?#...)</span>';
    }

    public function visitConditional(Node\ConditionalNode $node): string
    {
        $condition = $node->condition->accept($this);
        $yes = $node->yes->accept($this);
        $no = $node->no->accept($this);
        $noPart = $no ? '<span class="' . self::CLASSES['meta'] . '">|</span>' . $no : '';
        return '<span class="' . self::CLASSES['meta'] . '">(?(' . '</span>' . $condition . '<span class="' . self::CLASSES['meta'] . '">)</span>' . $yes . $noPart . '<span class="' . self::CLASSES['meta'] . '">)</span>';
    }

    public function visitSubroutine(Node\SubroutineNode $node): string
    {
        return '<span class="' . self::CLASSES['type'] . '">(?' . htmlspecialchars($node->reference) . ')</span>';
    }

    public function visitPcreVerb(Node\PcreVerbNode $node): string
    {
        return '<span class="' . self::CLASSES['meta'] . '">(*' . htmlspecialchars($node->verb) . ')</span>';
    }

    public function visitDefine(Node\DefineNode $node): string
    {
        $inner = $node->content->accept($this);
        return '<span class="' . self::CLASSES['meta'] . '">(?(DEFINE)</span>' . $inner . '<span class="' . self::CLASSES['meta'] . '>)</span>';
    }

    public function visitLimitMatch(Node\LimitMatchNode $node): string
    {
        return '<span class="' . self::CLASSES['meta'] . '">(*LIMIT_MATCH=' . $node->limit . ')</span>';
    }

    public function visitCallout(Node\CalloutNode $node): string
    {
        $content = $node->isStringIdentifier ? '"' . htmlspecialchars((string)$node->identifier) . '"' : (string)$node->identifier;
        return '<span class="' . self::CLASSES['meta'] . '">(?C' . $content . ')</span>';
    }

    public function visitScriptRun(Node\ScriptRunNode $node): string
    {
        return '<span class="' . self::CLASSES['meta'] . '">(*script_run:' . htmlspecialchars($node->script) . ')</span>';
    }

    public function visitVersionCondition(Node\VersionConditionNode $node): string
    {
        return '<span class="' . self::CLASSES['meta'] . '">(?(VERSION&gt;=' . $node->version . ')</span>';
    }

    public function visitKeep(Node\KeepNode $node): string
    {
        return '<span class="' . self::CLASSES['type'] . '">\\K</span>';
    }

    public function visitControlChar(Node\ControlCharNode $node): string
    {
        return '<span class="' . self::CLASSES['type'] . '">\\c' . $node->char . '</span>';
    }

    public function visitClassOperation(Node\ClassOperationNode $node): string
    {
        $left = $node->left->accept($this);
        $right = $node->right->accept($this);
        $op = $node->type === Node\ClassOperationType::INTERSECTION ? '&&' : '--';
        return '<span class="' . self::CLASSES['meta'] . '">[' . '</span>' . $left . '<span class="' . self::CLASSES['meta'] . '">' . htmlspecialchars($op) . '</span>' . $right . '<span class="' . self::CLASSES['meta'] . '">]</span>';
    }
}