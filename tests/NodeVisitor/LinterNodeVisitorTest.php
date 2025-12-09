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

namespace RegexParser\Tests\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\Regex;

final class LinterNodeVisitorTest extends TestCase
{
    public function test_useless_i_flag_on_digits(): void
    {
        $regex = Regex::create()->parse('/^\d+$/i');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertContains("Flag 'i' is useless: the pattern contains no case-sensitive characters.", $warnings);
    }

    public function test_i_flag_not_useless_on_letters(): void
    {
        $regex = Regex::create()->parse('/[a-z]/i');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertNotContains("Flag 'i' is useless: the pattern contains no case-sensitive characters.", $warnings);
    }

    public function test_useless_s_flag_no_dots(): void
    {
        $regex = Regex::create()->parse('/^\d+$/s');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertContains("Flag 's' is useless: the pattern contains no dots.", $warnings);
    }

    public function test_s_flag_not_useless_with_dots(): void
    {
        $regex = Regex::create()->parse('/.+/s');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertNotContains("Flag 's' is useless: the pattern contains no dots.", $warnings);
    }

    public function test_useless_m_flag_no_anchors(): void
    {
        $regex = Regex::create()->parse('/\d+/m');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertContains("Flag 'm' is useless: the pattern contains no anchors.", $warnings);
    }

    public function test_m_flag_not_useless_with_anchors(): void
    {
        $regex = Regex::create()->parse('/^test$/m');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertNotContains("Flag 'm' is useless: the pattern contains no anchors.", $warnings);
    }
}