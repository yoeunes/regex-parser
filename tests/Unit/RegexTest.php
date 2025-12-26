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
use RegexParser\NodeVisitor\HtmlExplainNodeVisitor;
use RegexParser\Regex;
use RegexParser\TolerantParseResult;

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
        $redos = $report->redos();
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
        $this->assertStringContainsString('Expected )', (string) $result->getErrorMessage());
    }

    public function test_redos_method(): void
    {
        $analysis = $this->regexService->redos('/a+/');
        $this->assertIsBool($analysis->isSafe());
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
        $this->assertStringContainsString('a', $highlighted);
    }

    public function test_highlight_html_branch(): void
    {
        $highlighted = $this->regexService->highlight('/a+/', 'html');
        $this->assertStringContainsString('<span', $highlighted);
    }

    public function test_tokenize_extracts_pattern_and_flags(): void
    {
        $stream = Regex::tokenize('/ab/i');
        $this->assertSame('ab', $stream->getPattern());
        $this->assertGreaterThan(0, \count($stream->getTokens()));
    }

    public function test_build_visual_snippet_truncates_and_marks_caret(): void
    {
        $regex = Regex::create();
        $ref = new \ReflectionClass($regex);
        $method = $ref->getMethod('buildVisualSnippet');

        $pattern = str_repeat('a', 120);
        $snippet = $method->invoke($regex, $pattern, 110);

        $this->assertIsString($snippet);
        /** @var string $snippetString */
        $snippetString = $snippet;
        $this->assertStringContainsString('Line 1:', $snippetString);
        $this->assertStringContainsString('^', $snippetString);
        $this->assertStringContainsString('...', $snippetString);
    }

    public function test_build_visual_snippet_returns_empty_for_nulls(): void
    {
        $regex = Regex::create();
        $ref = new \ReflectionClass($regex);
        $method = $ref->getMethod('buildVisualSnippet');

        $this->assertSame('', $method->invoke($regex, null, null));
    }

    public function test_build_search_patterns_and_confidence_levels(): void
    {
        $regex = Regex::create();
        $ref = new \ReflectionClass($regex);

        $buildSearch = $ref->getMethod('buildSearchPatterns');
        $determine = $ref->getMethod('determineConfidenceLevel');

        $literalSet = new class {
            /**
             * @var array<string>
             */
            public array $prefixes = ['foo'];

            /**
             * @var array<string>
             */
            public array $suffixes = ['bar'];

            public bool $complete = false;

            public function isVoid(): bool
            {
                return false;
            }
        };

        /** @var array<string> $patterns */
        $patterns = $buildSearch->invoke($regex, $literalSet);
        $this->assertContains('^foo', $patterns);
        $this->assertContains('bar$', $patterns);
        $this->assertSame('medium', $determine->invoke($regex, $literalSet));

        $this->assertSame([], $buildSearch->invoke($regex, ['not-an-object']));
        $this->assertSame('low', $determine->invoke($regex, 'not-an-object'));
    }

    public function test_create_explanation_visitor_html_and_invalid(): void
    {
        $ref = new \ReflectionClass($this->regexService);
        $method = $ref->getMethod('createExplanationVisitor');

        $htmlVisitor = $method->invoke($this->regexService, 'html');
        $this->assertInstanceOf(HtmlExplainNodeVisitor::class, $htmlVisitor);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($this->regexService, 'invalid');
    }

    public function test_safe_extract_pattern_handles_parser_exception(): void
    {
        $regex = Regex::create();
        $ref = new \ReflectionClass($regex);
        $method = $ref->getMethod('safeExtractPattern');

        $result = $method->invoke($regex, 'invalid');

        $this->assertSame(['invalid', '', '/', \strlen('invalid')], $result);
    }

    public function test_parse_with_tolerant_mode(): void
    {
        $result = $this->regexService->parse('/a(/', true);
        $this->assertInstanceOf(TolerantParseResult::class, $result);
        $this->assertTrue($result->hasErrors());
    }
}
