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
use RegexParser\Node\UnicodeNode;
use RegexParser\NodeVisitor\ConsoleHighlighterVisitor;
use RegexParser\NodeVisitor\HtmlHighlighterVisitor;

final class HighlighterVisitorCoverageTest extends TestCase
{
    public function test_unicode_highlight_uses_original_hex_format(): void
    {
        $visitor = new HtmlHighlighterVisitor();
        $node = new UnicodeNode('41', 0, 0);

        $output = $node->accept($visitor);

        $this->assertStringContainsString('\\x41', $output);
    }

    public function test_console_unicode_highlight_uses_hex_format(): void
    {
        $visitor = new ConsoleHighlighterVisitor();
        $node = new UnicodeNode('41', 0, 0);

        $output = $node->accept($visitor);

        $this->assertStringContainsString('\\x41', $output);
    }
}
