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

namespace RegexParser\Bridge\Symfony\Command;

use RegexParser\Bridge\Symfony\Routing\RouteConflictAnalyzer;
use RegexParser\Bridge\Symfony\Routing\RouteConflictReport;
use RegexParser\Regex;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

/**
 * @phpstan-import-type RouteDescriptor from RouteConflictReport
 * @phpstan-import-type RouteConflict from RouteConflictReport
 */
#[AsCommand(
    name: 'regex:routes',
    description: 'Analyze Symfony routes for ordering conflicts and overlaps.',
)]
final class RegexRoutesCommand extends Command
{
    private const ARROW_LABEL = "\u{21B3}";
    private const TYPE_SHADOWED = 'shadowed';
    private const BADGE_FAIL = '<bg=red;fg=white;options=bold> FAIL </>';
    private const BADGE_WARN = '<bg=yellow;fg=black;options=bold> WARN </>';
    private const BADGE_PASS = '<bg=green;fg=white;options=bold> PASS </>';

    public function __construct(
        private readonly RouteConflictAnalyzer $analyzer,
        private readonly ?RouterInterface $router = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('check-conflicts', null, InputOption::VALUE_NONE, 'Check for route conflicts (default).')
            ->addOption('show-overlaps', null, InputOption::VALUE_NONE, 'Include partial overlaps in the report.')
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command inspects Symfony routes and detects ordering conflicts.

                By default, it reports shadowed (unreachable) routes.
                Use --show-overlaps to include partial overlaps.

                <info>php %command.full_name%</info>
                <info>php %command.full_name% --show-overlaps</info>
                EOF);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (null === $this->router) {
            $io->error('Router service is not available. Install Symfony Routing or enable the router service.');

            return Command::FAILURE;
        }

        $collection = $this->router->getRouteCollection();
        if (0 === \count($collection->all())) {
            $this->showBanner($io);
            $io->writeln('  '.self::BADGE_PASS.' <fg=white>No routes found.</>');
            $this->showFooter($io);

            return Command::SUCCESS;
        }

        $includeOverlaps = (bool) $input->getOption('show-overlaps');
        $report = $this->analyzer->analyze($collection, $includeOverlaps);

        $this->showBanner($io);
        $this->renderWarnings($io, $output, $report);
        $this->renderMeta($io, $report, $includeOverlaps);
        $this->renderStatus($io, $report, $includeOverlaps);

        if (0 === $report->stats['conflicts']) {
            if (!$includeOverlaps && $report->stats['overlaps'] > 0) {
                $message = 'Partial overlaps detected. Re-run with --show-overlaps to see details.';
                $io->writeln('  <fg=gray>'.self::ARROW_LABEL.' '.$message.'</>');
                $io->newLine();
            }

            $this->showFooter($io);

            return Command::SUCCESS;
        }

        $this->renderConflictsTable($io, $report);

        $suggestions = $this->collectSuggestions($report->conflicts);
        if ([] !== $suggestions) {
            $io->section('Suggestions');
            foreach ($suggestions as $suggestion) {
                $io->writeln('  <fg=gray>'.self::ARROW_LABEL.'</> '.$suggestion);
            }
            $io->newLine();
        }

        $this->showFooter($io);

        return Command::FAILURE;
    }

    private function showBanner(SymfonyStyle $io): void
    {
        $io->writeln('<fg=cyan;options=bold>RegexParser</> <fg=yellow>'.Regex::VERSION.'</> by Younes ENNAJI');
        $io->newLine();
    }

    private function renderWarnings(SymfonyStyle $io, OutputInterface $output, RouteConflictReport $report): void
    {
        if ([] !== $report->skippedRoutes) {
            $message = \sprintf('%d routes skipped due to unsupported regex features.', \count($report->skippedRoutes));
            $io->writeln('  '.self::BADGE_WARN.' <fg=white>'.$message.'</>');

            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $details = [];
                foreach ($report->skippedRoutes as $skip) {
                    $details[] = $skip['route'].': '.$skip['reason'];
                }
                foreach ($details as $detail) {
                    $io->writeln('      <fg=gray>'.self::ARROW_LABEL.' '.$detail.'</>');
                }
            }

            $io->newLine();
        }

        if ([] !== $report->routesWithConditions) {
            $message = \sprintf(
                '%d routes use conditions; conditions are not evaluated during conflict analysis.',
                \count(array_unique($report->routesWithConditions)),
            );
            $io->writeln('  '.self::BADGE_WARN.' <fg=white>'.$message.'</>');
            $io->newLine();
        }

        if ([] !== $report->routesWithUnsupportedHosts) {
            $message = \sprintf(
                '%d routes use host requirements that could not be analyzed.',
                \count(array_unique($report->routesWithUnsupportedHosts)),
            );
            $io->writeln('  '.self::BADGE_WARN.' <fg=white>'.$message.'</>');
            $io->newLine();
        }
    }

    private function renderMeta(SymfonyStyle $io, RouteConflictReport $report, bool $includeOverlaps): void
    {
        $mode = $includeOverlaps ? 'Shadowed + overlaps' : 'Shadowed only';

        $labels = ['Routes', 'Mode', 'Shadowed', 'Overlaps'];
        $maxLabelLength = max(array_map(strlen(...), $labels));
        $io->writeln($this->formatMetaLine('Routes', (string) $report->stats['routes'], $maxLabelLength));
        $io->writeln($this->formatMetaLine('Mode', $mode, $maxLabelLength));
        $io->writeln($this->formatMetaLine('Shadowed', (string) $report->stats['shadowed'], $maxLabelLength));
        $io->writeln($this->formatMetaLine('Overlaps', (string) $report->stats['overlaps'], $maxLabelLength));
        $io->newLine();
    }

    private function renderStatus(SymfonyStyle $io, RouteConflictReport $report, bool $includeOverlaps): void
    {
        $shadowed = $report->stats['shadowed'];
        $overlaps = $report->stats['overlaps'];

        if (0 === $shadowed && 0 === $overlaps) {
            $io->writeln('  '.self::BADGE_PASS.' <fg=white>No route conflicts detected.</>');
            $io->newLine();

            return;
        }

        if ($shadowed > 0) {
            $io->writeln(\sprintf(
                '  %s <fg=white>%d shadowed routes detected.</>',
                self::BADGE_FAIL,
                $shadowed,
            ));
        }

        if ($overlaps > 0) {
            $suffix = $includeOverlaps ? 'Listed below.' : 'Use --show-overlaps to include them.';
            $io->writeln(\sprintf(
                '  %s <fg=white>%d overlapping routes detected.</> <fg=gray>%s</>',
                self::BADGE_WARN,
                $overlaps,
                $suffix,
            ));
        }

        $io->newLine();
    }

    private function renderConflictsTable(SymfonyStyle $io, RouteConflictReport $report): void
    {
        $rows = [];
        foreach ($report->conflicts as $conflict) {
            $route = $conflict['route'];
            $other = $conflict['conflict'];
            $typeLabel = $this->formatType($conflict);
            $scope = $this->formatScope($conflict['methods'], $conflict['schemes']);
            $example = $this->formatExample($conflict['example']);

            $rows[] = [
                $this->formatRouteCell($route),
                $this->formatRouteCell($other),
                $typeLabel,
                $scope,
                $example,
            ];
        }

        $io->section('Route Conflicts Detected');
        $io->table(['Route', 'Conflict With', 'Type', 'Scope', 'Example'], $rows);
        $io->newLine();
    }

    /**
     * @phpstan-param array<RouteConflict> $conflicts
     *
     * @return array<int, string>
     */
    private function collectSuggestions(array $conflicts): array
    {
        $suggestions = [];

        foreach ($conflicts as $conflict) {
            $route = $conflict['route'];
            $other = $conflict['conflict'];

            $moveSuggestion = \sprintf(
                'Reorder routes: move "%s" before "%s".',
                $other['name'],
                $route['name'],
            );
            $suggestions[$moveSuggestion] = true;

            foreach ($this->suggestRequirementFixes($route, $other, $conflict['example']) as $suggestion) {
                $suggestions[$suggestion] = true;
            }
        }

        return array_keys($suggestions);
    }

    /**
     * @phpstan-param RouteDescriptor $route
     * @phpstan-param RouteDescriptor $other
     *
     * @return array<int, string>
     */
    private function suggestRequirementFixes(array $route, array $other, ?string $example): array
    {
        if (null === $example || '' === $example) {
            return [];
        }

        $variables = $this->extractRouteVariables($route['pathPattern'], $example);
        if ([] === $variables || [] === $other['staticSegments']) {
            return [];
        }

        $suggestions = [];
        $staticLookup = array_fill_keys($other['staticSegments'], true);

        foreach ($variables as $name => $value) {
            if ('' === $value || !isset($staticLookup[$value])) {
                continue;
            }

            $patternExample = $this->suggestRequirementPattern($name);
            if (null !== $patternExample) {
                $suggestions[] = \sprintf(
                    'Add a requirement for {%s} in "%s" (e.g. "%s").',
                    $name,
                    $route['name'],
                    $patternExample,
                );

                continue;
            }

            $suggestions[] = \sprintf(
                'Add a requirement for {%s} in "%s" to avoid matching "%s".',
                $name,
                $route['name'],
                $value,
            );
        }

        return $suggestions;
    }

    /**
     * @return array<string, string>
     */
    private function extractRouteVariables(string $pattern, string $example): array
    {
        $matches = [];
        if (1 !== \preg_match($pattern, $example, $matches)) {
            return [];
        }

        $variables = [];
        foreach ($matches as $key => $value) {
            if (!\is_string($key)) {
                continue;
            }
            if (!\is_string($value)) {
                continue;
            }
            $variables[$key] = $value;
        }

        return $variables;
    }

    private function suggestRequirementPattern(string $variable): ?string
    {
        $normalized = strtolower($variable);

        if ('uuid' === $normalized || str_contains($normalized, 'uuid')) {
            return '[0-9a-fA-F-]{36}';
        }

        if ('id' === $normalized || str_ends_with($normalized, 'id')) {
            return '\d+';
        }

        if (str_contains($normalized, 'slug')) {
            return '[a-z0-9-]+';
        }

        if (str_contains($normalized, 'locale')) {
            return '[a-z]{2}';
        }

        return null;
    }

    /**
     * @phpstan-param RouteConflict $conflict
     */
    private function formatType(array $conflict): string
    {
        $type = self::TYPE_SHADOWED === $conflict['type']
            ? self::BADGE_FAIL.' <fg=red>Shadowed</>'
            : self::BADGE_WARN.' <fg=yellow>Overlap</>';

        if ([] !== $conflict['notes']) {
            $type .= ' <fg=gray>(approx)</>';
        }

        return $type;
    }

    /**
     * @phpstan-param RouteDescriptor $route
     */
    private function formatRouteCell(array $route): string
    {
        $label = \sprintf('#%d %s', $route['index'], $route['name']);

        return $label."\n".self::ARROW_LABEL.' '.$route['path'];
    }

    /**
     * @param array<int, string> $methods
     * @param array<int, string> $schemes
     */
    private function formatScope(array $methods, array $schemes): string
    {
        if ([] === $methods && [] === $schemes) {
            return 'any';
        }

        $parts = [];
        if ([] !== $methods) {
            $parts[] = implode('|', $methods);
        }
        if ([] !== $schemes) {
            $parts[] = implode('|', $schemes);
        }

        return implode("\n", $parts);
    }

    private function formatExample(?string $example): string
    {
        if (null === $example) {
            return '-';
        }

        if ('' === $example) {
            return '"" (empty string)';
        }

        $escaped = '';
        $length = \strlen($example);
        for ($i = 0; $i < $length; $i++) {
            $byte = \ord($example[$i]);
            $escaped .= match ($byte) {
                0x0A => '\\n',
                0x0D => '\\r',
                0x09 => '\\t',
                0x5C => '\\\\',
                0x22 => '\\"',
                default => ($byte < 0x20 || $byte > 0x7E)
                    ? \sprintf('\\x%02X', $byte)
                    : $example[$i],
            };
        }

        return '<fg=cyan>"'.$escaped.'"</>';
    }

    private function showFooter(SymfonyStyle $io): void
    {
        $io->writeln('  <fg=gray>Found it useful? Consider starring: https://github.com/yoeunes/regex-parser</>');
        $io->newLine();
    }

    private function formatMetaLine(string $label, string $value, int $maxLabelLength): string
    {
        return \sprintf(
            '<fg=white;options=bold>%s</> : <fg=yellow>%s</>',
            str_pad($label, $maxLabelLength),
            $value,
        );
    }
}
