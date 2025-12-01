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
 * Dumps the Abstract Syntax Tree (AST) into a human-readable string format.
 *
 * Purpose: This visitor is a primary debugging tool for contributors. It traverses the
 * AST and generates an indented, hierarchical representation of the nodes. This allows
 * you to visually inspect the structure of the tree that the `Parser` produces, which is
 * essential for verifying that a regex is parsed correctly or for understanding how a
 * new node type fits into the overall structure.
 *
 * @implements NodeVisitorInterface<string>
 */
class DumperNodeVisitor implements NodeVisitorInterface
{
    private int $indent = 0;

    /**
     * Dumps the root `RegexNode`.
     *
     * Purpose: This is the entry point for the dumping process. It formats the top-level
     * node, including its delimiters and flags, and then recursively calls the visitor
     * on its child pattern.
     *
     * @param RegexNode $node The root node of the AST.
     * @return string The string representation of the entire AST.
     */
    public function visitRegex(RegexNode $node): string
    {
        $str = "Regex(delimiter: {$node->delimiter}, flags: {$node->flags})\n";
        $this->indent += 2;
        $str .= $node->pattern->accept($this);
        $this->indent -= 2;

        return $str;
    }

    /**
     * Dumps an `AlternationNode`.
     *
     * Purpose: This method visualizes an alternation (`|`). It prints the "Alternation"
     * label and then recursively dumps each of its alternative child sequences, clearly
     * showing the different branches the regex can take.
     *
     * @param AlternationNode $node The alternation node to dump.
     * @return string The string representation of the alternation and its children.
     */
    public function visitAlternation(AlternationNode $node): string
    {
        $str = str_repeat(' ', $this->indent)."Alternation:\n";
        $this->indent += 2;
        foreach ($node->alternatives as $alt) {
            $str .= str_repeat(' ', $this->indent).$alt->accept($this)."\n";
        }
        $this->indent -= 2;

        return rtrim($str, "\n");
    }

    /**
     * Dumps a `SequenceNode`.
     *
     * Purpose: This method visualizes a sequence of consecutive regex components. It
     * prints the "Sequence" label and then recursively dumps each child node in order,
     * showing how the components are arranged sequentially.
     *
     * @param SequenceNode $node The sequence node to dump.
     * @return string The string representation of the sequence and its children.
     */
    public function visitSequence(SequenceNode $node): string
    {
        $str = str_repeat(' ', $this->indent)."Sequence:\n";
        $this->indent += 2;
        foreach ($node->children as $child) {
            $str .= str_repeat(' ', $this->indent).$child->accept($this)."\n";
        }
        $this->indent -= 2;

        return rtrim($str, "\n");
    }

    /**
     * Dumps a `GroupNode`.
     *
     * Purpose: This method visualizes any type of group, such as capturing `(...)`,
     * non-capturing `(?:...)`, or lookaheads `(?=...)`. It prints the group's type,
     * name (if any), and flags, then recursively dumps the child expression inside
     * the group.
     *
     * @param GroupNode $node The group node to dump.
     * @return string The string representation of the group and its child.
     */
    public function visitGroup(GroupNode $node): string
    {
        $name = $node->name ?? '';
        $flags = $node->flags ?? '';

        // Only include "name:" label if name is not empty
        $nameStr = ('' !== $name) ? " name: {$name}" : '';
        $str = "Group(type: {$node->type->value}{$nameStr} flags: {$flags})\n";
        $this->indent += 2;
        $str .= $node->child->accept($this);
        $this->indent -= 2;

        return $str;
    }

    /**
     * Dumps a `QuantifierNode`.
     *
     * Purpose: This method visualizes a quantifier like `*`, `+`, or `{2,5}`. It
     * prints the quantifier's token and type (greedy, lazy, possessive), then
     * recursively dumps the node that the quantifier applies to.
     *
     * @param QuantifierNode $node The quantifier node to dump.
     * @return string The string representation of the quantifier and its child.
     */
    public function visitQuantifier(QuantifierNode $node): string
    {
        return "Quantifier(quant: {$node->quantifier}, type: {$node->type->value})\n".$this->indent(
            $node->node->accept($this),
        );
    }

    /**
     * Dumps a `LiteralNode`.
     *
     * Purpose: This method visualizes a literal character or string. It simply
     * prints "Literal" followed by the literal value itself.
     *
     * @param LiteralNode $node The literal node to dump.
     * @return string The string representation of the literal.
     */
    public function visitLiteral(LiteralNode $node): string
    {
        return "Literal('{$node->value}')";
    }

    /**
     * Dumps a `CharTypeNode`.
     *
     * Purpose: This method visualizes a character type escape sequence like `\d` or `\s`.
     * It prints "CharType" followed by the escaped sequence.
     *
     * @param CharTypeNode $node The character type node to dump.
     * @return string The string representation of the character type.
     */
    public function visitCharType(CharTypeNode $node): string
    {
        return "CharType('\\{$node->value}')";
    }

    /**
     * Dumps a `DotNode`.
     *
     * Purpose: This method visualizes the "any character" wildcard (`.`).
     *
     * @param DotNode $node The dot node to dump.
     * @return string The string representation of the dot.
     */
    public function visitDot(DotNode $node): string
    {
        return 'Dot(.)';
    }

    /**
     * Dumps an `AnchorNode`.
     *
     * Purpose: This method visualizes an anchor like `^` or `$`.
     *
     * @param AnchorNode $node The anchor node to dump.
     * @return string The string representation of the anchor.
     */
    public function visitAnchor(AnchorNode $node): string
    {
        return "Anchor({$node->value})";
    }

    /**
     * Dumps an `AssertionNode`.
     *
     * Purpose: This method visualizes a zero-width assertion like `\b` or `\A`.
     *
     * @param AssertionNode $node The assertion node to dump.
     * @return string The string representation of the assertion.
     */
    public function visitAssertion(AssertionNode $node): string
    {
        return "Assertion(\\{$node->value})";
    }

    /**
     * Dumps a `KeepNode`.
     *
     * Purpose: This method visualizes the `\K` (keep) escape sequence, which resets
     * the beginning of the reported match.
     *
     * @param KeepNode $node The keep node to dump.
     * @return string The string representation of the keep node.
     */
    public function visitKeep(KeepNode $node): string
    {
        return 'Keep(\K)';
    }

    /**
     * Dumps a `CharClassNode`.
     *
     * Purpose: This method visualizes a character class `[...]`. It indicates whether
     * the class is negated and then recursively dumps each of the components inside
     * the class (e.g., literals, ranges, character types).
     *
     * @param CharClassNode $node The character class node to dump.
     * @return string The string representation of the character class and its parts.
     */
    public function visitCharClass(CharClassNode $node): string
    {
        $neg = $node->isNegated ? '^' : '';
        $str = "CharClass({$neg})\n";
        $this->indent += 2;
        foreach ($node->parts as $part) {
            $str .= $this->indent($part->accept($this))."\n";
        }
        $this->indent -= 2;

        return $str;
    }

    /**
     * Dumps a `RangeNode`.
     *
     * Purpose: This method visualizes a range within a character class, like `a-z`.
     * It recursively dumps the start and end nodes of the range.
     *
     * @param RangeNode $node The range node to dump.
     * @return string The string representation of the range.
     */
    public function visitRange(RangeNode $node): string
    {
        return "Range({$node->start->accept($this)} - {$node->end->accept($this)})";
    }

    /**
     * Dumps a `BackrefNode`.
     *
     * Purpose: This method visualizes a backreference, like `\1` or `\k<name>`.
     *
     * @param BackrefNode $node The backreference node to dump.
     * @return string The string representation of the backreference.
     */
    public function visitBackref(BackrefNode $node): string
    {
        return "Backref(\\{$node->ref})";
    }

    /**
     * Dumps a `UnicodeNode`.
     *
     * Purpose: This method visualizes a Unicode character escape, like `\x{...}`.
     *
     * @param UnicodeNode $node The Unicode node to dump.
     * @return string The string representation of the Unicode character.
     */
    public function visitUnicode(UnicodeNode $node): string
    {
        return "Unicode({$node->code})";
    }

    /**
     * Dumps a `UnicodePropNode`.
     *
     * Purpose: This method visualizes a Unicode property escape, like `\p{L}`.
     *
     * @param UnicodePropNode $node The Unicode property node to dump.
     * @return string The string representation of the Unicode property.
     */
    public function visitUnicodeProp(UnicodePropNode $node): string
    {
        return "UnicodeProp(\\p{{$node->prop}})";
    }

    /**
     * Dumps an `OctalNode`.
     *
     * Purpose: This method visualizes a modern octal character escape, like `\o{...}`.
     *
     * @param OctalNode $node The octal node to dump.
     * @return string The string representation of the octal character.
     */
    public function visitOctal(OctalNode $node): string
    {
        return "Octal({$node->code})";
    }

    /**
     * Dumps an `OctalLegacyNode`.
     *
     * Purpose: This method visualizes a legacy octal character escape, like `\077`.
     *
     * @param OctalLegacyNode $node The legacy octal node to dump.
     * @return string The string representation of the legacy octal character.
     */
    public function visitOctalLegacy(OctalLegacyNode $node): string
    {
        return "OctalLegacy(\\{$node->code})";
    }

    /**
     * Dumps a `PosixClassNode`.
     *
     * Purpose: This method visualizes a POSIX character class, like `[:alpha:]`.
     *
     * @param PosixClassNode $node The POSIX class node to dump.
     * @return string The string representation of the POSIX class.
     */
    public function visitPosixClass(PosixClassNode $node): string
    {
        return "PosixClass([[:{$node->class}:]])";
    }

    /**
     * Dumps a `CommentNode`.
     *
     * Purpose: This method visualizes an inline comment, like `(?#...)`.
     *
     * @param CommentNode $node The comment node to dump.
     * @return string The string representation of the comment.
     */
    public function visitComment(CommentNode $node): string
    {
        return "Comment('{$node->comment}')";
    }

    /**
     * Dumps a `ConditionalNode`.
     *
     * Purpose: This method visualizes a conditional subpattern, like `(?(cond)yes|no)`.
     * It recursively dumps the condition, the "yes" pattern, and the "no" pattern.
     *
     * @param ConditionalNode $node The conditional node to dump.
     * @return string The string representation of the conditional structure.
     */
    public function visitConditional(ConditionalNode $node): string
    {
        $str = "Conditional:\n";
        $this->indent += 2;
        $str .= $this->indent('Condition: '.$node->condition->accept($this))."\n";
        $str .= $this->indent('Yes: '.$node->yes->accept($this))."\n";
        $str .= $this->indent('No: '.$node->no->accept($this))."\n";
        $this->indent -= 2;

        return $str;
    }

    /**
     * Dumps a `SubroutineNode`.
     *
     * Purpose: This method visualizes a subroutine call, like `(?R)` or `(?&name)`.
     *
     * @param SubroutineNode $node The subroutine node to dump.
     * @return string The string representation of the subroutine call.
     */
    public function visitSubroutine(SubroutineNode $node): string
    {
        return "Subroutine(ref: {$node->reference}, syntax: '{$node->syntax}')";
    }

    /**
     * Dumps a `PcreVerbNode`.
     *
     * Purpose: This method visualizes a PCRE verb, like `(*FAIL)` or `(*MARK)`.
     *
     * @param PcreVerbNode $node The PCRE verb node to dump.
     * @return string The string representation of the verb.
     */
    public function visitPcreVerb(PcreVerbNode $node): string
    {
        return "PcreVerb(value: {$node->verb})";
    }

    /**
     * Dumps a `DefineNode`.
     *
     * Purpose: This method visualizes a `(?(DEFINE)...)` group, which is used to
     * define subroutines that are not executed in place.
     *
     * @param DefineNode $node The define node to dump.
     * @return string The string representation of the define group.
     */
    public function visitDefine(DefineNode $node): string
    {
        $str = "Define:\n";
        $this->indent += 2;
        $str .= $this->indent('Content: '.$node->content->accept($this))."\n";
        $this->indent -= 2;

        return $str;
    }

    private function indent(string $str): string
    {
        $indentStr = str_repeat(' ', $this->indent);

        return $indentStr.str_replace("\n", "\n".$indentStr, $str);
    }
}
