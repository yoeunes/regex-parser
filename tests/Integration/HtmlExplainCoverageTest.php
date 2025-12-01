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

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\HtmlExplainVisitor;
use RegexParser\Regex;

/**
 * Tests to increase HtmlExplainVisitor coverage.
 */
class HtmlExplainCoverageTest extends TestCase
{
    private Regex $regex;

    private HtmlExplainVisitor $visitor;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
        $this->visitor = new HtmlExplainVisitor();
    }

    public function test_html_explain_unicode_prop(): void
    {
        $ast = $this->regex->parse('/\p{L}/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('Unicode', $result);
    }

    public function test_html_explain_negated_unicode_prop(): void
    {
        $ast = $this->regex->parse('/\P{L}/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_octal(): void
    {
        $ast = $this->regex->parse('/\o{101}/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_octal_legacy(): void
    {
        $ast = $this->regex->parse('/\07/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_unicode(): void
    {
        $ast = $this->regex->parse('/\u{41}/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_backref(): void
    {
        $ast = $this->regex->parse('/(abc)\1/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
        $this->assertStringContainsStringIgnoringCase('backreference', $result);
    }

    public function test_html_explain_range(): void
    {
        $ast = $this->regex->parse('/[a-z]/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_char_class(): void
    {
        $ast = $this->regex->parse('/[abc]/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_keep(): void
    {
        $ast = $this->regex->parse('/abc\Kdef/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_assertion(): void
    {
        $ast = $this->regex->parse('/(?=abc)/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_anchor(): void
    {
        $ast = $this->regex->parse('/^abc$/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_dot(): void
    {
        $ast = $this->regex->parse('/./');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_char_type(): void
    {
        $ast = $this->regex->parse('/\d/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_literal(): void
    {
        $ast = $this->regex->parse('/abc/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_quantifier_greedy(): void
    {
        $ast = $this->regex->parse('/a+/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_quantifier_lazy(): void
    {
        $ast = $this->regex->parse('/a+?/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_quantifier_exact(): void
    {
        $ast = $this->regex->parse('/a{3}/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_quantifier_range(): void
    {
        $ast = $this->regex->parse('/a{2,5}/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_quantifier_at_least(): void
    {
        $ast = $this->regex->parse('/a{2,}/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_group(): void
    {
        $ast = $this->regex->parse('/(abc)/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_sequence(): void
    {
        $ast = $this->regex->parse('/abc/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_alternation(): void
    {
        $ast = $this->regex->parse('/abc|def/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_posix_class(): void
    {
        $ast = $this->regex->parse('/[[:alnum:]]/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_comment(): void
    {
        $ast = $this->regex->parse('/(?#comment)/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_conditional(): void
    {
        $ast = $this->regex->parse('/(?(?=a)b|c)/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_subroutine(): void
    {
        $ast = $this->regex->parse('/(?<name>abc)(?&name)/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_pcre_verb(): void
    {
        $ast = $this->regex->parse('/(*FAIL)/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_complex_pattern(): void
    {
        $ast = $this->regex->parse('/^(?:[a-z]+|\d{2,5})*$/i');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('<', $result);
    }

    public function test_html_explain_with_special_chars(): void
    {
        $ast = $this->regex->parse('/[<>&"\']/');
        $result = $ast->accept($this->visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_non_printable(): void
    {
        // Caractère ASCII 1 (Start of Heading), non imprimable et non mappé spécifiquement
        $ast = $this->regex->parse("/\x01/");
        $result = $ast->accept($this->visitor);

        $this->assertStringContainsString('non-printable char', $result);
    }
}
