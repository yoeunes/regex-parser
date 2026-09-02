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

namespace RegexParser\Cli;

use RegexParser\Cli\Command\AnalyzeCommand;
use RegexParser\Cli\Command\ClearCacheCommand;
use RegexParser\Cli\Command\CompareCommand;
use RegexParser\Cli\Command\DebugCommand;
use RegexParser\Cli\Command\DiagramCommand;
use RegexParser\Cli\Command\ExplainCommand;
use RegexParser\Cli\Command\GraphCommand;
use RegexParser\Cli\Command\HelpCommand;
use RegexParser\Cli\Command\HighlightCommand;
use RegexParser\Cli\Command\ParseCommand;
use RegexParser\Cli\Command\RedosCommand;
use RegexParser\Cli\Command\SelfUpdateCommand;
use RegexParser\Cli\Command\TranspileCommand;
use RegexParser\Cli\Command\ValidateCommand;
use RegexParser\Cli\Command\VersionCommand;
use RegexParser\Cli\SelfUpdate\SelfUpdater;
use RegexParser\Lint\Command\LintArgumentParser;
use RegexParser\Lint\Command\LintCommand;
use RegexParser\Lint\Command\LintConfigLoader;
use RegexParser\Lint\Command\LintDefaultsBuilder;
use RegexParser\Lint\Command\LintExtractorFactory;
use RegexParser\Lint\Command\LintOutputRenderer;

/**
 * Builds the CLI with every command it ships.
 *
 * The binary is one line long because of this: the wiring belongs where it
 * can be read, and where a test can ask the application what it knows.
 */
final class ApplicationFactory
{
    public static function create(Output $output): Application
    {
        $help = new HelpCommand();
        $application = new Application(new GlobalOptionsParser(), $output, $help);

        foreach (self::commands($help) as $command) {
            $application->register($command);
        }

        return $application;
    }

    /**
     * @return array<int, Command\CommandInterface>
     */
    public static function commands(HelpCommand $help): array
    {
        return [
            $help,
            new VersionCommand(),
            new SelfUpdateCommand(new SelfUpdater()),
            new ParseCommand(),
            new ExplainCommand(),
            new AnalyzeCommand(),
            new DebugCommand(new LintConfigLoader(), new LintDefaultsBuilder()),
            new CompareCommand(),
            new RedosCommand(),
            new DiagramCommand(),
            new GraphCommand(),
            new HighlightCommand(),
            new TranspileCommand(),
            new ValidateCommand(),
            new ClearCacheCommand(),
            new LintCommand(
                $help,
                new LintConfigLoader(),
                new LintDefaultsBuilder(),
                new LintArgumentParser(),
                new LintExtractorFactory(),
                new LintOutputRenderer(),
            ),
        ];
    }
}
