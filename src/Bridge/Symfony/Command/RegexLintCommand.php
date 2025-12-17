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

use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\Regex;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
            $paths = ['.'];
        }

        $extractor = new RegexPatternExtractor();
        $patterns = $extractor->extract($paths);

        if ([] === $patterns) {
            $io->success('No constant preg_* patterns found.');

            return Command::SUCCESS;
        }

        $hasErrors = false;
        $hasWarnings = false;

        foreach ($patterns as $occurrence) {
            $validation = $this->regex->validate($occurrence->pattern);
            if (!$validation->isValid) {
                $hasErrors = true;
                $io->writeln(\sprintf(
                    '<error>[error]</error> %s:%d %s',
                    $occurrence->file,
                    $occurrence->line,
                    $validation->error ?? 'Invalid regex.',
                ));

                continue;
            }

            $ast = $this->regex->parse($occurrence->pattern);
            $linter = new LinterNodeVisitor();
            $ast->accept($linter);

            foreach ($linter->getIssues() as $issue) {
                $hasWarnings = true;
                $io->writeln(\sprintf(
                    '<comment>[warn]</comment> %s:%d [%s] %s',
                    $occurrence->file,
                    $occurrence->line,
                    $issue->id,
                    $issue->message,
                ));

                if (null !== $issue->hint) {
                    $io->writeln('  '.$issue->hint);
                }
            }
        }

        if (!$hasErrors && !$hasWarnings) {
            $io->success('No lint issues detected.');

            return Command::SUCCESS;
        }

        if (!$hasErrors && $hasWarnings) {
            $io->success('Regex lint completed with warnings only.');
        }

        $failOnWarnings = (bool) $input->getOption('fail-on-warnings');

        return ($hasErrors || ($failOnWarnings && $hasWarnings)) ? Command::FAILURE : Command::SUCCESS;
    }
}

