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
use RegexParser\Node\CalloutNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\ClassOperationNode;
use RegexParser\Node\ClassOperationType;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ControlCharNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LimitMatchNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\ScriptRunNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\Node\VersionConditionNode;

/**
 * Base visitor for highlighting regex syntax.
 *
 * @extends AbstractNodeVisitor<string>
 */
abstract class HighlighterVisitor extends AbstractNodeVisitor
{
    #[\Override]
    public function visitRegex(RegexNode $node): string
    {
        return $node->pattern->accept($this);
    }

    #[\Override]
    public function visitAlternation(AlternationNode $node): string
    {
        $parts = [];
        foreach ($node->alternatives as $alt) {
            $parts[] = $alt->accept($this);
        }

        return implode($this->wrap('|', 'meta'), $parts);
    }

    #[\Override]
    public function visitSequence(SequenceNode $node): string
    {
        $parts = [];
        foreach ($node->children as $child) {
            $parts[] = $child->accept($this);
        }

        return implode('', $parts);
    }

    #[\Override]
    public function visitQuantifier(QuantifierNode $node): string
    {
        $inner = $node->node->accept($this);
        $quant = $node->quantifier;
        if (QuantifierType::T_LAZY === $node->type) {
            $quant .= '?';
        } elseif (QuantifierType::T_POSSESSIVE === $node->type) {
            $quant .= '+';
        }

        return $inner.$this->wrap($this->escape($quant), 'quantifier');
    }

    #[\Override]
    public function visitLiteral(LiteralNode $node): string
    {
        return $this->wrap($this->escape($node->value), 'literal');
    }

    #[\Override]
    public function visitCharType(CharTypeNode $node): string
    {
        return $this->wrap('\\'.$node->value, 'type');
    }

    #[\Override]
    public function visitDot(DotNode $node): string
    {
        return $this->wrap('.', 'meta');
    }

    #[\Override]
    public function visitAnchor(AnchorNode $node): string
    {
        return $this->wrap($this->escape($node->value), 'anchor');
    }

    #[\Override]
    public function visitAssertion(AssertionNode $node): string
    {
        return $this->wrap('\\'.$node->value, 'type');
    }

    #[\Override]
    public function visitCharClass(CharClassNode $node): string
    {
        $parts = $node->expression instanceof AlternationNode
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
    public function visitRange(RangeNode $node): string
    {
        $start = $node->start->accept($this);
        $end = $node->end->accept($this);

        return $start.$this->wrap('-', 'meta').$end;
    }

    #[\Override]
    public function visitBackref(BackrefNode $node): string
    {
        return $this->wrap('\\'.$this->escape($node->ref), 'type');
    }

    #[\Override]
    public function visitUnicode(UnicodeNode $node): string
    {
        // For non-printable characters (control chars and extended ASCII),
        // convert back to hex representation to avoid display issues
        if (1 === \strlen($node->code)) {
            $charCode = \ord($node->code);
            // Control characters (0x00-0x1F, 0x7F) and extended ASCII (0x80-0xFF)
            if ($charCode <= 0x1F
                || 0x7F === $charCode
                || $charCode >= 0x80) {
                return $this->wrap('\\x'.strtoupper(str_pad(dechex($charCode), 2, '0', \STR_PAD_LEFT)), 'type');
            }
        }

        // For regular Unicode escape sequences, use the original format
        return $this->wrap('\\x'.$node->code, 'type');
    }

    #[\Override]
    public function visitUnicodeProp(UnicodePropNode $node): string
    {
        $inner = $node->hasBraces ? trim($node->prop, '{}') : $node->prop;
        $isNegated = str_starts_with($inner, '^');
        $inner = ltrim($inner, '^');
        $prefix = $isNegated ? 'P' : 'p';
        $display = '{'.$inner.'}';

        return $this->wrap('\\'.$prefix.$this->escape($display), 'type');
    }

    #[\Override]
    public function visitPosixClass(PosixClassNode $node): string
    {
        return $this->wrap('[:'.$this->escape($node->class).':]', 'type');
    }

    #[\Override]
    public function visitComment(CommentNode $node): string
    {
        return $this->wrap('(?#...)', 'meta');
    }

    #[\Override]
    public function visitSubroutine(SubroutineNode $node): string
    {
        return $this->wrap('(?'.$this->escape($node->reference).')', 'type');
    }

    #[\Override]
    public function visitPcreVerb(PcreVerbNode $node): string
    {
        return $this->wrap('(*'.$this->escape($node->verb).')', 'meta');
    }

    #[\Override]
    public function visitDefine(DefineNode $node): string
    {
        $inner = $node->content->accept($this);

        return $this->wrap('(?(DEFINE)', 'meta').$inner.$this->wrap(')', 'meta');
    }

    #[\Override]
    public function visitLimitMatch(LimitMatchNode $node): string
    {
        return $this->wrap('(*LIMIT_MATCH='.$node->limit.')', 'meta');
    }

    #[\Override]
    public function visitCallout(CalloutNode $node): string
    {
        $content = $node->isStringIdentifier ? '"'.$this->escape((string) $node->identifier).'"' : (string) $node->identifier;

        return $this->wrap('(?C'.$content.')', 'meta');
    }

    #[\Override]
    public function visitScriptRun(ScriptRunNode $node): string
    {
        return $this->wrap('(*script_run:'.$this->escape($node->script).')', 'meta');
    }

    #[\Override]
    public function visitVersionCondition(VersionConditionNode $node): string
    {
        return $this->wrap('(?(VERSION>='.$node->version.')', 'meta');
    }

    #[\Override]
    public function visitKeep(KeepNode $node): string
    {
        return $this->wrap('\\K', 'type');
    }

    #[\Override]
    public function visitControlChar(ControlCharNode $node): string
    {
        return $this->wrap('\\c'.$node->char, 'type');
    }

    #[\Override]
    public function visitClassOperation(ClassOperationNode $node): string
    {
        $left = $node->left->accept($this);
        $right = $node->right->accept($this);
        $op = ClassOperationType::INTERSECTION === $node->type ? '&&' : '--';

        return $this->wrap('[', 'meta').$left.$this->wrap($this->escape($op), 'meta').$right.$this->wrap(']', 'meta');
    }

    abstract protected function wrap(string $content, string $type): string;

    abstract protected function escape(string $string): string;
}
