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

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RegexParser\Cli\Command\HelpCommand;
use RegexParser\Cli\VersionResolver;
use RegexParser\Lint\Command\LintArgumentParser;
use RegexParser\Lint\Command\LintCommand;
use RegexParser\Lint\Command\LintConfigLoader;
use RegexParser\Lint\Command\LintDefaultsBuilder;
use RegexParser\Lint\Command\LintExtractorFactory;
use RegexParser\Lint\Command\LintOutputRenderer;

final class LintCommandClassCoverageTest extends TestCase
{
    public function test_lint_command_class_instantiation(): void
    {
        $versionResolver = new VersionResolver();
        $helpCommand = new HelpCommand($versionResolver);
        $configLoader = new LintConfigLoader();
        $defaultsBuilder = new LintDefaultsBuilder();
        $argumentParser = new LintArgumentParser();
        $extractorFactory = new LintExtractorFactory();
        $outputRenderer = new LintOutputRenderer($versionResolver);

        $command = new LintCommand(
            $helpCommand,
            $configLoader,
            $defaultsBuilder,
            $argumentParser,
            $extractorFactory,
            $outputRenderer,
        );

        $this->assertInstanceOf(LintCommand::class, $command);
        $this->assertSame('lint', $command->getName());
        $this->assertSame([], $command->getAliases());
        $this->assertSame('Lint regex patterns in PHP source code', $command->getDescription());
    }

    public function test_lint_command_getters(): void
    {
        $versionResolver = new VersionResolver();
        $helpCommand = new HelpCommand($versionResolver);
        $configLoader = new LintConfigLoader();
        $defaultsBuilder = new LintDefaultsBuilder();
        $argumentParser = new LintArgumentParser();
        $extractorFactory = new LintExtractorFactory();
        $outputRenderer = new LintOutputRenderer($versionResolver);

        $command = new LintCommand(
            $helpCommand,
            $configLoader,
            $defaultsBuilder,
            $argumentParser,
            $extractorFactory,
            $outputRenderer,
        );

        // Test all getter methods
        $this->assertIsString($command->getName());
        $this->assertIsArray($command->getAliases());
        $this->assertIsString($command->getDescription());
    }
}
