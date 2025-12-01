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
use RegexParser\Node\NodeInterface;
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
 * Generates a human-readable, step-by-step explanation of what a regex does.
 *
 * Purpose: This visitor traverses the AST and translates each node into a natural
 * language description. It's the engine behind the `Regex::explain()` method.
 * For contributors, this class demonstrates how to consume the AST to produce
 * meaningful, user-facing output. Each `visit` method is responsible for
 * generating the English explanation for a specific regex component.
 *
 * @implements NodeVisitorInterface<string>
 */
class ExplainNodeVisitor implements NodeVisitorInterface
{
    private const array CHAR_TYPE_MAP = [
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

    private const array ANCHOR_MAP = [
        '^' => 'the start of the string (or line, with /m flag)',
        '$' => 'the end of the string (or line, with /m flag)',
    ];

    private const array ASSERTION_MAP = [
        'A' => 'the absolute start of the string',
        'z' => 'the absolute end of the string',
        'Z' => 'the end of the string (before final newline)',
        'G' => 'the position of the last successful match',
        'b' => 'a word boundary',
        'B' => 'a non-word boundary',
    ];

    private int $indentLevel = 0;

    /**
     * Explains the root `RegexNode`.
     *
     * Purpose: This is the entry point for the explanation. It sets up the initial
     * context, mentioning the regex flags, and then recursively calls the visitor
     * on the main pattern.
     *
     * @param RegexNode $node the root node of the AST
     *
     * @return string the complete, human-readable explanation of the regex
     */
    public function visitRegex(RegexNode $node): string
    {
        $this->indentLevel = 0;
        $patternExplain = $node->pattern->accept($this);
        $flags = $node->flags ? ' (with flags: '.$node->flags.')' : '';

        return \sprintf("Regex matches%s:\n%s", $flags, $patternExplain);
    }

    /**
     * Explains an `AlternationNode`.
     *
     * Purpose: This method describes an alternation (`|`), making it clear that the
     * regex engine will try to match one of several possibilities. It formats the
     * output with "EITHER...OR..." to be intuitive.
     *
     * @param AlternationNode $node the alternation node to explain
     *
     * @return string a description of the alternative branches
     */
    public function visitAlternation(AlternationNode $node): string
    {
        $this->indentLevel++;
        $alts = array_map(
            fn (NodeInterface $alt) => $alt->accept($this),
            $node->alternatives,
        );
        $this->indentLevel--;

        $indent = $this->indent();

        return \sprintf(
            "EITHER:\n%s%s",
            $indent,
            implode(\sprintf("\n%sOR:\n%s", $indent, $indent), $alts),
        );
    }

    /**
     * Explains a `SequenceNode`.
     *
     * Purpose: This method describes a sequence of regex components that must be
     * matched in order. It recursively explains each child and joins the
     * descriptions with newlines to represent the sequence.
     *
     * @param SequenceNode $node the sequence node to explain
     *
     * @return string a description of the sequential components
     */
    public function visitSequence(SequenceNode $node): string
    {
        $parts = array_map(fn ($child) => $child->accept($this), $node->children);

        // Filter out empty strings (e.g., from empty nodes)
        $parts = array_filter($parts, fn ($part) => '' !== $part);

        return implode(\sprintf("\n%s", $this->indent()), $parts);
    }

    /**
     * Explains a `GroupNode`.
     *
     * Purpose: This method provides a description for any type of group, clearly
     * stating its function (e.g., capturing, non-capturing, lookahead) and any
     * associated metadata like a name or inline flags.
     *
     * @param GroupNode $node the group node to explain
     *
     * @return string a description of the group and its contents
     */
    public function visitGroup(GroupNode $node): string
    {
        $this->indentLevel++;
        $childExplain = $node->child->accept($this);
        $this->indentLevel--;

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
            GroupType::T_GROUP_BRANCH_RESET => 'Start Branch Reset Group',
            GroupType::T_GROUP_INLINE_FLAGS => \sprintf("Start Group (with flags: '%s')", $node->flags),
        };

        return \sprintf("%s:\n%s%s\n%sEnd Group", $type, $indent, $childExplain, $this->indent(false));
    }

    /**
     * Explains a `QuantifierNode`.
     *
     * Purpose: This method describes how many times a preceding element can be
     * matched, translating tokens like `*`, `+`, and `{n,m}` into clear English
     * phrases like "zero or more times" or "between 2 and 5 times".
     *
     * @param QuantifierNode $node the quantifier node to explain
     *
     * @return string a description of the quantified element
     */
    public function visitQuantifier(QuantifierNode $node): string
    {
        $childExplain = $node->node->accept($this);
        $quantExplain = $this->explainQuantifierValue($node->quantifier, $node->type->value);

        // If the child is simple (one line), put it on one line.
        if (!str_contains($childExplain, "\n")) {
            return \sprintf('%s (%s)', $childExplain, $quantExplain);
        }

        // If the child is complex, indent it.
        $this->indentLevel++;
        $childExplain = $node->node->accept($this);
        $this->indentLevel--;

        return \sprintf(
            "Start Quantified Group (%s):\n%s%s\n%sEnd Quantified Group",
            $quantExplain,
            $this->indent(),
            $childExplain,
            $this->indent(false),
        );
    }

    /**
     * Explains a `LiteralNode`.
     *
     * Purpose: This method describes a literal character or string, handling
     * special whitespace characters to make them readable.
     *
     * @param LiteralNode $node the literal node to explain
     *
     * @return string a description of the literal value
     */
    public function visitLiteral(LiteralNode $node): string
    {
        return 'Literal: '.$this->explainLiteral($node->value);
    }

    /**
     * Explains a `CharTypeNode`.
     *
     * Purpose: This method translates a character type escape sequence (e.g., `\d`, `\s`)
     * into its well-known meaning (e.g., "any digit", "any whitespace character").
     *
     * @param CharTypeNode $node the character type node to explain
     *
     * @return string a description of the character type
     */
    public function visitCharType(CharTypeNode $node): string
    {
        return 'Character Type: '.(self::CHAR_TYPE_MAP[$node->value] ?? 'unknown (\\'.$node->value.')');
    }

    /**
     * Explains a `DotNode`.
     *
     * Purpose: This method describes the wildcard (`.`) character, noting its
     * behavior with respect to newlines and the `/s` flag.
     *
     * @param DotNode $node the dot node to explain
     *
     * @return string a description of the wildcard
     */
    public function visitDot(DotNode $node): string
    {
        return 'Wildcard: any character (except newline, unless /s flag is used)';
    }

    /**
     * Explains an `AnchorNode`.
     *
     * Purpose: This method describes an anchor like `^` or `$`, explaining that it
     * asserts a position (start or end of string/line).
     *
     * @param AnchorNode $node the anchor node to explain
     *
     * @return string a description of the anchor
     */
    public function visitAnchor(AnchorNode $node): string
    {
        return 'Anchor: '.(self::ANCHOR_MAP[$node->value] ?? $node->value);
    }

    /**
     * Explains an `AssertionNode`.
     *
     * Purpose: This method describes a zero-width assertion like `\b` (word boundary)
     * or `\A` (start of string).
     *
     * @param AssertionNode $node the assertion node to explain
     *
     * @return string a description of the assertion
     */
    public function visitAssertion(AssertionNode $node): string
    {
        return 'Assertion: '.(self::ASSERTION_MAP[$node->value] ?? '\\'.$node->value);
    }

    /**
     * Explains a `KeepNode`.
     *
     * Purpose: This method describes the `\K` sequence, explaining its function of
     * resetting the start of the overall match.
     *
     * @param KeepNode $node the keep node to explain
     *
     * @return string a description of the `\K` assertion
     */
    public function visitKeep(KeepNode $node): string
    {
        return 'Assertion: \K (reset match start)';
    }

    /**
     * Explains a `CharClassNode`.
     *
     * Purpose: This method describes a character class `[...]`, indicating whether it's
     * negated and listing the characters or ranges it contains.
     *
     * @param CharClassNode $node the character class node to explain
     *
     * @return string a description of the character class
     */
    public function visitCharClass(CharClassNode $node): string
    {
        $neg = $node->isNegated ? 'NOT ' : '';
        $parts = array_map(fn (NodeInterface $part) => $part->accept($this), $node->parts);

        return \sprintf('Character Class: any character %sin [ %s ]', $neg, implode(', ', $parts));
    }

    /**
     * Explains a `RangeNode`.
     *
     * Purpose: This method describes a range within a character class (e.g., `a-z`),
     * making it clear what the start and end of the range are.
     *
     * @param RangeNode $node the range node to explain
     *
     * @return string a description of the character range
     */
    public function visitRange(RangeNode $node): string
    {
        $start = ($node->start instanceof LiteralNode)
            ? $this->explainLiteral($node->start->value)
            : $node->start->accept($this); // Fallback

        $end = ($node->end instanceof LiteralNode)
            ? $this->explainLiteral($node->end->value)
            : $node->end->accept($this); // Fallback

        return \sprintf('Range: from %s to %s', $start, $end);
    }

    /**
     * Explains a `BackrefNode`.
     *
     * Purpose: This method describes a backreference (e.g., `\1`), explaining that
     * it matches the text previously captured by a specific group.
     *
     * @param BackrefNode $node the backreference node to explain
     *
     * @return string a description of the backreference
     */
    public function visitBackref(BackrefNode $node): string
    {
        return \sprintf('Backreference: matches text from group "%s"', $node->ref);
    }

    /**
     * Explains a `UnicodeNode`.
     *
     * Purpose: This method describes a Unicode character escape sequence.
     *
     * @param UnicodeNode $node the Unicode node to explain
     *
     * @return string a description of the Unicode character
     */
    public function visitUnicode(UnicodeNode $node): string
    {
        return \sprintf('Unicode: %s', $node->code);
    }

    /**
     * Explains a `UnicodePropNode`.
     *
     * Purpose: This method describes a Unicode property escape (e.g., `\p{L}`),
     * explaining that it matches any character with a specific Unicode property.
     *
     * @param UnicodePropNode $node the Unicode property node to explain
     *
     * @return string a description of the Unicode property match
     */
    public function visitUnicodeProp(UnicodePropNode $node): string
    {
        $type = str_starts_with($node->prop, '^') ? 'non-matching' : 'matching';
        $prop = ltrim($node->prop, '^');

        return \sprintf('Unicode Property: any character %s "%s"', $type, $prop);
    }

    /**
     * Explains an `OctalNode`.
     *
     * Purpose: This method describes a modern octal character escape (`\o{...}`).
     *
     * @param OctalNode $node the octal node to explain
     *
     * @return string a description of the octal character
     */
    public function visitOctal(OctalNode $node): string
    {
        return 'Octal: '.$node->code;
    }

    /**
     * Explains an `OctalLegacyNode`.
     *
     * Purpose: This method describes a legacy octal character escape (e.g., `\077`).
     *
     * @param OctalLegacyNode $node the legacy octal node to explain
     *
     * @return string a description of the legacy octal character
     */
    public function visitOctalLegacy(OctalLegacyNode $node): string
    {
        return 'Legacy Octal: \\'.$node->code;
    }

    /**
     * Explains a `PosixClassNode`.
     *
     * Purpose: This method describes a POSIX character class (e.g., `[:alpha:]`).
     *
     * @param PosixClassNode $node the POSIX class node to explain
     *
     * @return string a description of the POSIX class
     */
    public function visitPosixClass(PosixClassNode $node): string
    {
        return 'POSIX Class: '.$node->class;
    }

    /**
     * Explains a `CommentNode`.
     *
     * Purpose: This method describes an inline regex comment `(?#...)`.
     *
     * @param CommentNode $node the comment node to explain
     *
     * @return string the content of the comment
     */
    public function visitComment(CommentNode $node): string
    {
        return \sprintf("Comment: '%s'", $node->comment);
    }

    /**
     * Explains a `ConditionalNode`.
     *
     * Purpose: This method describes a conditional subpattern `(?(cond)yes|no)`,
     * clearly laying out the condition, the "yes" pattern, and the optional "no" pattern.
     *
     * @param ConditionalNode $node the conditional node to explain
     *
     * @return string a description of the conditional logic
     */
    public function visitConditional(ConditionalNode $node): string
    {
        $this->indentLevel++;
        $cond = $node->condition->accept($this);
        $yes = $node->yes->accept($this);

        // Check if the 'no' branch is an empty literal node
        $hasElseBranch = !($node->no instanceof LiteralNode && '' === $node->no->value);
        $no = $hasElseBranch ? $node->no->accept($this) : '';

        $this->indentLevel--;

        $indent = $this->indent();

        if ('' === $no) {
            return \sprintf("Conditional: IF (%s) THEN:\n%s%s", $cond, $indent, $yes);
        }

        return \sprintf("Conditional: IF (%s) THEN:\n%s%s\n%sELSE:\n%s%s", $cond, $indent, $yes, $this->indent(false), $indent, $no);
    }

    /**
     * Explains a `SubroutineNode`.
     *
     * Purpose: This method describes a subroutine call (e.g., `(?R)`), explaining
     * that it recursively calls another part of the pattern.
     *
     * @param SubroutineNode $node the subroutine node to explain
     *
     * @return string a description of the subroutine call
     */
    public function visitSubroutine(SubroutineNode $node): string
    {
        $ref = match ($node->reference) {
            'R' => 'the entire pattern',
            '0' => 'the entire pattern',
            default => 'group '.$node->reference,
        };

        return \sprintf('Subroutine Call: recurses to %s', $ref);
    }

    /**
     * Explains a `PcreVerbNode`.
     *
     * Purpose: This method describes a PCRE verb like `(*FAIL)`, which controls
     * the backtracking process of the regex engine.
     *
     * @param PcreVerbNode $node the PCRE verb node to explain
     *
     * @return string a description of the PCRE verb
     */
    public function visitPcreVerb(PcreVerbNode $node): string
    {
        return 'PCRE Verb: (*'.$node->verb.')';
    }

    /**
     * Explains a `DefineNode`.
     *
     * Purpose: This method describes a `(?(DEFINE)...)` block, explaining that it
     * defines subpatterns for later use in subroutines without matching anything itself.
     *
     * @param DefineNode $node the define node to explain
     *
     * @return string a description of the DEFINE block
     */
    public function visitDefine(DefineNode $node): string
    {
        $this->indentLevel++;
        $content = $node->content->accept($this);
        $this->indentLevel--;

        $indent = $this->indent();

        return \sprintf("DEFINE Block (defines subpatterns without matching):\n%s%s\n%sEnd DEFINE Block", $indent, $content, $this->indent(false));
    }

    private function explainQuantifierValue(string $q, string $type): string
    {
        $desc = match ($q) {
            '*' => 'zero or more times',
            '+' => 'one or more times',
            '?' => 'zero or one time',
            default => preg_match('/^\{(\d+)(?:,(\d*))?\}$/', $q, $m) ?
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
