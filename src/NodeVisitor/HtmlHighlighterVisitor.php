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
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\ControlCharNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LimitMatchNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\ScriptRunNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\Node\VersionConditionNode;

/**
 * Highlights regex syntax for HTML output using span tags with classes.
 */
final class HtmlHighlighterVisitor extends HighlighterVisitor
{
    private const CLASSES = [
        'meta' => 'regex-meta',
        'quantifier' => 'regex-quantifier',
        'type' => 'regex-type',
        'anchor' => 'regex-anchor',
        'literal' => 'regex-literal',
    ];

    #[\Override]
    public function visitGroup(GroupNode $node): string
    {
        $inner = $node->child->accept($this);
        $prefix = match ($node->type) {
            GroupType::T_GROUP_NON_CAPTURING => '?:',
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE => '?=',
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE => '?!',
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE => '?<=',
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE => '?<!',
            GroupType::T_GROUP_ATOMIC => '?>',
            GroupType::T_GROUP_NAMED => "?&lt;{$node->name}&gt;",
            default => '',
        };
        $opening = $this->wrap('('.$this->escape($prefix), 'meta');
        $closing = $this->wrap(')', 'meta');

        return $opening.$inner.$closing;
    }

    #[\Override]
    public function visitLiteral(LiteralNode $node): string
    {
        return '<span class="'.self::CLASSES['literal'].'">'.htmlspecialchars($node->value).'</span>';
    }

    #[\Override]
    public function visitCharType(CharTypeNode $node): string
    {
        return '<span class="'.self::CLASSES['type'].'">\\'.$node->value.'</span>';
    }

    #[\Override]
    public function visitDot(DotNode $node): string
    {
        return '<span class="'.self::CLASSES['meta'].'">.</span>';
    }

    #[\Override]
    public function visitAnchor(AnchorNode $node): string
    {
        return '<span class="'.self::CLASSES['anchor'].'">'.htmlspecialchars($node->value).'</span>';
    }

    #[\Override]
    public function visitAssertion(AssertionNode $node): string
    {
        return '<span class="'.self::CLASSES['type'].'">\\'.$node->value.'</span>';
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

        return '<span class="'.self::CLASSES['meta'].'>['.htmlspecialchars($neg).'</span>'.$inner.'<span class="'.self::CLASSES['meta'].'">]</span>';
    }

    #[\Override]
    public function visitRange(RangeNode $node): string
    {
        $start = $node->start->accept($this);
        $end = $node->end->accept($this);

        return $start.'<span class="'.self::CLASSES['meta'].'">-</span>'.$end;
    }

    // Implement other visit methods
    #[\Override]
    public function visitBackref(BackrefNode $node): string
    {
        return '<span class="'.self::CLASSES['type'].'">\\'.htmlspecialchars($node->ref).'</span>';
    }

    #[\Override]
    public function visitUnicode(UnicodeNode $node): string
    {
        return '<span class="'.self::CLASSES['type'].'">\\x'.$node->code.'</span>';
    }

    #[\Override]
    public function visitUnicodeProp(UnicodePropNode $node): string
    {
        $prop = $node->prop;
        if (\strlen($prop) > 1 || str_starts_with($prop, '^')) {
            $prop = '{'.$prop.'}';
        }

        return '<span class="'.self::CLASSES['type'].'">\\p'.htmlspecialchars($prop).'</span>';
    }

    #[\Override]
    public function visitPosixClass(PosixClassNode $node): string
    {
        return '<span class="'.self::CLASSES['type'].'">[:'.htmlspecialchars($node->class).':]</span>';
    }

    #[\Override]
    public function visitComment(CommentNode $node): string
    {
        return '<span class="'.self::CLASSES['meta'].'">(?#...)</span>';
    }

    #[\Override]
    public function visitConditional(ConditionalNode $node): string
    {
        $condition = $node->condition->accept($this);
        $yes = $node->yes->accept($this);
        $no = $node->no->accept($this);
        $noPart = $no ? $this->wrap('|', 'meta').$no : '';

        return $this->wrap('(?(', 'meta').$condition.$this->wrap(')', 'meta').$yes.$noPart.$this->wrap(')', 'meta');
    }

    #[\Override]
    public function visitSubroutine(SubroutineNode $node): string
    {
        return '<span class="'.self::CLASSES['type'].'">(?'.htmlspecialchars($node->reference).')</span>';
    }

    #[\Override]
    public function visitPcreVerb(PcreVerbNode $node): string
    {
        return '<span class="'.self::CLASSES['meta'].'">(*'.htmlspecialchars($node->verb).')</span>';
    }

    #[\Override]
    public function visitDefine(DefineNode $node): string
    {
        $inner = $node->content->accept($this);

        return '<span class="'.self::CLASSES['meta'].'">(?(DEFINE)</span>'.$inner.'<span class="'.self::CLASSES['meta'].'>)</span>';
    }

    #[\Override]
    public function visitLimitMatch(LimitMatchNode $node): string
    {
        return '<span class="'.self::CLASSES['meta'].'">(*LIMIT_MATCH='.$node->limit.')</span>';
    }

    #[\Override]
    public function visitCallout(CalloutNode $node): string
    {
        $content = $node->isStringIdentifier ? '"'.htmlspecialchars((string) $node->identifier).'"' : (string) $node->identifier;

        return '<span class="'.self::CLASSES['meta'].'">(?C'.$content.')</span>';
    }

    #[\Override]
    public function visitScriptRun(ScriptRunNode $node): string
    {
        return '<span class="'.self::CLASSES['meta'].'">(*script_run:'.htmlspecialchars($node->script).')</span>';
    }

    #[\Override]
    public function visitVersionCondition(VersionConditionNode $node): string
    {
        return '<span class="'.self::CLASSES['meta'].'">(?(VERSION&gt;='.$node->version.')</span>';
    }

    #[\Override]
    public function visitKeep(KeepNode $node): string
    {
        return '<span class="'.self::CLASSES['type'].'">\\K</span>';
    }

    #[\Override]
    public function visitControlChar(ControlCharNode $node): string
    {
        return '<span class="'.self::CLASSES['type'].'">\\c'.$node->char.'</span>';
    }

    #[\Override]
    public function visitClassOperation(ClassOperationNode $node): string
    {
        $left = $node->left->accept($this);
        $right = $node->right->accept($this);
        $op = ClassOperationType::INTERSECTION === $node->type ? '&&' : '--';

        return '<span class="'.self::CLASSES['meta'].'">[</span>'.$left.'<span class="'.self::CLASSES['meta'].'">'.htmlspecialchars($op).'</span>'.$right.'<span class="'.self::CLASSES['meta'].'">]</span>';
    }

    protected function wrap(string $content, string $type): string
    {
        return '<span class="'.self::CLASSES[$type].'">'.$content.'</span>';
    }

    protected function escape(string $string): string
    {
        return htmlspecialchars($string);
    }
}
