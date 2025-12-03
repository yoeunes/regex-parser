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
use RegexParser\Parser;
use RegexParser\Token;
use RegexParser\TokenType;

class ParserReflectionTest extends TestCase
{
    /**
     * This test covers 100% of the private Parser::reconstructTokenValue method
     * which contains a giant switch normally inaccessible.
     */
    public function test_reconstruct_token_value_exhaustive(): void
    {
        $parser = new Parser();
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('reconstructTokenValue');

        // Exhaustive list of all match() cases
        $scenarios = [
            [TokenType::T_LITERAL, 'a', 'a'],
            [TokenType::T_DOT, '.', '.'],
            [TokenType::T_CHAR_TYPE, 'd', '\d'], // Adds backslash
            [TokenType::T_ASSERTION, 'b', '\b'],
            [TokenType::T_KEEP, 'K', '\K'],
            [TokenType::T_OCTAL_LEGACY, '01', '\01'],
            [TokenType::T_LITERAL_ESCAPED, '.', '\.'],
            [TokenType::T_BACKREF, '\1', '\1'],
            [TokenType::T_UNICODE, '\x41', '\x41'],
            [TokenType::T_UNICODE_PROP, 'L', '\pL'], // Short form
            [TokenType::T_UNICODE_PROP, '{Lu}', '\p{Lu}'], // Long form
            [TokenType::T_UNICODE_PROP, '^N', '\p{^N}'], // Negated
            [TokenType::T_POSIX_CLASS, 'alnum', '[[:alnum:]]'],
            [TokenType::T_PCRE_VERB, 'FAIL', '(*FAIL)'],
            [TokenType::T_CALLOUT, '1', '(?C1)'],
            [TokenType::T_GROUP_MODIFIER_OPEN, '(?', '(?'],
            [TokenType::T_COMMENT_OPEN, '(?#', '(?#'],
            [TokenType::T_QUOTE_MODE_START, '\Q', '\Q'],
            [TokenType::T_QUOTE_MODE_END, '\E', '\E'],
            [TokenType::T_EOF, '', ''],
        ];

        foreach ($scenarios as [$type, $value, $expected]) {
            $token = new Token($type, $value, 0);
            $result = $method->invoke($parser, $token);
            $this->assertSame($expected, $result, "Failed reconstruction for token type {$type->name}");
        }
    }

    /**
     * Exhaustively tests the private reconstructTokenValue method via reflection.
     * This method contains a large switch/match that is difficult to fully cover
     * via normal comment analysis.
     */
    public function test_reconstruct_token_value_exhaustive_others(): void
    {
        $parser = new Parser();
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('reconstructTokenValue');
        // $method->setAccessible(true); // Not necessary in modern PHP if invoke() is used, but good to know

        // Scenarios: [TokenType, Token value, Expected result after reconstruction]
        $scenarios = [
            // Simple cases (returns value as is)
            [TokenType::T_LITERAL, 'a', 'a'],
            [TokenType::T_NEGATION, '^', '^'],
            [TokenType::T_RANGE, '-', '-'],
            [TokenType::T_DOT, '.', '.'],
            [TokenType::T_GROUP_OPEN, '(', '('],
            [TokenType::T_GROUP_CLOSE, ')', ')'],
            [TokenType::T_CHAR_CLASS_OPEN, '[', '['],
            [TokenType::T_CHAR_CLASS_CLOSE, ']', ']'],
            [TokenType::T_QUANTIFIER, '+', '+'],
            [TokenType::T_ALTERNATION, '|', '|'],
            [TokenType::T_ANCHOR, '$', '$'],
            [TokenType::T_BACKREF, '\1', '\1'],
            [TokenType::T_G_REFERENCE, '\g{1}', '\g{1}'],
            [TokenType::T_UNICODE, '\x41', '\x41'],
            [TokenType::T_OCTAL, '\o{123}', '\o{123}'],

            // Cases with backslash addition
            [TokenType::T_CHAR_TYPE, 'd', '\d'],
            [TokenType::T_ASSERTION, 'b', '\b'],
            [TokenType::T_KEEP, 'K', '\K'],
            [TokenType::T_OCTAL_LEGACY, '01', '\01'],
            [TokenType::T_LITERAL_ESCAPED, '.', '\.'],

            // Complex cases
            // Unicode Prop: short, long, negation, braces
            [TokenType::T_UNICODE_PROP, 'L', '\pL'],
            [TokenType::T_UNICODE_PROP, '{L}', '\p{L}'], // Already with braces
            [TokenType::T_UNICODE_PROP, 'Lu', '\p{Lu}'], // Long without braces -> adds {}
            [TokenType::T_UNICODE_PROP, '^L', '\p{^L}'], // Negation -> adds {}

            [TokenType::T_POSIX_CLASS, 'alnum', '[[:alnum:]]'],
            [TokenType::T_PCRE_VERB, 'FAIL', '(*FAIL)'],
            [TokenType::T_GROUP_MODIFIER_OPEN, '', '(?'], // Value ignored
            [TokenType::T_COMMENT_OPEN, '', '(?#'],      // Value ignored
            [TokenType::T_QUOTE_MODE_START, '', '\Q'],    // Value ignored
            [TokenType::T_QUOTE_MODE_END, '', '\E'],      // Value ignored
            [TokenType::T_CALLOUT, '"foo"', '(?C"foo")'],
            [TokenType::T_EOF, '', ''],
        ];

        foreach ($scenarios as [$type, $value, $expected]) {
            $token = new Token($type, $value, 0);
            $result = $method->invoke($parser, $token);
            $this->assertSame($expected, $result, "Failed for token {$type->name} with value '$value'");
        }
    }
}
