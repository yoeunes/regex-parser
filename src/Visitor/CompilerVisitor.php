<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\AnchorNode;
use RegexParser\Ast\CharTypeNode;
use RegexParser\Ast\DotNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\RegexNode;
use RegexParser\Ast\SequenceNode;

/**
 * A visitor that recompiles the AST back into a regex string.
 *
 * @implements VisitorInterface<string>
 */
class CompilerVisitor implements VisitorInterface
{
    // PCRE meta-characters that must be escaped outside a character class.
    private const META_CHARACTERS = [
        '\\' => true, '.' => true, '^' => true, '$' => true,
        '[' => true, ']' => true, '(' => true, ')' => true,
        '|' => true, '*' => true, '+' => true, '?' => true, '{' => true,
    ];

    public function visitRegex(RegexNode $node): string
    {
        // Assumes '/' as the delimiter.
        return '/'.$node->pattern->accept($this).'/'.$node->flags;
    }

    public function visitAlternation(AlternationNode $node): string
    {
        return implode('|', array_map(fn ($alt) => $alt->accept($this), $node->alternatives));
    }

    public function visitSequence(SequenceNode $node): string
    {
        // Concatenates the results of the sequence's children
        return implode('', array_map(fn ($child) => $child->accept($this), $node->children));
    }

    public function visitGroup(GroupNode $node): string
    {
        // The child of the group is visited
        return '('.$node->child->accept($this).')';
    }

    public function visitQuantifier(QuantifierNode $node): string
    {
        /** @var string $nodeCompiled */
        $nodeCompiled = $node->node->accept($this);

        // Add non-capturing group if needed (e.g., "abc*" vs "(abc)*")
        if ($node->node instanceof SequenceNode || $node->node instanceof AlternationNode) {
            $nodeCompiled = '(?:'.$nodeCompiled.')';
        }

        return $nodeCompiled.$node->quantifier;
    }

    public function visitLiteral(LiteralNode $node): string
    {
        // Re-escape special meta-characters
        if (isset(self::META_CHARACTERS[$node->value])) {
            return '\\'.$node->value;
        }

        return $node->value;
    }

    public function visitCharType(CharTypeNode $node): string
    {
        // Re-add the backslash
        return '\\'.$node->value;
    }

    public function visitDot(DotNode $node): string
    {
        return '.';
    }

    public function visitAnchor(AnchorNode $node): string
    {
        return $node->value;
    }
}
