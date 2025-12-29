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
        $helpCommand = new HelpCommand();
        $configLoader = new LintConfigLoader();
        $defaultsBuilder = new LintDefaultsBuilder();
        $argumentParser = new LintArgumentParser();
        $extractorFactory = new LintExtractorFactory();
        $outputRenderer = new LintOutputRenderer();

        $command = new LintCommand(
            $helpCommand,
            $configLoader,
            $defaultsBuilder,
            $argumentParser,
            $extractorFactory,
            $outputRenderer,
        );

        $this->assertSame('lint', $command->getName());
        $this->assertSame([], $command->getAliases());
        $this->assertSame('Lint regex patterns in PHP source code', $command->getDescription());
    }
}
