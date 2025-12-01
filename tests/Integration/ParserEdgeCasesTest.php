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
use RegexParser\Regex;
use RegexParser\Tests\TestUtils\ParserAccessor;
use RegexParser\TokenType;

class ParserEdgeCasesTest extends TestCase
{
    private Regex $regex;

    private Parser $parser;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
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
        $this->regex->parse('/^+/');
    }

    public function test_quantifier_on_verb_throws(): void
    {
        // (*FAIL)+ est invalide
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Quantifier "+" cannot be applied to assertion or verb "(*FAIL)"');
        $this->regex->parse('/(*FAIL)+/');
    }

    public function test_conditional_invalid_condition(): void
    {
        // (?(?~)...) -> ?~ n'est pas une condition valide (ni lookaround, ni assertion)
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid conditional condition');
        $this->regex->parse('/(?(?~a)b)/');
    }

    public function test_group_modifier_invalid_syntax(): void
    {
        // (??) est invalide
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid group modifier syntax');
        $this->regex->parse('/(??)/');
    }

    public function test_unclosed_group_in_subroutine(): void
    {
        $this->expectException(ParserException::class);
        // (?&name sans fermer
        $this->regex->parse('/(?&name/');
    }
}
