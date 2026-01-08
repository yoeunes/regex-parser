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

namespace RegexParser\Bridge\Symfony\Analyzer;

use RegexParser\Bridge\Symfony\Security\SecurityAccessControlAnalyzer;
use RegexParser\Bridge\Symfony\Security\SecurityAccessControlReport;
use RegexParser\Bridge\Symfony\Security\SecurityAccessSuggestionBuilder;
use RegexParser\Bridge\Symfony\Security\SecurityConfigExtractor;
use RegexParser\Bridge\Symfony\Security\SecurityConfigLocator;
use RegexParser\Bridge\Symfony\Security\SecurityFirewallAnalyzer;
use RegexParser\Bridge\Symfony\Security\SecurityFirewallReport;
use RegexParser\Lint\Formatter\RelativePathHelper;

/**
 * @internal
 *
 * @phpstan-import-type AccessConflict from SecurityAccessControlReport
 * @phpstan-import-type AccessRule from SecurityAccessControlReport
 * @phpstan-import-type FirewallFinding from SecurityFirewallReport
 */
final readonly class SecurityBridgeAnalyzer implements BridgeAnalyzerInterface
{
    private const ID = 'security';
    private const PRIORITY = 20;
    private const ARROW_LABEL = "\u{21B3}";

    public function __construct(
        private SecurityConfigExtractor $extractor,
        private SecurityConfigLocator $locator,
        private SecurityAccessControlAnalyzer $accessAnalyzer,
        private SecurityFirewallAnalyzer $firewallAnalyzer,
        private SecurityAccessSuggestionBuilder $suggestionBuilder = new SecurityAccessSuggestionBuilder(),
    ) {}

    public function getId(): string
    {
        return self::ID;
    }

    public function getLabel(): string
    {
        return 'Security';
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function analyze(BridgeRunContext $context): array
    {
        $paths = $context->securityConfigPaths;
        if ([] === $paths) {
            $paths = $this->locator->locate($context->projectDir, $context->environment);
        }

        if ([] === $paths) {
            return [
                new BridgeReportSection(
                    self::ID.'_access',
                    'Security Access Control',
                    summary: [
                        new BridgeNotice(BridgeSeverity::WARN, 'No security config files found.'),
                    ],
                ),
            ];
        }

        $rules = [];
        $firewalls = [];
        $skippedFiles = [];

        foreach ($paths as $path) {
            if (!is_file($path)) {
                $skippedFiles[] = ['file' => $path, 'reason' => 'File not found.'];
                continue;
            }

            if (!$this->isYamlFile($path)) {
                $skippedFiles[] = ['file' => $path, 'reason' => 'Only YAML security config files are supported.'];
                continue;
            }

            $data = $this->extractor->extract($path, $context->environment);
            $rules = array_merge($rules, $data['accessControl']);
            $firewalls = array_merge($firewalls, $data['firewalls']);
        }

        $pathHelper = new RelativePathHelper($context->projectDir);
        $accessReport = $this->accessAnalyzer->analyze($rules, $context->includeOverlaps);

        $sections = [
            $this->buildAccessSection($accessReport, $context, $pathHelper, $skippedFiles),
        ];

        if (!$context->skipFirewalls) {
            $firewallReport = $this->firewallAnalyzer->analyze($firewalls, $context->redosThreshold);
            $sections[] = $this->buildFirewallSection($firewallReport, $context, $pathHelper);
        }

        return $sections;
    }

    /**
     * @param array<int, array{file: string, reason: string}> $skippedFiles
     */
    private function buildAccessSection(
        SecurityAccessControlReport $report,
        BridgeRunContext $context,
        RelativePathHelper $pathHelper,
        array $skippedFiles,
    ): BridgeReportSection {
        $meta = [
            'Rules' => $report->stats['rules'],
            'Mode' => $context->includeOverlaps ? 'Shadowed + overlaps' : 'Shadowed only',
            'Shadowed' => $report->stats['shadowed'],
            'Overlaps' => $report->stats['overlaps'],
            'Critical' => $report->stats['critical'],
        ];

        $warnings = $this->buildAccessWarnings($report, $skippedFiles);
        $summary = $this->buildAccessSummary($report, $context->includeOverlaps);

        $issues = [];
        foreach ($report->conflicts as $conflict) {
            $issues[] = $this->buildAccessIssue($conflict, $pathHelper);
        }

        $suggestions = [];
        if ([] !== $report->conflicts) {
            $suggestions = $this->suggestionBuilder->collect(
                $report->conflicts,
                static fn (string $file, int $line): string => $pathHelper->getRelativePath($file).':'.$line,
            );
        }

        return new BridgeReportSection(
            self::ID.'_access',
            'Security Access Control',
            $meta,
            $summary,
            $warnings,
            $issues,
            $suggestions,
        );
    }

    /**
     * @param array<int, array{file: string, reason: string}> $skippedFiles
     *
     * @return array<int, BridgeNotice>
     */
    private function buildAccessWarnings(SecurityAccessControlReport $report, array $skippedFiles): array
    {
        $warnings = [];

        if ([] !== $skippedFiles) {
            $warnings[] = new BridgeNotice(
                BridgeSeverity::WARN,
                \sprintf('%d security config files were skipped.', \count($skippedFiles)),
            );
        }

        if ([] !== $report->skippedRules) {
            $warnings[] = new BridgeNotice(
                BridgeSeverity::WARN,
                \sprintf(
                    '%d access_control rules skipped due to unsupported regex features.',
                    \count($report->skippedRules),
                ),
            );
        }

        if ([] !== $report->rulesWithAllowIf) {
            $warnings[] = new BridgeNotice(
                BridgeSeverity::WARN,
                \sprintf(
                    '%d rules use allow_if; conditions are not evaluated during analysis.',
                    \count(array_unique($report->rulesWithAllowIf)),
                ),
            );
        }

        if ([] !== $report->rulesWithIps) {
            $warnings[] = new BridgeNotice(
                BridgeSeverity::WARN,
                \sprintf(
                    '%d rules use IP restrictions; IPs are not evaluated during analysis.',
                    \count(array_unique($report->rulesWithIps)),
                ),
            );
        }

        if ([] !== $report->rulesWithNoPath) {
            $warnings[] = new BridgeNotice(
                BridgeSeverity::WARN,
                \sprintf(
                    '%d rules have no path and match all requests.',
                    \count(array_unique($report->rulesWithNoPath)),
                ),
            );
        }

        if ([] !== $report->rulesWithUnsupportedHosts) {
            $warnings[] = new BridgeNotice(
                BridgeSeverity::WARN,
                \sprintf(
                    '%d rules use host restrictions that could not be analyzed.',
                    \count(array_unique($report->rulesWithUnsupportedHosts)),
                ),
            );
        }

        return $warnings;
    }

    /**
     * @return array<int, BridgeNotice>
     */
    private function buildAccessSummary(SecurityAccessControlReport $report, bool $includeOverlaps): array
    {
        $summary = [];
        $shadowed = $report->stats['shadowed'];
        $overlaps = $report->stats['overlaps'];
        $critical = $report->stats['critical'];

        if (0 === $shadowed && 0 === $overlaps) {
            $summary[] = new BridgeNotice(BridgeSeverity::PASS, 'No access_control conflicts detected.');

            return $summary;
        }

        if ($critical > 0) {
            $summary[] = new BridgeNotice(
                BridgeSeverity::CRITICAL,
                \sprintf('%d critical shadowing conflicts detected.', $critical),
            );
        }

        if ($shadowed > 0) {
            $summary[] = new BridgeNotice(
                BridgeSeverity::FAIL,
                \sprintf('%d shadowed rules detected.', $shadowed),
            );
        }

        if ($overlaps > 0) {
            $suffix = $includeOverlaps ? 'Listed below.' : 'Use --show-overlaps to include them.';
            $summary[] = new BridgeNotice(
                BridgeSeverity::WARN,
                \sprintf('%d overlapping rules detected. %s', $overlaps, $suffix),
            );
        }

        return $summary;
    }

    /**
     * @phpstan-param AccessConflict $conflict
     */
    private function buildAccessIssue(array $conflict, RelativePathHelper $pathHelper): BridgeIssue
    {
        $rule = $conflict['rule'];
        $other = $conflict['conflict'];
        $type = $conflict['type'];

        $severity = match (true) {
            'critical' === $conflict['severity'] => BridgeSeverity::CRITICAL,
            'shadowed' === $type => BridgeSeverity::FAIL,
            default => BridgeSeverity::WARN,
        };

        $title = \sprintf(
            '#%d (%s) %s #%d (%s)',
            $rule['index'],
            $this->formatLocation($pathHelper, $rule['file'], $rule['line']),
            self::ARROW_LABEL,
            $other['index'],
            $this->formatLocation($pathHelper, $other['file'], $other['line']),
        );

        $details = [
            new BridgeIssueDetail('Rule', $this->formatRuleSummary($rule, $pathHelper)),
            new BridgeIssueDetail('Conflict', $this->formatRuleSummary($other, $pathHelper)),
        ];

        if (null !== $conflict['example']) {
            $details[] = new BridgeIssueDetail('Example', $conflict['example'], 'example');
        }

        return new BridgeIssue(
            $type,
            $severity,
            $title,
            $details,
            $conflict['notes'],
        );
    }

    private function buildFirewallSection(
        SecurityFirewallReport $report,
        BridgeRunContext $context,
        RelativePathHelper $pathHelper,
    ): BridgeReportSection {
        $meta = [
            'Firewalls' => $report->stats['firewalls'],
            'ReDoS >=' => $context->redosThreshold->value,
            'Flagged' => $report->stats['flagged'],
        ];

        $warnings = [];
        if ([] !== $report->skippedFirewalls) {
            $warnings[] = new BridgeNotice(
                BridgeSeverity::WARN,
                \sprintf('%d firewalls skipped during ReDoS analysis.', \count($report->skippedFirewalls)),
            );
        }

        $summary = [];
        if ([] === $report->findings) {
            $summary[] = new BridgeNotice(BridgeSeverity::PASS, 'No risky firewall regex detected.');
        } else {
            $summary[] = new BridgeNotice(
                BridgeSeverity::FAIL,
                \sprintf('%d firewall regex patterns exceed the ReDoS threshold.', $report->stats['flagged']),
            );
        }

        $issues = [];
        foreach ($report->findings as $finding) {
            $issues[] = $this->buildFirewallIssue($finding, $pathHelper);
        }

        return new BridgeReportSection(
            self::ID.'_firewall',
            'Security Firewall Regex',
            $meta,
            $summary,
            $warnings,
            $issues,
        );
    }

    /**
     * @phpstan-param FirewallFinding $finding
     */
    private function buildFirewallIssue(array $finding, RelativePathHelper $pathHelper): BridgeIssue
    {
        $severity = match ($finding['severity']) {
            'critical' => BridgeSeverity::CRITICAL,
            'high' => BridgeSeverity::FAIL,
            default => BridgeSeverity::WARN,
        };

        $location = $this->formatLocation($pathHelper, $finding['file'], $finding['line']);
        $title = \sprintf('%s (%s)', $finding['name'], $location);

        $details = [
            new BridgeIssueDetail('Severity', strtoupper($finding['severity'])),
            new BridgeIssueDetail('Score', (string) $finding['score']),
            new BridgeIssueDetail('Pattern', $finding['pattern'], 'pattern'),
        ];

        if (null !== $finding['vulnerable'] && '' !== $finding['vulnerable']) {
            $details[] = new BridgeIssueDetail('Vulnerable', $finding['vulnerable'], 'pattern');
        }

        if (null !== $finding['trigger'] && '' !== $finding['trigger']) {
            $details[] = new BridgeIssueDetail('Trigger', $finding['trigger'], 'example');
        }

        return new BridgeIssue('redos', $severity, $title, $details);
    }

    /**
     * @phpstan-param AccessRule $rule
     */
    private function formatRuleSummary(array $rule, RelativePathHelper $pathHelper): string
    {
        $location = $this->formatLocation($pathHelper, $rule['file'], $rule['line']);
        $parts = [
            \sprintf('#%d %s', $rule['index'], $location),
            'path='.$this->formatPathValue($rule['path']),
            'roles='.$this->formatListValue($rule['roles'], 'any'),
        ];

        $scope = $this->formatScope($rule['methods'], $rule['host'], $rule['ips'], $rule['requiresChannel']);
        if ('any' !== $scope) {
            $parts[] = 'scope='.$scope;
        }

        return implode('  ', $parts);
    }

    private function formatPathValue(?string $path): string
    {
        if (null === $path || '' === $path) {
            return '(any)';
        }

        return $path;
    }

    /**
     * @param array<int, string> $values
     */
    private function formatListValue(array $values, string $emptyLabel): string
    {
        if ([] === $values) {
            return $emptyLabel;
        }

        return implode('|', $values);
    }

    /**
     * @param array<int, string> $methods
     * @param array<int, string> $ips
     */
    private function formatScope(array $methods, ?string $host, array $ips, ?string $requiresChannel): string
    {
        if ([] === $methods && [] === $ips && null === $host && null === $requiresChannel) {
            return 'any';
        }

        $parts = [];
        if ([] !== $methods) {
            $parts[] = 'methods='.implode('|', $methods);
        }
        if (null !== $host && '' !== trim($host)) {
            $parts[] = 'host='.$host;
        }
        if ([] !== $ips) {
            $parts[] = 'ips='.implode('|', $ips);
        }
        if (null !== $requiresChannel && '' !== trim($requiresChannel)) {
            $parts[] = 'channel='.$requiresChannel;
        }

        return implode(' • ', $parts);
    }

    private function formatLocation(RelativePathHelper $pathHelper, string $file, int $line): string
    {
        return $pathHelper->getRelativePath($file).':'.$line;
    }

    private function isYamlFile(string $path): bool
    {
        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));

        return 'yaml' === $extension || 'yml' === $extension;
    }
}
