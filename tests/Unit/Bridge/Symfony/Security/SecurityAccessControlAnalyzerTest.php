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

namespace RegexParser\Tests\Unit\Bridge\Symfony\Security;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Security\SecurityAccessControlAnalyzer;
use RegexParser\Bridge\Symfony\Security\SecurityConfigExtractor;
use RegexParser\Regex;

final class SecurityAccessControlAnalyzerTest extends TestCase
{
    #[Test]
    public function test_detects_critical_shadowing(): void
    {
        $path = dirname(__DIR__, 4).'/Fixtures/Symfony/security_access_control.yaml';
        $extractor = new SecurityConfigExtractor();
        $result = $extractor->extract($path);

        $analyzer = new SecurityAccessControlAnalyzer(Regex::create());
        $report = $analyzer->analyze($result['accessControl']);

        $this->assertSame(1, $report->stats['shadowed']);
        $this->assertSame(1, $report->stats['critical']);
        $this->assertCount(1, $report->conflicts);
        $this->assertSame('shadowed', $report->conflicts[0]['type']);
        $this->assertSame('critical', $report->conflicts[0]['severity']);
    }

    #[Test]
    public function test_detects_prefix_shadowing_with_search_semantics(): void
    {
        $rules = [
            [
                'file' => 'security.yaml',
                'line' => 10,
                'path' => '^/admin',
                'host' => null,
                'roles' => ['PUBLIC_ACCESS'],
                'methods' => [],
                'ips' => [],
                'allowIf' => null,
                'requestMatcher' => null,
                'requiresChannel' => null,
            ],
            [
                'file' => 'security.yaml',
                'line' => 12,
                'path' => '^/admin/secure',
                'host' => null,
                'roles' => ['ROLE_ADMIN'],
                'methods' => [],
                'ips' => [],
                'allowIf' => null,
                'requestMatcher' => null,
                'requiresChannel' => null,
            ],
        ];

        $analyzer = new SecurityAccessControlAnalyzer(Regex::create());
        $report = $analyzer->analyze($rules);

        $this->assertSame(1, $report->stats['shadowed']);
        $this->assertSame(1, $report->stats['critical']);
        $this->assertSame('shadowed', $report->conflicts[0]['type']);
    }
}
