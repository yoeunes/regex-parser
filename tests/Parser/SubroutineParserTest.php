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
use RegexParser\Ast\RegexNode;
use RegexParser\Ast\SubroutineNode;
use RegexParser\Exception\ParserException;
use RegexParser\Parser\Parser;

class SubroutineParserTest extends TestCase
{
    public function testParseRecursion(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?R)/');

        $this->assertInstanceOf(RegexNode::class, $ast);
        $this->assertInstanceOf(SubroutineNode::class, $ast->pattern);
        $this->assertSame('R', $ast->pattern->reference);
        $this->assertSame('', $ast->pattern->syntax);
    }

    public function testParseNumericSubroutine(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?1)/');

        $this->assertInstanceOf(RegexNode::class, $ast);
        $this->assertInstanceOf(SubroutineNode::class, $ast->pattern);
        $this->assertSame('1', $ast->pattern->reference);
    }

    public function testParseRelativeNumericSubroutine(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?-1)/');

        $this->assertInstanceOf(RegexNode::class, $ast);
        $this->assertInstanceOf(SubroutineNode::class, $ast->pattern);
        $this->assertSame('-1', $ast->pattern->reference);
    }

    public function testParseNamedSubroutine(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?&name)/');

        $this->assertInstanceOf(RegexNode::class, $ast);
        $this->assertInstanceOf(SubroutineNode::class, $ast->pattern);
        $this->assertSame('name', $ast->pattern->reference);
        $this->assertSame('&', $ast->pattern->syntax);
    }

    public function testParsePNamedSubroutine(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?P>name)/');

        $this->assertInstanceOf(RegexNode::class, $ast);
        $this->assertInstanceOf(SubroutineNode::class, $ast->pattern);
        $this->assertSame('name', $ast->pattern->reference);
        $this->assertSame('P>', $ast->pattern->syntax);
    }

    public function testThrowsOnIncompleteSubroutine(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected ) to close subroutine call');
        $parser = new Parser();
        $parser->parse('/(?&name');
    }
}
