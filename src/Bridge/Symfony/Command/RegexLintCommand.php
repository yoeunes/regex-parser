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
    private readonly RelativePathHelper $relativePathHelper;

    private readonly LinkFormatter $linkFormatter;

    public function __construct(
        private RegexAnalysisService $regexAnalysis,
        ?string $editorUrl = null,
        private array $defaultPaths = ['src'],
        private array $excludePaths = ['vendor'],
        private int $minOptimizationSavings = 1,
    ) {
        $workingDirectory = getcwd() ?: null;
        $this->relativePathHelper = new RelativePathHelper($workingDirectory);
        $this->linkFormatter = new LinkFormatter($editorUrl, $this->relativePathHelper);
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->writeln('');
        $io->writeln('  <fg=white;options=bold>REGEX PARSER</> <fg=cyan>Linting & Optimization</>');
        $io->writeln('');

        $io->write('  <fg=cyan>🔍  Scanning files...</>');
        $patterns = $this->regexAnalysis->scan($this->defaultPaths, $this->excludePaths);
        $io->writeln(' <fg=green;options=bold>Done.</>');
        $io->writeln('');

        if ([] === $patterns) {
            $io->block('No constant preg_* patterns found.', 'INFO', 'fg=black;bg=blue', ' ', true);

            return Command::SUCCESS;
        }

        $stats = ['errors' => 0, 'warnings' => 0, 'optimizations' => 0];

        $issues = [];
        if (!empty($patterns)) {
            $progressBar = $io->createProgressBar(\count($patterns));
            $progressBar->setEmptyBarCharacter('░');
            $progressBar->setProgressCharacter('');
            $progressBar->setBarCharacter('▓');
            $progressBar->setFormat('  <fg=blue>%bar%</> <fg=cyan>%percent:3s%%</>');
            $progressBar->start();

            $issues = $this->regexAnalysis->lint($patterns, static fn () => $progressBar->advance());

            $progressBar->finish();
            $io->writeln(['', '']);
        }

        foreach ($issues as $issue) {
            if ('error' === $issue['type']) {
                $stats['errors']++;
            } else {
                $stats['warnings']++;
            }
        }

        if (!empty($issues)) {
            $this->outputLintIssues($io, $issues);
        }

        $optimizations = [];
        if (!empty($patterns)) {
            $optimizations = $this->regexAnalysis->suggestOptimizations($patterns, $this->minOptimizationSavings);
            if (!empty($optimizations)) {
                $stats['optimizations'] += \count($optimizations);
            }
        }

        if (!empty($optimizations)) {
            $this->outputOptimizationSuggestions($io, $optimizations);
        }

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

        $issuesByFile = [];
        foreach ($issues as $issue) {
            $issuesByFile[$issue['file']][] = $issue;
        }

        foreach ($issuesByFile as $file => $fileIssues) {
            $relFile = $this->linkFormatter->getRelativePath($file);
            $io->writeln("  <fg=gray>in</> <fg=cyan;options=bold>{$relFile}</>");

            foreach ($fileIssues as $issue) {
                $isError = 'error' === $issue['type'];
                $color = $isError ? 'red' : 'yellow';
                $letter = $isError ? 'E' : 'W';
                $line = $issue['line'];
                $lineLabel = str_pad((string) $line, 4);
                $penLink = $this->linkFormatter->format($file, $line, '✏️', 1, '✏️');

                $messageRaw = (string) $issue['message'];
                $cleanMessage = $this->cleanMessageIndentation($messageRaw);

                $lines = explode("\n", $cleanMessage);
                $firstLine = array_shift($lines);

                $io->writeln(\sprintf('  <fg=%s;options=bold>%s</>  <fg=white;options=bold>%s</>  %s  %s', $color, $letter, $lineLabel, $penLink, $firstLine));

                foreach ($lines as $msgLine) {
                    $io->writeln('              '.$msgLine);
                }

                if (isset($issue['hint']) && $issue['hint']) {
                    $io->writeln("         <fg=cyan>💡</> <fg=cyan>{$issue['hint']}</>");
                }
            }
            $io->writeln('');
        }
    }

    private function outputOptimizationSuggestions(SymfonyStyle $io, array $suggestions): void
    {
        $io->writeln('  <fg=green;options=bold>Optimizations</>');
        $io->newLine();

        foreach ($suggestions as $item) {
            $relFile = $this->linkFormatter->getRelativePath($item['file']);
            $line = $item['line'];
            $lineLabel = str_pad((string) $line, 4);
            $penLink = $this->linkFormatter->format($item['file'], $line, '✏️', 1, '✏️');

            $io->writeln(\sprintf('  <fg=green;options=bold>O</>  <fg=white;options=bold>%s</>  %s  <fg=green>Saved %d chars</> <fg=gray>in</> <fg=cyan;options=bold>%s</>', $lineLabel, $penLink, $item['savings'], $relFile));

            $original = $this->regexAnalysis->highlight($item['optimization']->original);
            $optimized = $this->regexAnalysis->highlight($item['optimization']->optimized);

            $io->writeln(\sprintf('         <fg=red>-</> %s', $original));
            $io->writeln(\sprintf('         <fg=green>+</> %s', $optimized));
            $io->writeln('');
        }
    }

    private function cleanMessageIndentation(string $message): string
    {
        return preg_replace_callback('/^Line \d+:/m', fn ($matches) => str_repeat(' ', \strlen($matches[0])), $message);
    }
}
