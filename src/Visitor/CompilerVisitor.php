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
use RegexParser\Ast\CharClassNode;
use RegexParser\Ast\CharTypeNode;
use RegexParser\Ast\DotNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\RangeNode;
use RegexParser\Ast\RegexNode;
use RegexParser\Ast\SequenceNode;

/**
 * A visitor that recompiles the AST back into a regex string.
 *
 * @implements VisitorInterface<string>
 */
class CompilerVisitor implements VisitorInterface
{
    // PCRE meta-characters that must be escaped *outside* a character class.
    private const META_CHARACTERS = [
        '\\' => true, '.' => true, '^' => true, '$' => true,
        '[' => true, ']' => true, '(' => true, ')' => true,
        '|' => true, '*' => true, '+' => true, '?' => true, '{' => true,
    ];

    // Meta-characters that must be escaped *inside* a character class.
    private const CHAR_CLASS_META = [
        '\\' => true, ']' => true, '-' => true, '^' => true,
    ];

    /**
     * Tracks if we are currently compiling inside a character class.
     */
    private bool $inCharClass = false;

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
        // Use different escaping rules depending on context
        $meta = $this->inCharClass ? self::CHAR_CLASS_META : self::META_CHARACTERS;

        if (isset($meta[$node->value])) {
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

    public function visitCharClass(CharClassNode $node): string
    {
        $this->inCharClass = true; // Set context for visitLiteral

        $parts = implode('', array_map(fn ($part) => $part->accept($this), $node->parts));
        $result = '['.($node->isNegated ? '^' : '').$parts.']';

        $this->inCharClass = false; // Unset context

        return $result;
    }

    public function visitRange(RangeNode $node): string
    {
        // Note: visitLiteral will handle escaping for start/end if they are meta-chars (e.g., "[-]")
        return $node->start->accept($this).'-'.$node->end->accept($this);
    }
}
