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
 * A visitor that dumps the AST as a string for debugging.
 *
 * @implements NodeVisitorInterface<string>
 */
class DumperNodeNodeVisitor implements NodeVisitorInterface
{
    private int $indent = 0;

    public function visitRegex(RegexNode $node): string
    {
        return "Regex(delimiter: {$node->delimiter}, flags: {$node->flags})\n".$this->indent(
            $node->pattern->accept($this)
        );
    }

    public function visitAlternation(AlternationNode $node): string
    {
        $str = "Alternation:\n";
        $this->indent += 2;
        foreach ($node->alternatives as $alt) {
            $str .= $this->indent($alt->accept($this))."\n";
        }
        $this->indent -= 2;

        return $str;
    }

    public function visitSequence(SequenceNode $node): string
    {
        $str = "Sequence:\n";
        $this->indent += 2;
        foreach ($node->children as $child) {
            $str .= $this->indent($child->accept($this))."\n";
        }
        $this->indent -= 2;

        return $str;
    }

    public function visitGroup(GroupNode $node): string
    {
        $name = $node->name ? " name: {$node->name}" : '';
        $flags = (string) ($node->flags ?? '');

        return "Group(type: {$node->type->value}{$name} flags: {$flags})\n".$this->indent($node->child->accept($this));
    }

    public function visitQuantifier(QuantifierNode $node): string
    {
        return "Quantifier(quant: {$node->quantifier}, type: {$node->type->value})\n".$this->indent(
            $node->node->accept($this)
        );
    }

    public function visitLiteral(LiteralNode $node): string
    {
        return "Literal('{$node->value}')";
    }

    public function visitCharType(CharTypeNode $node): string
    {
        return "CharType('\\{$node->value}')";
    }

    public function visitDot(DotNode $node): string
    {
        return 'Dot(.)';
    }

    public function visitAnchor(AnchorNode $node): string
    {
        return "Anchor({$node->value})";
    }

    public function visitAssertion(AssertionNode $node): string
    {
        return "Assertion(\\{$node->value})";
    }

    public function visitKeep(KeepNode $node): string
    {
        return 'Keep(\K)';
    }

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

    public function visitRange(RangeNode $node): string
    {
        return "Range({$node->start->accept($this)} - {$node->end->accept($this)})";
    }

    public function visitBackref(BackrefNode $node): string
    {
        return "Backref(\\{$node->ref})";
    }

    public function visitUnicode(UnicodeNode $node): string
    {
        return "Unicode({$node->code})";
    }

    public function visitUnicodeProp(UnicodePropNode $node): string
    {
        return "UnicodeProp(\\p{{$node->prop}})";
    }

    public function visitOctal(OctalNode $node): string
    {
        return "Octal(\\o{{$node->code}})";
    }

    public function visitOctalLegacy(OctalLegacyNode $node): string
    {
        return "OctalLegacy(\\{$node->code})";
    }

    public function visitPosixClass(PosixClassNode $node): string
    {
        return "PosixClass([[:{$node->class}:]])";
    }

    public function visitComment(CommentNode $node): string
    {
        return "Comment('{$node->comment}')";
    }

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

    public function visitSubroutine(SubroutineNode $node): string
    {
        return "Subroutine(ref: {$node->reference}, syntax: '{$node->syntax}')";
    }

    public function visitPcreVerb(PcreVerbNode $node): string
    {
        return "PcreVerb(value: {$node->verb})";
    }

    private function indent(string $str): string
    {
        return str_replace("\n", "\n".str_repeat(' ', $this->indent), $str);
    }
}
