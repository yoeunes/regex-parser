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
use RegexParser\RegexProblem;
use RegexParser\Severity;
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
    private const SUPPORTED_FORMATS = ['console', 'json', 'github', 'checkstyle', 'junit'];

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
        $format = \is_string($formatOption) ? strtolower($formatOption) : 'console';

        if (!\in_array($format, self::SUPPORTED_FORMATS, true)) {
            $io->error(\sprintf(
                "Invalid format '%s'. Supported formats: %s",
                $format,
                implode(', ', self::SUPPORTED_FORMATS),
            ));

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

        $allResults = $this->sortResultsByFileAndLine($report->results);
        $stats = $report->stats;

        switch ($format) {
            case 'json':
                $this->renderJsonOutput($output, $stats, $allResults);

                break;
            case 'github':
                $this->renderGithubOutput($output, $allResults);

                break;
            case 'checkstyle':
                $this->renderCheckstyleOutput($output, $allResults);

                break;
            case 'junit':
                $this->renderJunitOutput($output, $allResults);

                break;
            default:
                if (!empty($allResults)) {
                    $this->displayResults($io, $allResults);
                }

                $this->renderSummary($io, $stats);

                break;
        }

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function showBanner(SymfonyStyle $io): void
    {
        $io->newLine();
        $io->writeln('  <fg=white;options=bold>🔍 Regex Parser</> <fg=gray>linting...</>');
        $io->newLine();
    }

    /**
     * @phpstan-return LintStats
     */
    private function createStats(): array
    {
        return ['errors' => 0, 'warnings' => 0, 'optimizations' => 0];
    }

    private function renderCollectionFailure(
        string $format,
        OutputInterface $output,
        SymfonyStyle $io,
        string $errorMessage,
    ): int {
        $message = "Failed to collect patterns: {$errorMessage}";

        match ($format) {
            'json' => $output->writeln(json_encode([
                'error' => $message,
            ], \JSON_THROW_ON_ERROR)),
            'github' => $output->writeln($this->formatGithubMessage('error', $message)),
            'checkstyle' => $output->writeln($this->renderCheckstyleError($message)),
            'junit' => $output->writeln($this->renderJunitError($message)),
            default => $io->error($message),
        };

        return Command::FAILURE;
    }

    private function renderEmptyResults(string $format, OutputInterface $output, SymfonyStyle $io): int
    {
        $stats = $this->createStats();

        switch ($format) {
            case 'json':
                $this->renderJsonOutput($output, $stats, []);

                break;
            case 'checkstyle':
                $this->renderCheckstyleOutput($output, []);

                break;
            case 'junit':
                $this->renderJunitOutput($output, []);

                break;
            case 'github':
                break;
            default:
                $this->renderSummary($io, $stats, isEmpty: true);

                break;
        }

        return Command::SUCCESS;
    }

    /**
     * @phpstan-param LintStats $stats
     * @phpstan-param list<LintResult> $results
     */
    private function renderJsonOutput(OutputInterface $output, array $stats, array $results): void
    {
        $output->writeln(json_encode([
            'stats' => $stats,
            'results' => $this->normalizeResultsForJson($results),
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @phpstan-param list<LintResult> $results
     */
    private function renderGithubOutput(OutputInterface $output, array $results): void
    {
        foreach ($this->flattenProblems($results) as $entry) {
            $output->writeln($this->formatGithubAnnotation($entry));
        }
    }

    /**
     * @phpstan-param list<LintResult> $results
     */
    private function renderCheckstyleOutput(OutputInterface $output, array $results): void
    {
        $entries = $this->flattenProblems($results);
        $byFile = [];

        foreach ($entries as $entry) {
            $file = $this->normalizeFile($entry['file']);
            $byFile[$file][] = $entry;
        }

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<checkstyle version="4.3">'];
        foreach ($byFile as $file => $fileEntries) {
            $lines[] = \sprintf('  <file name="%s">', $this->escapeXml($file));
            foreach ($fileEntries as $entry) {
                $problem = $entry['problem'];
                $line = $this->normalizeLine($entry['line']);
                $column = $this->normalizeColumn($problem->position);
                $severity = $this->mapCheckstyleSeverity($problem->severity);
                $message = $this->formatProblemMessage($problem, $entry);
                $source = $this->formatCheckstyleSource($problem);
                $lines[] = \sprintf(
                    '    <error line="%d" column="%d" severity="%s" message="%s" source="%s"/>',
                    $line,
                    $column,
                    $this->escapeXml($severity),
                    $this->escapeXml($message),
                    $this->escapeXml($source),
                );
            }
            $lines[] = '  </file>';
        }

        $lines[] = '</checkstyle>';
        $output->writeln(implode("\n", $lines));
    }

    /**
     * @phpstan-param list<LintResult> $results
     */
    private function renderJunitOutput(OutputInterface $output, array $results): void
    {
        $entries = $this->flattenProblems($results);
        $tests = \count($entries);
        $failures = 0;
        $errors = 0;

        foreach ($entries as $entry) {
            $severity = $entry['problem']->severity;
            if (Severity::Critical === $severity) {
                $errors++;
            } elseif (Severity::Error === $severity) {
                $failures++;
            }
        }

        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            \sprintf(
                '<testsuite name="regex-parser" tests="%d" failures="%d" errors="%d" skipped="0">',
                $tests,
                $failures,
                $errors,
            ),
        ];

        foreach ($entries as $entry) {
            $problem = $entry['problem'];
            $name = $this->formatProblemTitle($problem);
            $file = $this->normalizeFile($entry['file']);
            $line = $this->normalizeLine($entry['line']);
            $message = $this->formatProblemMessage($problem, $entry);
            $lines[] = \sprintf(
                '  <testcase name="%s" classname="%s:%d">',
                $this->escapeXml($name),
                $this->escapeXml($file),
                $line,
            );

            if (Severity::Critical === $problem->severity) {
                $lines[] = \sprintf(
                    '    <error message="%s">%s</error>',
                    $this->escapeXml($problem->message),
                    $this->escapeXml($message),
                );
            } elseif (Severity::Error === $problem->severity) {
                $lines[] = \sprintf(
                    '    <failure message="%s">%s</failure>',
                    $this->escapeXml($problem->message),
                    $this->escapeXml($message),
                );
            } else {
                $lines[] = \sprintf(
                    '    <system-out>%s</system-out>',
                    $this->escapeXml($message),
                );
            }

            $lines[] = '  </testcase>';
        }

        $lines[] = '</testsuite>';
        $output->writeln(implode("\n", $lines));
    }

    /**
     * @phpstan-param list<LintResult> $results
     *
     * @phpstan-return list<JsonLintResult>
     */
    private function normalizeResultsForJson(array $results): array
    {
        $normalized = [];

        foreach ($results as $result) {
            $problems = [];
            foreach ($result['problems'] as $problem) {
                if ($problem instanceof RegexProblem) {
                    $problems[] = $problem->toArray();
                }
            }

            $result['problems'] = $problems;
            $normalized[] = $result;
        }

        return $normalized;
    }

    /**
     * @phpstan-param list<LintResult> $results
     *
     * @phpstan-return list<array{
     *     file: string,
     *     line: int,
     *     source?: string|null,
     *     pattern?: string|null,
     *     location?: string|null,
     *     problem: RegexProblem
     * }>
     */
    private function flattenProblems(array $results): array
    {
        $flattened = [];

        foreach ($results as $result) {
            foreach ($result['problems'] as $problem) {
                if (!$problem instanceof RegexProblem) {
                    continue;
                }

                $flattened[] = [
                    'file' => $result['file'],
                    'line' => $result['line'],
                    'source' => $result['source'] ?? null,
                    'pattern' => $result['pattern'] ?? null,
                    'location' => $result['location'] ?? null,
                    'problem' => $problem,
                ];
            }
        }

        return $flattened;
    }

    /**
     * @param array{location?: string|null, ...} $context
     */
    private function formatProblemMessage(RegexProblem $problem, array $context): string
    {
        $parts = [$problem->message];
        $location = $context['location'] ?? null;

        if (\is_string($location) && '' !== $location) {
            $parts[] = 'Location: '.$location;
        }

        if (null !== $problem->snippet && '' !== $problem->snippet) {
            $parts[] = $problem->snippet;
        }

        if (null !== $problem->suggestion && '' !== $problem->suggestion) {
            $parts[] = 'Suggestion: '.$problem->suggestion;
        }

        return implode("\n", $parts);
    }

    private function formatProblemTitle(RegexProblem $problem): string
    {
        $title = ucfirst($problem->type->value);
        if (null !== $problem->code && '' !== $problem->code) {
            $title .= ' '.$problem->code;
        }

        return $title;
    }

    /**
     * @param array{
     *     file: string,
     *     line: int,
     *     source?: string|null,
     *     pattern?: string|null,
     *     location?: string|null,
     *     problem: RegexProblem
     * } $entry
     */
    private function formatGithubAnnotation(array $entry): string
    {
        $problem = $entry['problem'];
        $level = $this->mapAnnotationLevel($problem->severity);
        $file = $this->pathHelper->getRelativePath($entry['file']);
        $line = $this->normalizeLine($entry['line']);
        $column = $this->normalizeColumn($problem->position);
        $title = $this->formatProblemTitle($problem);
        $message = $this->formatProblemMessage($problem, $entry);

        $properties = [];
        if ('' !== $file) {
            $properties[] = 'file='.$this->escapeGithubProperty($file);
            $properties[] = 'line='.$line;
            $properties[] = 'col='.$column;
        }

        if ('' !== $title) {
            $properties[] = 'title='.$this->escapeGithubProperty($title);
        }

        $suffix = [] === $properties ? '' : ' '.implode(',', $properties);

        return \sprintf('::%s%s::%s', $level, $suffix, $this->escapeGithubData($message));
    }

    private function formatGithubMessage(string $level, string $message): string
    {
        return \sprintf('::%s::%s', $level, $this->escapeGithubData($message));
    }

    private function renderCheckstyleError(string $message): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<checkstyle version="4.3">',
            '  <file name="regex-parser">',
            \sprintf(
                '    <error line="1" column="1" severity="error" message="%s" source="regex-parser"/>',
                $this->escapeXml($message),
            ),
            '  </file>',
            '</checkstyle>',
        ];

        return implode("\n", $lines);
    }

    private function renderJunitError(string $message): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<testsuite name="regex-parser" tests="1" failures="1" errors="0" skipped="0">',
            '  <testcase name="pattern-collection">',
            \sprintf(
                '    <failure message="%s">%s</failure>',
                $this->escapeXml($message),
                $this->escapeXml($message),
            ),
            '  </testcase>',
            '</testsuite>',
        ];

        return implode("\n", $lines);
    }

    private function normalizeFile(string $file): string
    {
        $relative = $this->pathHelper->getRelativePath($file);

        return '' === $relative ? 'unknown' : $relative;
    }

    private function normalizeLine(int $line): int
    {
        return $line > 0 ? $line : 1;
    }

    private function normalizeColumn(?int $position): int
    {
        if (null !== $position && $position >= 0) {
            return $position + 1;
        }

        return 1;
    }

    private function mapAnnotationLevel(Severity $severity): string
    {
        return match ($severity) {
            Severity::Critical, Severity::Error => 'error',
            Severity::Warning => 'warning',
            Severity::Info => 'notice',
        };
    }

    private function mapCheckstyleSeverity(Severity $severity): string
    {
        return match ($severity) {
            Severity::Critical, Severity::Error => 'error',
            Severity::Warning => 'warning',
            Severity::Info => 'info',
        };
    }

    private function formatCheckstyleSource(RegexProblem $problem): string
    {
        $source = 'regex-parser.'.$problem->type->value;
        if (null !== $problem->code && '' !== $problem->code) {
            $source .= '.'.$problem->code;
        }

        return $source;
    }

    private function escapeGithubData(string $value): string
    {
        return str_replace(
            ['%', "\r", "\n"],
            ['%25', '%0D', '%0A'],
            $value,
        );
    }

    private function escapeGithubProperty(string $value): string
    {
        return str_replace(
            ['%', "\r", "\n", ':', ','],
            ['%25', '%0D', '%0A', '%3A', '%2C'],
            $value,
        );
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_XML1);
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
        $io->writeln(\sprintf('  <fg=white;bg=gray;options=bold> 📁 %s </>', $relPath));
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
            $tip = $issue['tip'] ?? ($issue['problem']->tip ?? null);
            $this->displaySingleIssue($io, $badge, $issue['message'], $tip);

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

    private function displaySingleIssue(SymfonyStyle $io, string $badge, string $message, ?string $tip = null): void
    {
        // If tip is provided separately, remove it from the message
        if (null !== $tip && '' !== $tip) {
            $tipPos = strpos($message, 'Tip:');
            if (false !== $tipPos) {
                $message = substr($message, 0, $tipPos);
            }
        }

        // Split message by newline to handle carets/pointers correctly
        $lines = explode("\n", $message);
        $firstLine = array_shift($lines);

        // Print the primary error message on the same line as the badge
        $io->writeln(\sprintf('    %s <fg=white>%s</>', $badge, trim($firstLine)));

        // Print subsequent lines (like regex pointers ^) with indentation preserved
        if (!empty($lines)) {
            foreach ($lines as $index => $line) {
                $io->writeln(\sprintf('         <fg=gray>%s %s</>', 0 === $index ? '↳' : ' ', $this->stripMessageLine($line)));
            }
        }

        // Print the tip in a styled box if present
        if (null !== $tip && '' !== $tip) {
            $io->writeln('    <bg=cyan;fg=white;options=bold> TIP  </> <fg=cyan;options=bold>'.trim($tip).'</>');
        }
    }

    /**
     * @phpstan-param LintStats $stats
     */
    private function renderSummary(SymfonyStyle $io, array $stats, bool $isEmpty = false): void
    {
        $io->newLine();

        if ($isEmpty) {
            $io->writeln('  <bg=green;fg=white;options=bold> ✅ PASS </> <fg=gray>No regex patterns found.</>');
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
                '  <bg=red;fg=white;options=bold> ❌ FAIL </> <fg=red;options=bold>%d invalid patterns</><fg=gray>, %d warnings, %d optimizations.</>',
                $errors, $warnings, $optimizations,
            ),
            $warnings > 0 => \sprintf(
                '  <bg=yellow;fg=black;options=bold> ⚠️  PASS </> <fg=yellow;options=bold>%d warnings found</><fg=gray>, %d optimizations available.</>',
                $warnings, $optimizations,
            ),
            default => \sprintf(
                '  <bg=green;fg=white;options=bold> ✅ PASS </> <fg=green;options=bold>No issues found</><fg=gray>, %d optimizations available.</>',
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
