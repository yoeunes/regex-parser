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

namespace RegexParser\Tests\Parser;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Parser;
use RegexParser\Tests\TestUtils\ParserAccessor;

/**
 * Tests the private utility methods of the Parser class.
 */
class ParserUtilityTest extends TestCase
{
    private ParserAccessor $accessor;

    protected function setUp(): void
    {
        $parser = new Parser();
        $this->accessor = new ParserAccessor($parser);
    }

    public function test_extract_pattern_handles_escaped_delimiter_in_flags(): void
    {
        // Regex: /abc\/def/i
        // Le slash au milieu est échappé et ne doit pas être considéré comme le délimiteur de fin.
        $result = $this->accessor->callPrivateMethod('extractPatternAndFlags', ['/abc\/def/i']);
        assert(is_array($result));
        [$pattern, $flags, $delimiter] = $result;

        $this->assertSame('/', $delimiter);
        $this->assertSame('i', $flags);
        $this->assertSame('abc\/def', $pattern);
    }

    public function test_extract_pattern_handles_alternating_delimiters(): void
    {
        // Regex: (abc)i
        $result = $this->accessor->callPrivateMethod('extractPatternAndFlags', ['(abc)i']);
        assert(is_array($result));
        [$pattern, $flags, $delimiter] = $result;

        $this->assertSame('(', $delimiter);
        $this->assertSame('i', $flags);
        $this->assertSame('abc', $pattern);
    }

    public function test_extract_pattern_throws_on_malformed_flags(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown regex flag(s) found: "!"');

        $this->accessor->callPrivateMethod('extractPatternAndFlags', ['/abc/i!']);
    }

    public function test_parse_group_name_throws_on_missing_name(): void
    {
        // Simuler un état où nous avons consommé '(?<' mais pas le nom
        $this->accessor->setTokens(['>', 'a', ')']); // Tentative de fermer immédiatement
        $this->accessor->setPosition(0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected group name at position');

        // La méthode attend que le délimiteur d'ouverture soit déjà consommé
        $this->accessor->callPrivateMethod('parseGroupName');
    }

    public function test_parse_group_name_with_quotes(): void
    {
        // Simuler: 'name' + '>'
        $tokens = [
            $this->accessor->createToken(\RegexParser\TokenType::T_LITERAL, "'", 1),
            $this->accessor->createToken(\RegexParser\TokenType::T_LITERAL, 'test_name', 2),
            $this->accessor->createToken(\RegexParser\TokenType::T_LITERAL, "'", 11),
            $this->accessor->createToken(\RegexParser\TokenType::T_LITERAL, '>', 12),
        ];
        $this->accessor->setTokens($tokens);
        $this->accessor->setPosition(0);

        // parseGroupName handles the quotes itself
        $name = $this->accessor->callPrivateMethod('parseGroupName');
        $this->assertSame('test_name', $name);

        // Vérifie que l'accolade fermante (ou >) est toujours là pour la consommation
        $this->assertSame('>', $this->accessor->current()->value);
    }

    public function test_consume_literal_throws_on_type_mismatch(): void
    {
        // Token actuel est T_LITERAL with empty value, attend T_LITERAL with value 'a'
        $this->accessor->setTokens(['']);
        $this->accessor->setPosition(0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected error at position 0 (found literal with value )');

        $this->accessor->callPrivateMethod('consumeLiteral', ['a', 'Expected error']);
    }

    public function test_check_literal_returns_false_at_eof(): void
    {
        $this->accessor->setTokens(['']);
        $this->accessor->setPosition(0);

        $result = $this->accessor->callPrivateMethod('checkLiteral', ['a']);
        $this->assertFalse($result);
    }
}
