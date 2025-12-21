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

use RegexParser\Node;

/**
 * Base visitor for highlighting regex syntax.
 *
 * @extends AbstractNodeVisitor<string>
 */
abstract class HighlighterVisitor extends AbstractNodeVisitor
{
    #[\Override]
    public function visitRegex(Node\RegexNode $node): string
    {
        return $node->pattern->accept($this);
    }

    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): string
    {
        $parts = [];
        foreach ($node->alternatives as $alt) {
            $parts[] = $alt->accept($this);
        }

        return implode($this->wrap('|', 'meta'), $parts);
    }

    #[\Override]
    public function visitSequence(Node\SequenceNode $node): string
    {
        $parts = [];
        foreach ($node->children as $child) {
            $parts[] = $child->accept($this);
        }

        return implode('', $parts);
    }

    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): string
    {
        $inner = $node->node->accept($this);
        $quant = $node->quantifier;
        if (Node\QuantifierType::T_LAZY === $node->type) {
            $quant .= '?';
        } elseif (Node\QuantifierType::T_POSSESSIVE === $node->type) {
            $quant .= '+';
        }

        return $inner.$this->wrap($this->escape($quant), 'quantifier');
    }

    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): string
    {
        return $this->wrap($this->escape($node->value), 'literal');
    }

    #[\Override]
    public function visitCharType(Node\CharTypeNode $node): string
    {
        return $this->wrap('\\'.$node->value, 'type');
    }

    #[\Override]
    public function visitDot(Node\DotNode $node): string
    {
        return $this->wrap('.', 'meta');
    }

    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): string
    {
        return $this->wrap($this->escape($node->value), 'anchor');
    }

    #[\Override]
    public function visitAssertion(Node\AssertionNode $node): string
    {
        return $this->wrap('\\'.$node->value, 'type');
    }

    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): string
    {
        $parts = $node->expression instanceof Node\AlternationNode
            ? $node->expression->alternatives
            : [$node->expression];
        $inner = '';
        foreach ($parts as $part) {
            $inner .= $part->accept($this);
        }
        $neg = $node->isNegated ? '^' : '';

        return $this->wrap('[', 'meta').$this->escape($neg).$inner.$this->wrap(']', 'meta');
    }

    #[\Override]
    public function visitRange(Node\RangeNode $node): string
    {
        $start = $node->start->accept($this);
        $end = $node->end->accept($this);

        return $start.$this->wrap('-', 'meta').$end;
    }

    #[\Override]
    public function visitBackref(Node\BackrefNode $node): string
    {
        return $this->wrap('\\'.$this->escape($node->ref), 'type');
    }

    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): string
    {
        // For non-printable characters (control chars and extended ASCII), 
        // convert back to hex representation to avoid display issues
        if (strlen($node->code) === 1) {
            $charCode = ord($node->code);
            // Control characters (0x00-0x1F, 0x7F) and extended ASCII (0x80-0xFF)
            if (($charCode >= 0x00 && $charCode <= 0x1F) || 
                $charCode === 0x7F || 
                ($charCode >= 0x80 && $charCode <= 0xFF)) {
                return $this->wrap('\\x'.strtoupper(str_pad(dechex($charCode), 2, '0', STR_PAD_LEFT)), 'type');
            }
        }
        
        // For regular Unicode escape sequences, use the original format
        return $this->wrap('\\x'.$node->code, 'type');
    }

    #[\Override]
    public function visitUnicodeProp(Node\UnicodePropNode $node): string
    {
        $prop = $node->prop;
        if (\strlen($prop) > 1 || str_starts_with($prop, '^')) {
            $prop = '{'.$prop.'}';
        }

        return $this->wrap('\\p'.$this->escape($prop), 'type');
    }

    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): string
    {
        return $this->wrap('[:'.$this->escape($node->class).':]', 'type');
    }

    #[\Override]
    public function visitComment(Node\CommentNode $node): string
    {
        return $this->wrap('(?#...)', 'meta');
    }

    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): string
    {
        return $this->wrap('(?'.$this->escape($node->reference).')', 'type');
    }

    #[\Override]
    public function visitPcreVerb(Node\PcreVerbNode $node): string
    {
        return $this->wrap('(*'.$this->escape($node->verb).')', 'meta');
    }

    #[\Override]
    public function visitDefine(Node\DefineNode $node): string
    {
        $inner = $node->content->accept($this);

        return $this->wrap('(?(DEFINE)', 'meta').$inner.$this->wrap(')', 'meta');
    }

    #[\Override]
    public function visitLimitMatch(Node\LimitMatchNode $node): string
    {
        return $this->wrap('(*LIMIT_MATCH='.$node->limit.')', 'meta');
    }

    #[\Override]
    public function visitCallout(Node\CalloutNode $node): string
    {
        $content = $node->isStringIdentifier ? '"'.$this->escape((string) $node->identifier).'"' : (string) $node->identifier;

        return $this->wrap('(?C'.$content.')', 'meta');
    }

    #[\Override]
    public function visitScriptRun(Node\ScriptRunNode $node): string
    {
        return $this->wrap('(*script_run:'.$this->escape($node->script).')', 'meta');
    }

    #[\Override]
    public function visitVersionCondition(Node\VersionConditionNode $node): string
    {
        return $this->wrap('(?(VERSION>='.$node->version.')', 'meta');
    }

    #[\Override]
    public function visitKeep(Node\KeepNode $node): string
    {
        return $this->wrap('\\K', 'type');
    }

    #[\Override]
    public function visitControlChar(Node\ControlCharNode $node): string
    {
        return $this->wrap('\\c'.$node->char, 'type');
    }

    #[\Override]
    public function visitClassOperation(Node\ClassOperationNode $node): string
    {
        $left = $node->left->accept($this);
        $right = $node->right->accept($this);
        $op = Node\ClassOperationType::INTERSECTION === $node->type ? '&&' : '--';

        return $this->wrap('[', 'meta').$left.$this->wrap($this->escape($op), 'meta').$right.$this->wrap(']', 'meta');
    }

    abstract protected function wrap(string $content, string $type): string;

    abstract protected function escape(string $string): string;
}
