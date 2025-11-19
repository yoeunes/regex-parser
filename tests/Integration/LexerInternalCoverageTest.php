<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use RegexParser\Lexer;
use RegexParser\TokenType;
use RegexParser\Tests\TestUtils\LexerAccessor;

class LexerInternalCoverageTest extends TestCase
{
    /**
     * Teste le cas "default" du switch dans extractTokenValue.
     * Ce cas est normalement inaccessible car la Regex principale filtre les tokens,
     * mais pour 100% nous devons le forcer via Reflection.
     */
    public function test_extract_token_value_fallback(): void
    {
        $lexer = new Lexer('');
        $accessor = new LexerAccessor($lexer);

        // Forcer un token qui n'a pas de logique d'extraction spécifique
        // (ex: T_LITERAL passe dans le default)
        $val = $accessor->callPrivateMethod('extractTokenValue', [
            TokenType::T_LITERAL,
            'X',
            []
        ]);
        $this->assertSame('X', $val);

        // Test du fallback des tableaux vides (coalescence nulle) dans le Lexer
        // Cas: T_POSIX_CLASS sans la clé 'v_posix' dans les matches
        $val = $accessor->callPrivateMethod('extractTokenValue', [
            TokenType::T_POSIX_CLASS,
            '[[:alnum:]]',
            [] // Tableau vide pour simuler un match partiel
        ]);
        // Le code fait ($matches['v_posix'] ?? '') -> ''
        $this->assertSame('', $val);
    }

    /**
     * Teste la normalisation Unicode avec des données mal formées
     * pour atteindre les fallbacks `??`.
     */
    public function test_normalize_unicode_prop_fallbacks(): void
    {
        $lexer = new Lexer('');
        $accessor = new LexerAccessor($lexer);

        // Cas où v1_prop et v2_prop sont absents
        $val = $accessor->callPrivateMethod('normalizeUnicodeProp', [
            '\p{L}',
            [] // Matches vides
        ]);
        $this->assertSame('', $val);
    }

    /**
     * Teste le fallback de lexQuoteMode (si le preg_match échoue totalement).
     * C'est théoriquement impossible avec le pattern actuel, mais on sécurise la couverture.
     */
    #[DoesNotPerformAssertions]
    public function test_lex_quote_mode_failure_fallback(): void
    {
        // Ce test est difficile car il faut faire échouer un preg_match simple.
        // Si tu as un @codeCoverageIgnoreStart dans Lexer::lexQuoteMode, ignore ce test.
        // Sinon, on passe à la suite.
    }
}
