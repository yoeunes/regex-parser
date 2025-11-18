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

namespace RegexParser\Tests\Parser;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Node\SubroutineNode;
use RegexParser\Parser;

class SubroutineParserTest extends TestCase
{
    public function test_parse_recursion(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?R)/');

        $this->assertInstanceOf(SubroutineNode::class, $ast->pattern);
        $this->assertSame('R', $ast->pattern->reference);
        $this->assertSame('', $ast->pattern->syntax);
    }

    public function test_parse_numeric_subroutine(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?1)/');

        $this->assertInstanceOf(SubroutineNode::class, $ast->pattern);
        $this->assertSame('1', $ast->pattern->reference);
    }

    public function test_parse_relative_numeric_subroutine(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?-1)/');

        $this->assertInstanceOf(SubroutineNode::class, $ast->pattern);
        $this->assertSame('-1', $ast->pattern->reference);
    }

    public function test_parse_named_subroutine(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?&name)/');

        $this->assertInstanceOf(SubroutineNode::class, $ast->pattern);
        $this->assertSame('name', $ast->pattern->reference);
        $this->assertSame('&', $ast->pattern->syntax);
    }

    public function test_parse_p_named_subroutine(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/(?P>name)/');

        $this->assertInstanceOf(SubroutineNode::class, $ast->pattern);
        $this->assertSame('name', $ast->pattern->reference);
        $this->assertSame('P>', $ast->pattern->syntax);
    }

    public function test_throws_on_incomplete_subroutine(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected ) to close subroutine call');
        $parser = new Parser();
        // The regex must be validly delimited for the parser to run
        $parser->parse('/(?&name/i');
    }
}
