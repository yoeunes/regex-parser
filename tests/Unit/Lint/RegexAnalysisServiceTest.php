<?php

declare(strict_types=1);

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

    public function test_analyzeRedos_returns_empty_array_for_no_patterns(): void
    {
        $result = $this->analysis->analyzeRedos([], ReDoSSeverity::MEDIUM);

        $this->assertSame([], $result);
    }

    public function test_analyzeRedos_returns_empty_array_for_invalid_pattern(): void
    {
        $patterns = [
            new RegexPatternOccurrence('/[a-z/', 'test.php', 1, 'preg_match'),
        ];

        $result = $this->analysis->analyzeRedos($patterns, ReDoSSeverity::MEDIUM);

        $this->assertSame([], $result);
    }

    public function test_analyzeRedos_detects_vulnerable_pattern(): void
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

    public function test_analyzeRedos_filters_by_threshold(): void
    {
        $patterns = [
            new RegexPatternOccurrence('/(a+)+/', 'test.php', 1, 'preg_match'),
        ];

        $result = $this->analysis->analyzeRedos($patterns, ReDoSSeverity::CRITICAL);

        $this->assertSame([], $result);
    }

    public function test_suggestOptimizations_returns_empty_array_for_no_patterns(): void
    {
        $result = $this->analysis->suggestOptimizations([], 0);

        $this->assertSame([], $result);
    }

    public function test_suggestOptimizations_returns_empty_array_for_invalid_pattern(): void
    {
        $patterns = [
            new RegexPatternOccurrence('/[a-z/', 'test.php', 1, 'preg_match'),
        ];

        $result = $this->analysis->suggestOptimizations($patterns, 0);

        $this->assertSame([], $result);
    }

    public function test_suggestOptimizations_filters_by_min_savings(): void
    {
        $patterns = [
            new RegexPatternOccurrence('/test/', 'test.php', 1, 'preg_match'),
        ];

        $result = $this->analysis->suggestOptimizations($patterns, 100);

        $this->assertSame([], $result);
    }

    public function test_suggestOptimizations_includes_extended_mode_normalization(): void
    {
        $patterns = [
            new RegexPatternOccurrence('/(?x)  test  # comment/s', 'test.php', 1, 'preg_match'),
        ];

        $result = $this->analysis->suggestOptimizations($patterns, 0);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('optimization', $result[0]);
    }

    public function test_suggestOptimizations_with_custom_config(): void
    {
        $patterns = [
            new RegexPatternOccurrence('/[0-9]/', 'test.php', 1, 'preg_match'),
        ];

        $result = $this->analysis->suggestOptimizations($patterns, 0, ['digits' => true]);

        $this->assertNotEmpty($result);
    }

    public function test_highlight_returns_highlighted_string(): void
    {
        $result = $this->analysis->highlight('/test/');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_highlightBody_returns_highlighted_string(): void
    {
        $result = $this->analysis->highlightBody('test', 'i');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_highlightBody_with_custom_delimiter(): void
    {
        $result = $this->analysis->highlightBody('test', 'i', '#');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_scan_with_no_paths(): void
    {
        $result = $this->analysis->scan([], []);

        $this->assertSame([], $result);
    }

    public function test_construct_with_custom_warning_threshold(): void
    {
        $analysis = new RegexAnalysisService(Regex::create(), null, 100);

        $this->assertInstanceOf(RegexAnalysisService::class, $analysis);
    }

    public function test_construct_with_custom_redos_threshold(): void
    {
        $analysis = new RegexAnalysisService(Regex::create(), null, 50, 'low');

        $this->assertInstanceOf(RegexAnalysisService::class, $analysis);
    }

    public function test_construct_with_invalid_redos_threshold_falls_back_to_high(): void
    {
        $analysis = new RegexAnalysisService(Regex::create(), null, 50, 'invalid');

        $this->assertInstanceOf(RegexAnalysisService::class, $analysis);
    }

    public function test_construct_with_ignored_patterns(): void
    {
        $analysis = new RegexAnalysisService(
            Regex::create(),
            null,
            50,
            'high',
            ['pattern1', 'pattern2']
        );

        $this->assertInstanceOf(RegexAnalysisService::class, $analysis);
    }

    public function test_construct_with_redos_ignored_patterns(): void
    {
        $analysis = new RegexAnalysisService(
            Regex::create(),
            null,
            50,
            'high',
            [],
            ['redos_pattern1', 'redos_pattern2']
        );

        $this->assertInstanceOf(RegexAnalysisService::class, $analysis);
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
            true
        );

        $this->assertInstanceOf(RegexAnalysisService::class, $analysis);
    }
}
