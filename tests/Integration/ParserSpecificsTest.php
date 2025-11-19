<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Parser;

class ParserSpecificsTest extends TestCase
{
    public function test_subroutine_name_unexpected_token(): void
    {
        // (?&name!) -> '!' is not allowed in subroutine name
        $parser = new Parser();

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unexpected token');

        $parser->parse('/(?&name!)/');
    }

    public function test_quantifier_on_start_of_pattern(): void
    {
        // A quantifier at the very start of the pattern (after delimiter)
        // hits the "Quantifier without target" check in parseQuantifiedAtom
        $parser = new Parser();

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Quantifier without target');

        $parser->parse('/+abc/');
    }
}
