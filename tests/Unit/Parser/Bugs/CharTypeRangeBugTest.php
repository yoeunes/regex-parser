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

namespace RegexParser\Tests\Unit\Parser\Bugs;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\RangeNode;
use RegexParser\Regex;

/**
 * Regression tests for the CharType range bug in the Parser.
 *
 * Bug description:
 * The parser was throwing an exception when encountering a hyphen immediately
 * following a Character Type (like \w, \d, \s) inside a character class.
 *
 * Example:
 * - Input: /[\w-_]/
 * - Error: "Invalid range: ranges must be between literal characters... Found RegexParser\Node\CharTypeNode..."
 *
 * Analysis:
 * The parser was interpreting the `-` as a range operator trying to create a range
 * starting from `\w`. In PCRE, `\w` cannot be the start of a range. If a hyphen
 * follows a CharType, it should be treated as a **literal hyphen**.
 *
 * Expected behavior after fix:
 * /[\w-_]/ should parse to a CharClassNode containing:
 * 1. CharTypeNode (\w)
 * 2. LiteralNode (-)
 * 3. LiteralNode (_)
 */
final class CharTypeRangeBugTest extends TestCase
{
    private Regex $regex;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
    }

    /**
     * Test that /[\w-_]/ parses correctly with hyphen as literal.
     */
    public function test_chartype_followed_by_hyphen_parses_as_literal(): void
    {
        $ast = $this->regex->parse('/[\w-_]/');

        $this->assertInstanceOf(CharClassNode::class, $ast->pattern);

        $expression = $ast->pattern->expression;
        $this->assertInstanceOf(AlternationNode::class, $expression);
        $this->assertCount(3, $expression->alternatives);

        // Child 0: CharTypeNode (\w)
        $this->assertInstanceOf(CharTypeNode::class, $expression->alternatives[0]);
        $this->assertSame('w', $expression->alternatives[0]->value);

        // Child 1: LiteralNode (-)
        $this->assertInstanceOf(LiteralNode::class, $expression->alternatives[1]);
        $this->assertSame('-', $expression->alternatives[1]->value);

        // Child 2: LiteralNode (_)
        $this->assertInstanceOf(LiteralNode::class, $expression->alternatives[2]);
        $this->assertSame('_', $expression->alternatives[2]->value);
    }

    /**
     * Test various CharType patterns followed by hyphen.
     */
    #[DataProvider('charTypeHyphenProvider')]
    public function test_various_chartypes_followed_by_hyphen(string $pattern, string $charTypeValue): void
    {
        $ast = $this->regex->parse($pattern);

        $this->assertInstanceOf(CharClassNode::class, $ast->pattern);

        $expression = $ast->pattern->expression;
        $this->assertInstanceOf(AlternationNode::class, $expression);
        $this->assertGreaterThanOrEqual(2, \count($expression->alternatives));

        // First child should be CharTypeNode
        $this->assertInstanceOf(CharTypeNode::class, $expression->alternatives[0]);
        $this->assertSame($charTypeValue, $expression->alternatives[0]->value);

        // Second child should be LiteralNode (hyphen)
        $this->assertInstanceOf(LiteralNode::class, $expression->alternatives[1]);
        $this->assertSame('-', $expression->alternatives[1]->value);
    }

    /**
     * Data provider for various CharType patterns.
     *
     * @return \Iterator<string, array{0: string, 1: string}>
     */
    public static function charTypeHyphenProvider(): \Iterator
    {
        yield 'word character \w followed by hyphen' => ['/[\w-a]/', 'w'];
        yield 'digit \d followed by hyphen' => ['/[\d-a]/', 'd'];
        yield 'whitespace \s followed by hyphen' => ['/[\s-a]/', 's'];
        yield 'non-word \W followed by hyphen' => ['/[\W-a]/', 'W'];
        yield 'non-digit \D followed by hyphen' => ['/[\D-a]/', 'D'];
        yield 'non-whitespace \S followed by hyphen' => ['/[\S-a]/', 'S'];
        yield 'horizontal whitespace \h followed by hyphen' => ['/[\h-a]/', 'h'];
        yield 'vertical whitespace \v followed by hyphen' => ['/[\v-a]/', 'v'];
    }

    /**
     * Test that valid ranges still work correctly.
     */
    public function test_valid_literal_ranges_still_work(): void
    {
        $ast = $this->regex->parse('/[a-z]/');

        $this->assertInstanceOf(CharClassNode::class, $ast->pattern);
        // Should be a RangeNode, not separate literals
        $this->assertInstanceOf(RangeNode::class, $ast->pattern->expression);
    }

    /**
     * Test mixed CharType and valid range in same character class.
     */
    public function test_mixed_chartype_and_valid_range(): void
    {
        $ast = $this->regex->parse('/[\w-_a-z]/');

        $this->assertInstanceOf(CharClassNode::class, $ast->pattern);

        $expression = $ast->pattern->expression;
        $this->assertInstanceOf(AlternationNode::class, $expression);

        // Should have: CharTypeNode(\w), LiteralNode(-), LiteralNode(_), RangeNode(a-z)
        $this->assertCount(4, $expression->alternatives);

        $this->assertInstanceOf(CharTypeNode::class, $expression->alternatives[0]);
        $this->assertInstanceOf(LiteralNode::class, $expression->alternatives[1]);
        $this->assertSame('-', $expression->alternatives[1]->value);
        $this->assertInstanceOf(LiteralNode::class, $expression->alternatives[2]);
        $this->assertSame('_', $expression->alternatives[2]->value);
        $this->assertInstanceOf(RangeNode::class, $expression->alternatives[3]);
    }

    /**
     * Test that hyphen at end of character class is still literal.
     */
    public function test_trailing_hyphen_is_literal(): void
    {
        $ast = $this->regex->parse('/[abc-]/');

        $this->assertInstanceOf(CharClassNode::class, $ast->pattern);

        $expression = $ast->pattern->expression;
        $this->assertInstanceOf(AlternationNode::class, $expression);

        // Last element should be a literal hyphen
        $lastElement = $expression->alternatives[\count($expression->alternatives) - 1];
        $this->assertInstanceOf(LiteralNode::class, $lastElement);
        $this->assertSame('-', $lastElement->value);
    }

    /**
     * Test that hyphen at start of character class is still literal.
     */
    public function test_leading_hyphen_is_literal(): void
    {
        $ast = $this->regex->parse('/[-abc]/');

        $this->assertInstanceOf(CharClassNode::class, $ast->pattern);

        $expression = $ast->pattern->expression;
        $this->assertInstanceOf(AlternationNode::class, $expression);

        // First element should be a literal hyphen
        $this->assertInstanceOf(LiteralNode::class, $expression->alternatives[0]);
        $this->assertSame('-', $expression->alternatives[0]->value);
    }
}
