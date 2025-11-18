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
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\NodeVisitor\HtmlExplainVisitor;
use RegexParser\Parser;

class ExplainVisitorTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function test_text_explain(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/^a|b$/i');
        $visitor = new ExplainVisitor();

        $output = $ast->accept($visitor);

        $this->assertStringContainsString('Regex matches (with flags: i)', $output);
        $this->assertStringContainsString('EITHER:', $output);
        $this->assertStringContainsString('Anchor: the start of the string', $output);
        $this->assertStringContainsString('OR:', $output);
    }

    public function test_html_explain_escaping(): void
    {
        $parser = new Parser();
        // The parser splits "<script>" into multiple literals: "<", "s", "c", ...
        $ast = $parser->parse('/<script>/');
        $visitor = new HtmlExplainVisitor();

        $output = $ast->accept($visitor);

        // We check that special HTML characters are correctly escaped in the rendering
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;', $output);
        $this->assertStringContainsString('&gt;', $output);
    }

    public function test_quantifier_formatting_simple_vs_complex(): void
    {
        // Simple: a* -> single line
        $ast = $this->parser->parse('/a*/');
        $text = $ast->accept(new ExplainVisitor());
        $html = $ast->accept(new HtmlExplainVisitor());

        $this->assertStringNotContainsString('Start Quantified Group', $text); // Simple format
        $this->assertStringContainsString('<li>(zero or more times)', $html); // Injected into <li>

        // Complex: (a|b)* -> multi line block
        $ast = $this->parser->parse('/(a|b)*/');
        $text = $ast->accept(new ExplainVisitor());
        $html = $ast->accept(new HtmlExplainVisitor());

        $this->assertStringContainsString('Start Quantified Group', $text);
        $this->assertStringContainsString('<li><strong>Quantifier', $html); // Wrapped
    }

    public function test_conditional_no_else_branch(): void
    {
        // (?(1)a) -> No else branch
        $ast = $this->parser->parse('/(?(1)a)/');
        $text = $ast->accept(new ExplainVisitor());
        $html = $ast->accept(new HtmlExplainVisitor());

        $this->assertStringNotContainsString('ELSE:', $text);
        $this->assertStringNotContainsString('ELSE:', $html);
    }

    public function test_subroutine_references(): void
    {
        // (?0), (?R), (?1)
        $ast = $this->parser->parse('/(?R)(?1)/');
        $text = $ast->accept(new ExplainVisitor());

        $this->assertStringContainsString('recurses to the entire pattern', $text);
        $this->assertStringContainsString('recurses to group 1', $text);
    }

    public function test_all_char_types_explanation(): void
    {
        // \d \D \s \S \w \W \v \V \h \H \R
        $ast = $this->parser->parse('/\d\D\s\S\w\W\v\V\h\H\R/');
        $text = $ast->accept(new ExplainVisitor());

        $this->assertStringContainsString('digit', $text);
        $this->assertStringContainsString('non-digit', $text);
        $this->assertStringContainsString('whitespace', $text);
        $this->assertStringContainsString('vertical', $text);
        $this->assertStringContainsString('horizontal', $text);
    }
}
