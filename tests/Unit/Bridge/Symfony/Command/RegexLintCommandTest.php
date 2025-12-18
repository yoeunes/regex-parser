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
use RegexParser\Bridge\Symfony\Service\RouteValidationService;
use RegexParser\Bridge\Symfony\Service\ValidatorValidationService;
use RegexParser\Regex;
use Symfony\Component\Console\Tester\CommandTester;

final class RegexLintCommandTest extends TestCase
{
    public function test_command_succeeds_by_default_with_no_patterns(): void
    {
        $command = $this->createCommand(defaultPaths: ['nonexistent'], excludePaths: []);

        $tester = new CommandTester($command);
        $status = $tester->execute([]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('No constant preg_* patterns found', $tester->getDisplay());
    }

    public function test_command_has_correct_name(): void
    {
        $command = $this->createCommand(defaultPaths: [], excludePaths: []);

        $this->assertSame('regex:lint', $command->getName());
    }

    public function test_command_has_all_expected_options(): void
    {
        $command = $this->createCommand(defaultPaths: [], excludePaths: []);

        $definition = $command->getDefinition();

        // Test basic options exist
        $this->assertTrue($definition->hasOption('fail-on-warnings'));
        $this->assertTrue($definition->hasOption('analyze-redos'));
        $this->assertTrue($definition->hasOption('redos-threshold'));
        $this->assertTrue($definition->hasOption('optimize'));
        $this->assertTrue($definition->hasOption('min-savings'));
        $this->assertTrue($definition->hasOption('validate-symfony'));
        $this->assertTrue($definition->hasOption('fail-on-suggestions'));
        $this->assertTrue($definition->hasOption('all'));

        // Test default values
        $this->assertSame('high', $definition->getOption('redos-threshold')->getDefault());
        $this->assertSame(1, $definition->getOption('min-savings')->getDefault());
    }

    public function test_command_with_validate_symfony_missing_services(): void
    {
        $command = $this->createCommand(defaultPaths: ['nonexistent'], excludePaths: []);

        $tester = new CommandTester($command);
        $status = $tester->execute(['--validate-symfony' => true]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('No router service was found', $tester->getDisplay());
        $this->assertStringContainsString('No validator service was found', $tester->getDisplay());
    }

    private function createCommand(array $defaultPaths, array $excludePaths): RegexLintCommand
    {
        return new RegexLintCommand(
            regexAnalysis: new RegexAnalysisService(Regex::create()),
            routeValidation: new RouteValidationService(null, null),
            validatorValidation: new ValidatorValidationService(null, null, null),
            editorFormat: null,
            defaultPaths: $defaultPaths,
            excludePaths: $excludePaths,
            defaultRedosThreshold: 'high',
        );
    }
}
