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

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Parser;

class ParserEdgeCaseTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function test_quantifier_on_empty_sequence(): void
    {
        // Case: /(?:)+/ -> Empty group (empty sequence) quantified
        // This triggers the condition "if ($node instanceof LiteralNode && '' === $node->value)" in parseQuantifiedAtom
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Quantifier without target');
        $this->parser->parse('/(?:)+/');
    }

    public function test_subroutine_empty_name(): void
    {
        // Case: (?&) -> subroutine call without name
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected subroutine name');
        $this->parser->parse('/(?&)/');
    }

    public function test_named_group_empty_name_angle_brackets(): void
    {
        // Case: (?<>) -> Empty named group
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected group name');
        $this->parser->parse('/(?<>)/');
    }

    public function test_unclosed_group_in_subroutine_name(): void
    {
        // Case: (?&name -> no closing parenthesis, but end of string
        // Should trigger "Unexpected token" or "Expected )"
        $this->expectException(ParserException::class);
        $this->parser->parse('/(?&name/');
    }
}
