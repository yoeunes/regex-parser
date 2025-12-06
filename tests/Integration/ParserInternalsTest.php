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
use RegexParser\Exception\ParserException;
use RegexParser\Parser;
use RegexParser\Regex;
use RegexParser\Tests\TestUtils\ParserAccessor;
use RegexParser\TokenType;

final class ParserInternalsTest extends TestCase
{
    public function test_extract_pattern_throws_on_preg_replace_error(): void
    {
        $regex = Regex::create();

        // extractPatternAndFlags is now on Regex class, use reflection
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Regex is too short');
        $regex->extractPatternAndFlags('/');
    }

    public function test_extract_pattern_regex_too_short(): void
    {
        $regex = Regex::create();

        // Calling public method directly to ensure this specific exception path is hit
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Regex is too short');
        $regex->extractPatternAndFlags('/');
    }

    public function test_extract_pattern_no_closing_delimiter(): void
    {
        $regex = Regex::create();

        // Forces the loop to finish without finding the delimiter
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('No closing delimiter "/" found');
        $regex->extractPatternAndFlags('/abc');
    }

    public function test_consume_literal_throws_on_mismatch(): void
    {
        // Test direct de consumeLiteral pour vérifier le message d'erreur exact
        $parser = new Parser();

        $accessor = new ParserAccessor($parser);

        // On initialise avec un token 'a'
        $accessor->setTokens(['a']);
        $accessor->setPosition(0);

        $this->expectException(ParserException::class);
        // On essaie de consommer 'b' alors que le token courant est 'a'
        $accessor->callPrivateMethod('consumeLiteral', ['b', 'Error expected']);
    }

    public function test_extract_pattern_too_short(): void
    {
        $regex = Regex::create();

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Regex is too short');
        $regex->extractPatternAndFlags('/');
    }

    public function test_consume_literal_throws_exception(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // On initialise avec un token qui n'est pas celui attendu
        $accessor->setTokens(['b']);
        $accessor->setPosition(0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected error message');

        // On demande de consommer 'a', mais on a 'b', ça doit planter
        $accessor->callPrivateMethod('consumeLiteral', ['a', 'Expected error message']);
    }

    public function test_consume_throws_exception_at_end_of_input(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // On simule la fin de flux - setTokens() adds T_EOF automatically
        // So with an empty array, we get only T_EOF at position 0
        $accessor->setTokens([]);
        $accessor->setPosition(0); // Now we're at EOF

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected something at end of input');

        $accessor->callPrivateMethod('consume', [TokenType::T_LITERAL, 'Expected something']);
    }
}
