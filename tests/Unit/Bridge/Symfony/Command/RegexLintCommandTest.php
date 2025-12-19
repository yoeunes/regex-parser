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

namespace RegexParser\Tests\Unit\Bridge\Symfony\Command;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Command\RegexLintCommand;
use RegexParser\Bridge\Symfony\Service\RegexAnalysisService;
use RegexParser\Regex;
use Symfony\Component\Console\Tester\CommandTester;

final class RegexLintCommandTest extends TestCase
{
    public function test_command_succeeds_by_default_with_no_patterns(): void
    {
        $command = $this->createCommand();

        $tester = new CommandTester($command);
        $status = $tester->execute(['paths' => ['nonexistent']]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('No regex patterns found', $tester->getDisplay());
    }

    public function test_command_has_correct_name(): void
    {
        $command = $this->createCommand();

        $this->assertSame('regex:lint', $command->getName());
    }

    public function test_command_has_all_expected_options(): void
    {
        $command = $this->createCommand();

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('paths'));
        $this->assertTrue($definition->hasOption('exclude'));
        $this->assertTrue($definition->hasOption('min-savings'));
        $this->assertTrue($definition->hasOption('no-routes'));
        $this->assertTrue($definition->hasOption('no-validators'));

        $this->assertFalse($definition->hasOption('analyze-redos'));
        $this->assertFalse($definition->hasOption('optimize'));
    }

    private function createCommand(): RegexLintCommand
    {
        return new RegexLintCommand(
            analysis: new RegexAnalysisService(Regex::create()),
            editorUrl: null,
        );
    }
}
