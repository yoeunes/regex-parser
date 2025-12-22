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

namespace RegexParser\Tests\Regression;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\Regex;

final class RealWorldCasesTest extends TestCase
{
    #[DataProvider('provideRealWorldRegex')]
    public function testRealWorldCases(string $pattern, string $expectedIssueMessage, string $sourceFile): void
    {
        $report = Regex::new()->analyze($pattern);
        $issueMessages = array_map(fn($issue) => $issue->message, $report->lintIssues);

        $this->assertContains($expectedIssueMessage, $issueMessages, "Expected issue message not found for pattern from $sourceFile");
    }

    public static function provideRealWorldRegex(): \Iterator
    {
        // Nested quantifiers (from real-world)
        yield ['~{args.((?:[^{}}]++|(?R))*)}~', 'Nested quantifiers can cause catastrophic backtracking.', './livehelperchat/lhc_web/lib/core/lhchat/lhchatvalidator.php'];

        // WARN cases
        yield ['@^0([0-7]+)$@i', "Flag 'i' is useless: the pattern contains no case-sensitive characters.", './livehelperchat/lhc_web/ezcomponents/Configuration/src/ini/ini_writer.php'];
        yield ['@^(([0-9]*.[0-9]+)|([0-9]+(.[0-9]*)?))(e[+-]?[0-9]+)?$@i', 'Alternation branches have overlapping character sets, which may cause unnecessary backtracking.', './livehelperchat/lhc_web/ezcomponents/Configuration/src/ini/ini_parser.php'];
        yield ['/=(([a-f][a-f0-9])|([a-f0-9][a-f]))/', 'Alternation branches have overlapping character sets, which may cause unnecessary backtracking.', './livehelperchat/lhc_web/ezcomponents/Mail/src/tools.php'];

        // Known ReDoS pattern
        yield ['/(a+)+/', 'Nested quantifiers can cause catastrophic backtracking.', 'Known ReDoS pattern'];
    }
}