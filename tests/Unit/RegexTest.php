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

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Regex;

final class RegexTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    /**
     * @param class-string<NodeInterface> $expectedPatternClass
     */
    #[DataProvider('provideValidRegexForParsing')]
    public function test_parse_method_with_valid_regex(
        string $pattern,
        string $expectedDelimiter,
        string $expectedFlags,
        int $expectedEndPosition,
        string $expectedPatternClass
    ): void {
        $ast = $this->regexService->parse($pattern);

        $this->assertSame($expectedDelimiter, $ast->delimiter);
        $this->assertSame($expectedFlags, $ast->flags);
        $this->assertSame(0, $ast->startPosition);
        $this->assertSame($expectedEndPosition, $ast->endPosition);
        $this->assertInstanceOf($expectedPatternClass, $ast->pattern);
    }

    #[DataProvider('provideRegexForOptimization')]
    public function test_optimize_method(string $pattern, string $expectedOptimizedPattern): void
    {
        $optimized = $this->regexService->optimize($pattern)->optimized;
        $this->assertSame($expectedOptimizedPattern, $optimized);
    }

    #[DataProvider('provideRegexForExplanation')]
    public function test_explain_method(string $pattern, string $expectedExplanation): void
    {
        $explanation = $this->regexService->explain($pattern);
        $this->assertStringContainsString($expectedExplanation, $explanation);
    }

    #[DataProvider('provideInvalidRegexForParsing')]
    public function test_parse_method_with_invalid_regex_parsing(string $pattern, string $exceptionMessage): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->regexService->parse($pattern);
    }

    #[DataProvider('provideInvalidRegexForLexing')]
    public function test_parse_method_with_invalid_regex_lexing(string $pattern, string $exceptionMessage): void
    {
        $this->expectException(LexerException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->regexService->parse($pattern);
    }

    public function test_parse_pattern_wraps_delimiters_and_flags(): void
    {
        $fromFull = $this->regexService->parse('/foo/i');
        $fromPattern = $this->regexService->parse('/foo/i');

        $this->assertEquals($fromFull, $fromPattern);
    }

    public function test_parse_pattern_rejects_invalid_delimiter(): void
    {
        $this->expectException(ParserException::class);
        $this->regexService->parse('#foo#ab');
    }

    public static function provideValidRegexForParsing(): \Generator
    {
        yield 'simple literal' => ['/abc/', '/', '', 3, SequenceNode::class];
        yield 'with quantifier' => ['/a+/', '/', '', 2, QuantifierNode::class];
        yield 'with character class' => ['/[a-z]/', '/', '', 5, CharClassNode::class];
        yield 'with alternation' => ['/a|b/', '/', '', 3, AlternationNode::class];
        yield 'with group' => ['/(a)/', '/', '', 3, GroupNode::class];
        yield 'with lookaround and flags' => ['/(?=a)/i', '/', 'i', 5, GroupNode::class];
        yield 'with conditional and different delimiter' => ['#(?(1)a|b)#', '#', '', 9, ConditionalNode::class];
    }

    public static function provideRegexForOptimization(): \Generator
    {
        yield 'does not merge literals' => ['/a-b-c/', '/a-b-c/'];
        yield 'optimizes char class' => ['/[0-9]/', '/\d/'];
        yield 'no change needed' => ['/a+/', '/a+/'];
    }

    public static function provideRegexForExplanation(): \Generator
    {
        yield 'simple literal' => ['/a/', "'a'"];
        yield 'quantifier' => ['/a+/', "'a' (one or more times)"];
        yield 'character class' => ['/[a-z]/', "Character Class: any character in [   Range: from 'a' to 'z' ]"];
    }

    public static function provideInvalidRegexForParsing(): \Generator
    {
        yield 'unclosed group' => ['/(a/', 'Expected ) at end of input (found eof)'];
        yield 'quantifier on nothing' => ['/*/', 'Quantifier without target at position 0'];
        yield 'invalid flag' => ['/a/invalid', 'Unknown regex flag(s) found: "v", "a", "l", "d"'];
    }

    public static function provideInvalidRegexForLexing(): \Generator
    {
        yield 'unclosed character class' => ['/[a/', 'Unclosed character class "]" at end of input.'];
    }

    public function test_analyze_method(): void
    {
        $report = $this->regexService->analyze('/a+/');
        $this->assertIsBool($report->isValid);
        $this->assertIsArray($report->errors());
        $this->assertInstanceOf(\RegexParser\ReDoS\ReDoSAnalysis::class, $report->redos());
    }

    public function test_validate_method_with_valid_regex(): void
    {
        $result = $this->regexService->validate('/a+/');
        $this->assertTrue($result->isValid());
        $this->assertNull($result->getErrorMessage());
    }

    public function test_validate_method_with_invalid_regex(): void
    {
        $result = $this->regexService->validate('/a(/');
        $this->assertFalse($result->isValid());
        $this->assertStringContains('Expected )', $result->getErrorMessage());
    }

    public function test_redos_method(): void
    {
        $analysis = $this->regexService->redos('/a+/');
        $this->assertIsBool($analysis->isSafe());
        $this->assertIsString($analysis->getSeverity()->value);
    }

    public function test_literals_method(): void
    {
        $result = $this->regexService->literals('/a+/');
        $this->assertIsArray($result->literals);
    }

    public function test_generate_method(): void
    {
        $sample = $this->regexService->generate('/a+/');
        $this->assertIsString($sample);
        $this->assertMatchesRegularExpression('/a+/', $sample);
    }

    public function test_highlight_method(): void
    {
        $highlighted = $this->regexService->highlight('/a+/');
        $this->assertIsString($highlighted);
        $this->assertStringContains('a', $highlighted);
    }

    public function test_parse_with_tolerant_mode(): void
    {
        $result = $this->regexService->parse('/a(/', true);
        $this->assertInstanceOf(\RegexParser\TolerantParseResult::class, $result);
        $this->assertFalse($result->isValid());
    }
}
