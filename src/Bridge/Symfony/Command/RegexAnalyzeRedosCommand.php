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

use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'regex:analyze-redos',
    description: 'Analyzes constant preg_* patterns for ReDoS risk.',
)]
final class RegexAnalyzeRedosCommand extends Command
{
    protected static ?string $defaultName = 'regex:analyze-redos';

    protected static ?string $defaultDescription = 'Analyzes constant preg_* patterns for ReDoS risk.';

    public function __construct(
        private readonly Regex $regex,
        private readonly string $defaultThreshold = 'high',
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY, 'Files/directories to scan (defaults to current directory).')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Minimum severity to report (safe|low|medium|high|critical).', $this->defaultThreshold);
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

        $threshold = (string) $input->getOption('threshold');
        $severityThreshold = ReDoSSeverity::tryFrom(strtolower($threshold)) ?? ReDoSSeverity::HIGH;

        $extractor = new RegexPatternExtractor();
        $patterns = $extractor->extract($paths);

        if ([] === $patterns) {
            $io->success('No constant preg_* patterns found.');

            return Command::SUCCESS;
        }

        $hasErrors = false;

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

            $analysis = $this->regex->analyzeReDoS($occurrence->pattern);
            if (!$analysis->exceedsThreshold($severityThreshold)) {
                continue;
            }

            $hasErrors = true;
            $summary = \sprintf(
                '%s:%d severity=%s score=%d',
                $occurrence->file,
                $occurrence->line,
                strtoupper($analysis->severity->value),
                $analysis->score,
            );

            $io->writeln('<error>[redos]</error> '.$summary);

            if (null !== $analysis->trigger) {
                $io->writeln('  Trigger: '.$analysis->trigger);
            }

            if (null !== $analysis->confidence) {
                $io->writeln('  Confidence: '.$analysis->confidence->value);
            }

            if (null !== $analysis->falsePositiveRisk) {
                $io->writeln('  False positive risk: '.$analysis->falsePositiveRisk);
            }

            foreach ($analysis->recommendations as $recommendation) {
                $io->writeln('  - '.$recommendation);
            }
        }

        if (!$hasErrors) {
            $io->success('No ReDoS findings above threshold.');

            return Command::SUCCESS;
        }

        $io->error(\sprintf('ReDoS analysis found issues (threshold: %s).', $severityThreshold->value));

        return Command::FAILURE;
    }
}

