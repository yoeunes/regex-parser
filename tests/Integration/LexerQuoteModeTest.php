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

class LexerQuoteModeTest extends TestCase
{
    /**
     * Teste \Q au milieu sans \E à la fin.
     */
    public function test_quote_mode_mid_string_unterminated(): void
    {
        $lexer = new Lexer('a\Qbc');
        $tokens = $lexer->tokenize();

        // a (LITERAL), bc (LITERAL via quote mode), EOF
        $this->assertCount(3, $tokens);
        $this->assertSame('bc', $tokens[1]->value);
    }

    /**
     * Teste \Q...\E où ... est vide.
     * Cela peut retourner null dans consumeQuoteMode et doit être géré.
     */
    public function test_quote_mode_empty_content(): void
    {
        $lexer = new Lexer('a\Q\Eb');
        $tokens = $lexer->tokenize();

        // a, b, EOF. (Le vide entre \Q et \E est ignoré)
        $this->assertCount(3, $tokens);
        $this->assertSame('a', $tokens[0]->value);
        $this->assertSame('b', $tokens[1]->value);
    }
}
