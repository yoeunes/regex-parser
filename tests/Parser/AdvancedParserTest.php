<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests\Parser;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Parser;

class AdvancedParserTest extends TestCase
{
    public function testParseNamedGroup(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?<name>a)/');

        $this->assertInstanceOf(RegexNode::class, $ast);
        $this->assertInstanceOf(GroupNode::class, $ast->pattern);
        $this->assertSame(GroupType::T_GROUP_NAMED, $ast->pattern->type);
        $this->assertSame('name', $ast->pattern->name);
    }

    public function testParseLazyQuantifier(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a+?/');

        $this->assertInstanceOf(RegexNode::class, $ast);
        $this->assertInstanceOf(QuantifierNode::class, $ast->pattern);
        $this->assertSame('+', $ast->pattern->quantifier);
        $this->assertSame(QuantifierType::T_LAZY, $ast->pattern->type);
    }

    public function testParsePossessiveQuantifier(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a{2,3}+/');

        $this->assertInstanceOf(RegexNode::class, $ast);
        $this->assertInstanceOf(QuantifierNode::class, $ast->pattern);
        $this->assertSame('{2,3}', $ast->pattern->quantifier);
        $this->assertSame(QuantifierType::T_POSSESSIVE, $ast->pattern->type);
    }

    public function testParseLookahead(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a(?=b)/');

        $this->assertInstanceOf(RegexNode::class, $ast);
        // $ast->pattern est un SequenceNode(Literal(a), GroupNode(...))
        $this->assertInstanceOf(SequenceNode::class, $ast->pattern);
        $this->assertCount(2, $ast->pattern->children);
        $group = $ast->pattern->children[1];
        $this->assertInstanceOf(GroupNode::class, $group);
        $this->assertSame(GroupType::T_GROUP_LOOKAHEAD_POSITIVE, $group->type);
    }

    public function testParseAlternativeDelimiter(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('#a|b#imsU');

        $this->assertSame('#', $ast->delimiter);
        $this->assertSame('imsU', $ast->flags);
    }

    public function testParseBraceDelimiter(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('{foo(bar)}i');

        $this->assertSame('{', $ast->delimiter);
        $this->assertSame('i', $ast->flags);
        $this->assertInstanceOf(SequenceNode::class, $ast->pattern);
        $this->assertCount(4, $ast->pattern->children); // 'f','o','o','(bar)'
        $this->assertInstanceOf(GroupNode::class, $ast->pattern->children[3]);
    }
}
