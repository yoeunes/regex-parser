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
use RegexParser\Regex;
use Symfony\Component\Console\Tester\CommandTester;

final class RegexLintCommandTest extends TestCase
{
    public function test_command_succeeds_by_default_with_no_patterns(): void
    {
        $command = new RegexLintCommand(
            regex: Regex::create(),
            editorUrl: null,
            defaultPaths: ['nonexistent'],
            excludePaths: [],
            routeAnalyzer: null,
            validatorAnalyzer: null,
            router: null,
            validator: null,
            validatorLoader: null,
            defaultRedosThreshold: 'high',
        );

        $tester = new CommandTester($command);
        $status = $tester->execute([]);

        self::assertSame(0, $status);
        self::assertStringContainsString('No constant preg_* patterns found', $tester->getDisplay());
    }

    public function test_command_has_correct_name(): void
    {
        $command = new RegexLintCommand(
            regex: Regex::create(),
            editorUrl: null,
            defaultPaths: [],
            excludePaths: [],
            routeAnalyzer: null,
            validatorAnalyzer: null,
            router: null,
            validator: null,
            validatorLoader: null,
            defaultRedosThreshold: 'high',
        );

        self::assertSame('regex:lint', $command->getName());
    }

    public function test_command_has_all_expected_options(): void
    {
        $command = new RegexLintCommand(
            regex: Regex::create(),
            editorUrl: null,
            defaultPaths: [],
            excludePaths: [],
            routeAnalyzer: null,
            validatorAnalyzer: null,
            router: null,
            validator: null,
            validatorLoader: null,
            defaultRedosThreshold: 'high',
        );

        $definition = $command->getDefinition();
        
        // Test basic options exist
        self::assertTrue($definition->hasOption('fail-on-warnings'));
        self::assertTrue($definition->hasOption('analyze-redos'));
        self::assertTrue($definition->hasOption('redos-threshold'));
        self::assertTrue($definition->hasOption('optimize'));
        self::assertTrue($definition->hasOption('min-savings'));
        self::assertTrue($definition->hasOption('validate-symfony'));
        self::assertTrue($definition->hasOption('fail-on-suggestions'));
        self::assertTrue($definition->hasOption('all'));
        
        // Test default values
        self::assertSame('high', $definition->getOption('redos-threshold')->getDefault());
        self::assertSame(1, $definition->getOption('min-savings')->getDefault());
    }

    public function test_command_with_validate_symfony_missing_services(): void
    {
        $command = new RegexLintCommand(
            regex: Regex::create(),
            editorUrl: null,
            defaultPaths: ['nonexistent'],
            excludePaths: [],
            routeAnalyzer: null,
            validatorAnalyzer: null,
            router: null,
            validator: null,
            validatorLoader: null,
            defaultRedosThreshold: 'high',
        );

        $tester = new CommandTester($command);
        $status = $tester->execute(['--validate-symfony' => true]);

        self::assertSame(0, $status);
        self::assertStringContainsString('No router service was found', $tester->getDisplay());
        self::assertStringContainsString('No validator service was found', $tester->getDisplay());
    }
}