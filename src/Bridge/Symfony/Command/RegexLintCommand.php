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

use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use RegexParser\Bridge\Symfony\Analyzer\ValidatorRegexAnalyzer;
use RegexParser\Bridge\Symfony\Extractor\RegexPatternExtractor;
use RegexParser\Bridge\Symfony\Extractor\TokenBasedExtractionStrategy;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'regex:lint',
    description: 'Lints, validates, optimizes, and analyzes ReDoS risk for constant preg_* patterns found in PHP files.',
)]
final class RegexLintCommand extends Command
{
    protected static ?string $defaultName = 'regex:lint';
    protected static ?string $defaultDescription = 'Lints, validates, optimizes, and analyzes ReDoS risk for constant preg_* patterns found in PHP files.';

    public function __construct(
        private readonly Regex $regex,
        private readonly ?string $editorUrl = null,
        private readonly array $defaultPaths = ['src'],
        private readonly array $excludePaths = ['vendor'],
        private readonly ?RouteRequirementAnalyzer $routeAnalyzer = null,
        private readonly ?ValidatorRegexAnalyzer $validatorAnalyzer = null,
        private readonly ?RouterInterface $router = null,
        private readonly ?ValidatorInterface $validator = null,
        private readonly ?LoaderInterface $validatorLoader = null,
        private readonly string $defaultRedosThreshold = 'high',
        private readonly ?RegexPatternExtractor $extractor = null,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY,
                'Files/directories to scan (defaults to current directory).'
            )
            ->addOption(
                'fail-on-warnings',
                null,
                InputOption::VALUE_NONE,
                'Exit with a non-zero code when warnings are found.'
            )
            ->addOption('analyze-redos', null, InputOption::VALUE_NONE, 'Analyze patterns for ReDoS risk.')
            ->addOption(
                'redos-threshold',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum ReDoS severity to report (safe|low|medium|high|critical).',
                $this->defaultRedosThreshold
            )
            ->addOption('optimize', null, InputOption::VALUE_NONE, 'Suggest safe optimizations for patterns.')
            ->addOption(
                'min-savings',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum character savings to report for optimizations.',
                1
            )
            ->addOption(
                'validate-symfony',
                null,
                InputOption::VALUE_NONE,
                'Validate regex usage in Symfony routes and validators.'
            )
            ->addOption(
                'fail-on-suggestions',
                null,
                InputOption::VALUE_NONE,
                'Exit with a non-zero code when optimization suggestions are found.'
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'Run all analyses (lint, ReDoS, optimization, and Symfony validation).'
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

        $runAll = (bool)$input->getOption('all');
        $analyzeRedos = $runAll || (bool)$input->getOption('analyze-redos');
        $optimize = $runAll || (bool)$input->getOption('optimize');
        $validateSymfony = $runAll || (bool)$input->getOption('validate-symfony');

        $editorUrlTemplate = $this->editorUrl;

        $extractor = $this->extractor ?? new RegexPatternExtractor(
            new TokenBasedExtractionStrategy(),
        );

        $io->write('  <fg=cyan>🔍  Scanning files...</>');
        $patterns = $extractor->extract($paths, $this->excludePaths);
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
            $progressBar = $io->createProgressBar(count($patterns));
            $progressBar->setEmptyBarCharacter('░');
            $progressBar->setProgressCharacter('');
            $progressBar->setBarCharacter('▓');
            $progressBar->setFormat('  <fg=blue>%bar%</> <fg=cyan>%percent:3s%%</>');
            $progressBar->start();

            foreach ($patterns as $occurrence) {
                $validation = $this->regex->validate($occurrence->pattern);
                if (!$validation->isValid) {
                    $hasErrors = true;
                    $stats['errors']++;
                    $lintIssues[] = [
                        'type'    => 'error',
                        'file'    => $occurrence->file,
                        'line'    => $occurrence->line,
                        'column'  => 1,
                        'message' => $validation->error ?? 'Invalid regex.',
                    ];
                    $progressBar->advance();
                    continue;
                }

                $ast = $this->regex->parse($occurrence->pattern);
                $linter = new LinterNodeVisitor();
                $ast->accept($linter);

                foreach ($linter->getIssues() as $issue) {
                    $hasWarnings = true;
                    $stats['warnings']++;
                    $lintIssues[] = [
                        'type'    => 'warning',
                        'file'    => $occurrence->file,
                        'line'    => $occurrence->line,
                        'column'  => 1,
                        'issueId' => $issue->id,
                        'message' => $issue->message,
                        'hint'    => $issue->hint,
                    ];
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $io->writeln(['', '']);
        }

        if (!empty($lintIssues)) {
            $this->outputLintIssues($io, $lintIssues, $editorUrlTemplate);
        }

        // 2. ReDoS Analysis
        $redosIssues = [];
        if ($analyzeRedos && !empty($patterns)) {
            $redosThreshold = (string)$input->getOption('redos-threshold');
            $severityThreshold = ReDoSSeverity::tryFrom(strtolower($redosThreshold)) ?? ReDoSSeverity::HIGH;

            foreach ($patterns as $occurrence) {
                $validation = $this->regex->validate($occurrence->pattern);
                if (!$validation->isValid) {
                    continue;
                }

                $analysis = $this->regex->analyzeReDoS($occurrence->pattern);
                if (!$analysis->exceedsThreshold($severityThreshold)) {
                    continue;
                }

                $hasErrors = true;
                $stats['redos']++;
                $redosIssues[] = [
                    'file'     => $occurrence->file,
                    'line'     => $occurrence->line,
                    'analysis' => $analysis,
                ];
            }
        }

        if (!empty($redosIssues)) {
            $this->outputRedosIssues($io, $redosIssues, $editorUrlTemplate);
        }

        // 3. Optimizations
        $optimizationSuggestions = [];
        if ($optimize && !empty($patterns)) {
            $minSavings = (int)$input->getOption('min-savings');
            if ($minSavings < 0) {
                $minSavings = 0;
            }

            foreach ($patterns as $occurrence) {
                $validation = $this->regex->validate($occurrence->pattern);
                if (!$validation->isValid) {
                    continue;
                }

                try {
                    $optimization = $this->regex->optimize($occurrence->pattern);
                } catch (\Throwable) {
                    continue;
                }

                if (!$optimization->isChanged()) {
                    continue;
                }

                $savings = \strlen($optimization->original) - \strlen($optimization->optimized);
                if ($savings < $minSavings) {
                    continue;
                }

                $hasSuggestions = true;
                $stats['optimizations']++;
                $optimizationSuggestions[] = [
                    'file'         => $occurrence->file,
                    'line'         => $occurrence->line,
                    'optimization' => $optimization,
                    'savings'      => $savings,
                ];
            }
        }

        if (!empty($optimizationSuggestions)) {
            $this->outputOptimizationSuggestions($io, $optimizationSuggestions, $editorUrlTemplate);
        }

        // 4. Symfony Validation
        $validationIssues = [];
        if ($validateSymfony) {
            if (null !== $this->routeAnalyzer && null !== $this->router) {
                $validationIssues = array_merge(
                    $validationIssues,
                    $this->routeAnalyzer->analyze($this->router->getRouteCollection())
                );
            }

            if (null !== $this->validatorAnalyzer && null !== $this->validator && null !== $this->validatorLoader) {
                $validationIssues = array_merge(
                    $validationIssues,
                    $this->validatorAnalyzer->analyze($this->validator, $this->validatorLoader)
                );
            }
        }

        if (!empty($validationIssues)) {
            $this->outputValidationIssues($io, $validationIssues);
        }

        // Final Status
        $allHasErrors = $hasErrors || !empty(array_filter($validationIssues, fn($i) => $i->isError));
        $allHasWarnings = $hasWarnings || !empty(array_filter($validationIssues, fn($i) => !$i->isError));

        if (!$allHasErrors && !$allHasWarnings && !$hasSuggestions) {
            $io->block('No issues found. Your regex patterns are clean.', 'PASS', 'fg=black;bg=green', ' ', true);

            return Command::SUCCESS;
        }

        $failOnWarnings = (bool)$input->getOption('fail-on-warnings');
        $failOnSuggestions = (bool)$input->getOption('fail-on-suggestions');
        $failed = $allHasErrors || ($failOnWarnings && $allHasWarnings) || ($failOnSuggestions && $hasSuggestions);

        if ($failed) {
            $io->newLine();
            $io->writeln(
                \sprintf(
                    '  <bg=red;fg=white;options=bold> FAIL </><fg=red;options=bold> %d errors</><fg=gray>, %d warnings, %d suggestions.</>',
                    $stats['errors'] + $stats['redos'],
                    $stats['warnings'],
                    $stats['optimizations']
                )
            );
            $io->newLine();

            return Command::FAILURE;
        }

        $io->newLine();
        $io->writeln(
            \sprintf(
                '  <bg=yellow;fg=black;options=bold> WARN </><fg=yellow;options=bold> %d warnings</><fg=gray>, %d suggestions.</>',
                $stats['warnings'],
                $stats['optimizations']
            )
        );
        $io->newLine();

        return Command::SUCCESS;
    }

    private function outputLintIssues(SymfonyStyle $io, array $issues, ?string $editorUrlTemplate): void
    {
        $io->writeln('  <fg=white;options=bold>Issues Found</>');
        $io->newLine();

        $issuesByFile = [];
        foreach ($issues as $issue) {
            $issuesByFile[$issue['file']][] = $issue;
        }

        foreach ($issuesByFile as $file => $fileIssues) {
            $relFile = $this->getRelativePath($file);
            $io->writeln("  <fg=cyan>in</> <fg=white>{$relFile}</>");

            foreach ($fileIssues as $issue) {
                $isError = 'error' === $issue['type'];
                $color = $isError ? 'red' : 'yellow';
                $letter = $isError ? 'E' : 'W';
                $line = $issue['line'];
                $pen = $this->makeClickable($editorUrlTemplate, $file, $line, '✏️');

                // Process message to remove "Line 1:" and align
                $messageRaw = (string)$issue['message'];
                $cleanMessage = $this->cleanMessageIndentation($messageRaw);

                $lines = explode("\n", $cleanMessage);
                $firstLine = array_shift($lines);

                $io->writeln(
                    \sprintf(
                        '  <fg=%s;options=bold>%s</>  <fg=cyan>%s</> %s  %s',
                        $color,
                        $letter,
                        str_pad((string)$line, 4),
                        $pen,
                        $firstLine
                    )
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

    private function outputRedosIssues(SymfonyStyle $io, array $issues, ?string $editorUrlTemplate): void
    {
        $io->writeln('  <fg=red;options=bold>ReDoS Vulnerabilities</>');
        $io->newLine();

        foreach ($issues as $issue) {
            $relFile = $this->getRelativePath($issue['file']);
            $line = $issue['line'];
            $severity = strtoupper($issue['analysis']->severity->value);
            $pen = $this->makeClickable($editorUrlTemplate, $issue['file'], $line, '✏️');

            $io->writeln(
                \sprintf(
                    '  <fg=red;options=bold>R</>  <fg=cyan>%s</> %s  <fg=red>%s severity</> <fg=cyan>in %s</>',
                    str_pad((string)$line, 4),
                    $pen,
                    $severity,
                    $relFile
                )
            );

            if ($issue['analysis']->trigger) {
                $trigger = $this->regex->highlightCli($issue['analysis']->trigger);
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
        ?string $editorUrlTemplate
    ): void {
        $io->writeln('  <fg=green;options=bold>Optimizations</>');
        $io->newLine();

        foreach ($suggestions as $item) {
            $relFile = $this->getRelativePath($item['file']);
            $line = $item['line'];
            $pen = $this->makeClickable($editorUrlTemplate, $item['file'], $line, '✏️');

            $io->writeln(
                \sprintf(
                    '  <fg=green;options=bold>O</>  <fg=cyan>%s</> %s  <fg=green>Saved %d chars</> <fg=cyan>in %s</>',
                    str_pad((string)$line, 4),
                    $pen,
                    $item['savings'],
                    $relFile
                )
            );

            $original = $this->regex->highlightCli($item['optimization']->original);
            $optimized = $this->regex->highlightCli($item['optimization']->optimized);

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
                    $issue->message
                )
            );
        }
        $io->writeln('');
    }

    /**
     * Cleans up "Line X:" prefixes to align error context beautifully.
     */
    private function cleanMessageIndentation(string $message): string
    {
        return preg_replace_callback(
            '/^Line \d+:/m',
            fn($matches) => str_repeat(' ', \strlen($matches[0])),
            $message
        );
    }

    private function makeClickable(
        ?string $editorUrlTemplate,
        string $file,
        int $line,
        string $text,
        int $column = 1
    ): string {
        if (!$editorUrlTemplate) {
            return $text;
        }

        $url = str_replace(
            ['%%file%%', '%%line%%', '%%column%%'],
            [$file, $line, $column],
            $editorUrlTemplate,
        );

        return "\033]8;;{$url}\033\\{$text}\033]8;;\033\\";
    }

    private function getRelativePath(string $path): string
    {
        $cwd = getcwd();
        if (false === $cwd || !str_starts_with($path, $cwd)) {
            return $path;
        }

        return ltrim(substr($path, \strlen($cwd)), '/\\');
    }
}
