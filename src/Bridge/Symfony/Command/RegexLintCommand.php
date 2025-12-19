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

use RegexParser\Bridge\Symfony\Console\LinkFormatter;
use RegexParser\Bridge\Symfony\Console\RelativePathHelper;
use RegexParser\Bridge\Symfony\Service\RegexAnalysisService;
use RegexParser\Bridge\Symfony\Service\RegexLintRequest;
use RegexParser\Bridge\Symfony\Service\RegexLintService;
use RegexParser\OptimizationResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @phpstan-type LintIssue array{
 *     type: string,
 *     message: string,
 *     file: string,
 *     line: int,
 *     column?: int,
 *     issueId?: string,
 *     hint?: string|null,
 *     source?: string,
 *     pattern?: string,
 *     regex?: string
 * }
 * @phpstan-type OptimizationEntry array{
 *     file: string,
 *     line: int,
 *     optimization: OptimizationResult,
 *     savings: int,
 *     source?: string
 * }
 * @phpstan-type LintResult array{
 *     file: string,
 *     line: int,
 *     source?: string|null,
 *     pattern: string|null,
 *     location?: string|null,
 *     issues: list<LintIssue>,
 *     optimizations: list<OptimizationEntry>
 * }
 * @phpstan-type LintStats array{errors: int, warnings: int, optimizations: int}
 */
#[AsCommand(
    name: 'regex:lint',
    description: 'Lints, validates, and optimizes regex patterns in your PHP code.',
)]
final class RegexLintCommand extends Command
{
    private readonly RelativePathHelper $pathHelper;

    private readonly LinkFormatter $linkFormatter;

    public function __construct(
        private readonly RegexLintService $lint,
        private readonly RegexAnalysisService $analysis,
        ?string $editorUrl = null,
    ) {
        $this->pathHelper = new RelativePathHelper(getcwd() ?: null);
        $this->linkFormatter = new LinkFormatter($editorUrl, $this->pathHelper);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'The paths to analyze', ['src'])
            ->addOption('exclude', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Paths to exclude', ['vendor'])
            ->addOption('min-savings', null, InputOption::VALUE_OPTIONAL, 'Minimum optimization savings in characters', 1)
            ->addOption('no-routes', null, InputOption::VALUE_NONE, 'Skip route validation')
            ->addOption('no-validators', null, InputOption::VALUE_NONE, 'Skip validator validation')
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command scans your PHP code for regex patterns and provides:

                * Validation of regex syntax
                * Performance and security warnings  
                * Optimization suggestions
                * Integration with Symfony routes and validators

                <info>php %command.full_name%</info>

                Analyze specific directories:
                <info>php %command.full_name% src/ lib/</info>

                Exclude directories:
                <info>php %command.full_name% --exclude=tests --exclude=vendor</info>

                Show only significant optimizations:
                <info>php %command.full_name% --min-savings=10</info>

                Skip specific validations:
                <info>php %command.full_name% --no-routes --no-validators</info>
                EOF
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $paths = $this->normalizeStringList($input->getArgument('paths'));
        $exclude = $this->normalizeStringList($input->getOption('exclude'));
        $minSavingsValue = $input->getOption('min-savings');
        $minSavings = is_numeric($minSavingsValue) ? (int) $minSavingsValue : 1;
        $skipRoutes = (bool) $input->getOption('no-routes');
        $skipValidators = (bool) $input->getOption('no-validators');

        $this->showBanner($io);

        try {
            $request = new RegexLintRequest(
                paths: $paths,
                excludePaths: $exclude,
                minSavings: $minSavings,
                disabledSources: array_values(array_filter([
                    $skipRoutes ? 'routes' : null,
                    $skipValidators ? 'validators' : null,
                ], static fn (?string $source): bool => null !== $source)),
            );
            $patterns = $this->lint->collectPatterns($request);
        } catch (\Throwable $e) {
            $io->error("Failed to collect patterns: {$e->getMessage()}");

            return Command::FAILURE;
        }

        if (empty($patterns)) {
            $this->renderSummary($io, $this->createStats(), isEmpty: true);

            return Command::SUCCESS;
        }

        $io->progressStart(\count($patterns));
        $report = $this->lint->analyze($patterns, $request, fn () => $io->progressAdvance());
        $io->progressFinish();

        $stats = $report->stats;
        $allResults = $report->results;

        if (!empty($allResults)) {
            usort($allResults, fn ($a, $b) => $this->getSeverityScore($b) <=> $this->getSeverityScore($a));
            $this->displayResults($io, $allResults);
        }

        $exitCode = $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
        $this->renderSummary($io, $stats);

        return $exitCode;
    }

    private function showBanner(SymfonyStyle $io): void
    {
        $io->newLine();
        $io->writeln('  <fg=white;options=bold>Regex Parser</> <fg=gray>linting...</>');
        $io->newLine();
    }

    /**
     * @phpstan-return LintStats
     */
    private function createStats(): array
    {
        return ['errors' => 0, 'warnings' => 0, 'optimizations' => 0];
    }

    /**
     * @phpstan-param list<LintResult> $results
     */
    private function displayResults(SymfonyStyle $io, array $results): void
    {
        $this->outputIntegratedResults($io, $results);
    }

    /**
     * @phpstan-param list<LintResult> $results
     */
    private function outputIntegratedResults(SymfonyStyle $io, array $results): void
    {
        if (empty($results)) {
            return;
        }

        $groupedByFile = $this->groupResultsByFile($results);

        foreach ($groupedByFile as $file => $fileResults) {
            $this->renderFileHeader($io, $file);
            array_walk($fileResults, fn ($result) => $this->renderResultCard($io, $result));
        }
    }

    /**
     * @phpstan-param list<LintResult> $results
     *
     * @phpstan-return array<string, list<LintResult>>
     */
    private function groupResultsByFile(array $results): array
    {
        $grouped = [];
        foreach ($results as $result) {
            $grouped[$result['file']][] = $result;
        }

        return $grouped;
    }

    private function renderFileHeader(SymfonyStyle $io, string $file): void
    {
        $relPath = $this->linkFormatter->getRelativePath($file);
        $io->writeln(\sprintf('  <fg=white;bg=gray;options=bold> %s </>', $relPath));
    }

    /**
     * @phpstan-param LintResult $result
     */
    private function renderResultCard(SymfonyStyle $io, array $result): void
    {
        $this->displayPatternContext($io, $result);
        $this->displayIssues($io, $result['issues'] ?? []);
        $this->displayOptimizations($io, $result['optimizations'] ?? []);
        $io->newLine();
    }

    /**
     * @phpstan-param LintResult $result
     */
    private function displayPatternContext(SymfonyStyle $io, array $result): void
    {
        $pattern = $this->extractPatternForResult($result);
        $line = $result['line'];
        $file = $result['file'];

        if (null !== $pattern && '' !== $pattern) {
            $highlighted = $this->safelyHighlightPattern($pattern);
            $io->writeln(\sprintf('  <fg=gray>%d:</> %s', $line, $highlighted));
        } else {
            $link = $this->linkFormatter->format($file, $line, 'line '.$line, 1, (string) $line);
            $io->writeln(\sprintf('  <fg=gray>%s:</>', $link));
        }
    }

    private function safelyHighlightPattern(string $pattern): string
    {
        try {
            return $this->analysis->highlight(OutputFormatter::escape($pattern));
        } catch (\Exception) {
            return OutputFormatter::escape($pattern);
        }
    }

    /**
     * @phpstan-param list<LintIssue> $issues
     */
    private function displayIssues(SymfonyStyle $io, array $issues): void
    {
        foreach ($issues as $issue) {
            $badge = $this->getIssueBadge($issue['type']);
            $this->displaySingleIssue($io, $badge, $issue['message']);

            $hint = $issue['hint'] ?? null;
            if (null !== $hint && '' !== $hint) {
                $io->writeln(\sprintf('         <fg=gray>↳ %s</>', $hint));
            }
        }
    }

    private function getIssueBadge(string $type): string
    {
        return match ($type) {
            'error' => '<bg=red;fg=white;options=bold> FAIL </>',
            'warning' => '<bg=yellow;fg=black;options=bold> WARN </>',
            default => '<bg=gray;fg=white;options=bold> INFO </>',
        };
    }

    /**
     * @phpstan-param list<OptimizationEntry> $optimizations
     */
    private function displayOptimizations(SymfonyStyle $io, array $optimizations): void
    {
        foreach ($optimizations as $opt) {
            $io->writeln('    <bg=blue;fg=white;options=bold> FIX </> <fg=blue;options=bold>Optimization available</>');

            $original = $this->safelyHighlightPattern($opt['optimization']->original);
            $optimized = $this->safelyHighlightPattern($opt['optimization']->optimized);

            $io->writeln(\sprintf('         <fg=red>- %s</>', $original));
            $io->writeln(\sprintf('         <fg=green>+ %s</>', $optimized));
        }
    }

    private function displaySingleIssue(SymfonyStyle $io, string $badge, string $message): void
    {
        // Split message by newline to handle carets/pointers correctly
        $lines = explode("\n", $message);
        $firstLine = array_shift($lines);

        // Print the primary error message on the same line as the badge
        $io->writeln(\sprintf('    %s <fg=white>%s</>', $badge, $firstLine));

        // Print subsequent lines (like regex pointers ^) with indentation preserved
        if (!empty($lines)) {
            foreach ($lines as $index => $line) {
                $io->writeln(\sprintf('         <fg=gray>%s %s</>', 0 === $index ? '↳' : ' ', $this->stripMessageLine($line)));
            }
        }
    }

    /**
     * @phpstan-param LintStats $stats
     */
    private function renderSummary(SymfonyStyle $io, array $stats, bool $isEmpty = false): void
    {
        $io->newLine();

        if ($isEmpty) {
            $io->writeln('  <bg=green;fg=white;options=bold> PASS </> <fg=gray>No regex patterns found.</>');
            $this->showFooter($io);

            return;
        }

        $this->showSummaryMessage($io, $stats);
        $this->showFooter($io);
    }

    /**
     * @phpstan-param LintStats $stats
     */
    private function showSummaryMessage(SymfonyStyle $io, array $stats): void
    {
        $errors = $stats['errors'];
        $warnings = $stats['warnings'];
        $optimizations = $stats['optimizations'];

        $message = match (true) {
            $errors > 0 => \sprintf(
                '  <bg=red;fg=white;options=bold> FAIL </> <fg=red;options=bold>%d invalid patterns</><fg=gray>, %d warnings, %d optimizations.</>',
                $errors, $warnings, $optimizations,
            ),
            $warnings > 0 => \sprintf(
                '  <bg=yellow;fg=black;options=bold> PASS </> <fg=yellow;options=bold>%d warnings found</><fg=gray>, %d optimizations available.</>',
                $warnings, $optimizations,
            ),
            default => \sprintf(
                '  <bg=green;fg=white;options=bold> PASS </> <fg=green;options=bold>No issues found</><fg=gray>, %d optimizations available.</>',
                $optimizations,
            ),
        };

        $io->writeln($message);
    }

    private function showFooter(SymfonyStyle $io): void
    {
        $io->newLine();
        $io->writeln('  <fg=gray>Star the repo: https://github.com/yoeunes/regex-parser</>');
        $io->newLine();
    }

    /**
     * @phpstan-param LintResult $result
     */
    private function extractPatternForResult(array $result): ?string
    {
        $pattern = $result['pattern'] ?? null;
        if (\is_string($pattern) && '' !== $pattern) {
            return $pattern;
        }

        if (!empty($result['issues'])) {
            $firstIssue = $result['issues'][0];
            $issuePattern = $firstIssue['pattern'] ?? $firstIssue['regex'] ?? null;
            if (\is_string($issuePattern) && '' !== $issuePattern) {
                return $issuePattern;
            }
        }

        if (!empty($result['optimizations'])) {
            $firstOpt = $result['optimizations'][0];
            $optimization = $firstOpt['optimization'] ?? null;
            if ($optimization instanceof OptimizationResult) {
                return $optimization->original;
            }
        }

        return null;
    }

    /**
     * @phpstan-param LintResult $result
     */
    private function getSeverityScore(array $result): int
    {
        $issueScore = 0;
        foreach ($result['issues'] as $issue) {
            $issueScore += match ($issue['type']) {
                'error' => 100,
                'warning' => 10,
                default => 0,
            };
        }

        return $issueScore + \count($result['optimizations']);
    }

    private function stripMessageLine(string $message): string
    {
        return preg_replace_callback(
            '/^Line \d+:/m',
            fn ($matches) => str_repeat(' ', \strlen($matches[0])),
            $message,
        ) ?? $message;
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn ($item): bool => \is_string($item) && '' !== $item));
    }
}
