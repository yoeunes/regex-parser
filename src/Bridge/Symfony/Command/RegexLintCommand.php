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
use RegexParser\Lint\RegexAnalysisService;
use RegexParser\Lint\RegexLintRequest;
use RegexParser\Lint\RegexLintService;
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
 *     regex?: string,
 *     analysis?: \RegexParser\ReDoS\ReDoSAnalysis,
 *     validation?: \RegexParser\ValidationResult
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
    private RelativePathHelper $pathHelper;

    private LinkFormatter $linkFormatter;

    /**
     * @var list<string>
     */
    private array $defaultPaths;

    /**
     * @var list<string>
     */
    private array $defaultExcludePaths;

    /**
     * @param list<string> $defaultPaths
     * @param list<string> $defaultExcludePaths
     */
    public function __construct(
        private readonly RegexLintService $lint,
        private readonly RegexAnalysisService $analysis,
        array $defaultPaths = ['src'],
        array $defaultExcludePaths = ['vendor'],
        private readonly ?string $editorUrl = null,
    ) {
        $this->defaultPaths = $this->normalizeStringList($defaultPaths);
        $this->defaultExcludePaths = $this->normalizeStringList($defaultExcludePaths);

        if ([] === $this->defaultPaths) {
            $this->defaultPaths = ['src'];
        }

        if ([] === $this->defaultExcludePaths) {
            $this->defaultExcludePaths = ['vendor'];
        }

        // Initialize with temporary path helper, will be updated in execute()
        $this->pathHelper = new RelativePathHelper(getcwd() ?: null);
        $this->linkFormatter = new LinkFormatter($this->editorUrl, $this->pathHelper);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'The paths to analyze', $this->defaultPaths)
            ->addOption('exclude', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Paths to exclude', $this->defaultExcludePaths)
            ->addOption('min-savings', null, InputOption::VALUE_OPTIONAL, 'Minimum optimization savings in characters', 1)
            ->addOption('no-routes', null, InputOption::VALUE_NONE, 'Skip route validation')
            ->addOption('no-validators', null, InputOption::VALUE_NONE, 'Skip validator validation')
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format (console or json)', 'console')
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

                Output format for CI/CD:
                <info>php %command.full_name% --format=json</info>
                EOF
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Update the working directory at execution time to handle Symfony commands properly
        $workingDir = getcwd() ?: null;
        $this->pathHelper = new RelativePathHelper($workingDir);
        $this->linkFormatter = new LinkFormatter($this->editorUrl, $this->pathHelper);

        $paths = $this->normalizeStringList($input->getArgument('paths'));
        $exclude = $this->normalizeStringList($input->getOption('exclude'));
        $minSavingsValue = $input->getOption('min-savings');
        $minSavings = is_numeric($minSavingsValue) ? (int) $minSavingsValue : 1;
        $skipRoutes = (bool) $input->getOption('no-routes');
        $skipValidators = (bool) $input->getOption('no-validators');
        $formatOption = $input->getOption('format');
        $format = \is_string($formatOption) ? $formatOption : 'console';

        if (!\in_array($format, ['console', 'json'], true)) {
            $io->error('Invalid format \''.$format.'\'. Supported formats: console, json');

            return Command::FAILURE;
        }

        // Only show banner and progress for console format
        if ('console' === $format) {
            $this->showBanner($io);
        }

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
            if ('json' === $format) {
                $output->writeln(json_encode([
                    'error' => "Failed to collect patterns: {$e->getMessage()}",
                ], \JSON_THROW_ON_ERROR));

                return Command::FAILURE;
            }

            $io->error("Failed to collect patterns: {$e->getMessage()}");

            return Command::FAILURE;
        }

        if (empty($patterns)) {
            if ('json' === $format) {
                $output->writeln(json_encode([
                    'stats' => $this->createStats(),
                    'results' => [],
                ], \JSON_THROW_ON_ERROR));

                return Command::SUCCESS;
            }

            $this->renderSummary($io, $this->createStats(), isEmpty: true);

            return Command::SUCCESS;
        }

        if ('console' === $format) {
            $io->progressStart(\count($patterns));
            $progressCallback = fn () => $io->progressAdvance();
        } else {
            $progressCallback = null;
        }

        $report = $this->lint->analyze($patterns, $request, $progressCallback);

        if ('console' === $format) {
            $io->progressFinish();
        }

        $allResults = $this->sortResultsByFileAndLine($report->results);
        $stats = $report->stats;

        if ('json' === $format) {
            $output->writeln(json_encode([
                'stats' => $stats,
                'results' => $allResults,
            ], \JSON_THROW_ON_ERROR));
        } else {
            if (!empty($allResults)) {
                $this->displayResults($io, $allResults);
            }

            $this->renderSummary($io, $stats);
        }

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
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
        if (empty($results)) {
            return;
        }

        $currentFile = null;
        foreach ($results as $result) {
            $file = $result['file'];
            if ($file !== $currentFile) {
                $currentFile = $file;
                $this->renderFileHeader($io, $file);
            }

            $this->renderResultCard($io, $result);
        }
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
        $location = $result['location'] ?? null;

        $hasLocation = \is_string($location) && '' !== $location;
        $showLine = $line > 0 && !$hasLocation;

        if ($showLine) {
            // Create clickable pen emoji
            $penLabel = $this->getPenLabel($io);
            $penLink = $this->linkFormatter->format($file, $line, $penLabel, 1, '✏️');

            if (null !== $pattern && '' !== $pattern) {
                $highlighted = $this->safelyHighlightPattern($pattern);
                $io->writeln(\sprintf('  <fg=gray>%d:</> %s %s', $line, $penLink, $highlighted));
            } else {
                $io->writeln(\sprintf('  <fg=gray>%s:</> %s', 'line '.$line, $penLink));
            }
        } else {
            if (null !== $pattern && '' !== $pattern) {
                $highlighted = $this->safelyHighlightPattern($pattern);
                $io->writeln(\sprintf('  %s', $highlighted));
            } else {
                $io->writeln('  <fg=gray>(pattern unavailable)</>');
            }

            if ($hasLocation) {
                $io->writeln(\sprintf('     <fg=gray>↳ %s</>', OutputFormatter::escape($location)));
            }
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

    private function getPenLabel(SymfonyStyle $io): string
    {
        if (!$io->isDecorated()) {
            return '✏️';
        }

        // Use SGR 24 to avoid hyperlink underline in terminals that honor it.
        return "\033[24m✏️\033[24m";
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
            $io->writeln('    <bg=cyan;fg=white;options=bold> TIP </> <fg=cyan;options=bold>Optimization available</>');

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

    /**
     * @phpstan-param list<LintResult> $results
     *
     * @phpstan-return list<LintResult>
     */
    private function sortResultsByFileAndLine(array $results): array
    {
        usort($results, static function (array $a, array $b): int {
            $fileCompare = strcmp((string) $a['file'], (string) $b['file']);
            if (0 !== $fileCompare) {
                return $fileCompare;
            }

            return $a['line'] <=> $b['line'];
        });

        return $results;
    }
}
