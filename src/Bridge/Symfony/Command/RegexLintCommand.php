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
use RegexParser\NodeVisitor\LinterNodeVisitor;
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
    description: 'Lints constant preg_* patterns found in PHP files.',
)]
final class RegexLintCommand extends Command
{
    protected static ?string $defaultName = 'regex:lint';

    protected static ?string $defaultDescription = 'Lints constant preg_* patterns found in PHP files.';

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
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY, 'Files/directories to scan (defaults to current directory).')
            ->addOption('fail-on-warnings', null, InputOption::VALUE_NONE, 'Exit with a non-zero code when warnings are found.');
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

        $editorUrlTemplate = $this->editorUrl;

        $extractor = new RegexPatternExtractor($this->excludePaths);
        $patterns = $extractor->extract($paths);

        if ([] === $patterns) {
            $io->success('No constant preg_* patterns found.');

            return Command::SUCCESS;
        }

        $hasErrors = false;
        $hasWarnings = false;
        $issues = [];

        foreach ($patterns as $occurrence) {
            $validation = $this->regex->validate($occurrence->pattern);
            if (!$validation->isValid) {
                $hasErrors = true;
                $issues[] = [
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
                $issues[] = [
                    'type' => 'warning',
                    'file' => $occurrence->file,
                    'line' => $occurrence->line,
                    'issueId' => $issue->id,
                    'message' => $issue->message,
                    'hint' => $issue->hint,
                ];
            }
        }

        // Output linting issues
        $issuesByFile = [];
        foreach ($issues as $issue) {
            $relativeFile = $this->getRelativePath($issue['file']);
            $issuesByFile[$relativeFile][] = $issue;
        }

        foreach ($issuesByFile as $file => $fileIssues) {
            $io->writeln('<comment>' . $file . '</comment>');
            foreach ($fileIssues as $issue) {
                $clickableIcon = $this->makeClickable($editorUrlTemplate, $issue['file'], $issue['line'], '✏️');
                $io->writeln(\sprintf('  <info>%d</info>: [lint] %s %s', $issue['line'], $issue['message'], $clickableIcon));

                if ('warning' === $issue['type'] && isset($issue['issueId'])) {
                    $io->writeln(\sprintf('         🪪  %s', $issue['issueId']));
                }

                if (isset($issue['hint']) && null !== $issue['hint']) {
                    $hints = explode("\n", $issue['hint']);
                    foreach ($hints as $hint) {
                        $hint = trim($hint);
                        if ('' !== $hint) {
                            $io->writeln('         💡  ' . $hint);
                        }
                    }
                }
            }
            $io->writeln('');
        }

        // Run validation
        $validationIssues = [];
        if (null !== $this->routeAnalyzer && null !== $this->router) {
            $validationIssues = array_merge($validationIssues, $this->routeAnalyzer->analyze($this->router->getRouteCollection()));
        }
        if (null !== $this->validatorAnalyzer && null !== $this->validator && null !== $this->validatorLoader) {
            $validationIssues = array_merge($validationIssues, $this->validatorAnalyzer->analyze($this->validator, $this->validatorLoader));
        }

        // Output validation issues
        if (!empty($validationIssues)) {
            $io->writeln('<comment>Validation</comment>');
            foreach ($validationIssues as $issue) {
                $io->writeln(\sprintf('  [validation] %s', $issue->message));
            }
            $io->writeln('');
        }

        $allHasErrors = $hasErrors || !empty(array_filter($validationIssues, fn($i) => $i->isError));
        $allHasWarnings = $hasWarnings || !empty(array_filter($validationIssues, fn($i) => !$i->isError));

        if (!$allHasErrors && !$allHasWarnings) {
            $io->success('No regex issues detected.');
            return Command::SUCCESS;
        }

        if (!$allHasErrors && $allHasWarnings) {
            $io->success('Regex analysis completed with warnings only.');
        }

        $failOnWarnings = (bool) $input->getOption('fail-on-warnings');

        return ($allHasErrors || ($failOnWarnings && $allHasWarnings)) ? Command::FAILURE : Command::SUCCESS;
    }

    private function makeClickable(?string $editorUrlTemplate, string $file, int $line, string $text): string
    {
        if (!$editorUrlTemplate) {
            return $text;
        }

        $editorUrl = str_replace(['%%file%%', '%%line%%'], [$file, $line], $editorUrlTemplate);

        return "\033]8;;" . $editorUrl . "\033\\" . $text . "\033]8;;\033\\";
    }



    private function getRelativePath(string $path): string
    {
        $cwd = getcwd();
        if (false === $cwd) {
            return $path;
        }

        if (str_starts_with($path, $cwd)) {
            return ltrim(substr($path, strlen($cwd)), '/\\');
        }

        return $path;
    }
}
