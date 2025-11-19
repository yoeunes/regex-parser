<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Lexer;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;
use RegexParser\TokenType;

class ReflectionCoverageTest extends TestCase
{
    /**
     * Teste le fallback "empty" de getRandomChar dans SampleGeneratorVisitor.
     * Ce cas est impossible via l'API publique car le visiteur ne passe jamais de tableau vide.
     */
    public function test_sample_generator_get_random_char_empty(): void
    {
        $visitor = new SampleGeneratorVisitor();
        $reflection = new \ReflectionClass($visitor);
        $method = $reflection->getMethod('getRandomChar');

        // Appel direct : getRandomChar([])
        $result = $method->invoke($visitor, []);

        $this->assertSame('?', $result);
    }

    /**
     * Teste le fallback "default" de extractTokenValue dans Lexer.
     * Simule un token T_LITERAL_ESCAPED qui n'est pas dans la liste connue (\t, \n, etc.)
     * pour forcer le 'default => substr($matchedValue, 1)'.
     */
    public function test_lexer_extract_token_value_default_escape(): void
    {
        $lexer = new Lexer('');
        $reflection = new \ReflectionClass($lexer);
        $method = $reflection->getMethod('extractTokenValue');

        // Simule un caractère échappé inconnu, ex: '\@' -> '@'
        $result = $method->invoke($lexer, TokenType::T_LITERAL_ESCAPED, '\@', []);

        $this->assertSame('@', $result);
    }

    /**
     * Teste le fallback "default" global de extractTokenValue.
     * Force un type de token qui n'a pas de logique spécifique.
     */
    public function test_lexer_extract_token_value_global_default(): void
    {
        $lexer = new Lexer('');
        $reflection = new \ReflectionClass($lexer);
        $method = $reflection->getMethod('extractTokenValue');

        // T_LITERAL tombe dans le default
        $result = $method->invoke($lexer, TokenType::T_LITERAL, 'A', []);

        $this->assertSame('A', $result);
    }

    /**
     * Teste le fallback de normalizeUnicodeProp quand les captures sont manquantes.
     */
    public function test_lexer_normalize_unicode_missing_captures(): void
    {
        $lexer = new Lexer('');
        $reflection = new \ReflectionClass($lexer);
        $method = $reflection->getMethod('normalizeUnicodeProp');

        // Pas de v1_prop ni v2_prop dans le tableau matches
        $result = $method->invoke($lexer, '\p{L}', []);

        $this->assertSame('', $result);
    }
}
