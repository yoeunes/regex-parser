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

namespace RegexParser\Tests\Unit\Bridge\Symfony\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Analyzer\AnalysisContext;
use RegexParser\Bridge\Symfony\Analyzer\AnalysisIssue;
use RegexParser\Bridge\Symfony\Analyzer\AnalysisNotice;
use RegexParser\Bridge\Symfony\Analyzer\AnalyzerInterface;
use RegexParser\Bridge\Symfony\Analyzer\AnalyzerRegistry;
use RegexParser\Bridge\Symfony\Analyzer\Formatter\ConsoleReportFormatter;
use RegexParser\Bridge\Symfony\Analyzer\Formatter\JsonReportFormatter;
use RegexParser\Bridge\Symfony\Analyzer\IssueDetail;
use RegexParser\Bridge\Symfony\Analyzer\ReportSection;
use RegexParser\Bridge\Symfony\Analyzer\Severity;
use RegexParser\Bridge\Symfony\Command\RegexAnalyzeCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class RegexAnalyzeCommandTest extends TestCase
{
    #[Test]
    public function test_command_outputs_json_report(): void
    {
        $registry = new AnalyzerRegistry([
            new class implements AnalyzerInterface {
                public function getId(): string
                {
                    return 'routes';
                }

                public function getLabel(): string
                {
                    return 'Routes';
                }

                public function getPriority(): int
                {
                    return 10;
                }

                public function analyze(AnalysisContext $context): array
                {
                    return [
                        new ReportSection(
                            'routes',
                            'Routes',
                            meta: ['Routes' => 1],
                            summary: [new AnalysisNotice(Severity::FAIL, '1 shadowed route detected.')],
                            issues: [
                                new AnalysisIssue(
                                    'shadowed',
                                    Severity::FAIL,
                                    'demo (#1) -> demo (#2)',
                                    [new IssueDetail('Example', '/demo', 'example')],
                                ),
                            ],
                        ),
                    ];
                }
            },
        ]);

        $command = new RegexAnalyzeCommand(
            $registry,
            new ConsoleReportFormatter(),
            new JsonReportFormatter(),
        );

        $tester = new CommandTester($command);
        $status = $tester->execute(['--format' => 'json']);

        $this->assertSame(1, $status);

        $payload = json_decode((string) $tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('sections', $payload);
        $this->assertIsArray($payload['sections']);
        $this->assertIsArray($payload['sections'][0]);
        $this->assertSame('routes', $payload['sections'][0]['id']);
        $this->assertSame('Routes', $payload['sections'][0]['title']);
        $this->assertIsArray($payload['sections'][0]['issues']);
        $this->assertIsArray($payload['sections'][0]['issues'][0]);
        $this->assertSame('shadowed', $payload['sections'][0]['issues'][0]['kind']);
    }

    #[Test]
    public function test_command_allows_disabling_fail_on(): void
    {
        $registry = new AnalyzerRegistry([
            new class implements AnalyzerInterface {
                public function getId(): string
                {
                    return 'security';
                }

                public function getLabel(): string
                {
                    return 'Security';
                }

                public function getPriority(): int
                {
                    return 10;
                }

                public function analyze(AnalysisContext $context): array
                {
                    return [
                        new ReportSection(
                            'security',
                            'Security',
                            summary: [new AnalysisNotice(Severity::FAIL, '1 finding.')],
                            issues: [new AnalysisIssue('redos', Severity::FAIL, 'Firewall')],
                        ),
                    ];
                }
            },
        ]);

        $command = new RegexAnalyzeCommand(
            $registry,
            new ConsoleReportFormatter(),
            new JsonReportFormatter(),
        );

        $tester = new CommandTester($command);
        $status = $tester->execute(['--format' => 'json', '--fail-on' => ['none']]);

        $this->assertSame(0, $status);
    }
}
