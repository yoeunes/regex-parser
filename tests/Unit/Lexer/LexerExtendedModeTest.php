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

namespace RegexParser\Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Lexer;
use RegexParser\Regex;
use RegexParser\TokenType;

final class LexerExtendedModeTest extends TestCase
{
    #[Test]
    public function test_bracket_in_extended_comment_does_not_open_a_character_class(): void
    {
        $tokens = (new Lexer())->tokenize("a # [ x\nb", 'x')->getTokens();

        $types = array_map(static fn ($token) => $token->type, $tokens);
        $this->assertNotContains(TokenType::T_CHAR_CLASS_OPEN, $types);

        $reconstructed = implode('', array_map(static fn ($token) => $token->value, $tokens));
        $this->assertSame("a # [ x\nb", $reconstructed);
    }

    #[Test]
    public function test_extended_comment_runs_to_end_of_pattern(): void
    {
        $tokens = (new Lexer())->tokenize('a # [ unterminated', 'x')->getTokens();

        $types = array_map(static fn ($token) => $token->type, $tokens);
        $this->assertNotContains(TokenType::T_CHAR_CLASS_OPEN, $types);
    }

    #[Test]
    public function test_hash_is_literal_without_the_x_flag(): void
    {
        $tokens = (new Lexer())->tokenize('a#[b]', '')->getTokens();

        $types = array_map(static fn ($token) => $token->type, $tokens);
        $this->assertContains(TokenType::T_CHAR_CLASS_OPEN, $types);
    }

    #[Test]
    public function test_hash_inside_a_character_class_is_literal_under_x(): void
    {
        $tokens = (new Lexer())->tokenize('[a#b]c', 'x')->getTokens();

        $types = array_map(static fn ($token) => $token->type, $tokens);
        $this->assertContains(TokenType::T_CHAR_CLASS_CLOSE, $types);
    }

    /**
     * Patterns PCRE compiles happily; RegexParser used to reject the ones
     * whose /x comment holds an unbalanced '[' or '('.
     */
    #[Test]
    #[DataProvider('provideExtendedPatterns')]
    public function test_extended_patterns_are_accepted(string $pattern): void
    {
        $this->assertNotFalse(@preg_match($pattern, ''), 'PCRE must accept the pattern');

        $result = Regex::create()->validate($pattern);

        $this->assertTrue($result->isValid, $result->error ?? '');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideExtendedPatterns(): iterable
    {
        yield 'opening bracket in comment' => ["/a # [ x\nb/x"];
        yield 'closing bracket in comment' => ["/a # ] x\nb/x"];
        yield 'both brackets in comment' => ["/a # [ or ]\nb/x"];
        yield 'unbalanced parenthesis in comment' => ["/a # (unbalanced\nb/x"];
        yield 'comment without trailing newline' => ['/a # trailing/x'];
        yield 'escaped hash is not a comment' => ['/a\\#b/x'];
        yield 'hash inside character class' => ['/[a#b]/x'];
        yield 'drupal token scanner' => ["/\n      \\[             # [ - pattern start\n      ([^\\s\\[\\]:]+)  # match \$type not containing whitespace : [ or ]\n      :              # : - separator\n      ([^\\[\\]]+)     # match \$name not containing [ or ]\n      \\]             # ] - pattern end\n      /x"];
    }
}
