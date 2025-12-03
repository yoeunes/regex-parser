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
use RegexParser\Lexer;
use RegexParser\Regex;

class LexerQuoteModeTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    /**
     * Teste \Q au milieu sans \E à la fin.
     */
    public function test_quote_mode_mid_string_unterminated(): void
    {
        $tokens = $this->regexService->getLexer()->tokenize('a\Qbc')->getTokens();

        // Now emits: a (LITERAL), \Q (T_QUOTE_MODE_START), bc (LITERAL via quote mode), EOF
        $this->assertCount(4, $tokens);
        $this->assertSame('a', $tokens[0]->value);
        $this->assertSame('\Q', $tokens[1]->value);
        $this->assertSame('bc', $tokens[2]->value);
    }

    /**
     * Teste \Q...\E où ... est vide.
     * Cela peut retourner null dans consumeQuoteMode et doit être géré.
     */
    public function test_quote_mode_empty_content(): void
    {
        $tokens = $this->regexService->getLexer()->tokenize('a\Q\Eb')->getTokens();

        // Now emits: a, \Q, \E, b, EOF
        $this->assertCount(5, $tokens);
        $this->assertSame('a', $tokens[0]->value);
        $this->assertSame('\Q', $tokens[1]->value);
        $this->assertSame('\E', $tokens[2]->value);
        $this->assertSame('b', $tokens[3]->value);
    }
}
