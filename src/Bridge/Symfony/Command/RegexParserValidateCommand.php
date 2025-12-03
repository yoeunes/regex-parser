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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

final class RegexParserValidateCommand extends Command
{
    protected static $defaultName = 'regex-parser:check';

    protected static $defaultDescription = 'Validates regex usage found in the Symfony application.';

    public function __construct(
        private readonly RouteRequirementAnalyzer $analyzer,
        private readonly ?RouterInterface $router = null,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (null === $this->router) {
            $io->warning('No router service was found; skipping regex checks.');

            return Command::SUCCESS;
        }

        $issues = $this->analyzer->analyze($this->router->getRouteCollection());

        if ([] === $issues) {
            $io->success('No regex issues detected in route requirements.');

            return Command::SUCCESS;
        }

        $hasErrors = false;
        foreach ($issues as $issue) {
            $hasErrors = $hasErrors || $issue->isError;
            $io->writeln(\sprintf(
                '%s %s',
                $issue->isError ? '<error>[error]</error>' : '<comment>[warn]</comment>',
                $issue->message,
            ));
        }

        if (!$hasErrors) {
            $io->success('RegexParser found warnings only.');
        }

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }
}
