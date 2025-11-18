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

        // On simule un match où 'v_posix' est manquant
        $matches = ['[[:alnum:]]'];

        $result = $accessor->callPrivateMethod('extractTokenValue', [
            TokenType::T_POSIX_CLASS,
            '[[:alnum:]]',
            $matches
        ]);

        // Doit retourner une chaîne vide grâce au ?? ''
        $this->assertSame('', $result);
    }

    /**
     * Teste le fallback "?? ''" dans normalizeUnicodeProp.
     */
    public function test_normalize_unicode_fallback(): void
    {
        $lexer = new Lexer('');
        $accessor = new LexerAccessor($lexer);

        // Simule un match incomplet sans v1_prop ni v2_prop
        $matches = ['\p{L}'];

        $result = $accessor->callPrivateMethod('normalizeUnicodeProp', [
            '\p{L}',
            $matches
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

        // \@ n'est pas dans la liste (t, n, r, f, v, e)
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

        // Simule \1 mais sans la capture nommée v_backref_num
        $result = $accessor->callPrivateMethod('extractTokenValue', [
            TokenType::T_BACKREF,
            '\1',
            []
        ]);

        $this->assertSame('\1', $result);
    }
}
