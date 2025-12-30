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
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\NodeVisitor\AsciiTreeVisitor;

final class AsciiTreeVisitorCoverageTest extends TestCase
{
    public function test_char_type_and_unicode_nodes_are_rendered(): void
    {
        $visitor = new AsciiTreeVisitor();

        $charTypeRegex = new RegexNode(new CharTypeNode('d', 0, 0), '', '/', 0, 0);
        $diagram = $charTypeRegex->accept($visitor);
        $this->assertStringContainsString('CharType (\\d)', $diagram);

        $unicodeRegex = new RegexNode(new UnicodeNode('41', 0, 0), '', '/', 0, 0);
        $diagram = $unicodeRegex->accept($visitor);
        $this->assertStringContainsString('Unicode (\\x41)', $diagram);
    }
}
