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
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\OctalLegacyNode;
use RegexParser\Node\OctalNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;

/**
 * A visitor that recompiles the AST back into a regex string.
 *
 * @implements NodeVisitorInterface<string>
 */
class CompilerNodeVisitor implements NodeVisitorInterface
{
    // PCRE meta-characters that must be escaped *outside* a character class.
    private const array META_CHARACTERS = [
        '\\' => true, '.' => true, '^' => true, '$' => true,
        '[' => true, ']' => true, '(' => true, ')' => true,
        '|' => true, '*' => true, '+' => true, '?' => true, '{' => true, '}' => true,
        '/' => true,
    ];

    // Meta-characters that must be escaped *inside* a character class.
    // '-' is crucial to escape to prevent creating unintended ranges.
    // '^' is crucial to escape to prevent unintended negation if placed at start.
    private const array CHAR_CLASS_META = [
        '\\' => true, ']' => true, '-' => true, '^' => true,
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
        return implode('|', array_map(fn ($alt) => $alt->accept($this), $node->alternatives));
    }

    public function visitSequence(SequenceNode $node): string
    {
        // Concatenates the results of the sequence's children
        return implode('', array_map(fn ($child) => $child->accept($this), $node->children));
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
            GroupType::T_GROUP_ATOMIC => '(?>'.$child.')',
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

    public function visitKeep(KeepNode $node): string
    {
        return '\K';
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
        // Note: visitLiteral will handle escaping for start/end if they are meta-chars
        return $node->start->accept($this).'-'.$node->end->accept($this);
    }

    public function visitBackref(BackrefNode $node): string
    {
        return $node->ref; // Already \1 or \k<name> or \g{1}
    }

    public function visitUnicode(UnicodeNode $node): string
    {
        return $node->code; // Already \xHH or \u{...}
    }

    public function visitUnicodeProp(UnicodePropNode $node): string
    {
        if (str_starts_with($node->prop, '^')) {
            return '\p{'.$node->prop.'}';
        }

        if (\strlen($node->prop) > 1) {
            return '\p{'.$node->prop.'}';
        }

        return '\p'.$node->prop;
    }

    public function visitOctal(OctalNode $node): string
    {
        return $node->code; // Already \o{...}
    }

    public function visitOctalLegacy(OctalLegacyNode $node): string
    {
        return '\\'.$node->code;
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
        if ('' === $no) {
            return '(?('.$cond.')'.$yes.')';
        }

        return '(?('.$cond.')'.$yes.'|'.$no.')';
    }

    public function visitSubroutine(SubroutineNode $node): string
    {
        return match ($node->syntax) {
            '&' => '(?&'.$node->reference.')',
            'P>' => '(?P>'.$node->reference.')',
            'g' => '\g<'.$node->reference.'>', // Re-compile as \g<name>
            default => '(?'.$node->reference.')', // Handles (?R), (?1), (?-1)
        };
    }

    public function visitPcreVerb(PcreVerbNode $node): string
    {
        return '(*'.$node->verb.')';
    }
}
