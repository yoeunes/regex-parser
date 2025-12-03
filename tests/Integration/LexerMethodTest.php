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
use RegexParser\Tests\TestUtils\LexerAccessor;
use RegexParser\TokenType;

class LexerMethodTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    /**
     * Teste le fallback par défaut de extractTokenValue pour un type inconnu.
     * This case is normally unreachable as the main Regex filters tokens,
     * but for 100% coverage we must force it via Reflection.
     */
    public function test_extract_token_value_unknown_type(): void
    {
        $lexer = $this->regexService->getLexer();
        $lexer->tokenize('');
        $accessor = new LexerAccessor($lexer);

        // Force a token that has no specific extraction logic
        // (e.g. T_LITERAL goes to the default)
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
        $lexer = $this->regexService->getLexer();
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
        $lexer = $this->regexService->getLexer();
        $lexer->tokenize('');
        $accessor = new LexerAccessor($lexer);

        $val = $accessor->callPrivateMethod('normalizeUnicodeProp', [
            '\p{L}',
            [] // Tableau vide -> force le ?? ''
        ]);
        $this->assertSame('', $val); // Retourne la propriété vide
    }
}
