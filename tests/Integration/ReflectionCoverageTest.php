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
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;
use RegexParser\TokenType;

final class ReflectionCoverageTest extends TestCase
{
    /**
     * Tests the "empty" fallback of getRandomChar in SampleGeneratorVisitor.
     * This case is impossible via the public API as the visitor never passes an empty array.
     */
    public function test_sample_generator_get_random_char_empty(): void
    {
        $visitor = new SampleGeneratorNodeVisitor();
        $reflection = new \ReflectionClass($visitor);
        $method = $reflection->getMethod('getRandomChar');

        // Direct call: getRandomChar([])
        $result = $method->invoke($visitor, []);

        $this->assertSame('?', $result);
    }

    /**
     * Tests the "default" fallback of extractTokenValue in Lexer.
     * Simulates a T_LITERAL_ESCAPED token that is not in the known list (\t, \n, etc.)
     * to force the 'default => substr($matchedValue, 1)'.
     */
    public function test_lexer_extract_token_value_default_escape(): void
    {
        $lexer = new Lexer();
        $lexer->tokenize('');
        $reflection = new \ReflectionClass($lexer);
        $method = $reflection->getMethod('extractTokenValue');

        // Simulates an unknown escaped character, e.g. '\@' -> '@'
        $result = $method->invoke($lexer, TokenType::T_LITERAL_ESCAPED, '\@', []);

        $this->assertSame('@', $result);
    }

    /**
     * Tests the global "default" fallback of extractTokenValue.
     * Forces a token type that has no specific logic.
     */
    public function test_lexer_extract_token_value_global_default(): void
    {
        $lexer = new Lexer();
        $lexer->tokenize('');
        $reflection = new \ReflectionClass($lexer);
        $method = $reflection->getMethod('extractTokenValue');

        // T_LITERAL falls into the default
        $result = $method->invoke($lexer, TokenType::T_LITERAL, 'A', []);

        $this->assertSame('A', $result);
    }

    /**
     * Tests the fallback of normalizeUnicodeProp when captures are missing.
     */
    public function test_lexer_normalize_unicode_missing_captures(): void
    {
        $lexer = new Lexer();
        $lexer->tokenize('');
        $reflection = new \ReflectionClass($lexer);
        $method = $reflection->getMethod('normalizeUnicodeProp');

        // Empty property to hit the fallback path
        $result = $method->invoke($lexer, '\p{}');

        $this->assertSame('', $result);
    }
}
