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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
        private RegexAnalysisService $analysis,
        ?string $editorUrl = null,
        private array $paths = ['src'],
        private array $exclude = ['vendor'],
        private int $minSavings = 1,
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

        $this->showHeader($io);
        
        $patterns = $this->scanFiles($io);
        
        if (empty($patterns)) {
            $this->showNoPatternsMessage($io);
            return Command::SUCCESS;
        }

        $stats = $this->initializeStats();
        
        $allResults = $this->analyzePatternsIntegrated($io, $patterns);
        
        if (!empty($allResults)) {
            $this->outputIntegratedResults($io, $allResults);
            $stats = $this->updateStatsFromResults($stats, $allResults);
        }

        return $this->determineExitCode($io, $stats);
    }

    private function showHeader(SymfonyStyle $io): void
    {
        $io->writeln('');
        $io->writeln('  <fg=white;options=bold>REGEX PARSER</> <fg=cyan>Linting & Optimization</>');
        $io->writeln('');
    }

    private function scanFiles(SymfonyStyle $io): array
    {
        $io->write('  <fg=cyan>🔍  Scanning files...</>');
        $patterns = $this->analysis->scan($this->paths, $this->exclude);
        $io->writeln(' <fg=green;options=bold>Done.</>');
        $io->writeln('');
        
        return $patterns;
    }

    private function showNoPatternsMessage(SymfonyStyle $io): void
    {
        $io->block('No constant preg_* patterns found.', 'INFO', 'fg=black;bg=blue', ' ', true);
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

        $bar = $this->createProgressBar($io, count($patterns));
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
            $key = $pattern->file . ':' . $pattern->line;
            $patternMap[$key] = $pattern->pattern;
        }
        
        // Group issues by file and line
        foreach ($issues as $issue) {
            $key = $issue['file'] . ':' . $issue['line'];
            if (!isset($results[$key])) {
                $results[$key] = [
                    'file' => $issue['file'],
                    'line' => $issue['line'],
                    'pattern' => $patternMap[$key] ?? null,
                    'issues' => [],
                    'optimizations' => [],
                ];
            }
            $results[$key]['issues'][] = $issue;
        }
        
        // Group optimizations by file and line
        foreach ($optimizations as $opt) {
            $key = $opt['file'] . ':' . $opt['line'];
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
            if ($results[$key]['pattern'] === null && !empty($opt['optimization']->original)) {
                $results[$key]['pattern'] = $opt['optimization']->original;
            }
        }
        
        return array_values($results);
    }

    private function createProgressBar(SymfonyStyle $io, int $total): \Symfony\Component\Console\Helper\ProgressBar
    {
        $bar = $io->createProgressBar($total);
        $bar->setEmptyBarCharacter('░');
        $bar->setProgressCharacter('');
        $bar->setBarCharacter('▓');
        $bar->setFormat('  <fg=blue>%bar%</> <fg=cyan>%percent:3s%%</>');
        
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
            $stats['optimizations'] += count($result['optimizations']);
        }
        
        return $stats;
    }

    private function determineExitCode(SymfonyStyle $io, array $stats): int
    {
        if (0 === $stats['errors']) {
            if (0 === $stats['warnings'] && 0 === $stats['optimizations']) {
                $io->block('No issues found. Your regex patterns are clean.', 'PASS', 'fg=black;bg=green', ' ', true);
            } else {
                $io->newLine();
                $io->writeln(\sprintf('  <bg=blue;fg=white;options=bold> INFO </><fg=white;options=bold> %d warnings</><fg=gray>, %d optimizations.</>', $stats['warnings'], $stats['optimizations']));
                $io->newLine();
            }

            return Command::SUCCESS;
        }

        $io->newLine();
        $io->writeln(\sprintf('  <bg=red;fg=white;options=bold> FAIL </><fg=red;options=bold> %d invalid regex patterns</>', $stats['errors']));
        $io->newLine();

        return Command::FAILURE;
    }

    private function outputIntegratedResults(SymfonyStyle $io, array $results): void
    {
        if (empty($results)) {
            return;
        }

        $io->writeln('  <fg=white;options=bold>Issues Found</>');
        $io->newLine();

        // Group results by file for better organization
        $byFile = [];
        foreach ($results as $result) {
            $byFile[$result['file']][] = $result;
        }

        foreach ($byFile as $file => $fileResults) {
            $this->showFileHeader($io, $file);
            
            foreach ($fileResults as $result) {
                $this->displayPatternResult($io, $result);
            }
            
            $io->writeln('');
        }
    }

    private function showFileHeader(SymfonyStyle $io, string $file): void
    {
        $relPath = $this->linkFormatter->getRelativePath($file);
        $io->writeln("  <fg=gray>in</> <fg=cyan;options=bold>{$relPath}</>");
    }

    private function displayPatternResult(SymfonyStyle $io, array $result): void
    {
        $file = $result['file'];
        $line = $result['line'];
        $lineNum = str_pad((string) $line, 4);
        $link = $this->linkFormatter->format($file, $line, '✏️', 1, '✏️');

        // Show the regex pattern if we have it
        $pattern = $this->extractPatternForResult($result);
        if ($pattern !== null) {
            try {
                $highlighted = $this->analysis->highlight($pattern);
                $io->writeln(\sprintf('  <fg=gray>Pattern:</> %s', $highlighted));
            } catch (\Exception $e) {
                // If highlighting fails (e.g., invalid regex), show raw pattern
                $io->writeln(\sprintf('  <fg=gray>Pattern:</> %s', $pattern));
            }
        }

        // Display issues
        foreach ($result['issues'] as $issue) {
            $this->displaySingleIssue($io, $issue, $lineNum, $link);
        }

        // Display optimizations
        foreach ($result['optimizations'] as $opt) {
            $this->displayOptimization($io, $opt, $lineNum, $link);
        }
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
        list($firstLine, $restLines) = $this->splitMessage($msg);

        $io->writeln(\sprintf('  <fg=%s;options=bold>%s</>  <fg=white;options=bold>%s</>  %s  %s', $color, $letter, $lineNum, $link, $firstLine));

        $this->displayMessageLines($io, $restLines);

        if (!empty($issue['hint'])) {
            $io->writeln("         <fg=cyan>💡</> <fg=cyan>{$issue['hint']}</>");
        }
    }

    private function displayOptimization(SymfonyStyle $io, array $opt, string $lineNum, string $link): void
    {
        $io->writeln(\sprintf('  <fg=green;options=bold>O</>  <fg=white;options=bold>%s</>  %s  <fg=green>Saved %d chars</>', $lineNum, $link, $opt['savings']));

        $original = $this->analysis->highlight($opt['optimization']->original);
        $optimized = $this->analysis->highlight($opt['optimization']->optimized);

        $io->writeln(\sprintf('         <fg=red>-</> %s', $original));
        $io->writeln(\sprintf('         <fg=green>+</> %s', $optimized));
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
            $io->writeln('              '.$line);
        }
    }



    private function cleanMessageIndentation(string $message): string
    {
        return preg_replace_callback('/^Line \d+:/m', fn ($matches) => str_repeat(' ', \strlen($matches[0])), $message);
    }
}
