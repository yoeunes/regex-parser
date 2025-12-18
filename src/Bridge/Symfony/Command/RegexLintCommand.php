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
use RegexParser\ReDoS\ReDoSSeverity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'regex:lint',
    description: 'Lints, validates, optimizes, and analyzes ReDoS risk for constant preg_* patterns found in PHP files.',
)]
final class RegexLintCommand extends Command
{
    protected static ?string $defaultName = 'regex:lint';

    protected static ?string $defaultDescription = 'Lints, validates, optimizes, and analyzes ReDoS risk for constant preg_* patterns found in PHP files.';

    private readonly RelativePathHelper $relativePathHelper;
    private readonly LinkFormatter $linkFormatter;

    public function __construct(
        private readonly RegexAnalysisService $regexAnalysis,
        private readonly ?RouteValidationService $routeValidation = null,
        private readonly ?ValidatorValidationService $validatorValidation = null,
        private readonly ?string $editorFormat = null,
        private readonly array $defaultPaths = ['src'],
        private readonly array $excludePaths = ['vendor'],
        private readonly string $defaultRedosThreshold = 'high',
    ) {
        $workingDirectory = getcwd() ?: null;
        $this->relativePathHelper = new RelativePathHelper($workingDirectory);
        $this->linkFormatter = new LinkFormatter($this->editorFormat, $this->relativePathHelper);
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY,
                'Files/directories to scan (defaults to current directory).',
            )
            ->addOption(
                'fail-on-warnings',
                null,
                InputOption::VALUE_NONE,
                'Exit with a non-zero code when warnings are found.',
            )
            ->addOption('analyze-redos', null, InputOption::VALUE_NONE, 'Analyze patterns for ReDoS risk.')
            ->addOption(
                'redos-threshold',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum ReDoS severity to report (safe|low|medium|high|critical).',
                $this->defaultRedosThreshold,
            )
            ->addOption('optimize', null, InputOption::VALUE_NONE, 'Suggest safe optimizations for patterns.')
            ->addOption(
                'min-savings',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum character savings to report for optimizations.',
                1,
            )
            ->addOption(
                'validate-symfony',
                null,
                InputOption::VALUE_NONE,
                'Validate regex usage in Symfony routes and validators.',
            )
            ->addOption(
                'fail-on-suggestions',
                null,
                InputOption::VALUE_NONE,
                'Exit with a non-zero code when optimization suggestions are found.',
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'Run all analyses (lint, ReDoS, optimization, and Symfony validation).',
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->writeln('');
        $io->writeln('  <fg=white;options=bold>REGEX PARSER</> <fg=cyan>Linting & Analysis</>');
        $io->writeln('');

        /** @var list<string> $paths */
        $paths = $input->getArgument('paths');
        if ([] === $paths) {
            $paths = $this->defaultPaths;
        }

        $runAll = (bool) $input->getOption('all');
        $analyzeRedos = $runAll || (bool) $input->getOption('analyze-redos');
        $optimize = $runAll || (bool) $input->getOption('optimize');
        $validateSymfony = $runAll || (bool) $input->getOption('validate-symfony');

        $io->write('  <fg=cyan>🔍  Scanning files...</>');
        $patterns = $this->regexAnalysis->scan($paths, $this->excludePaths);
        $io->writeln(' <fg=green;options=bold>Done.</>');
        $io->writeln('');

        if ([] === $patterns && !$validateSymfony) {
            $io->block('No constant preg_* patterns found.', 'INFO', 'fg=black;bg=blue', ' ', true);

            return Command::SUCCESS;
        }

        $hasErrors = false;
        $hasWarnings = false;
        $hasSuggestions = false;
        $stats = ['errors' => 0, 'warnings' => 0, 'optimizations' => 0, 'redos' => 0];

        // 1. Basic Linting
        $lintIssues = [];
        if (!empty($patterns)) {
            $progressBar = $io->createProgressBar(\count($patterns));
            $progressBar->setEmptyBarCharacter('░');
            $progressBar->setProgressCharacter('');
            $progressBar->setBarCharacter('▓');
            $progressBar->setFormat('  <fg=blue>%bar%</> <fg=cyan>%percent:3s%%</>');
            $progressBar->start();

            $lintIssues = $this->regexAnalysis->lint(
                $patterns,
                static fn () => $progressBar->advance(),
            );

            $progressBar->finish();
            $io->writeln(['', '']);
        }

        foreach ($lintIssues as $issue) {
            if ('error' === $issue['type']) {
                $hasErrors = true;
                $stats['errors']++;
            } else {
                $hasWarnings = true;
                $stats['warnings']++;
            }
        }

        if (!empty($lintIssues)) {
            $this->outputLintIssues($io, $lintIssues);
        }

        // 2. ReDoS Analysis
        $redosIssues = [];
        if ($analyzeRedos && !empty($patterns)) {
            $redosThreshold = (string) $input->getOption('redos-threshold');
            $severityThreshold = ReDoSSeverity::tryFrom(strtolower($redosThreshold)) ?? ReDoSSeverity::HIGH;

            $redosIssues = $this->regexAnalysis->analyzeRedos($patterns, $severityThreshold);
            $hasErrors = $hasErrors || !empty($redosIssues);
            $stats['redos'] += \count($redosIssues);
        }

        if (!empty($redosIssues)) {
            $this->outputRedosIssues($io, $redosIssues);
        }

        // 3. Optimizations
        $optimizationSuggestions = [];
        if ($optimize && !empty($patterns)) {
            $minSavings = (int) $input->getOption('min-savings');
            if ($minSavings < 0) {
                $minSavings = 0;
            }

            $optimizationSuggestions = $this->regexAnalysis->suggestOptimizations($patterns, $minSavings);
            if (!empty($optimizationSuggestions)) {
                $hasSuggestions = true;
                $stats['optimizations'] += \count($optimizationSuggestions);
            }
        }

        if (!empty($optimizationSuggestions)) {
            $this->outputOptimizationSuggestions($io, $optimizationSuggestions);
        }

        // 4. Symfony Validation
        $validationIssues = [];
        if ($validateSymfony) {
            if ($this->routeValidation?->isSupported()) {
                $validationIssues = array_merge(
                    $validationIssues,
                    $this->routeValidation->analyze(),
                );
            } else {
                $io->writeln('  <fg=yellow>No router service was found; skipping Symfony route validation.</>');
            }

            if ($this->validatorValidation?->isSupported()) {
                $validationIssues = array_merge(
                    $validationIssues,
                    $this->validatorValidation->analyze(),
                );
            } else {
                $io->writeln('  <fg=yellow>No validator service was found; skipping Symfony validator checks.</>');
            }
        }

        if (!empty($validationIssues)) {
            $this->outputValidationIssues($io, $validationIssues);
        }

        // Final Status
        $allHasErrors = $hasErrors || !empty(array_filter($validationIssues, fn ($i) => $i->isError));
        $allHasWarnings = $hasWarnings || !empty(array_filter($validationIssues, fn ($i) => !$i->isError));

        if (!$allHasErrors && !$allHasWarnings && !$hasSuggestions) {
            $io->block('No issues found. Your regex patterns are clean.', 'PASS', 'fg=black;bg=green', ' ', true);

            return Command::SUCCESS;
        }

        $failOnWarnings = (bool) $input->getOption('fail-on-warnings');
        $failOnSuggestions = (bool) $input->getOption('fail-on-suggestions');
        $failed = $allHasErrors || ($failOnWarnings && $allHasWarnings) || ($failOnSuggestions && $hasSuggestions);

        if ($failed) {
            $io->newLine();
            $io->writeln(
                \sprintf(
                    '  <bg=red;fg=white;options=bold> FAIL </><fg=red;options=bold> %d errors</><fg=gray>, %d warnings, %d suggestions.</>',
                    $stats['errors'] + $stats['redos'],
                    $stats['warnings'],
                    $stats['optimizations'],
                ),
            );
            $io->newLine();

            return Command::FAILURE;
        }

        $io->newLine();
        $io->writeln(
            \sprintf(
                '  <bg=yellow;fg=black;options=bold> WARN </><fg=yellow;options=bold> %d warnings</><fg=gray>, %d suggestions.</>',
                $stats['warnings'],
                $stats['optimizations'],
            ),
        );
        $io->newLine();

        return Command::SUCCESS;
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
            // File path: Cyan and Bold
            $io->writeln("  <fg=gray>in</> <fg=cyan;options=bold>{$relFile}</>");

            foreach ($fileIssues as $issue) {
                $isError = 'error' === $issue['type'];
                $color = $isError ? 'red' : 'yellow';
                $letter = $isError ? 'E' : 'W';
                $line = $issue['line'];
                $lineLabel = str_pad((string) $line, 4);
                $penLink = $this->linkFormatter->format($file, $line, '✏️', 1, '✏️');

                // Process message to remove "Line 1:" and align
                $messageRaw = (string) $issue['message'];
                $cleanMessage = $this->cleanMessageIndentation($messageRaw);

                $lines = explode("\n", $cleanMessage);
                $firstLine = array_shift($lines);

                // Line number: White and Bold
                $io->writeln(
                    \sprintf(
                        '  <fg=%s;options=bold>%s</>  <fg=white;options=bold>%s</>  %s  %s',
                        $color,
                        $letter,
                        $lineLabel,
                        $penLink,
                        $firstLine,
                    ),
                );

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

    private function outputRedosIssues(SymfonyStyle $io, array $issues): void
    {
        $io->writeln('  <fg=red;options=bold>ReDoS Vulnerabilities</>');
        $io->newLine();

        foreach ($issues as $issue) {
            $relFile = $this->linkFormatter->getRelativePath($issue['file']);
            $line = $issue['line'];
            $severity = strtoupper($issue['analysis']->severity->value);
            $lineLabel = str_pad((string) $line, 4);
            $penLink = $this->linkFormatter->format($issue['file'], $line, '✏️', 1, '✏️');

            $io->writeln(
                \sprintf(
                    '  <fg=red;options=bold>R</>  <fg=white;options=bold>%s</>  %s  <fg=red>%s severity</> <fg=gray>in</> <fg=cyan;options=bold>%s</>',
                    $lineLabel,
                    $penLink,
                    $severity,
                    $relFile,
                ),
            );

            if ($issue['analysis']->trigger) {
                $trigger = $this->regexAnalysis->highlight($issue['analysis']->trigger);
                $io->writeln(\sprintf('         <fg=cyan>Trigger:</> %s', $trigger));
            }

            foreach ($issue['analysis']->recommendations as $rec) {
                $io->writeln("         <fg=cyan>👉</> <fg=cyan>{$rec}</>");
            }
            $io->writeln('');
        }
    }

    private function outputOptimizationSuggestions(
        SymfonyStyle $io,
        array $suggestions,
    ): void {
        $io->writeln('  <fg=green;options=bold>Optimizations</>');
        $io->newLine();

        foreach ($suggestions as $item) {
            $relFile = $this->linkFormatter->getRelativePath($item['file']);
            $line = $item['line'];
            $lineLabel = str_pad((string) $line, 4);
            $penLink = $this->linkFormatter->format($item['file'], $line, '✏️', 1, '✏️');

            $io->writeln(
                \sprintf(
                    '  <fg=green;options=bold>O</>  <fg=white;options=bold>%s</>  %s  <fg=green>Saved %d chars</> <fg=gray>in</> <fg=cyan;options=bold>%s</>',
                    $lineLabel,
                    $penLink,
                    $item['savings'],
                    $relFile,
                ),
            );

            $original = $this->regexAnalysis->highlight($item['optimization']->original);
            $optimized = $this->regexAnalysis->highlight($item['optimization']->optimized);

            $io->writeln(\sprintf('         <fg=red>-</> %s', $original));
            $io->writeln(\sprintf('         <fg=green>+</> %s', $optimized));
            $io->writeln('');
        }
    }

    private function outputValidationIssues(SymfonyStyle $io, array $issues): void
    {
        $io->writeln('  <fg=blue;options=bold>Symfony Validation</>');
        $io->newLine();

        foreach ($issues as $issue) {
            $letter = $issue->isError ? 'E' : 'W';
            $color = $issue->isError ? 'red' : 'yellow';

            $io->writeln(
                \sprintf(
                    '  <fg=%s;options=bold>%s</>  %s',
                    $color,
                    $letter,
                    $issue->message,
                ),
            );
        }
        $io->writeln('');
    }

    private function cleanMessageIndentation(string $message): string
    {
        return preg_replace_callback(
            '/^Line \d+:/m',
            fn ($matches) => str_repeat(' ', \strlen($matches[0])),
            $message,
        );
    }
}
