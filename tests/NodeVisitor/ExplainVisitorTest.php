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
}
