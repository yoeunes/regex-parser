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

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RegexParser\Lexer;
use RegexParser\Node\RegexNode;
use RegexParser\Parser;

final class ParserClassCoverageTest extends TestCase
{
    public function test_parser_class_instantiation(): void
    {
        $parser = new Parser();

        $this->assertInstanceOf(Parser::class, $parser);
    }

    public function test_parser_can_parse_simple_pattern(): void
    {
        $parser = new Parser();
        $lexer = new Lexer();

        $tokenStream = $lexer->tokenize('test');
        $ast = $parser->parse($tokenStream);

        $this->assertNotNull($ast);
        $this->assertInstanceOf(RegexNode::class, $ast);
    }

    public function test_parser_can_parse_with_flags(): void
    {
        $parser = new Parser();
        $lexer = new Lexer();

        $tokenStream = $lexer->tokenize('test');
        $ast = $parser->parse($tokenStream, 'i');

        $this->assertNotNull($ast);
        $this->assertInstanceOf(RegexNode::class, $ast);
        $this->assertSame('i', $ast->flags);
    }

    public function test_parser_with_custom_recursion_depth(): void
    {
        $parser = new Parser(100);
        $lexer = new Lexer();

        $tokenStream = $lexer->tokenize('test');
        $ast = $parser->parse($tokenStream);

        $this->assertNotNull($ast);
        $this->assertInstanceOf(RegexNode::class, $ast);
    }
}
