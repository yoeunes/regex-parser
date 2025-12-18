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
            ->addArgument('paths', InputArgument::IS_ARRAY, 'Files/directories to scan (defaults to current directory).')
            ->addOption('fail-on-warnings', null, InputOption::VALUE_NONE, 'Exit with a non-zero code when warnings are found.')
            ->addOption('analyze-redos', null, InputOption::VALUE_NONE, 'Analyze patterns for ReDoS risk.')
            ->addOption('redos-threshold', null, InputOption::VALUE_REQUIRED, 'Minimum ReDoS severity to report (safe|low|medium|high|critical).', $this->defaultRedosThreshold)
            ->addOption('optimize', null, InputOption::VALUE_NONE, 'Suggest safe optimizations for patterns.')
            ->addOption('min-savings', null, InputOption::VALUE_REQUIRED, 'Minimum character savings to report for optimizations.', 1)
            ->addOption('validate-symfony', null, InputOption::VALUE_NONE, 'Validate regex usage in Symfony routes and validators.')
            ->addOption('fail-on-suggestions', null, InputOption::VALUE_NONE, 'Exit with a non-zero code when optimization suggestions are found.')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Run all analyses (lint, ReDoS, optimization, and Symfony validation).');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var list<string> $paths */
        $paths = $input->getArgument('paths');
        if ([] === $paths) {
            $paths = $this->defaultPaths;
        }

        $runAll = (bool) $input->getOption('all');
        $analyzeRedos = $runAll || (bool) $input->getOption('analyze-redos');
        $optimize = $runAll || (bool) $input->getOption('optimize');
        $validateSymfony = $runAll || (bool) $input->getOption('validate-symfony');

        $editorUrlTemplate = $this->editorUrl;

        $extractor = $this->extractor ?? new RegexPatternExtractor(
            // Fallback to token-based if no injector is available
            new TokenBasedExtractionStrategy(),
        );
        $patterns = $extractor->extract($paths, $this->excludePaths);

        if ([] === $patterns && !$validateSymfony) {
            $io->success('No constant preg_* patterns found.');

            return Command::SUCCESS;
        }

        $hasErrors = false;
        $hasWarnings = false;
        $hasSuggestions = false;

        // Basic linting (always runs by default)
        $lintIssues = [];
        if (!empty($patterns)) {
            foreach ($patterns as $occurrence) {
                $validation = $this->regex->validate($occurrence->pattern);
                if (!$validation->isValid) {
                    $hasErrors = true;
                    $lintIssues[] = [
                        'type' => 'error',
                        'file' => $occurrence->file,
                        'line' => $occurrence->line,
                        'message' => $validation->error ?? 'Invalid regex.',
                    ];

                    continue;
                }

                $ast = $this->regex->parse($occurrence->pattern);
                $linter = new LinterNodeVisitor();
                $ast->accept($linter);

                foreach ($linter->getIssues() as $issue) {
                    $hasWarnings = true;
                    $lintIssues[] = [
                        'type' => 'warning',
                        'file' => $occurrence->file,
                        'line' => $occurrence->line,
                        'issueId' => $issue->id,
                        'message' => $issue->message,
                        'hint' => $issue->hint,
                    ];
                }
            }
        }

        // Output linting issues
        if (!empty($lintIssues)) {
            $this->outputLintIssues($io, $lintIssues, $editorUrlTemplate);
        }

        // ReDoS analysis
        $redosIssues = [];
        if ($analyzeRedos && !empty($patterns)) {
            $redosThreshold = (string) $input->getOption('redos-threshold');
            $severityThreshold = ReDoSSeverity::tryFrom(strtolower($redosThreshold)) ?? ReDoSSeverity::HIGH;

            foreach ($patterns as $occurrence) {
                $validation = $this->regex->validate($occurrence->pattern);
                if (!$validation->isValid) {
                    continue; // Already reported in linting
                }

                $analysis = $this->regex->analyzeReDoS($occurrence->pattern);
                if (!$analysis->exceedsThreshold($severityThreshold)) {
                    continue;
                }

                $hasErrors = true;
                $redosIssues[] = [
                    'file' => $occurrence->file,
                    'line' => $occurrence->line,
                    'analysis' => $analysis,
                ];
            }
        }

        // Output ReDoS issues
        if (!empty($redosIssues)) {
            $this->outputRedosIssues($io, $redosIssues);
        }

        // Optimization suggestions
        $optimizationSuggestions = [];
        if ($optimize && !empty($patterns)) {
            $minSavings = (int) $input->getOption('min-savings');
            if ($minSavings < 0) {
                $minSavings = 0;
            }

            foreach ($patterns as $occurrence) {
                $validation = $this->regex->validate($occurrence->pattern);
                if (!$validation->isValid) {
                    continue; // Already reported in linting
                }

                try {
                    $optimization = $this->regex->optimize($occurrence->pattern);
                } catch (\Throwable $e) {
                    $hasErrors = true;
                    $io->writeln(\sprintf('<error>[error]</error> %s:%d %s', $occurrence->file, $occurrence->line, $e->getMessage()));

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
                $optimizationSuggestions[] = [
                    'file' => $occurrence->file,
                    'line' => $occurrence->line,
                    'optimization' => $optimization,
                    'savings' => $savings,
                ];
            }
        }

        // Output optimization suggestions
        if (!empty($optimizationSuggestions)) {
            $this->outputOptimizationSuggestions($io, $optimizationSuggestions);
        }

        // Symfony validation
        $validationIssues = [];
        if ($validateSymfony) {
            if (null !== $this->routeAnalyzer && null !== $this->router) {
                $validationIssues = array_merge($validationIssues, $this->routeAnalyzer->analyze($this->router->getRouteCollection()));
            } else {
                $io->warning('No router service was found; skipping route regex checks.');
            }

            if (null !== $this->validatorAnalyzer && null !== $this->validator && null !== $this->validatorLoader) {
                $validationIssues = array_merge($validationIssues, $this->validatorAnalyzer->analyze($this->validator, $this->validatorLoader));
            } else {
                $io->warning('No validator service was found; skipping validator regex checks.');
            }
        }

        // Output validation issues
        if (!empty($validationIssues)) {
            $this->outputValidationIssues($io, $validationIssues);
        }

        $allHasErrors = $hasErrors || !empty(array_filter($validationIssues, fn ($i) => $i->isError));
        $allHasWarnings = $hasWarnings || !empty(array_filter($validationIssues, fn ($i) => !$i->isError));

        if (!$allHasErrors && !$allHasWarnings && !$hasSuggestions) {
            $io->success('No regex issues detected.');

            return Command::SUCCESS;
        }

        if (!$allHasErrors && ($allHasWarnings || $hasSuggestions)) {
            $io->success('Regex analysis completed with warnings or suggestions only.');
        }

        $failOnWarnings = (bool) $input->getOption('fail-on-warnings');
        $failOnSuggestions = (bool) $input->getOption('fail-on-suggestions');

        return ($allHasErrors || ($failOnWarnings && $allHasWarnings) || ($failOnSuggestions && $hasSuggestions)) ? Command::FAILURE : Command::SUCCESS;
    }

    private function outputLintIssues(SymfonyStyle $io, array $issues, ?string $editorUrlTemplate): void
    {
        $issuesByFile = [];
        foreach ($issues as $issue) {
            $relativeFile = $this->getRelativePath($issue['file']);
            $issuesByFile[$relativeFile][] = $issue;
        }

        foreach ($issuesByFile as $file => $fileIssues) {
            $io->writeln('<comment>'.$file.'</comment>');
            foreach ($fileIssues as $issue) {
                $clickableIcon = $this->makeClickable($editorUrlTemplate, $issue['file'], $issue['line'], '✏️');
                $tag = 'error' === $issue['type'] ? '<fg=red>[error]</fg=red>' : '<comment>[lint]</comment>';
                $paddedTag = 'error' === $issue['type'] ? \str_pad('[error]', 8) : \str_pad('[lint]', 8);
                $coloredTag = 'error' === $issue['type'] ? '<fg=red>'.$paddedTag.'</fg=red>' : '<comment>'.$paddedTag.'</comment>';
                $messageColor = 'error' === $issue['type'] ? '<fg=red>%s</fg=red>' : '<comment>%s</comment>';
                $io->writeln(\sprintf('  <info>%4d</info> %s %s '. $messageColor, $issue['line'], $clickableIcon, $coloredTag, $issue['message']));

                if ('warning' === $issue['type'] && isset($issue['issueId'])) {
                    $io->writeln(\sprintf('    🪪  %s', $issue['issueId']));
                }

                if (isset($issue['hint']) && null !== $issue['hint']) {
                    $hints = explode("\n", $issue['hint']);
                    foreach ($hints as $hint) {
                        $hint = trim($hint);
                        if ('' !== $hint) {
                            $io->writeln('    💡  '.$hint);
                        }
                    }
                }
            }
            $io->writeln('');
        }
    }

    private function outputRedosIssues(SymfonyStyle $io, array $issues): void
    {
        $io->writeln('<comment>ReDoS Analysis</comment>');
        foreach ($issues as $issue) {
            $summary = \sprintf(
                '%s:%d severity=%s score=%d',
                $issue['file'],
                $issue['line'],
                strtoupper((string) $issue['analysis']->severity->value),
                $issue['analysis']->score,
            );

            $io->writeln('<error>[redos]</error> '.$summary);

            if (null !== $issue['analysis']->trigger) {
                $io->writeln('  Trigger: '.$issue['analysis']->trigger);
            }

            if (null !== $issue['analysis']->confidence) {
                $io->writeln('  Confidence: '.$issue['analysis']->confidence->value);
            }

            if (null !== $issue['analysis']->falsePositiveRisk) {
                $io->writeln('  False positive risk: '.$issue['analysis']->falsePositiveRisk);
            }

            foreach ($issue['analysis']->recommendations as $recommendation) {
                $io->writeln('  - '.$recommendation);
            }
        }
        $io->writeln('');
    }

    private function outputOptimizationSuggestions(SymfonyStyle $io, array $suggestions): void
    {
        $io->writeln('<comment>Optimization Suggestions</comment>');
        foreach ($suggestions as $suggestion) {
            $io->writeln(\sprintf(
                '<comment>[suggest]</comment> %s:%d saved=%d',
                $suggestion['file'],
                $suggestion['line'],
                $suggestion['savings'],
            ));
            $io->writeln('  - '.$suggestion['optimization']->original);
            $io->writeln('  + '.$suggestion['optimization']->optimized);
        }
        $io->writeln('');
    }

    private function outputValidationIssues(SymfonyStyle $io, array $issues): void
    {
        $io->writeln('<comment>Symfony Validation</comment>');
        foreach ($issues as $issue) {
            $io->writeln(\sprintf('  [validation] %s', $issue->message));
        }
        $io->writeln('');
    }

    private function makeClickable(?string $editorUrlTemplate, string $file, int $line, string $text): string
    {
        if (!$editorUrlTemplate) {
            return $text;
        }

        $editorUrl = str_replace(['%%file%%', '%%line%%'], [$file, $line], $editorUrlTemplate);

        return "\033]8;;".$editorUrl."\033\\".$text."\033]8;;\033\\";
    }

    private function getRelativePath(string $path): string
    {
        $cwd = getcwd();
        if (false === $cwd) {
            return $path;
        }

        if (str_starts_with($path, $cwd)) {
            return ltrim(substr($path, \strlen($cwd)), '/\\');
        }

        return $path;
    }
}
