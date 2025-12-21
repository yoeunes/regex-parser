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
use RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter;
use RegexParser\Lint\Formatter\FormatterRegistry;
use RegexParser\Lint\RegexAnalysisService;
use RegexParser\Lint\RegexLintReport;
use RegexParser\Lint\RegexLintRequest;
use RegexParser\Lint\RegexLintService;
use RegexParser\OptimizationResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
 *     position?: int,
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
 *     optimizations: list<OptimizationEntry>,
 *     problems: list<\RegexParser\RegexProblem>
 * }
 * @phpstan-type LintStats array{errors: int, warnings: int, optimizations: int}
 * @phpstan-type JsonProblem array{
 *     type: string,
 *     severity: string,
 *     message: string,
 *     code: ?string,
 *     position: ?int,
 *     snippet: ?string,
 *     suggestion: ?string,
 *     docsAnchor: ?string
 * }
 * @phpstan-type JsonLintResult array{
 *     file: string,
 *     line: int,
 *     source?: string|null,
 *     pattern: string|null,
 *     location?: string|null,
 *     issues: list<LintIssue>,
 *     optimizations: list<OptimizationEntry>,
 *     problems: list<JsonProblem>
 * }
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
        private readonly FormatterRegistry $formatterRegistry = new FormatterRegistry(),
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
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format (console, json, github, checkstyle, junit)', 'console')
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
                <info>php %command.full_name% --format=github</info>
                <info>php %command.full_name% --format=checkstyle</info>
                <info>php %command.full_name% --format=junit</info>
                EOF
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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
        $format = \is_string($formatOption) ? strtolower($formatOption) : 'console';

        $this->formatterRegistry->override(
            'console',
            new SymfonyConsoleFormatter($this->analysis, $this->linkFormatter, $output->isDecorated()),
        );

        if (!$this->formatterRegistry->has($format)) {
            $io->error(\sprintf(
                "Invalid format '%s'. Supported formats: %s",
                $format,
                implode(', ', $this->formatterRegistry->getNames()),
            ));

            return Command::FAILURE;
        }

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
            return $this->renderCollectionFailure($format, $output, $io, $e->getMessage());
        }

        if (empty($patterns)) {
            return $this->renderEmptyResults($format, $output, $io);
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

        $report = new RegexLintReport(
            $this->sortResultsByFileAndLine($report->results),
            $report->stats,
        );

        $stats = $report->stats;

        $formatter = $this->formatterRegistry->get($format);
        $output->writeln($formatter->format($report));

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function showBanner(SymfonyStyle $io): void
    {
        $io->newLine();
        $io->writeln('  <fg=white;options=bold>Regex Parser</> <fg=gray>linting...</>');
        $io->newLine();
    }

    private function renderCollectionFailure(
        string $format,
        OutputInterface $output,
        SymfonyStyle $io,
        string $errorMessage,
    ): int {
        $message = "Failed to collect patterns: {$errorMessage}";

        if ('console' === $format) {
            $io->error($message);
        } else {
            $formatter = $this->formatterRegistry->get($format);
            $output->writeln($formatter->formatError($message));
        }

        return Command::FAILURE;
    }

    private function renderEmptyResults(string $format, OutputInterface $output, SymfonyStyle $io): int
    {
        if ('console' === $format) {
            $this->renderEmptySummary($io);

            return Command::SUCCESS;
        }

        $emptyReport = new RegexLintReport([], ['errors' => 0, 'warnings' => 0, 'optimizations' => 0]);

        $formatter = $this->formatterRegistry->get($format);
        $output->writeln($formatter->format($emptyReport));

        return Command::SUCCESS;
    }

    private function renderEmptySummary(SymfonyStyle $io): void
    {
        $io->newLine();
        $io->writeln('  <bg=green;fg=white;options=bold> PASS </> <fg=gray>No regex patterns found.</>');
        $this->showFooter($io);
    }

    private function showFooter(SymfonyStyle $io): void
    {
        $io->newLine();
        $io->writeln('  <fg=gray>Star the repo: https://github.com/yoeunes/regex-parser</>');
        $io->newLine();
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
