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

namespace RegexParser\Tests\Unit\Bridge\Symfony\Service;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\RegexPatternOccurrence;
use RegexParser\Lint\RegexAnalysisService;
use RegexParser\Regex;

final class RegexAnalysisServiceTest extends TestCase
{
    public function test_reports_invalid_pattern(): void
    {
        $service = $this->createService(warningThreshold: 10, redosThreshold: 'high');
        $pattern = new RegexPatternOccurrence('#^($#', 'file.php', 1, 'route:test', '(');

        $issues = $service->lint([$pattern]);

        $this->assertCount(1, $issues);
        $this->assertSame('error', $issues[0]['type']);
    }

    public function test_warns_on_complexity_threshold(): void
    {
        $service = $this->createService(warningThreshold: 0, redosThreshold: 'critical');
        $pattern = new RegexPatternOccurrence('#^[a-z]+$#', 'file.php', 1, 'route:test', '[a-z]+');

        $issues = $service->lint([$pattern]);

        $this->assertCount(1, $issues);
        $this->assertSame('warning', $issues[0]['type']);
        $this->assertArrayHasKey('issueId', $issues[0]);
        $this->assertSame('regex.lint.complexity', $issues[0]['issueId']);
    }

    public function test_trivial_alternation_skips_risk_checks(): void
    {
        $service = $this->createService(warningThreshold: 0, redosThreshold: 'critical');
        $pattern = new RegexPatternOccurrence('#^en|fr|de$#', 'file.php', 1, 'route:test', 'en|fr|de');

        $issues = $service->lint([$pattern]);

        $this->assertSame([], $issues);
    }

    public function test_ignored_patterns_skip_risk_checks(): void
    {
        $service = $this->createService(
            warningThreshold: 0,
            redosThreshold: 'critical',
            ignoredPatterns: ['[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*'],
        );
        $pattern = new RegexPatternOccurrence(
            '#^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$#',
            'file.php',
            1,
            'route:test',
            '^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$',
        );

        $issues = $service->lint([$pattern]);

        $this->assertSame([], $issues);
    }

    public function test_reports_redos_risk(): void
    {
        $service = $this->createService(warningThreshold: 50, redosThreshold: 'low');
        $pattern = new RegexPatternOccurrence('/(a+)+b/', 'file.php', 1, 'php:preg_match()');

        $issues = $service->lint([$pattern]);

        $redosIssues = array_values(array_filter(
            $issues,
            static fn (array $issue): bool => 'regex.lint.redos' === ($issue['issueId'] ?? null),
        ));

        $this->assertNotEmpty($redosIssues);
        $this->assertSame('error', $redosIssues[0]['type']);
    }

    /**
     * @param list<string> $ignoredPatterns
     */
    private function createService(int $warningThreshold, string $redosThreshold, array $ignoredPatterns = []): RegexAnalysisService
    {
        return new RegexAnalysisService(
            Regex::create(),
            null,
            $warningThreshold,
            $redosThreshold,
            $ignoredPatterns,
        );
    }
}
