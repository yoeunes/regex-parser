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
        
        $issues = $this->analyzePatterns($io, $patterns);
        $stats = $this->updateStatsWithIssues($stats, $issues);
        
        if (!empty($issues)) {
            $this->outputLintIssues($io, $issues);
        }

        $optimizations = $this->getOptimizations($patterns);
        $stats = $this->updateStatsWithOptimizations($stats, $optimizations);

        if (!empty($optimizations)) {
            $this->outputOptimizationSuggestions($io, $optimizations);
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

    private function analyzePatterns(SymfonyStyle $io, array $patterns): array
    {
        if (empty($patterns)) {
            return [];
        }

        $bar = $this->createProgressBar($io, count($patterns));
        $bar->start();

        $issues = $this->analysis->lint($patterns, static fn () => $bar->advance());

        $bar->finish();
        $io->writeln(['', '']);

        return $issues;
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

    private function updateStatsWithIssues(array $stats, array $issues): array
    {
        foreach ($issues as $issue) {
            if ('error' === $issue['type']) {
                $stats['errors']++;
            } else {
                $stats['warnings']++;
            }
        }
        
        return $stats;
    }

    private function getOptimizations(array $patterns): array
    {
        if (empty($patterns)) {
            return [];
        }

        return $this->analysis->suggestOptimizations($patterns, $this->minSavings);
    }

    private function updateStatsWithOptimizations(array $stats, array $optimizations): array
    {
        if (!empty($optimizations)) {
            $stats['optimizations'] += count($optimizations);
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

    private function outputLintIssues(SymfonyStyle $io, array $issues): void
    {
        $io->writeln('  <fg=white;options=bold>Issues Found</>');
        $io->newLine();

        $grouped = $this->groupIssuesByFile($issues);

        foreach ($grouped as $file => $fileIssues) {
            $this->showFileHeader($io, $file);
            $this->displayFileIssues($io, $file, $fileIssues);
        }
    }

    private function groupIssuesByFile(array $issues): array
    {
        $grouped = [];
        foreach ($issues as $issue) {
            $grouped[$issue['file']][] = $issue;
        }
        
        return $grouped;
    }

    private function showFileHeader(SymfonyStyle $io, string $file): void
    {
        $relPath = $this->linkFormatter->getRelativePath($file);
        $io->writeln("  <fg=gray>in</> <fg=cyan;options=bold>{$relPath}</>");
    }

    private function displayFileIssues(SymfonyStyle $io, string $file, array $fileIssues): void
    {
        foreach ($fileIssues as $issue) {
            $this->displaySingleIssue($io, $file, $issue);
        }
        $io->writeln('');
    }

    private function displaySingleIssue(SymfonyStyle $io, string $file, array $issue): void
    {
        $isError = 'error' === $issue['type'];
        $color = $isError ? 'red' : 'yellow';
        $letter = $isError ? 'E' : 'W';
        $line = $issue['line'];
        $lineNum = str_pad((string) $line, 4);
        $link = $this->linkFormatter->format($file, $line, '✏️', 1, '✏️');

        $msg = $this->formatIssueMessage($issue['message']);
        list($firstLine, $restLines) = $this->splitMessage($msg);

        $io->writeln(\sprintf('  <fg=%s;options=bold>%s</>  <fg=white;options=bold>%s</>  %s  %s', $color, $letter, $lineNum, $link, $firstLine));

        $this->displayMessageLines($io, $restLines);

        if (!empty($issue['hint'])) {
            $io->writeln("         <fg=cyan>💡</> <fg=cyan>{$issue['hint']}</>");
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
            $io->writeln('              '.$line);
        }
    }

    private function outputOptimizationSuggestions(SymfonyStyle $io, array $suggestions): void
    {
        $io->writeln('  <fg=green;options=bold>Optimizations</>');
        $io->newLine();

        foreach ($suggestions as $item) {
            $this->displayOptimizationItem($io, $item);
        }
    }

    private function displayOptimizationItem(SymfonyStyle $io, array $item): void
    {
        $this->showOptimizationHeader($io, $item);
        $this->showOptimizationDiff($io, $item);
        $io->writeln('');
    }

    private function showOptimizationHeader(SymfonyStyle $io, array $item): void
    {
        $relPath = $this->linkFormatter->getRelativePath($item['file']);
        $line = $item['line'];
        $lineNum = str_pad((string) $line, 4);
        $link = $this->linkFormatter->format($item['file'], $line, '✏️', 1, '✏️');

        $io->writeln(\sprintf('  <fg=green;options=bold>O</>  <fg=white;options=bold>%s</>  %s  <fg=green>Saved %d chars</> <fg=gray>in</> <fg=cyan;options=bold>%s</>', $lineNum, $link, $item['savings'], $relPath));
    }

    private function showOptimizationDiff(SymfonyStyle $io, array $item): void
    {
        $original = $this->analysis->highlight($item['optimization']->original);
        $optimized = $this->analysis->highlight($item['optimization']->optimized);

        $io->writeln(\sprintf('         <fg=red>-</> %s', $original));
        $io->writeln(\sprintf('         <fg=green>+</> %s', $optimized));
    }

    private function cleanMessageIndentation(string $message): string
    {
        return preg_replace_callback('/^Line \d+:/m', fn ($matches) => str_repeat(' ', \strlen($matches[0])), $message);
    }
}
