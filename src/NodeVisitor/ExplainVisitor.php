<?php

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
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;

/**
 * A visitor that explains the AST in a human-readable string.
 *
 * @implements NodeVisitorInterface<string>
 */
class ExplainVisitor implements NodeVisitorInterface
{
    /** @var array<string, string> */
    private const CHAR_TYPE_MAP = [
        'd' => 'any digit (0-9)',
        'D' => 'any non-digit',
        's' => 'any whitespace character',
        'S' => 'any non-whitespace character',
        'w' => 'any "word" character (alphanumeric or _)',
        'W' => 'any "non-word" character',
        'h' => 'any horizontal whitespace',
        'H' => 'any non-horizontal whitespace',
        'v' => 'any vertical whitespace',
        'V' => 'any non-vertical whitespace',
        'R' => 'a generic newline (\\r\\n, \\r, or \\n)',
    ];

    /** @var array<string, string> */
    private const ANCHOR_MAP = [
        '^' => 'the start of the string (or line, with /m flag)',
        '$' => 'the end of the string (or line, with /m flag)',
    ];

    /** @var array<string, string> */
    private const ASSERTION_MAP = [
        'A' => 'the absolute start of the string',
        'z' => 'the absolute end of the string',
        'Z' => 'the end of the string (before final newline)',
        'G' => 'the position of the last successful match',
        'b' => 'a word boundary',
        'B' => 'a non-word boundary',
    ];

    private int $indentLevel = 0;

    public function visitRegex(RegexNode $node): string
    {
        $this->indentLevel = 0;
        $patternExplain = $node->pattern->accept($this);
        $flags = $node->flags ? ' (with flags: '.$node->flags.')' : '';

        return \sprintf("Regex matches%s:\n%s", $flags, $patternExplain);
    }

    public function visitAlternation(AlternationNode $node): string
    {
        ++$this->indentLevel;
        $alts = array_map(
            fn (SequenceNode $alt) => $alt->accept($this),
            $node->alternatives
        );
        --$this->indentLevel;

        $indent = $this->indent();

        return \sprintf(
            "EITHER:\n%s%s",
            $indent,
            implode(\sprintf("\n%sOR:\n%s", $indent, $indent), $alts)
        );
    }

    public function visitSequence(SequenceNode $node): string
    {
        $parts = array_map(fn ($child) => $child->accept($this), $node->children);

        // Filter out empty strings (e.g., from empty nodes)
        $parts = array_filter($parts, fn ($part) => '' !== $part);

        return implode(\sprintf("\n%s", $this->indent()), $parts);
    }

    public function visitGroup(GroupNode $node): string
    {
        ++$this->indentLevel;
        $childExplain = $node->child->accept($this);
        --$this->indentLevel;

        $indent = $this->indent();
        $type = match ($node->type) {
            GroupType::T_GROUP_CAPTURING => 'Start Capturing Group',
            GroupType::T_GROUP_NON_CAPTURING => 'Start Non-Capturing Group',
            GroupType::T_GROUP_NAMED => \sprintf("Start Capturing Group (named: '%s')", $node->name),
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE => 'Start Positive Lookahead',
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE => 'Start Negative Lookahead',
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE => 'Start Positive Lookbehind',
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE => 'Start Negative Lookbehind',
            GroupType::T_GROUP_ATOMIC => 'Start Atomic Group',
            GroupType::T_GROUP_INLINE_FLAGS => \sprintf("Start Group (with flags: '%s')", $node->flags),
        };

        return \sprintf("%s:\n%s%s\n%sEnd Group", $type, $indent, $childExplain, $this->indent(false));
    }

    public function visitQuantifier(QuantifierNode $node): string
    {
        $childExplain = $node->node->accept($this);
        $quantExplain = $this->explainQuantifierValue($node->quantifier, $node->type->value);

        // If the child is simple (one line), put it on one line.
        if (!str_contains($childExplain, "\n")) {
            return \sprintf('%s (%s)', $childExplain, $quantExplain);
        }

        // If the child is complex, indent it.
        ++$this->indentLevel;
        $childExplain = $node->node->accept($this);
        --$this->indentLevel;

        return \sprintf(
            "Start Quantified Group (%s):\n%s%s\n%sEnd Quantified Group",
            $quantExplain,
            $this->indent(),
            $childExplain,
            $this->indent(false)
        );
    }

    private function explainQuantifierValue(string $q, string $type): string
    {
        $desc = match ($q) {
            '*' => 'zero or more times',
            '+' => 'one or more times',
            '?' => 'zero or one time',
            default => preg_match('/^{(\d+)(?:,(\d*))?}$/', $q, $m) ?
                (isset($m[2]) ? ('' === $m[2] ?
                    \sprintf('at least %d times', $m[1]) :
                    \sprintf('between %d and %d times', $m[1], $m[2])
                ) :
                    \sprintf('exactly %d times', $m[1])
                ) :
                'with quantifier '.$q, // Fallback
        };

        $desc .= match ($type) {
            'lazy' => ' (as few as possible)',
            'possessive' => ' (and do not backtrack)',
            default => '',
        };

        return $desc;
    }

    public function visitLiteral(LiteralNode $node): string
    {
        return 'Literal: '.($this->explainLiteral($node->value) ?? "''");
    }

    public function visitCharType(CharTypeNode $node): string
    {
        return 'Character Type: '.(self::CHAR_TYPE_MAP[$node->value] ?? 'unknown (\\'.$node->value.')');
    }

    public function visitDot(DotNode $node): string
    {
        return 'Wildcard: any character (except newline, unless /s flag is used)';
    }

    public function visitAnchor(AnchorNode $node): string
    {
        return 'Anchor: '.(self::ANCHOR_MAP[$node->value] ?? $node->value);
    }

    public function visitAssertion(AssertionNode $node): string
    {
        return 'Assertion: '.(self::ASSERTION_MAP[$node->value] ?? '\\'.$node->value);
    }

    public function visitKeep(KeepNode $node): string
    {
        return 'Assertion: \K (reset match start)';
    }

    public function visitCharClass(CharClassNode $node): string
    {
        $neg = $node->isNegated ? 'NOT ' : '';
        $parts = array_map(fn ($part) => $part->accept($this), $node->parts);

        return \sprintf('Character Class: any character %sin [ %s ]', $neg, implode(', ', $parts));
    }

    public function visitRange(RangeNode $node): string
    {
        $start = $this->explainLiteral($node->start->value);
        $end = $this->explainLiteral($node->end->value);

        return \sprintf('Range: from %s to %s', $start, $end);
    }

    public function visitBackref(BackrefNode $node): string
    {
        return \sprintf('Backreference: matches text from group "%s"', $node->ref);
    }

    public function visitUnicode(UnicodeNode $node): string
    {
        return \sprintf('Unicode: %s', $node->code);
    }

    public function visitUnicodeProp(UnicodePropNode $node): string
    {
        $type = str_starts_with($node->prop, '^') ? 'non-matching' : 'matching';
        $prop = ltrim($node->prop, '^');

        return \sprintf('Unicode Property: any character %s "%s"', $type, $prop);
    }

    public function visitOctal(OctalNode $node): string
    {
        return 'Octal: '.$node->code;
    }

    public function visitOctalLegacy(OctalLegacyNode $node): string
    {
        return 'Legacy Octal: \\'.$node->code;
    }

    public function visitPosixClass(PosixClassNode $node): string
    {
        return 'POSIX Class: '.$node->class;
    }

    public function visitComment(CommentNode $node): string
    {
        return \sprintf("Comment: '%s'", $node->comment);
    }

    public function visitConditional(ConditionalNode $node): string
    {
        ++$this->indentLevel;
        $cond = $node->condition->accept($this);
        $yes = $node->yes->accept($this);
        $no = $node->no->accept($this);
        --$this->indentLevel;

        $indent = $this->indent();

        if ('' === $no) {
            return \sprintf("Conditional: IF (%s) THEN:\n%s%s", $cond, $indent, $yes);
        }

        return \sprintf("Conditional: IF (%s) THEN:\n%s%s\n%sELSE:\n%s%s", $cond, $indent, $yes, $this->indent(false), $indent, $no);
    }

    public function visitSubroutine(SubroutineNode $node): string
    {
        $ref = match ($node->reference) {
            'R' => 'the entire pattern',
            '0' => 'the entire pattern',
            default => 'group '.$node->reference,
        };

        return \sprintf('Subroutine Call: recurses to %s', $ref);
    }

    public function visitPcreVerb(PcreVerbNode $node): string
    {
        return 'PCRE Verb: (*'.$node->verb.')';
    }

    private function indent(bool $withExtra = true): string
    {
        return str_repeat(' ', $this->indentLevel * 2).($withExtra ? '  ' : '');
    }

    private function explainLiteral(string $value): string
    {
        return match ($value) {
            ' ' => "' ' (space)",
            "\t" => "'\\t' (tab)",
            "\n" => "'\\n' (newline)",
            "\r" => "'\\r' (carriage return)",
            default => ctype_print($value) ? "'".$value."'" : '(non-printable char)',
        };
    }
}
