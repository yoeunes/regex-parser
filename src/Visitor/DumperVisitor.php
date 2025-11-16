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
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\OctalNode;
use RegexParser\Ast\PosixClassNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\RangeNode;
use RegexParser\Ast\RegexNode;
use RegexParser\Ast\SequenceNode;
use RegexParser\Ast\UnicodeNode;
use RegexParser\Ast\UnicodePropNode;

/**
 * A visitor that dumps the AST as a string for debugging.
 *
 * @implements VisitorInterface<string>
 */
class DumperVisitor implements VisitorInterface
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
        $flags = (string)($node->flags ?? '');

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
        return "Octal({$node->code})";
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

    private function indent(string $str): string
    {
        return str_replace("\n", "\n".str_repeat(' ', $this->indent), $str);
    }
}
