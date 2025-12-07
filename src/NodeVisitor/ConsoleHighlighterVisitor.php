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
 */
final class ConsoleHighlighterVisitor extends HighlighterVisitor
{
    private const string RESET = "\033[0m";

    private const array COLORS = [
        'meta' => "\033[1;34m",      // Bold Blue
        'quantifier' => "\033[1;33m", // Bold Yellow
        'type' => "\033[0;32m",      // Green
        'anchor' => "\033[0;35m",    // Magenta
        'literal' => '',             // Default
    ];

    #[\Override]
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
        $opening = self::COLORS['meta'].'('.$prefix.self::RESET;
        $closing = self::COLORS['meta'].')'.self::RESET;

        return $opening.$inner.$closing;
    }

    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): string
    {
        $condition = $node->condition->accept($this);
        $yes = $node->yes->accept($this);
        $no = $node->no->accept($this);
        $noPart = $no ? self::COLORS['meta'].'|'.self::RESET.$no : '';

        return self::COLORS['meta'].'(?('.self::RESET.$condition.self::COLORS['meta'].')'.self::RESET.$yes.$noPart.self::COLORS['meta'].')'.self::RESET;
    }

    protected function wrap(string $content, string $type): string
    {
        return self::COLORS[$type].$content.self::RESET;
    }

    protected function escape(string $string): string
    {
        return $string;
    }
}
