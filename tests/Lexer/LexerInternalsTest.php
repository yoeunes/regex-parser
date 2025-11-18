<?php

declare(strict_types=1);

namespace RegexParser\Tests\Lexer;

use PHPUnit\Framework\TestCase;
use RegexParser\Lexer;
use RegexParser\TokenType;
use RegexParser\Tests\TestUtils\LexerAccessor;

/**
 * Tests de "boîte blanche" pour forcer l'exécution des branches défensives
 * (coalescence nulle, switch default) inaccessibles via le parsing normal.
 */
class LexerInternalsTest extends TestCase
{
    /**
     * Teste le fallback "?? ''" dans l'extraction des classes POSIX.
     * Normalement, la regex garantit que v_posix est set, mais on teste la robustesse PHP.
     */
    public function test_extract_posix_fallback(): void
    {
        $lexer = new Lexer('');
        $accessor = new LexerAccessor($lexer);

        // Simule un match incomplet pour forcer le `?? ''`
        $result = $accessor->callPrivateMethod('extractTokenValue', [
            TokenType::T_POSIX_CLASS,
            '[[:alnum:]]',
            [] // Pas de clé 'v_posix'
        ]);

        $this->assertSame('', $result);
    }

    /**
     * Teste le fallback "?? ''" dans normalizeUnicodeProp.
     */
    public function test_normalize_unicode_fallback(): void
    {
        $lexer = new Lexer('');
        $accessor = new LexerAccessor($lexer);

        // Simule un match incomplet pour forcer le `?? ''`
        $result = $accessor->callPrivateMethod('normalizeUnicodeProp', [
            '\p{L}',
            [] // Pas de clés v1_prop ni v2_prop
        ]);

        $this->assertSame('', $result);
    }

    /**
     * Teste le cas `default` du switch T_LITERAL_ESCAPED avec un caractère bizarre.
     * Les tests normaux couvrent \t, \n etc. On veut tester le fallback `substr($val, 1)`.
     */
    public function test_extract_literal_escaped_default(): void
    {
        $lexer = new Lexer('');
        $accessor = new LexerAccessor($lexer);

        // Teste un caractère échappé qui n'est pas spécial (ex: \@)
        // Cela force le `default => substr(...)`
        $result = $accessor->callPrivateMethod('extractTokenValue', [
            TokenType::T_LITERAL_ESCAPED,
            '\@',
            []
        ]);

        $this->assertSame('@', $result);
    }

    /**
     * Teste le fallback des backreferences si v_backref_num manque.
     */
    public function test_extract_backref_fallback(): void
    {
        $lexer = new Lexer('');
        $accessor = new LexerAccessor($lexer);

        // Force le `??` pour le numéro de backref
        $result = $accessor->callPrivateMethod('extractTokenValue', [
            TokenType::T_BACKREF,
            '\1',
            [] // Pas de clé 'v_backref_num'
        ]);

        $this->assertSame('\1', $result);
    }

    public function test_extract_token_value_default_case(): void
    {
        $lexer = new Lexer('');
        $accessor = new \RegexParser\Tests\TestUtils\LexerAccessor($lexer);

        // Cas où le type est T_LITERAL (le default global du switch)
        $val = $accessor->callPrivateMethod('extractTokenValue', [
            \RegexParser\TokenType::T_LITERAL,
            'X',
            []
        ]);
        $this->assertSame('X', $val);

        // Cas où le type est T_LITERAL_ESCAPED mais le char n'est pas spécial (le default du match interne)
        $val = $accessor->callPrivateMethod('extractTokenValue', [
            \RegexParser\TokenType::T_LITERAL_ESCAPED,
            '\@', // @ n'est pas t, n, r, etc.
            []
        ]);
        // Le code fait substr($val, 1) -> "@"
        $this->assertSame('@', $val);
    }
}
