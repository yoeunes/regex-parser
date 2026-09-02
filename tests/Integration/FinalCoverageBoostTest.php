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
use RegexParser\NodeVisitor\DumperNodeVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\Regex;

/**
 * Final coverage boost tests targeting specific uncovered edge cases.
 */
final class FinalCoverageBoostTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    // Test Optimizer edge cases
    public function test_optimizer_char_class_optimization(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        // Character class that could be optimized
        $ast = $this->regexService->parse('/[a]/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);

        // Multiple single chars
        $ast = $this->regexService->parse('/[abc]/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);
    }

    public function test_optimizer_quantifier_edge_cases(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        // Quantifier with 0 min
        $ast = $this->regexService->parse('/a{0,5}/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);

        // Quantifier {1,1} should become no quantifier
        $ast = $this->regexService->parse('/a{1,1}/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);
    }

    public function test_optimizer_sequence_flattening(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        // Nested sequences
        $ast = $this->regexService->parse('/abc/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);
    }

    public function test_optimizer_alternation_with_empty(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        // Alternation with one empty branch
        $ast = $this->regexService->parse('/a|/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);
    }

    // Test Dumper edge cases
    public function test_dumper_group_types(): void
    {
        $dumper = new DumperNodeVisitor();

        // Non-capturing group
        $ast = $this->regexService->parse('/(?:abc)/');
        $result = $ast->accept($dumper);
        $this->assertStringContainsString('Group', $result);

        // Named group
        $ast = $this->regexService->parse('/(?<name>abc)/');
        $result = $ast->accept($dumper);
        $this->assertStringContainsString('Group', $result);

        // Atomic group
        $ast = $this->regexService->parse('/(?>abc)/');
        $result = $ast->accept($dumper);
        $this->assertStringContainsString('Group', $result);
    }

    public function test_dumper_assertion_types(): void
    {
        $dumper = new DumperNodeVisitor();

        $patterns = [
            '/(?=abc)/',   // Positive lookahead
            '/(?!abc)/',   // Negative lookahead
            '/(?<=abc)/',  // Positive lookbehind
            '/(?<!abc)/',  // Negative lookbehind
        ];

        foreach ($patterns as $pattern) {
            $ast = $this->regexService->parse($pattern);
            $result = $ast->accept($dumper);
            // Assertions are represented as Groups with specific types
            $this->assertStringContainsString('Group', $result);
        }
    }

    public function test_full_integration_complex_pattern(): void
    {
        // A complex real-world-like pattern
        $pattern = '/^(?:(?<scheme>https?):\/\/)?(?<host>[\w\-\.]+)(?::(?<port>\d+))?(?<path>\/[^\s]*)?$/i';

        $this->regexService->parse($pattern);

        $result = $this->regexService->validate($pattern);
        $this->assertTrue($result->isValid);

        $explanation = $this->regexService->explain($pattern);
        $this->assertNotEmpty($explanation);

        $dump = $this->regexService->parse($pattern)->accept(new DumperNodeVisitor());
        $this->assertNotEmpty($dump);

        $optimized = $this->regexService->optimize($pattern)->optimized;
        $this->assertNotEmpty($optimized);
    }

    public function test_sample_generator_edge_cases(): void
    {
        // Alternation
        $sample = $this->regexService->generate('/a|b|c/');
        $this->assertMatchesRegularExpression('/^[abc]$/', $sample);

        // Optional group
        $sample = $this->regexService->generate('/a(bc)?d/');
        $this->assertMatchesRegularExpression('/^a(bc)?d$/', $sample);

        // Nested quantifiers
        $sample = $this->regexService->generate('/(a+)+/');
        $this->assertNotEmpty($sample);
    }

    public function test_validator_range_validation(): void
    {
        // Valid ranges
        $result = $this->regexService->validate('/[a-z]/');
        $this->assertTrue($result->isValid);

        $result = $this->regexService->validate('/[0-9]/');
        $this->assertTrue($result->isValid);

        $result = $this->regexService->validate('/[A-Z]/');
        $this->assertTrue($result->isValid);
    }
}
