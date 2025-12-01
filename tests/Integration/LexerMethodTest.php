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
use RegexParser\Tests\TestUtils\LexerAccessor;
use RegexParser\TokenType;

class LexerMethodTest extends TestCase
{
    /**
     * Teste le fallback par défaut de extractTokenValue pour un type inconnu.
     */
    public function test_extract_token_value_unknown_type(): void
    {
        $lexer = new Lexer();
        $lexer->tokenize('');
        $accessor = new LexerAccessor($lexer);

        // Type qui n'a pas de logique spécifique -> retourne la valeur brute
        $val = $accessor->callPrivateMethod('extractTokenValue', [
            TokenType::T_LITERAL, // Cas default
            'TEST_VALUE',
            []
        ]);
        $this->assertSame('TEST_VALUE', $val);
    }

    /**
     * Teste le fallback de T_BACKREF si la clé 'v_backref_num' est absente du tableau de matches.
     */
    public function test_extract_token_value_backref_fallback(): void
    {
        $lexer = new Lexer();
        $lexer->tokenize('');
        $accessor = new LexerAccessor($lexer);

        $val = $accessor->callPrivateMethod('extractTokenValue', [
            TokenType::T_BACKREF,
            '\99',
            [] // Tableau vide -> force le ?? null
        ]);
        $this->assertSame('\99', $val);
    }

    /**
     * Teste le fallback de normalizeUnicodeProp si les clés de capture sont absentes.
     */
    public function test_normalize_unicode_prop_fallback(): void
    {
        $lexer = new Lexer();
        $lexer->tokenize('');
        $accessor = new LexerAccessor($lexer);

        $val = $accessor->callPrivateMethod('normalizeUnicodeProp', [
            '\p{L}',
            [] // Tableau vide -> force le ?? ''
        ]);
        $this->assertSame('', $val); // Retourne la propriété vide
    }
}
