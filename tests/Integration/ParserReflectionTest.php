<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Parser;
use RegexParser\Token;
use RegexParser\TokenType;

class ParserReflectionTest extends TestCase
{
    /**
     * Ce test couvre 100% de la méthode privée Parser::reconstructTokenValue
     * qui contient un switch géant normalement inaccessible.
     */
    public function test_reconstruct_token_value_exhaustive(): void
    {
        $parser = new Parser();
        $reflection = new \ReflectionClass($parser);
        $method = $reflection->getMethod('reconstructTokenValue');

        // Liste exhaustive de tous les cas du match()
        $scenarios = [
            [TokenType::T_LITERAL, 'a', 'a'],
            [TokenType::T_DOT, '.', '.'],
            [TokenType::T_CHAR_TYPE, 'd', '\d'], // Ajoute le backslash
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
}
