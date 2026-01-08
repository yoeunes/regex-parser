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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Command\RegexSecurityCommand;
use RegexParser\Bridge\Symfony\Security\SecurityAccessControlAnalyzer;
use RegexParser\Bridge\Symfony\Security\SecurityConfigExtractor;
use RegexParser\Bridge\Symfony\Security\SecurityFirewallAnalyzer;
use RegexParser\Regex;
use Symfony\Component\Console\Tester\CommandTester;

final class RegexSecurityCommandTest extends TestCase
{
    #[Test]
    public function test_command_reports_security_conflicts(): void
    {
        $path = dirname(__DIR__, 4).'/Fixtures/Symfony/security_access_control.yaml';

        $command = new RegexSecurityCommand(
            new SecurityConfigExtractor(),
            new SecurityAccessControlAnalyzer(Regex::create()),
            new SecurityFirewallAnalyzer(Regex::create()),
            null,
            'high',
        );

        $tester = new CommandTester($command);
        $status = $tester->execute(['--config' => [$path]]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Access Control Conflicts', (string) $tester->getDisplay());
        $this->assertStringContainsString('Firewall Regex ReDoS', (string) $tester->getDisplay());
    }
}
