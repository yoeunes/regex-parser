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
use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use RegexParser\Bridge\Symfony\Command\RegexParserValidateCommand;
use RegexParser\Regex;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class RegexParserValidateCommandTest extends TestCase
{
    public function test_command_succeeds_with_warnings_when_services_missing(): void
    {
        $command = new RegexParserValidateCommand(
            new RouteRequirementAnalyzer(Regex::create(), 10, 20),
            router: null,
            validatorAnalyzer: null,
            validator: null,
            validatorLoader: null,
        );

        $tester = new CommandTester($command);
        $status = $tester->execute([]);

        self::assertSame(0, $status);
        self::assertStringContainsString('No router service was found', $tester->getDisplay());
        self::assertStringContainsString('No validator service was found', $tester->getDisplay());
        self::assertStringContainsString('No regex issues detected.', $tester->getDisplay());
    }

    public function test_command_fails_when_errors_detected(): void
    {
        $regex = Regex::create();

        $routeAnalyzer = new RouteRequirementAnalyzer($regex, 0, 1);
        $router = new class implements RouterInterface {
            public function getRouteCollection(): RouteCollection
            {
                $routes = new RouteCollection();
                $routes->add('bad', new Route('/a', [], ['id' => '^($'])); // invalid

                return $routes;
            }

            public function setContext(\Symfony\Component\Routing\RequestContext $context): void {}

            public function getContext(): \Symfony\Component\Routing\RequestContext
            {
                return new \Symfony\Component\Routing\RequestContext();
            }

            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '';
            }

            public function match(string $pathinfo): array
            {
                return [];
            }
        };

        $command = new RegexParserValidateCommand($routeAnalyzer, $router, null, null, null);
        $tester = new CommandTester($command);

        $status = $tester->execute([]);

        self::assertSame(1, $status);
        self::assertStringContainsString('[error]', $tester->getDisplay());
    }
}

final class DummyValidated
{
    public string $value = '';
}
