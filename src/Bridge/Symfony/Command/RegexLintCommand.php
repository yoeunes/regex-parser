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
use Symfony\Component\Console\Input\InputInterface;
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->newLine();
        $io->writeln('  <fg=white;options=bold>Regex Parser</> <fg=gray>linting...</>');
        $io->newLine();

        $patterns = $this->analysis->scan($this->paths, $this->exclude);

        if (empty($patterns)) {
            $this->renderSummary($io, []);

            return Command::SUCCESS;
        }

        $stats = $this->initializeStats();
        $allResults = $this->analyzePatternsIntegrated($patterns);

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

        if (!empty($allResults)) {
            usort($allResults, fn ($a, $b) => $this->getSeverityScore($b) <=> $this->getSeverityScore($a));

            $this->outputIntegratedResults($io, $allResults);
            $stats = $this->updateStatsFromResults($stats, $allResults);
        }

        $exitCode = $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
        $this->renderSummary($io, $stats);

        return $exitCode;
    }

    private function analyzePatternsIntegrated(array $patterns): array
    {
        if (empty($patterns)) {
            return [];
        }

        $issues = $this->analysis->lint($patterns);
        $optimizations = $this->analysis->suggestOptimizations($patterns, $this->minSavings);

        return $this->combineResults($issues, $optimizations, $patterns);
    }

    private function outputIntegratedResults(SymfonyStyle $io, array $results): void
    {
        if (empty($results)) {
            return;
        }

        $byFile = [];
        foreach ($results as $result) {
            $byFile[$result['file']][] = $result;
        }

        foreach ($byFile as $file => $fileResults) {
            $this->showFileHeader($io, $file);

            foreach ($fileResults as $result) {
                $this->displayPatternResult($io, $result);
            }
        }
    }

    private function showFileHeader(SymfonyStyle $io, string $file): void
    {
        $relPath = $this->linkFormatter->getRelativePath($file);
        $io->writeln(\sprintf('  <fg=white;bg=gray;options=bold> %s </>', $relPath));
    }

    private function displayPatternResult(SymfonyStyle $io, array $result): void
    {
        $file = $result['file'];
        $line = $result['line'];

        $pattern = $this->extractPatternForResult($result);

        // Always display the regex context first so the user knows what failed
        if (null !== $pattern) {
            try {
                $highlighted = $this->analysis->highlight(OutputFormatter::escape($pattern));
                $io->writeln(\sprintf('  <fg=gray>%d:</> %s', $line, $highlighted));
            } catch (\Exception) {
                // If highlighting fails, show raw pattern
                $io->writeln(\sprintf('  <fg=gray>%d:</> %s', $line, OutputFormatter::escape($pattern)));
            }
        } else {
            // Fallback if no pattern found (e.g. general file error)
            $link = $this->linkFormatter->format($file, $line, 'line '.$line, 1, (string) $line);
            $io->writeln(\sprintf('  <fg=gray>%s:</>', $link));
        }

        // Display Issues
        foreach ($result['issues'] as $issue) {
            $isError = 'error' === $issue['type'];
            $badge = $isError
                ? '<bg=red;fg=white;options=bold> FAIL </>'
                : '<bg=yellow;fg=black;options=bold> WARN </>';

            $this->displaySingleIssue($io, $badge, $issue['message']);

            if (!empty($issue['hint'])) {
                $io->writeln(\sprintf('         <fg=gray>↳ %s</>', $issue['hint']));
            }
        }

        // Display Optimizations
        foreach ($result['optimizations'] as $opt) {
            $io->writeln(\sprintf(
                '    <bg=blue;fg=white;options=bold> FIX </> <fg=white>Optimization available</> <fg=gray>(saved %d chars)</>',
                $opt['savings'],
            ));

            $original = $this->analysis->highlight(OutputFormatter::escape($opt['optimization']->original));
            $optimized = $this->analysis->highlight(OutputFormatter::escape($opt['optimization']->optimized));

            $io->writeln(\sprintf('         <fg=red>- %s</>', $original));
            $io->writeln(\sprintf('         <fg=green>+ %s</>', $optimized));
        }

        $io->newLine();
    }

    private function displaySingleIssue(SymfonyStyle $io, string $badge, string $message): void
    {
        // Split message by newline to handle carets/pointers correctly
        $lines = explode("\n", $message);
        $firstLine = array_shift($lines);

        // Print the primary error message on the same line as the badge
        $io->writeln(\sprintf('    %s <fg=white>%s</>', $badge, $this->cleanMessageIndentation($firstLine)));

        // Print subsequent lines (like regex pointers ^) with indentation preserved
        if (!empty($lines)) {
            foreach ($lines as $index => $line) {
                $io->writeln(\sprintf('         <fg=gray>%s %s</>', 0 === $index ? '↳' : ' ', $this->cleanMessageIndentation($line)));
            }
        }
    }

    private function renderSummary(SymfonyStyle $io, array $stats): void
    {
        $errors = $stats['errors'] ?? 0;
        $warnings = $stats['warnings'] ?? 0;
        $optimizations = $stats['optimizations'] ?? 0;

        $io->newLine();

        if (0 === $errors && 0 === $warnings && 0 === $optimizations) {
            $io->block(
                messages: ['PASS', 'No regex issues found.'],
                type: 'OK',
                style: 'fg=black;bg=green',
                padding: true,
            );

            return;
        }

        if ($errors > 0) {
            $io->block(
                messages: [
                    'FAIL',
                    \sprintf('%d patterns invalid, %d warnings, %d optimizations.', $errors, $warnings, $optimizations),
                ],
                type: 'ERROR',
                style: 'fg=white;bg=red',
                padding: true,
            );
        } else {
            $io->block(
                messages: [
                    'DONE',
                    \sprintf('%d warnings found, %d optimizations available.', $warnings, $optimizations),
                ],
                style: 'fg=black;bg=yellow',
                padding: true,
            );
        }

        $io->writeln('  <fg=gray>Star the repo: https://github.com/yoeunes/regex-parser</>');
        $io->newLine();
    }

    private function combineResults(array $issues, array $optimizations, array $originalPatterns): array
    {
        $results = [];

        $patternMap = [];
        foreach ($originalPatterns as $pattern) {
            $key = $pattern->file.':'.$pattern->line;
            $patternMap[$key] = $pattern->pattern;
        }

        $fileCache = [];

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

            if (!isset($fileCache[$issue['file']])) {
                $fileCache[$issue['file']] = @file_get_contents($issue['file']) ?: '';
            }
            $lines = explode("\n", $fileCache[$issue['file']]);
            $prevLineIndex = $issue['line'] - 2;
            if ($prevLineIndex >= 0 && isset($lines[$prevLineIndex]) && str_contains($lines[$prevLineIndex], '// @regex-lint-ignore')) {
                continue;
            }

            $results[$key]['issues'][] = $issue;
        }

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

            if (null === $results[$key]['pattern'] && !empty($opt['optimization']->original)) {
                $results[$key]['pattern'] = $opt['optimization']->original;
            }
        }

        return array_values($results);
    }

    private function extractPatternForResult(array $result): ?string
    {
        if (!empty($result['pattern'])) {
            return $result['pattern'];
        }

        if (!empty($result['issues'])) {
            $firstIssue = $result['issues'][0];
            if (!empty($firstIssue['pattern'])) {
                return $firstIssue['pattern'];
            }
            if (!empty($firstIssue['regex'])) {
                return $firstIssue['regex'];
            }
        }

        if (!empty($result['optimizations'])) {
            $firstOpt = $result['optimizations'][0];
            if (isset($firstOpt['optimization']->original)) {
                return $firstOpt['optimization']->original;
            }
        }

        return null;
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

            if ($id && str_contains((string) $id, ' (Route: ')) {
                [$filePart, $routePart] = explode(' (Route: ', (string) $id, 2);
                $file = $filePart;
                $location = 'Route: '.rtrim($routePart, ')');
            } elseif ($id) {
                $location = $id;
            }

            if (null === $pattern && preg_match('/pattern: ([^)]+)/', (string) $message, $matches)) {
                $pattern = trim($matches[1], '#');
                $message = preg_replace('/ \(pattern: [^)]+\)/', '', (string) $message);
            }

            if (null === $location && preg_match('/Route "([^"]+)"/', (string) $message, $matches)) {
                $location = 'Route: '.$matches[1];
                $message = preg_replace('/Route "[^"]+" /', '', (string) $message);
            }

            $results[] = [
                'file' => $file,
                'line' => $index + 1,
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

    private function initializeStats(): array
    {
        return ['errors' => 0, 'warnings' => 0, 'optimizations' => 0];
    }

    private function getSeverityScore(array $result): int
    {
        $score = 0;
        foreach ($result['issues'] as $issue) {
            if ('error' === $issue['type']) {
                $score += 100;
            }
            if ('warning' === $issue['type']) {
                $score += 10;
            }
        }

        return $score + \count($result['optimizations']);
    }

    private function cleanMessageIndentation(string $message): string
    {
        return preg_replace_callback('/^Line \d+:/m', fn ($matches) => str_repeat(' ', \strlen($matches[0])), $message);
    }
}
