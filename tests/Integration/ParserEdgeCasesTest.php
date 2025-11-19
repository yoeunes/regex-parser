<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Parser;
use RegexParser\Tests\TestUtils\ParserAccessor;
use RegexParser\TokenType;

class ParserEdgeCasesTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function test_consume_throws_specific_error_message(): void
    {
        // On utilise l'accesseur pour injecter un état incohérent
        $accessor = new ParserAccessor($this->parser);
        // On met un token T_LITERAL 'a'
        $accessor->setTokens(['a']);
        $accessor->setPosition(0);

        $this->expectException(ParserException::class);
        // On essaie de consommer un T_DOT, ça doit planter
        $accessor->callPrivateMethod('consume', [TokenType::T_DOT, 'Custom Error']);
    }

    public function test_quantifier_on_anchor_throws(): void
    {
        // ^+ est invalide
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Quantifier "+" cannot be applied to assertion or verb "^"');
        $this->parser->parse('/^+/');
    }

    public function test_quantifier_on_verb_throws(): void
    {
        // (*FAIL)+ est invalide
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Quantifier "+" cannot be applied to assertion or verb "(*FAIL)"');
        $this->parser->parse('/(*FAIL)+/');
    }

    public function test_conditional_invalid_condition(): void
    {
        // (?(?~)...) -> ?~ n'est pas une condition valide (ni lookaround, ni assertion)
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid conditional condition');
        $this->parser->parse('/(?(?~a)b)/');
    }

    public function test_group_modifier_invalid_syntax(): void
    {
        // (??) est invalide
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid group modifier syntax');
        $this->parser->parse('/(??)/');
    }

    public function test_unclosed_group_in_subroutine(): void
    {
        $this->expectException(ParserException::class);
        // (?&name sans fermer
        $this->parser->parse('/(?&name/');
    }
}
