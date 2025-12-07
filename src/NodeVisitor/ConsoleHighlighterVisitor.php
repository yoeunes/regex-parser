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
 * Highlights regex syntax for console output using ANSI escape codes.
 *
 * @extends AbstractNodeVisitor<string>
 */
final class ConsoleHighlighterVisitor extends AbstractNodeVisitor
{
    private const RESET = "\033[0m";

    private const COLORS = [
        'meta' => "\033[1;34m",      // Bold Blue
        'quantifier' => "\033[1;33m", // Bold Yellow
        'type' => "\033[0;32m",      // Green
        'anchor' => "\033[0;35m",    // Magenta
        'literal' => '',             // Default
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
        return implode(self::COLORS['meta'] . '|' . self::RESET, $parts);
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
            Node\GroupType::T_GROUP_NAMED => "?<{$node->name}>",
            default => '',
        };
        $opening = self::COLORS['meta'] . '(' . $prefix . self::RESET;
        $closing = self::COLORS['meta'] . ')' . self::RESET;
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
        return $inner . self::COLORS['quantifier'] . $quant . self::RESET;
    }

    public function visitLiteral(Node\LiteralNode $node): string
    {
        return self::COLORS['literal'] . $node->value . self::RESET;
    }

    public function visitCharType(Node\CharTypeNode $node): string
    {
        return self::COLORS['type'] . '\\' . $node->value . self::RESET;
    }

    public function visitDot(Node\DotNode $node): string
    {
        return self::COLORS['meta'] . '.' . self::RESET;
    }

    public function visitAnchor(Node\AnchorNode $node): string
    {
        return self::COLORS['anchor'] . $node->value . self::RESET;
    }

    public function visitAssertion(Node\AssertionNode $node): string
    {
        return self::COLORS['type'] . '\\' . $node->value . self::RESET;
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
        return self::COLORS['meta'] . '[' . $neg . self::RESET . $inner . self::COLORS['meta'] . ']' . self::RESET;
    }

    public function visitRange(Node\RangeNode $node): string
    {
        $start = $node->start->accept($this);
        $end = $node->end->accept($this);
        return $start . self::COLORS['meta'] . '-' . self::RESET . $end;
    }

    // Implement other visit methods similarly, defaulting to literal or meta
    public function visitBackref(Node\BackrefNode $node): string
    {
        return self::COLORS['type'] . '\\' . $node->ref . self::RESET;
    }

    public function visitUnicode(Node\UnicodeNode $node): string
    {
        return self::COLORS['type'] . '\\x' . $node->code . self::RESET;
    }

    public function visitUnicodeNamed(Node\UnicodeNamedNode $node): string
    {
        return self::COLORS['type'] . '\\N{' . $node->name . '}' . self::RESET;
    }

    public function visitUnicodeProp(Node\UnicodePropNode $node): string
    {
        $prop = $node->prop;
        if (strlen($prop) > 1 || str_starts_with($prop, '^')) {
            $prop = '{' . $prop . '}';
        }
        return self::COLORS['type'] . '\\p' . $prop . self::RESET;
    }

    public function visitOctal(Node\OctalNode $node): string
    {
        return self::COLORS['type'] . '\\o{' . $node->code . '}' . self::RESET;
    }

    public function visitOctalLegacy(Node\OctalLegacyNode $node): string
    {
        return self::COLORS['type'] . '\\' . $node->code . self::RESET;
    }

    public function visitPosixClass(Node\PosixClassNode $node): string
    {
        return self::COLORS['type'] . '[:' . $node->class . ':]' . self::RESET;
    }

    public function visitComment(Node\CommentNode $node): string
    {
        return self::COLORS['meta'] . '(?#...' . ')' . self::RESET;
    }

    public function visitConditional(Node\ConditionalNode $node): string
    {
        $condition = $node->condition->accept($this);
        $yes = $node->yes->accept($this);
        $no = $node->no->accept($this);
        $noPart = $no ? self::COLORS['meta'] . '|' . self::RESET . $no : '';
        return self::COLORS['meta'] . '(?(' . self::RESET . $condition . self::COLORS['meta'] . ')' . self::RESET . $yes . $noPart . self::COLORS['meta'] . ')' . self::RESET;
    }

    public function visitSubroutine(Node\SubroutineNode $node): string
    {
        return self::COLORS['type'] . '(?' . $node->reference . ')' . self::RESET;
    }

    public function visitPcreVerb(Node\PcreVerbNode $node): string
    {
        return self::COLORS['meta'] . '(*' . $node->verb . ')' . self::RESET;
    }

    public function visitDefine(Node\DefineNode $node): string
    {
        $inner = $node->content->accept($this);
        return self::COLORS['meta'] . '(?(DEFINE)' . self::RESET . $inner . self::COLORS['meta'] . ')' . self::RESET;
    }

    public function visitLimitMatch(Node\LimitMatchNode $node): string
    {
        return self::COLORS['meta'] . '(*LIMIT_MATCH=' . $node->limit . ')' . self::RESET;
    }

    public function visitCallout(Node\CalloutNode $node): string
    {
        $content = $node->isStringIdentifier ? '"' . (string)$node->identifier . '"' : (string)$node->identifier;
        return self::COLORS['meta'] . '(?C' . $content . ')' . self::RESET;
    }

    public function visitScriptRun(Node\ScriptRunNode $node): string
    {
        return self::COLORS['meta'] . '(*script_run:' . $node->script . ')' . self::RESET;
    }

    public function visitVersionCondition(Node\VersionConditionNode $node): string
    {
        return self::COLORS['meta'] . '(?(VERSION>=' . $node->version . ')' . self::RESET;
    }

    public function visitKeep(Node\KeepNode $node): string
    {
        return self::COLORS['type'] . '\\K' . self::RESET;
    }

    public function visitControlChar(Node\ControlCharNode $node): string
    {
        return self::COLORS['type'] . '\\c' . $node->char . self::RESET;
    }

    public function visitClassOperation(Node\ClassOperationNode $node): string
    {
        $left = $node->left->accept($this);
        $right = $node->right->accept($this);
        $op = $node->type === Node\ClassOperationType::INTERSECTION ? '&&' : '--';
        return self::COLORS['meta'] . '[' . self::RESET . $left . self::COLORS['meta'] . $op . self::RESET . $right . self::COLORS['meta'] . ']' . self::RESET;
    }
}