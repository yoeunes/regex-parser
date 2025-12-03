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
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\ComplexityScoreNodeVisitor;
use RegexParser\NodeVisitor\DumperNodeVisitor;
use RegexParser\NodeVisitor\ExplainNodeVisitor;
use RegexParser\NodeVisitor\HtmlExplainNodeVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Regex;

final class AdvancedPcreFeaturesTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('provideCalloutPatterns')]
    public function test_it_parses_and_compiles_callouts_correctly(string $pattern, string $expectedIdentifier): void
    {
        $regexService = Regex::create();
        $ast = $regexService->parse($pattern);

        $compiler = new CompilerNodeVisitor();
        $recompiled = $ast->accept($compiler);

        $this->assertSame($pattern, $recompiled);

        // Verify the identifier in the AST (using Dumper for inspection)
        $dumper = new DumperNodeVisitor();
        $dump = $ast->accept($dumper);
        if (is_numeric($expectedIdentifier)) {
            $this->assertStringContainsString("Callout({$expectedIdentifier})", $dump);
        } else {
            $this->assertStringContainsString("Callout('{$expectedIdentifier}')", $dump);
        }
    }

    public static function provideCalloutPatterns(): \Iterator
    {
        yield 'numeric callout' => ['/(?C1)abc/', '1'];
        yield 'string callout' => ['/(?C"debug_log")abc/', 'debug_log'];
        yield 'callout with spaces' => ['/(?C"my function")def/', 'my function'];
        yield 'callout with special chars' => ['/(?C"func_123-abc")xyz/', 'func_123-abc'];
        yield 'callout with zero' => ['/(?C0)test/', '0'];
        yield 'callout with max int' => ['/(?C255)foo/', '255'];
        yield 'named callout' => ['/(?Cfoo)abc/', 'foo'];
    }

    public function test_it_validates_callout_arguments(): void
    {
        $regexService = Regex::create();
        $validator = new ValidatorNodeVisitor();

        // Valid numeric callout
        $ast = $regexService->parse('/(?C123)abc/');
        $ast->accept($validator); // Should not throw exception

        // Valid string callout
        $ast = $regexService->parse('/(?C"my_func")abc/');
        $ast->accept($validator); // Should not throw exception

        // Invalid numeric callout (too high)
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Callout identifier must be between 0 and 255, got 256 at position 4.');
        $ast = $regexService->parse('/(?C256)abc/');
        $ast->accept($validator);
    }

    public function test_it_throws_exception_for_empty_string_callout_identifier(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Callout string identifier cannot be empty at position 4.');
        $regexService = Regex::create();
        $ast = $regexService->parse('/(?C"")abc/');
        $validator = new ValidatorNodeVisitor();
        $ast->accept($validator);
    }

    public function test_it_explains_callouts_correctly(): void
    {
        $regexService = Regex::create();
        $ast = $regexService->parse('/(?C1)abc/');
        $explainer = new ExplainNodeVisitor();
        $explanation = $ast->accept($explainer);
        $this->assertStringContainsString('Callout: passes control to user function with argument 1', $explanation);

        $ast = $regexService->parse('/(?C"my_func")abc/');
        $explainer = new ExplainNodeVisitor();
        $explanation = $ast->accept($explainer);
        $this->assertStringContainsString('Callout: passes control to user function with argument "my_func"', $explanation);
    }

    public function test_it_html_explains_callouts_correctly(): void
    {
        $regexService = Regex::create();
        $ast = $regexService->parse('/(?C1)abc/');
        $explainer = new HtmlExplainNodeVisitor();
        $explanation = $ast->accept($explainer);
        $this->assertStringContainsString('Callout: <strong>(?C1)</strong></span></li>', $explanation);
        $this->assertStringContainsString('title="passes control to user function with argument 1"', $explanation);

        $ast = $regexService->parse('/(?C"my_func")abc/');
        $explainer = new HtmlExplainNodeVisitor();
        $explanation = $ast->accept($explainer);
        $this->assertStringContainsString('Callout: <strong>(?C&quot;my_func&quot;)</strong></span></li>', $explanation);
        $this->assertStringContainsString('title="passes control to user function with argument &quot;my_func&quot;"', $explanation);
    }

    public function test_it_optimizes_callouts_correctly(): void
    {
        $regexService = Regex::create();
        $ast = $regexService->parse('/(?C1)abc/');
        $optimizer = new OptimizerNodeVisitor();
        $optimizedAst = $ast->accept($optimizer);
        // Callouts are atomic and should not be changed by the optimizer
        $compiler = new CompilerNodeVisitor();
        $this->assertSame('/(?C1)abc/', $optimizedAst->accept($compiler));
    }

    public function test_it_generates_sample_for_callouts_correctly(): void
    {
        $regexService = Regex::create();
        $ast = $regexService->parse('/a(?C1)b/');
        $sampleGenerator = new SampleGeneratorNodeVisitor();
        $sample = $ast->accept($sampleGenerator);
        // Callouts do not match characters, so they should not appear in the sample
        $this->assertSame('ab', $sample);
    }

    public function test_it_calculates_complexity_score_for_callouts(): void
    {
        $regexService = Regex::create();
        $ast = $regexService->parse('/a(?C1)b/');
        $complexityVisitor = new ComplexityScoreNodeVisitor();
        $score = $ast->accept($complexityVisitor);
        // a (1) + (?C1) (5) + b (1) = 7
        $this->assertSame(7, $score);
    }

    public function test_it_allows_duplicate_group_names_with_j_flag(): void
    {
        $pattern = '/(?J)(?<name>a)|(?<name>b)/';
        $regexService = Regex::create();
        $ast = $regexService->parse($pattern);

        $validator = new ValidatorNodeVisitor();
        $ast->accept($validator); // Should not throw an exception

        $compiler = new CompilerNodeVisitor();
        $this->assertSame($pattern, $ast->accept($compiler));
    }

    public function test_it_throws_exception_for_duplicate_group_names_without_j_flag(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Duplicate group name "name" at position 10.');

        $pattern = '/(?<name>a)|(?<name>b)/';
        $regexService = Regex::create();
        $ast = $regexService->parse($pattern);

        $validator = new ValidatorNodeVisitor();
        $ast->accept($validator);
    }

    public function test_it_handles_inline_j_flag_for_duplicate_group_names(): void
    {
        $pattern = '/(?-J)(?J)(?<name>a)|(?<name>b)/'; // Disable J, then enable J
        $regexService = Regex::create();
        $ast = $regexService->parse($pattern);

        $validator = new ValidatorNodeVisitor();
        $ast->accept($validator); // Should not throw an exception

        $compiler = new CompilerNodeVisitor();
        $this->assertSame($pattern, $ast->accept($compiler));
    }

    public function test_it_respects_j_flag_scope_in_inline_modifiers(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Duplicate group name "name" at position 16.');

        // J is enabled globally, then disabled for the second group
        $pattern = '/(?J)(?<name>a)(?-J)(?<name>b)/';
        $regexService = Regex::create();
        $ast = $regexService->parse($pattern);

        $validator = new ValidatorNodeVisitor();
        $ast->accept($validator);
    }
}
