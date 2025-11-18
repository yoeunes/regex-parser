<?php

declare(strict_types=1);

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
        // Cas : /(?:)+/ -> Groupe vide (séquence vide) quantifié
        // Cela déclenche la condition "if ($node instanceof LiteralNode && '' === $node->value)" dans parseQuantifiedAtom
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Quantifier without target');
        $this->parser->parse('/(?:)+/');
    }

    public function test_subroutine_empty_name(): void
    {
        // Cas : (?&) -> appel de subroutine sans nom
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected subroutine name');
        $this->parser->parse('/(?&)/');
    }

    public function test_named_group_empty_name_angle_brackets(): void
    {
        // Cas : (?<>) -> Groupe nommé vide
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected group name');
        $this->parser->parse('/(?<>)/');
    }

    public function test_unclosed_group_in_subroutine_name(): void
    {
        // Cas : (?&name -> pas de parenthèse fermante, mais fin de chaine
        // Doit déclencher "Unexpected token" ou "Expected )"
        $this->expectException(ParserException::class);
        $this->parser->parse('/(?&name/');
    }
}
