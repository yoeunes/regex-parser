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

        // Ensure we did not incorrectly emit the useless-flag warning when
        // case-sensitive characters are present.
        $this->assertFalse(in_array("Flag 'i' is useless: the pattern contains no case-sensitive characters.", $warnings, true));
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

        $this->assertFalse(in_array("Flag 's' is useless: the pattern contains no dots.", $warnings, true));
    }

    public function test_useless_m_flag_no_anchors(): void
    {
        $regex = Regex::create()->parse('/\\d+/m');
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

    public function test_start_anchor_conflict(): void
    {
        $regex = Regex::create()->parse('/foo^bar/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertContains("Start anchor '^' appears after consuming characters, making it impossible to match.", $warnings);
    }

    public function test_end_anchor_conflict(): void
    {
        $regex = Regex::create()->parse('/$foo/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertContains("End anchor '$' appears before consuming characters, making it impossible to match.", $warnings);
    }

    public function test_no_anchor_conflict_at_boundaries(): void
    {
        $regex = Regex::create()->parse('/^foo$/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertNotContains("Start anchor '^' appears after consuming characters", $warnings);
        $this->assertNotContains("End anchor '$' appears before consuming characters", $warnings);
    }

    public function test_start_anchor_multiline_valid(): void
    {
        $regex = Regex::create()->parse('/^header\n^body/m');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertNotContains("Start anchor '^' appears after consuming characters", $warnings);
    }

    public function test_start_anchor_false_positive(): void
    {
        $regex = Regex::create()->parse('/foo^bar/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertContains("Start anchor '^' appears after consuming characters, making it impossible to match.", $warnings);
    }

    public function test_start_anchor_no_multiline_flag(): void
    {
        $regex = Regex::create()->parse('/foo\\n^bar/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertContains("Start anchor '^' appears after consuming characters, making it impossible to match.", $warnings);
    }
}
