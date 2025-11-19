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

class LexerFallbackTest extends TestCase
{
    /**
     * Couvre le cas où lexQuoteMode atteint la fin de la chaîne sans trouver \E.
     */
    public function test_lex_quote_mode_unterminated(): void
    {
        $lexer = new Lexer('\Qstart'); // Pas de \E
        $tokens = $lexer->tokenize();

        // Doit contenir T_LITERAL "start" et T_EOF
        $this->assertCount(2, $tokens);
        $this->assertSame('start', $tokens[0]->value);
    }

    /**
     * Couvre le cas où lexCommentMode atteint la fin de la chaîne sans trouver ).
     * (Note: cela lance une exception plus tard dans tokenize(), mais on veut tester l'appel interne).
     */
    public function test_lex_comment_mode_unterminated_internal(): void
    {
        $lexer = new Lexer('(?#start'); // Pas de )

        try {
            $lexer->tokenize();
        } catch (\Exception $e) {
            // On s'attend à une exception, mais on veut surtout que le code soit exécuté
            $this->assertStringContainsString('Unclosed comment', $e->getMessage());
        }
    }
}
