<?php
namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\AnchorNode;
use RegexParser\Ast\BackrefNode;
use RegexParser\Ast\CharClassNode;
use RegexParser\Ast\CharTypeNode;
use RegexParser\Ast\DotNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\PosixClassNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\RangeNode;
use RegexParser\Ast\RegexNode;
use RegexParser\Ast\SequenceNode;
use RegexParser\Ast\UnicodeNode;

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
        return "Regex(delimiter: {$node->delimiter}, flags: {$node->flags})\n" . $this->indent($node->pattern->accept($this));
    }

    public function visitAlternation(AlternationNode $node): string
    {
        $str = "Alternation:\n";
        $this->indent += 2;
        foreach ($node->alternatives as $alt) {
            $str .= $this->indent($alt->accept($this)) . "\n";
        }
        $this->indent -= 2;
        return $str;
    }

    public function visitSequence(SequenceNode $node): string
    {
        $str = "Sequence:\n";
        $this->indent += 2;
        foreach ($node->children as $child) {
            $str .= $this->indent($child->accept($this)) . "\n";
        }
        $this->indent -= 2;
        return $str;
    }

    public function visitGroup(GroupNode $node): string
    {
        $name = $node->name ? " name: {$node->name}" : '';
        return "Group(type: {$node->type->value}{$name})\n" . $this->indent($node->child->accept($this));
    }

    public function visitQuantifier(QuantifierNode $node): string
    {
        return "Quantifier(quant: {$node->quantifier}, type: {$node->type->value})\n" . $this->indent($node->node->accept($this));
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

    public function visitCharClass(CharClassNode $node): string
    {
        $neg = $node->isNegated ? '^' : '';
        $str = "CharClass({$neg})\n";
        $this->indent += 2;
        foreach ($node->parts as $part) {
            $str .= $this->indent($part->accept($this)) . "\n";
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

    public function visitPosixClass(PosixClassNode $node): string
    {
        return "PosixClass([[:{$node->class}:]])";
    }

    private function indent(string $str): string
    {
        return str_replace("\n", "\n" . str_repeat(' ', $this->indent), $str);
    }
}
