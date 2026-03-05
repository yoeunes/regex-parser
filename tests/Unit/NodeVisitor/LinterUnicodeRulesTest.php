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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\LintIssue;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\Regex;
use RegexParser\Severity;

final class LinterUnicodeRulesTest extends TestCase
{
    #[Test]
    public function test_shorthand_without_u_flag_warning(): void
    {
        $this->assertHasIssue('/\\w+/', 'regex.lint.unicode.shorthandWithoutU');
    }

    #[Test]
    public function test_shorthand_with_u_flag_no_warning(): void
    {
        $this->assertNoIssue('/\\w+/u', 'regex.lint.unicode.shorthandWithoutU');
    }

    #[Test]
    public function test_unicode_property_without_u_flag_error(): void
    {
        $this->assertHasIssue('/\\p{L}+/', 'regex.lint.unicode.propertyWithoutU');
    }

    #[Test]
    public function test_unicode_property_with_u_flag_no_error(): void
    {
        $this->assertNoIssue('/\\p{L}+/u', 'regex.lint.unicode.propertyWithoutU');
    }

    #[Test]
    public function test_braced_hex_without_u_flag_error(): void
    {
        $this->assertHasIssue('/\\x{100}/', 'regex.lint.unicode.bracedHexWithoutU');
    }

    #[Test]
    public function test_braced_hex_with_u_flag_no_error(): void
    {
        $this->assertNoIssue('/\\x{100}/u', 'regex.lint.unicode.bracedHexWithoutU');
    }

    #[Test]
    public function test_braced_hex_below_ff_no_error(): void
    {
        $this->assertNoIssue('/\\x{41}/', 'regex.lint.unicode.bracedHexWithoutU');
    }

    #[Test]
    public function test_braced_hex_at_ff_boundary_no_error(): void
    {
        $this->assertNoIssue('/\\x{FF}/', 'regex.lint.unicode.bracedHexWithoutU');
    }

    #[Test]
    #[DataProvider('provideShorthandPatterns')]
    public function test_all_shorthands_trigger_warning(string $pattern): void
    {
        $this->assertHasIssue($pattern, 'regex.lint.unicode.shorthandWithoutU');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideShorthandPatterns(): iterable
    {
        yield 'word' => ['/\\w/'];
        yield 'digit' => ['/\\d/'];
        yield 'space' => ['/\\s/'];
        yield 'non-word' => ['/\\W/'];
        yield 'non-digit' => ['/\\D/'];
        yield 'non-space' => ['/\\S/'];
    }

    #[Test]
    #[DataProvider('provideShorthandPatternsWithUFlag')]
    public function test_all_shorthands_no_warning_with_u_flag(string $pattern): void
    {
        $this->assertNoIssue($pattern, 'regex.lint.unicode.shorthandWithoutU');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideShorthandPatternsWithUFlag(): iterable
    {
        yield 'word' => ['/\\w/u'];
        yield 'digit' => ['/\\d/u'];
        yield 'space' => ['/\\s/u'];
        yield 'non-word' => ['/\\W/u'];
        yield 'non-digit' => ['/\\D/u'];
        yield 'non-space' => ['/\\S/u'];
    }

    #[Test]
    public function test_shorthand_issue_has_style_severity(): void
    {
        $issues = $this->lint('/\\w+/');
        $shorthandIssue = null;
        foreach ($issues as $issue) {
            if ('regex.lint.unicode.shorthandWithoutU' === $issue->id) {
                $shorthandIssue = $issue;

                break;
            }
        }

        $this->assertInstanceOf(LintIssue::class, $shorthandIssue);
        $this->assertSame(Severity::Style, $shorthandIssue->severity);
    }

    #[Test]
    public function test_property_issue_has_error_severity(): void
    {
        $issues = $this->lint('/\\p{L}+/');
        $propertyIssue = null;
        foreach ($issues as $issue) {
            if ('regex.lint.unicode.propertyWithoutU' === $issue->id) {
                $propertyIssue = $issue;

                break;
            }
        }

        $this->assertInstanceOf(LintIssue::class, $propertyIssue);
        $this->assertSame(Severity::Error, $propertyIssue->severity);
    }

    #[Test]
    public function test_braced_hex_issue_has_error_severity(): void
    {
        $issues = $this->lint('/\\x{100}/');
        $bracedHexIssue = null;
        foreach ($issues as $issue) {
            if ('regex.lint.unicode.bracedHexWithoutU' === $issue->id) {
                $bracedHexIssue = $issue;

                break;
            }
        }

        $this->assertInstanceOf(LintIssue::class, $bracedHexIssue);
        $this->assertSame(Severity::Error, $bracedHexIssue->severity);
    }

    #[Test]
    public function test_unicode_property_negated_without_u_flag(): void
    {
        $this->assertHasIssue('/\\P{L}+/', 'regex.lint.unicode.propertyWithoutU');
    }

    #[Test]
    public function test_multiple_shorthands_generate_multiple_issues(): void
    {
        $issues = $this->lint('/\\w\\d\\s/');
        $shorthandIssues = array_filter(
            $issues,
            static fn ($issue) => 'regex.lint.unicode.shorthandWithoutU' === $issue->id,
        );

        $this->assertCount(3, $shorthandIssues);
    }

    private function assertHasIssue(string $pattern, string $issueId): void
    {
        $issueIds = array_map(static fn ($issue) => $issue->id, $this->lint($pattern));
        $this->assertContains($issueId, $issueIds, \sprintf('Expected issue "%s" not found in pattern "%s"', $issueId, $pattern));
    }

    private function assertNoIssue(string $pattern, string $issueId): void
    {
        $issueIds = array_map(static fn ($issue) => $issue->id, $this->lint($pattern));
        $this->assertNotContains($issueId, $issueIds, \sprintf('Unexpected issue "%s" found in pattern "%s"', $issueId, $pattern));
    }

    /**
     * @return array<LintIssue>
     */
    private function lint(string $pattern): array
    {
        $regex = Regex::create()->parse($pattern);
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);

        return $linter->getIssues();
    }
}
