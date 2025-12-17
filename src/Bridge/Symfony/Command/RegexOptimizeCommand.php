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

use RegexParser\Regex;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'regex:optimize',
    description: 'Suggests safe optimizations for constant preg_* patterns found in PHP files.',
)]
final class RegexOptimizeCommand extends Command
{
    protected static ?string $defaultName = 'regex:optimize';

    protected static ?string $defaultDescription = 'Suggests safe optimizations for constant preg_* patterns found in PHP files.';

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
            ->addOption('min-savings', null, InputOption::VALUE_REQUIRED, 'Minimum character savings to report.', 1)
            ->addOption('fail-on-suggestions', null, InputOption::VALUE_NONE, 'Exit with a non-zero code when suggestions are found.');
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

        $minSavings = (int) $input->getOption('min-savings');
        if ($minSavings < 0) {
            $minSavings = 0;
        }

        $extractor = new RegexPatternExtractor();
        $patterns = $extractor->extract($paths);

        if ([] === $patterns) {
            $io->success('No constant preg_* patterns found.');

            return Command::SUCCESS;
        }

        $hasErrors = false;
        $hasSuggestions = false;

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

            $io->writeln(\sprintf(
                '<comment>[suggest]</comment> %s:%d saved=%d',
                $occurrence->file,
                $occurrence->line,
                $savings,
            ));
            $io->writeln('  - '.$optimization->original);
            $io->writeln('  + '.$optimization->optimized);
        }

        if (!$hasErrors && !$hasSuggestions) {
            $io->success('No optimization suggestions.');

            return Command::SUCCESS;
        }

        $failOnSuggestions = (bool) $input->getOption('fail-on-suggestions');

        return ($hasErrors || ($failOnSuggestions && $hasSuggestions)) ? Command::FAILURE : Command::SUCCESS;
    }
}

