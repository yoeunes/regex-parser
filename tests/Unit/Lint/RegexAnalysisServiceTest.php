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

namespace RegexParser\Tests\Unit\Lint;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\RegexAnalysisService;
use RegexParser\Lint\RegexPatternOccurrence;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;

final class RegexAnalysisServiceTest extends TestCase
{
    private RegexAnalysisService $analysis;

    protected function setUp(): void
    {
        $this->analysis = new RegexAnalysisService(Regex::create());
    }

    public function test_analyze_redos_returns_empty_array_for_no_patterns(): void
    {
        $result = $this->analysis->analyzeRedos([], ReDoSSeverity::MEDIUM);

        $this->assertSame([], $result);
    }

    public function test_analyze_redos_returns_empty_array_for_invalid_pattern(): void
    {
        $patterns = [
            new RegexPatternOccurrence('/[a-z/', 'test.php', 1, 'preg_match'),
        ];

        $result = $this->analysis->analyzeRedos($patterns, ReDoSSeverity::MEDIUM);

        $this->assertSame([], $result);
    }

    public function test_analyze_redos_detects_vulnerable_pattern(): void
    {
        $patterns = [
            new RegexPatternOccurrence('/(a+)+/', 'test.php', 1, 'preg_match'),
        ];

        $result = $this->analysis->analyzeRedos($patterns, ReDoSSeverity::LOW);

        $this->assertCount(1, $result);
        $this->assertSame('test.php', $result[0]['file']);
        $this->assertSame(1, $result[0]['line']);
        $this->assertArrayHasKey('analysis', $result[0]);
    }

    public function test_analyze_redos_filters_by_threshold(): void
    {
        $patterns = [
            new RegexPatternOccurrence('/\w+/', 'test.php', 1, 'preg_match'),
        ];

        $result = $this->analysis->analyzeRedos($patterns, ReDoSSeverity::HIGH);

        $this->assertSame([], $result);
    }

    public function test_suggest_optimizations_returns_empty_array_for_no_patterns(): void
    {
        $result = $this->analysis->suggestOptimizations([], 0);

        $this->assertSame([], $result);
    }

    public function test_suggest_optimizations_returns_empty_array_for_invalid_pattern(): void
    {
        $patterns = [
            new RegexPatternOccurrence('/[a-z/', 'test.php', 1, 'preg_match'),
        ];

        $result = $this->analysis->suggestOptimizations($patterns, 0);

        $this->assertSame([], $result);
    }

    public function test_suggest_optimizations_filters_by_min_savings(): void
    {
        $patterns = [
            new RegexPatternOccurrence('/test/', 'test.php', 1, 'preg_match'),
        ];

        $result = $this->analysis->suggestOptimizations($patterns, 100);

        $this->assertSame([], $result);
    }

    public function test_construct_with_ignore_parse_errors(): void
    {
        $analysis = new RegexAnalysisService(
            Regex::create(),
            null,
            50,
            'high',
            [],
            [],
            true,
        );

        $this->assertInstanceOf(RegexAnalysisService::class, $analysis);
    }
}
