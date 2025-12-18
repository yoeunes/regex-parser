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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'regex:check',
    description: 'Validates regex usage found in the Symfony application.',
)]
final class RegexParserValidateCommand extends Command
{
    protected static ?string $defaultName = 'regex:check';

    protected static ?string $defaultDescription = 'Validates regex usage found in the Symfony application.';

    public function __construct(
        private readonly RouteRequirementAnalyzer $analyzer,
        private readonly ?RouterInterface $router = null,
        private readonly ?ValidatorRegexAnalyzer $validatorAnalyzer = null,
        private readonly ?ValidatorInterface $validator = null,
        private readonly ?LoaderInterface $validatorLoader = null,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $issues = [];

        if (null !== $this->router) {
            $issues = array_merge($issues, $this->analyzer->analyze($this->router->getRouteCollection()));
        } else {
            $io->warning('No router service was found; skipping route regex checks.');
        }

        if (null !== $this->validatorAnalyzer) {
            $issues = array_merge($issues, $this->validatorAnalyzer->analyze($this->validator, $this->validatorLoader));
        } else {
            $io->warning('No validator service was found; skipping validator regex checks.');
        }

        if ([] === $issues) {
            $io->success('No regex issues detected.');

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
