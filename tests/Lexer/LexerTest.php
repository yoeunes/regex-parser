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

namespace RegexParser\Tests\Lexer;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\LexerException;
use RegexParser\Lexer;
use RegexParser\TokenType;

class LexerTest extends TestCase
{
    public function test_tokenize_simple_literal(): void
    {
        $lexer = new Lexer('foo'); // No delimiters
        $tokens = $lexer->tokenize();

        // f o o EOF = 4 tokens
        $this->assertCount(4, $tokens);
        $this->assertSame(TokenType::T_LITERAL, $tokens[0]->type);
        $this->assertSame('f', $tokens[0]->value);
        $this->assertSame(TokenType::T_LITERAL, $tokens[1]->type);
        $this->assertSame('o', $tokens[1]->value);
        $this->assertSame(TokenType::T_LITERAL, $tokens[2]->type);
        $this->assertSame('o', $tokens[2]->value);
        $this->assertSame(TokenType::T_EOF, $tokens[3]->type);
    }

    public function test_tokenize_multibyte_literal(): void
    {
        $lexer = new Lexer('fôô'); // No delimiters
        $tokens = $lexer->tokenize();

        // f ô ô EOF = 4 tokens
        $this->assertCount(4, $tokens);
        $this->assertSame(TokenType::T_LITERAL, $tokens[0]->type);
        $this->assertSame('f', $tokens[0]->value);
        $this->assertSame(TokenType::T_LITERAL, $tokens[1]->type);
        $this->assertSame('ô', $tokens[1]->value);
        $this->assertSame(TokenType::T_LITERAL, $tokens[2]->type);
        $this->assertSame('ô', $tokens[2]->value);
    }

    public function test_tokenize_group_and_quantifier(): void
    {
        $lexer = new Lexer('(bar)?'); // No delimiters
        $tokens = $lexer->tokenize();

        $expected = [
            TokenType::T_GROUP_OPEN,
            TokenType::T_LITERAL, // b
            TokenType::T_LITERAL, // a
            TokenType::T_LITERAL, // r
            TokenType::T_GROUP_CLOSE,
            TokenType::T_QUANTIFIER, // ?
            TokenType::T_EOF,
        ];
        $this->assertCount(\count($expected), $tokens);
        $this->assertSame('?', $tokens[5]->value);

        foreach ($expected as $i => $type) {
            $this->assertSame($type, $tokens[$i]->type);
        }
    }

    public function test_tokenize_alternation(): void
    {
        $lexer = new Lexer('foo|bar'); // No delimiters
        $tokens = $lexer->tokenize();
        // f o o | b a r EOF = 8 tokens
        $this->assertCount(8, $tokens);
        $this->assertSame(TokenType::T_ALTERNATION, $tokens[3]->type);
    }

    public function test_tokenize_custom_quantifier(): void
    {
        $lexer = new Lexer('a{2,4}'); // No delimiters
        $tokens = $lexer->tokenize();

        // a {2,4} EOF = 3 tokens
        $this->assertCount(3, $tokens);
        $this->assertSame(TokenType::T_LITERAL, $tokens[0]->type);
        $this->assertSame(TokenType::T_QUANTIFIER, $tokens[1]->type);
        $this->assertSame('{2,4}', $tokens[1]->value);
    }

    public function test_tokenize_invalid_quantifier_as_literal(): void
    {
        $lexer = new Lexer('a{b}'); // No delimiters
        $tokens = $lexer->tokenize();
        // a { b } EOF = 5 tokens
        $this->assertCount(5, $tokens);
        $this->assertSame(TokenType::T_LITERAL, $tokens[1]->type);
        $this->assertSame('{', $tokens[1]->value);
        $this->assertSame(TokenType::T_LITERAL, $tokens[2]->type);
        $this->assertSame('b', $tokens[2]->value);
        $this->assertSame(TokenType::T_LITERAL, $tokens[3]->type);
        $this->assertSame('}', $tokens[3]->value);
    }

    public function test_tokenize_escaped_meta_char(): void
    {
        $lexer = new Lexer('\\(a\\*\\)'); // No delimiters
        $tokens = $lexer->tokenize();

        // ( a * ) EOF = 5 tokens
        $this->assertCount(5, $tokens);

        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[0]->type); // \(
        $this->assertSame('(', $tokens[0]->value);

        $this->assertSame(TokenType::T_LITERAL, $tokens[1]->type); // a
        $this->assertSame('a', $tokens[1]->value);

        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[2]->type); // \*
        $this->assertSame('*', $tokens[2]->value);

        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[3]->type); // \)
        $this->assertSame(')', $tokens[3]->value);

        $this->assertSame(TokenType::T_EOF, $tokens[4]->type);
    }

    public function test_tokenize_char_types_and_dot(): void
    {
        $lexer = new Lexer('.\d\s\w\D\S\W'); // No delimiters
        $tokens = $lexer->tokenize();

        $expected = [
            TokenType::T_DOT,
            TokenType::T_CHAR_TYPE, // \d
            TokenType::T_CHAR_TYPE, // \s
            TokenType::T_CHAR_TYPE, // \w
            TokenType::T_CHAR_TYPE, // \D
            TokenType::T_CHAR_TYPE, // \S
            TokenType::T_CHAR_TYPE, // \W
            TokenType::T_EOF,
        ];
        $this->assertCount(\count($expected), $tokens);

        foreach ($expected as $i => $type) {
            $this->assertSame($type, $tokens[$i]->type);
        }

        $this->assertSame('d', $tokens[1]->value);
        $this->assertSame('W', $tokens[6]->value);
    }

    public function test_tokenize_anchors(): void
    {
        $lexer = new Lexer('^foo$'); // No delimiters
        $tokens = $lexer->tokenize();

        // ^ f o o $ EOF = 6 tokens
        $this->assertCount(6, $tokens);
        $this->assertSame(TokenType::T_ANCHOR, $tokens[0]->type);
        $this->assertSame('^', $tokens[0]->value);
        $this->assertSame(TokenType::T_ANCHOR, $tokens[4]->type);
        $this->assertSame('$', $tokens[4]->value);
    }

    public function test_tokenize_assertions(): void
    {
        $lexer = new Lexer('\\Afoo\\z\\b\\G\\B'); // No delimiters
        $tokens = $lexer->tokenize();

        $this->assertSame(TokenType::T_ASSERTION, $tokens[0]->type);
        $this->assertSame('A', $tokens[0]->value);
        // ... f o o
        $this->assertSame(TokenType::T_ASSERTION, $tokens[4]->type);
        $this->assertSame('z', $tokens[4]->value);
        $this->assertSame(TokenType::T_ASSERTION, $tokens[5]->type);
        $this->assertSame('b', $tokens[5]->value);
        $this->assertSame(TokenType::T_ASSERTION, $tokens[6]->type);
        $this->assertSame('G', $tokens[6]->value);
        $this->assertSame(TokenType::T_ASSERTION, $tokens[7]->type);
        $this->assertSame('B', $tokens[7]->value);
    }

    public function test_tokenize_unicode_prop(): void
    {
        $lexer = new Lexer('\\p{L}\\P{^L}\\pL'); // No delimiters
        $tokens = $lexer->tokenize();

        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[0]->type);
        $this->assertSame('L', $tokens[0]->value); // \p{L}
        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[1]->type);
        $this->assertSame('L', $tokens[1]->value); // \P{^L} - double negation cancels out
        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[2]->type);
        $this->assertSame('L', $tokens[2]->value); // \pL
    }

    public function test_tokenize_octal(): void
    {
        $lexer = new Lexer('\\o{777}'); // No delimiters
        $tokens = $lexer->tokenize();

        $this->assertSame(TokenType::T_OCTAL, $tokens[0]->type);
        $this->assertSame('\\o{777}', $tokens[0]->value);
    }

    public function test_throws_on_trailing_backslash(): void
    {
        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Trailing backslash');
        $lexer = new Lexer('foo\\'); // No delimiters
        $lexer->tokenize();
    }

    /**
     * This test validates that the internal regex constants of the Lexer
     * are valid PCRE patterns and compile without errors.
     */
    public function test_validate_regex_constants(): void
    {
        // Use reflection to access private constants
        $reflection = new \ReflectionClass(Lexer::class);
        $consts = $reflection->getConstants();

        $this->assertArrayHasKey('REGEX_OUTSIDE', $consts, 'Lexer class must define REGEX_OUTSIDE');
        $this->assertArrayHasKey('REGEX_INSIDE', $consts, 'Lexer class must define REGEX_INSIDE');

        $this->assertIsString($consts['REGEX_OUTSIDE']);
        $this->assertIsString($consts['REGEX_INSIDE']);

        // preg_match() will return false if the pattern fails to compile
        $this->assertNotFalse(
            @preg_match($consts['REGEX_OUTSIDE'], ''),
            'Lexer::REGEX_OUTSIDE failed to compile.',
        );

        $this->assertNotFalse(
            @preg_match($consts['REGEX_INSIDE'], ''),
            'Lexer::REGEX_INSIDE failed to compile.',
        );
    }

    public function test_tokenize_inside_char_class_range_negation_literals(): void
    {
        // Tests context-sensitive tokens: ^ (negation), - (range), and ] (literal)
        $lexer = new Lexer('[^a-z-]]');
        $tokens = $lexer->tokenize();

        $this->assertSame(TokenType::T_CHAR_CLASS_OPEN, $tokens[0]->type);
        $this->assertSame(TokenType::T_NEGATION, $tokens[1]->type); // ^ at start
        $this->assertSame(TokenType::T_LITERAL, $tokens[2]->type); // a
        $this->assertSame(TokenType::T_RANGE, $tokens[3]->type); // - in middle
        $this->assertSame(TokenType::T_LITERAL, $tokens[4]->type); // z
        $this->assertSame(TokenType::T_RANGE, $tokens[5]->type); // - in middle (literal if last, but here it's followed by ])
        $this->assertSame(TokenType::T_CHAR_CLASS_CLOSE, $tokens[6]->type); // ] at end
        $this->assertSame(TokenType::T_LITERAL, $tokens[7]->type); // Trailing ] (literal because of position logic)
    }

    public function test_tokenize_char_class_literal_at_start(): void
    {
        // [^]a] - literal ']' at start
        $lexer = new Lexer('[^]a]');
        $tokens = $lexer->tokenize();

        $this->assertSame(TokenType::T_NEGATION, $tokens[1]->type);
        $this->assertSame(TokenType::T_LITERAL, $tokens[2]->type); // ']' as literal
    }

    public function test_tokenize_posix_class(): void
    {
        $lexer = new Lexer('[[:alnum:]]');
        $tokens = $lexer->tokenize();

        $this->assertSame(TokenType::T_POSIX_CLASS, $tokens[1]->type);
        $this->assertSame('alnum', $tokens[1]->value);
    }

    public function test_tokenize_unclosed_char_class_error_with_end_of_input(): void
    {
        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Unclosed character class "]" at end of input.');
        $lexer = new Lexer('[a');
        $lexer->tokenize();
    }

    public function test_tokenize_quote_mode(): void
    {
        $lexer = new Lexer('\Q*+.\Efoo');
        $tokens = $lexer->tokenize();

        // Should have skipped \Q and \E and merged *+. into a single literal
        $this->assertSame(TokenType::T_LITERAL, $tokens[0]->type);
        $this->assertSame('*+.', $tokens[0]->value);
        $this->assertSame(TokenType::T_LITERAL, $tokens[1]->type);
        $this->assertSame('f', $tokens[1]->value);
        $this->assertSame(TokenType::T_EOF, $tokens[4]->type);
    }
}
