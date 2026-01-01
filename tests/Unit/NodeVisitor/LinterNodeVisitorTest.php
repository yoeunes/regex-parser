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

namespace RegexParser\Tests\Unit\NodeVisitor;

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
        $this->assertNotContains("Flag 'i' is useless: the pattern contains no case-sensitive characters.", $warnings);
    }

    public function test_i_flag_not_useless_with_backreference(): void
    {
        $regex = Regex::create()->parse('/^<(\\w+)>.*<\\/\\1>$/i');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertNotContains("Flag 'i' is useless: the pattern contains no case-sensitive characters.", $warnings);
    }

    public function test_i_flag_not_useless_with_named_backreference(): void
    {
        $regex = Regex::create()->parse('/^<(?<tag>\\w+)>.*<\\/\\k<tag>>$/i');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertNotContains("Flag 'i' is useless: the pattern contains no case-sensitive characters.", $warnings);
    }

    public function test_i_flag_not_useless_on_unicode_escape(): void
    {
        $regex = Regex::create()->parse('/\\x41/i');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertNotContains("Flag 'i' is useless: the pattern contains no case-sensitive characters.", $warnings);
    }

    public function test_i_flag_not_useless_on_char_class_unicode_escape(): void
    {
        $regex = Regex::create()->parse('/[\\x41]/i');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertNotContains("Flag 'i' is useless: the pattern contains no case-sensitive characters.", $warnings);
    }

    public function test_i_flag_not_useless_on_unicode_property(): void
    {
        $regex = Regex::create()->parse('/\\p{Lu}/iu');
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
        $regex = Regex::create()->parse('/\\d+/m');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertStringContainsString("Flag 'm' is useless:", $warnings[0] ?? '');
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

    public function test_start_anchor_assertion_conflict(): void
    {
        $regex = Regex::create()->parse('/foo\\Abar/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);

        $issueIds = array_map(static fn ($issue): string => $issue->id, $linter->getIssues());
        $this->assertContains('regex.lint.anchor.impossible.start', $issueIds);
    }

    public function test_end_anchor_assertion_conflict(): void
    {
        $regex = Regex::create()->parse('/foo\\zbar/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);

        $issueIds = array_map(static fn ($issue): string => $issue->id, $linter->getIssues());
        $this->assertContains('regex.lint.anchor.impossible.end', $issueIds);
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

    public function test_backref_to_nonexistent_group(): void
    {
        $regex = Regex::create()->parse('/\\2/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertContains('Backreference \\2 refers to a non-existent capturing group.', $warnings);
    }

    public function test_backref_to_valid_group(): void
    {
        $regex = Regex::create()->parse('/(a)\\1/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertNotContains('Backreference \\1 refers to a non-existent capturing group.', $warnings);
    }

    public function test_g_backref_to_nonexistent_group(): void
    {
        $regex = Regex::create()->parse('/\\g{2}/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertContains('Backreference \\2 refers to a non-existent capturing group.', $warnings);
    }

    public function test_g_backref_relative_reference_is_not_flagged(): void
    {
        $regex = Regex::create()->parse('/(a)\\g{-1}/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertNotContains('Backreference \\1 refers to a non-existent capturing group.', $warnings);
    }

    public function test_named_backref_to_nonexistent_group(): void
    {
        $regex = Regex::create()->parse('/\\k<foo>/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertContains('Backreference \\k<foo> refers to a non-existent named group.', $warnings);
    }

    public function test_named_backref_to_valid_group(): void
    {
        $regex = Regex::create()->parse('/(?<foo>a)\\k<foo>/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertNotContains('Backreference \\k<foo> refers to a non-existent named group.', $warnings);
    }

    public function test_semantic_overlap_in_alternation(): void
    {
        $regex = Regex::create()->parse('/[a-c]|[b-d]/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertContains('Alternation branches have overlapping character sets, which may cause unnecessary backtracking.', $warnings);
    }

    public function test_no_semantic_overlap_in_alternation(): void
    {
        $regex = Regex::create()->parse('/[a-c]|[d-e]/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertNotContains('Alternation branches have overlapping character sets, which may cause unnecessary backtracking.', $warnings);
    }

    public function test_semantic_overlap_with_char_types(): void
    {
        $regex = Regex::create()->parse('/\\d|[0-9]/');
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);
        $warnings = $linter->getWarnings();

        $this->assertContains('Alternation branches have overlapping character sets, which may cause unnecessary backtracking.', $warnings);
    }
}
