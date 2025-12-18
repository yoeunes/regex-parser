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
use RegexParser\Bridge\Symfony\Service\RouteValidationService;
use RegexParser\Bridge\Symfony\Service\ValidatorValidationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'regex:lint',
    description: 'Lints, validates, and optimizes constant preg_* patterns found in PHP files.',
)]
final class RegexLintCommand extends Command
{
    private readonly RelativePathHelper $pathHelper;

    private readonly LinkFormatter $linkFormatter;

    public function __construct(
        private readonly RegexAnalysisService $analysis,
        private readonly ?RouteValidationService $routeValidation = null,
        private readonly ?ValidatorValidationService $validatorValidation = null,
        ?string $editorUrl = null,
        private readonly array $paths = ['src'],
        private readonly array $exclude = ['vendor'],
        private readonly int $minSavings = 1,
    ) {
        $workDir = getcwd() ?: null;
        $this->pathHelper = new RelativePathHelper($workDir);
        $this->linkFormatter = new LinkFormatter($editorUrl, $this->pathHelper);
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('fail-on-warnings', null, InputOption::VALUE_NONE, 'Fail the command if warnings are found.');
        $this->addOption('fix', null, InputOption::VALUE_NONE, 'Automatically apply optimizations to files.');
        $this->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (console, json)', 'console');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        $failOnWarnings = $input->getOption('fail-on-warnings');
        $fix = $input->getOption('fix');

        $patterns = $this->analysis->scan($this->paths, $this->exclude);

        if ('json' === $format) {
            $issues = $this->analysis->lint($patterns);
            $optimizations = $this->analysis->suggestOptimizations($patterns, $this->minSavings);
            $results = $this->combineResults($issues, $optimizations, $patterns);

            $additionalResults = [];
            if ($this->routeValidation) {
                $routeIssues = $this->routeValidation->analyze();
                $additionalResults = array_merge($additionalResults, $this->convertAnalysisIssuesToResults($routeIssues, 'Symfony Router'));
            }
            if ($this->validatorValidation) {
                $validatorIssues = $this->validatorValidation->analyze();
                $additionalResults = array_merge($additionalResults, $this->convertAnalysisIssuesToResults($validatorIssues, 'Symfony Validator'));
            }
            $results = array_merge($results, $additionalResults);

            if ($fix) {
                $this->applyFixes($results);
            }
            $this->outputJsonResults($results, $output);

            return $this->determineJsonExitCode($results, $failOnWarnings);
        }

        $io = new SymfonyStyle($input, $output);

        $this->showHeader($io);
        $this->showScanMessage($io, $patterns);

        if (empty($patterns)) {
            $this->showNoPatternsMessage($io);
            $this->showFooter($io);

            return Command::SUCCESS;
        }

        $stats = $this->initializeStats();

        $allResults = $this->analyzePatternsIntegrated($io, $patterns);

        $additionalResults = [];
        if ($this->routeValidation) {
            $routeIssues = $this->routeValidation->analyze();
            $additionalResults = array_merge($additionalResults, $this->convertAnalysisIssuesToResults($routeIssues, 'Symfony Router'));
        }
        if ($this->validatorValidation) {
            $validatorIssues = $this->validatorValidation->analyze();
            $additionalResults = array_merge($additionalResults, $this->convertAnalysisIssuesToResults($validatorIssues, 'Symfony Validator'));
        }
        $allResults = array_merge($allResults, $additionalResults);

        if ($fix) {
            $this->applyFixes($allResults);
        }

        if (!empty($allResults)) {
            $this->outputIntegratedResults($io, $allResults, $fix);
            $stats = $this->updateStatsFromResults($stats, $allResults);
        }

        return $this->determineExitCode($io, $stats, $failOnWarnings);
    }

    private function scanFiles(SymfonyStyle $io): array
    {
        $io->write('  <fg=cyan>🔍 Scanning files...</>');
        $patterns = $this->analysis->scan($this->paths, $this->exclude);
        $io->writeln(' <fg=green;options=bold>✓</>');
        $io->writeln('');

        return $patterns;
    }

    private function showNoPatternsMessage(SymfonyStyle $io): void
    {
        $io->block('No constant preg_* patterns found.', 'INFO', 'fg=black;bg=blue', ' ', true);
    }

    private function showHeader(SymfonyStyle $io): void
    {
        $io->writeln('');
        $io->writeln('  <fg=white;options=bold>REGEX PARSER</> <fg=cyan>Linting & Optimization</>');
        $io->writeln('  <fg=gray>─────────────────────────────</>');
        $io->writeln('');
    }

    private function showFooter(SymfonyStyle $io): void
    {
        $io->writeln('  <fg=cyan>https://github.com/yoeunes/regex-parser</> ⭐ Give it a star! by Younes ENNAJI');
        $io->writeln('');
    }

    private function showScanMessage(SymfonyStyle $io, array $patterns): void
    {
        $io->write('  <fg=cyan>🔍 Scanning files...</>');
        $io->writeln(' <fg=green;options=bold>✓</>');
        $io->writeln('');
    }

    private function outputJsonResults(array $results, OutputInterface $output): void
    {
        $sanitized = array_map(function ($result) {
            $result['optimizations'] = array_map(function ($opt) {
                $opt['optimization'] = [
                    'original' => $opt['optimization']->original,
                    'optimized' => $opt['optimization']->optimized,
                ];
                return $opt;
            }, $result['optimizations']);
            return $result;
        }, $results);
        $output->write(json_encode($sanitized, JSON_PRETTY_PRINT));
    }

    private function determineJsonExitCode(array $results, bool $failOnWarnings): int
    {
        $errors = 0;
        $warnings = 0;
        foreach ($results as $result) {
            foreach ($result['issues'] as $issue) {
                if ('error' === $issue['type']) {
                    $errors++;
                } else {
                    $warnings++;
                }
            }
        }
        if ($errors > 0) {
            return Command::FAILURE;
        }
        if ($failOnWarnings && $warnings > 0) {
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    private function applyFixes(array $results): int
    {
        $fixesByFile = [];
        foreach ($results as $result) {
            foreach ($result['optimizations'] as $opt) {
                $file = $opt['file'];
                $line = $opt['line'];
                $original = $opt['optimization']->original;
                $optimized = $opt['optimization']->optimized;
                if (!isset($fixesByFile[$file])) {
                    $fixesByFile[$file] = [];
                }
                $fixesByFile[$file][] = [
                    'line' => $line,
                    'original' => $original,
                    'optimized' => $optimized,
                ];
            }
        }

        $fixedFiles = 0;
        foreach ($fixesByFile as $file => $fixes) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            foreach ($fixes as $fix) {
                $lineIndex = $fix['line'] - 1;
                if (isset($lines[$lineIndex])) {
                    $lines[$lineIndex] = str_replace($fix['original'], $fix['optimized'], $lines[$lineIndex]);
                }
            }
            file_put_contents($file, implode("\n", $lines));
            $fixedFiles++;
        }

        return $fixedFiles;
    }

    private function convertAnalysisIssuesToResults(array $issues, string $category): array
    {
        $results = [];
        foreach ($issues as $index => $issue) {
            $pattern = $issue->pattern;
            $message = $issue->message;
            $id = $issue->id;

            $file = $category;
            $location = null;

            if ($id && str_contains($id, ' (Route: ')) {
                [$filePart, $routePart] = explode(' (Route: ', $id, 2);
                $file = $filePart;
                $location = 'Route: ' . rtrim($routePart, ')');
            } elseif ($id) {
                $location = $id;
            }

            // Fallback parsing if not provided
            if (null === $pattern && preg_match('/pattern: ([^)]+)/', $message, $matches)) {
                $pattern = trim($matches[1], '#');
                $message = preg_replace('/ \(pattern: [^)]+\)/', '', $message);
            }

            if (null === $location && preg_match('/Route "([^"]+)"/', $message, $matches)) {
                $location = 'Route: ' . $matches[1];
                $message = preg_replace('/Route "[^"]+" /', '', $message);
            }

            $results[] = [
                'file' => $file,
                'line' => $index + 1, // Use index as line for uniqueness
                'pattern' => $pattern,
                'location' => $location,
                'issues' => [
                    [
                        'type' => $issue->isError ? 'error' : 'warning',
                        'message' => $message,
                        'file' => $file,
                        'line' => $index + 1,
                    ],
                ],
                'optimizations' => [],
            ];
        }
        return $results;
    }

    private function initializeStats(): array
    {
        return ['errors' => 0, 'warnings' => 0, 'optimizations' => 0];
    }

    private function analyzePatternsIntegrated(SymfonyStyle $io, array $patterns): array
    {
        if (empty($patterns)) {
            return [];
        }

        $bar = $this->createProgressBar($io, \count($patterns));
        $bar->start();

        $issues = $this->analysis->lint($patterns, static fn () => $bar->advance());
        $optimizations = $this->analysis->suggestOptimizations($patterns, $this->minSavings);

        $bar->finish();
        $io->writeln(['', '']);

        return $this->combineResults($issues, $optimizations, $patterns);
    }

    private function combineResults(array $issues, array $optimizations, array $originalPatterns): array
    {
        $results = [];

        // Create a lookup map for patterns by file and line
        $patternMap = [];
        foreach ($originalPatterns as $pattern) {
            $key = $pattern->file.':'.$pattern->line;
            $patternMap[$key] = $pattern->pattern;
        }

        // Cache file contents to avoid multiple reads
        $fileCache = [];

        // Group issues by file and line
        foreach ($issues as $issue) {
            $key = $issue['file'].':'.$issue['line'];
            if (!isset($results[$key])) {
                $results[$key] = [
                    'file' => $issue['file'],
                    'line' => $issue['line'],
                    'pattern' => $patternMap[$key] ?? null,
                    'issues' => [],
                    'optimizations' => [],
                ];
            }

            // Check for ignore comment on the previous line
            if (!isset($fileCache[$issue['file']])) {
                $fileCache[$issue['file']] = file_get_contents($issue['file']);
            }
            $lines = explode("\n", $fileCache[$issue['file']]);
            $prevLineIndex = $issue['line'] - 2; // 0-based index for previous line
            if ($prevLineIndex >= 0 && isset($lines[$prevLineIndex]) && str_contains($lines[$prevLineIndex], '// @regex-lint-ignore')) {
                continue; // Skip this issue
            }

            $results[$key]['issues'][] = $issue;
        }

        // Group optimizations by file and line
        foreach ($optimizations as $opt) {
            $key = $opt['file'].':'.$opt['line'];
            if (!isset($results[$key])) {
                $results[$key] = [
                    'file' => $opt['file'],
                    'line' => $opt['line'],
                    'pattern' => $patternMap[$key] ?? null,
                    'issues' => [],
                    'optimizations' => [],
                ];
            }
            $results[$key]['optimizations'][] = $opt;

            // Ensure we have pattern from optimization if no issue pattern found
            if (null === $results[$key]['pattern'] && !empty($opt['optimization']->original)) {
                $results[$key]['pattern'] = $opt['optimization']->original;
            }
        }

        return array_values($results);
    }

    private function createProgressBar(SymfonyStyle $io, int $total): ProgressBar
    {
        $bar = $io->createProgressBar($total);
        $bar->setEmptyBarCharacter('░');
        $bar->setProgressCharacter('█');
        $bar->setBarCharacter('█');
        $bar->setFormat('  <fg=blue>%bar%</> <fg=cyan>%percent:3s%%</> <fg=gray>%remaining:6s%</>');

        return $bar;
    }

    private function updateStatsFromResults(array $stats, array $results): array
    {
        foreach ($results as $result) {
            foreach ($result['issues'] as $issue) {
                if ('error' === $issue['type']) {
                    $stats['errors']++;
                } else {
                    $stats['warnings']++;
                }
            }
            $stats['optimizations'] += \count($result['optimizations']);
        }

        return $stats;
    }

    private function determineExitCode(SymfonyStyle $io, array $stats, bool $failOnWarnings): int
    {
        if (0 === $stats['errors']) {
            if ($failOnWarnings && $stats['warnings'] > 0) {
                $io->newLine();
                $io->writeln(
                    \sprintf('  <bg=red;fg=white;options=bold> FAIL </><fg=red;options=bold> %d warnings found and --fail-on-warnings is enabled</>', $stats['warnings']),
                );
                $io->newLine();
                $this->showFooter($io);

                return Command::FAILURE;
            }

            if (0 === $stats['warnings'] && 0 === $stats['optimizations']) {
                $io->writeln('');
                $io->block('No issues found. Your regex patterns are clean!', null, 'fg=black;bg=green', ' ', true);
            } else {
                $io->newLine();
                $io->writeln(
                    \sprintf('  <bg=blue;fg=white;options=bold> ℹ </><fg=white;options=bold> %d warnings</><fg=gray>, %d optimizations available.</>', $stats['warnings'], $stats['optimizations']));
                $io->newLine();
            }

            $this->showFooter($io);

            return Command::SUCCESS;
        }

        $io->newLine();
        $io->writeln(
            \sprintf('  <bg=red;fg=white;options=bold> FAIL </><fg=red;options=bold> %d invalid regex patterns found</>', $stats['errors']));
        $io->newLine();

        $this->showFooter($io);

        return Command::FAILURE;
    }

    private function outputIntegratedResults(SymfonyStyle $io, array $results, bool $fix): void
    {
        if (empty($results)) {
            return;
        }

        $io->writeln('  <fg=white;options=bold>Issues Found</>');
        $io->writeln('  <fg=gray>────────────────</>');
        $io->newLine();

        // Group results by file for better organization
        $byFile = [];
        foreach ($results as $result) {
            $byFile[$result['file']][] = $result;
        }

        foreach ($byFile as $file => $fileResults) {
            $this->showFileHeader($io, $file);

            foreach ($fileResults as $result) {
                $this->displayPatternResult($io, $result, $fix);
            }

            $io->writeln('');
        }
    }

    private function showFileHeader(SymfonyStyle $io, string $file): void
    {
        $relPath = $this->linkFormatter->getRelativePath($file);
        $io->writeln("  <fg=gray>in</> <fg=cyan;options=bold>{$relPath}</>");
        $io->writeln('  <fg=gray>───</>');
    }

    private function displayPatternResult(SymfonyStyle $io, array $result, bool $fix): void
    {
        $file = $result['file'];
        $line = $result['line'];
        $lineNum = str_pad((string) $line, 4);
        $link = $this->linkFormatter->format($file, $line, '✏️', 1, '✏️');

        // Show regex pattern if we have it
        $pattern = $this->extractPatternForResult($result);
        if (null !== $pattern) {
            try {
                $highlighted = $this->analysis->highlight(OutputFormatter::escape($pattern));
                $io->writeln(\sprintf('  <fg=gray>Pattern:</> <fg=white>%s</>', $highlighted));
            } catch (\Exception) {
                // If highlighting fails (e.g., invalid regex), show raw pattern with warning
                $io->writeln(\sprintf('  <fg=gray>Pattern:</> <fg=red>%s</> <fg=gray>(invalid)</>', OutputFormatter::escape($pattern)));
            }
        }

        // Show location if available (for Router/Validator issues)
        if (!empty($result['location'])) {
            $io->writeln(\sprintf('  <fg=gray>Location:</> <fg=white>%s</>', $result['location']));
        }

        // Display issues
        foreach ($result['issues'] as $issue) {
            $this->displaySingleIssue($io, $issue, $lineNum, $link);
        }

        // Display optimizations
        foreach ($result['optimizations'] as $opt) {
            $this->displayOptimization($io, $opt, $lineNum, $link, $fix);
        }

        // Add subtle spacing after this pattern result
        $io->writeln('');
    }

    private function extractPatternForResult(array $result): ?string
    {
        // First try to get pattern from result itself
        if (!empty($result['pattern'])) {
            return $result['pattern'];
        }

        // Try to get pattern from first issue
        if (!empty($result['issues'])) {
            $firstIssue = $result['issues'][0];
            if (isset($firstIssue['pattern']) && !empty($firstIssue['pattern'])) {
                return $firstIssue['pattern'];
            }
            if (isset($firstIssue['regex']) && !empty($firstIssue['regex'])) {
                return $firstIssue['regex'];
            }
        }

        // Try to get original pattern from first optimization
        if (!empty($result['optimizations'])) {
            $firstOpt = $result['optimizations'][0];
            if (isset($firstOpt['optimization']->original)) {
                return $firstOpt['optimization']->original;
            }
        }

        return null;
    }

    private function displaySingleIssue(SymfonyStyle $io, array $issue, string $lineNum, string $link): void
    {
        $isError = 'error' === $issue['type'];
        $color = $isError ? 'red' : 'yellow';
        $letter = $isError ? 'E' : 'W';

        $msg = $this->formatIssueMessage($issue['message']);
        [$firstLine, $restLines] = $this->splitMessage($msg);

        $io->writeln(\sprintf('  <fg=%s;options=bold>%s</>  <fg=white;options=bold>%s</>  %s  %s', $color, $letter, $lineNum, $link, $firstLine));

        $this->displayMessageLines($io, $restLines);

        if (!empty($issue['hint'])) {
            $io->writeln("         <fg=cyan>💡</> <fg=gray>{$issue['hint']}</>");
        }
    }

    private function displayOptimization(SymfonyStyle $io, array $opt, string $lineNum, string $link, bool $fix): void
    {
        if ($fix) {
            $io->writeln(\sprintf('  <fg=green;options=bold>0</>  <fg=white;options=bold>%s</>  %s  <fg=green>✅ Fixed</>', $lineNum, $link));
        } else {
            $io->writeln(\sprintf('  <fg=green;options=bold>0</>  <fg=white;options=bold>%s</>  %s  <fg=green>%d chars saved</>', $lineNum, $link, $opt['savings']));

            $original = $this->analysis->highlight(OutputFormatter::escape($opt['optimization']->original));
            $optimized = $this->analysis->highlight(OutputFormatter::escape($opt['optimization']->optimized));

            $io->writeln(\sprintf('         <fg=red>─</> %s', $original));
            $io->writeln(\sprintf('         <fg=green>✨</> %s', $optimized));
        }
    }

    private function formatIssueMessage(string $message): string
    {
        $raw = (string) $message;

        return $this->cleanMessageIndentation($raw);
    }

    private function splitMessage(string $message): array
    {
        $lines = explode("\n", $message);
        $first = array_shift($lines);

        return [$first, $lines];
    }

    private function displayMessageLines(SymfonyStyle $io, array $lines): void
    {
        foreach ($lines as $line) {
            $io->writeln('         <fg=gray>│</> <fg=white>'.$line.'</>');
        }
    }

    private function cleanMessageIndentation(string $message): string
    {
        return preg_replace_callback('/^Line \d+:/m', fn ($matches) => str_repeat(' ', \strlen($matches[0])), $message);
    }
}
