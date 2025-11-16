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
use RegexParser\Ast\AssertionNode;
use RegexParser\Ast\BackrefNode;
use RegexParser\Ast\CharClassNode;
use RegexParser\Ast\CharTypeNode;
use RegexParser\Ast\CommentNode;
use RegexParser\Ast\ConditionalNode;
use RegexParser\Ast\DotNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\GroupType;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\OctalNode;
use RegexParser\Ast\PosixClassNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\QuantifierType;
use RegexParser\Ast\RangeNode;
use RegexParser\Ast\RegexNode;
use RegexParser\Ast\SequenceNode;
use RegexParser\Ast\UnicodeNode;
use RegexParser\Ast\UnicodePropNode;

/**
 * A visitor that recompiles the AST back into a regex string.
 *
 * @implements VisitorInterface<string>
 */
class CompilerVisitor implements VisitorInterface
{
    // PCRE meta-characters that must be escaped *outside* a character class.
    private const META_CHARACTERS
        = [
            '\\' => true,
            '.'  => true,
            '^'  => true,
            '$'  => true,
            '['  => true,
            ']'  => true,
            '('  => true,
            ')'  => true,
            '|'  => true,
            '*'  => true,
            '+'  => true,
            '?'  => true,
            '{'  => true,
        ];
    // Meta-characters that must be escaped *inside* a character class.
    // The parser correctly identifies positional meta-chars (like ^, -, ])
    // as literals, so we only need to worry about \ and ].
    private const CHAR_CLASS_META
        = [
            '\\' => true,
            ']'  => true,
        ];
    /**
     * Tracks if we are currently compiling inside a character class.
     */
    private bool $inCharClass = false;

    public function visitRegex(RegexNode $node): string
    {
        // Re-add the dynamic delimiter and flags
        $map = [')' => '(', ']' => '[', '}' => '{', '>' => '<'];
        $closingDelimiter = $map[$node->delimiter] ?? $node->delimiter;

        return $node->delimiter.$node->pattern->accept($this).$closingDelimiter.$node->flags;
    }

    public function visitAlternation(AlternationNode $node): string
    {
        return implode('|', array_map(fn($alt) => $alt->accept($this), $node->alternatives));
    }

    public function visitSequence(SequenceNode $node): string
    {
        // Concatenates the results of the sequence's children
        return implode('', array_map(fn($child) => $child->accept($this), $node->children));
    }

    public function visitGroup(GroupNode $node): string
    {
        $child = $node->child->accept($this);
        $flags = $node->flags ?? '';

        return match ($node->type) {
            GroupType::T_GROUP_CAPTURING => '('.$child.')',
            GroupType::T_GROUP_NON_CAPTURING => '(?:'.$child.')',
            GroupType::T_GROUP_NAMED => '(?<'.$node->name.'>'.$child.')',
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE => '(?='.$child.')',
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE => '(?!'.$child.')',
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE => '(?<='.$child.')',
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE => '(?<!'.$child.')',
            GroupType::T_GROUP_INLINE_FLAGS => '(?'.$flags.':'.$child.')',
        };
    }

    public function visitQuantifier(QuantifierNode $node): string
    {
        /** @var string $nodeCompiled */
        $nodeCompiled = $node->node->accept($this);

        // Add non-capturing group if needed (e.g., "abc*" vs "(?:abc)*")
        if ($node->node instanceof SequenceNode || $node->node instanceof AlternationNode) {
            $nodeCompiled = '(?:'.$nodeCompiled.')';
        }

        $suffix = match ($node->type) {
            QuantifierType::T_LAZY => '?',
            QuantifierType::T_POSSESSIVE => '+',
            default => '',
        };

        return $nodeCompiled.$node->quantifier.$suffix;
    }

    public function visitLiteral(LiteralNode $node): string
    {
        // Use different escaping rules depending on context
        $meta = $this->inCharClass ? self::CHAR_CLASS_META : self::META_CHARACTERS;

        // Special case: ']' is not meta if it's not in a char class
        if (!$this->inCharClass && ']' === $node->value) {
            return $node->value;
        }

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

    public function visitAssertion(AssertionNode $node): string
    {
        return '\\'.$node->value;
    }

    public function visitCharClass(CharClassNode $node): string
    {
        $this->inCharClass = true; // Set context for visitLiteral

        $parts = implode('', array_map(fn($part) => $part->accept($this), $node->parts));
        $result = '['.($node->isNegated ? '^' : '').$parts.']';

        $this->inCharClass = false; // Unset context

        return $result;
    }

    public function visitRange(RangeNode $node): string
    {
        // Note: visitLiteral will handle escaping for start/end if they are meta-chars
        return $node->start->accept($this).'-'.$node->end->accept($this);
    }

    public function visitBackref(BackrefNode $node): string
    {
        return $node->ref; // Already \1 or \k<name>
    }

    public function visitUnicode(UnicodeNode $node): string
    {
        return $node->code; // Already \xHH or \u{...}
    }

    public function visitUnicodeProp(UnicodePropNode $node): string
    {
        $neg = strpos($node->prop, '^') === 0 ? 'P' : 'p';
        $prop = ltrim($node->prop, '^');
        if (strlen($prop) > 1) {
            return '\\'.$neg.'{'.$prop.'}';
        }

        return '\\'.$neg.$prop;
    }

    public function visitOctal(OctalNode $node): string
    {
        return $node->code; // Already \o{...}
    }

    public function visitPosixClass(PosixClassNode $node): string
    {
        return '[[:'.$node->class.':]]';
    }

    public function visitComment(CommentNode $node): string
    {
        return '(?#'.$node->comment.')';
    }

    public function visitConditional(ConditionalNode $node): string
    {
        $cond = $node->condition->accept($this);
        $yes = $node->yes->accept($this);
        $no = $node->no->accept($this);
        if ($no === '') {
            return '(?('.$cond.')'.$yes.')';
        }

        return '(?('.$cond.')'.$yes.'|'.$no.')';
    }
}
