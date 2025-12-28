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
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharLiteralType;
use RegexParser\Node\ControlCharNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\NodeVisitor\DumperNodeVisitor;

final class DumperNodeVisitorCoverageTest extends TestCase
{
    public function test_unicode_char_literal_and_control_char_dump(): void
    {
        $visitor = new DumperNodeVisitor();

        $unicode = new UnicodeNode('41', 0, 0);
        $this->assertSame('Unicode(41)', $unicode->accept($visitor));

        $named = new CharLiteralNode('\\N{LATIN SMALL LETTER A}', 0, CharLiteralType::UNICODE_NAMED, 0, 0);
        $this->assertSame('UnicodeNamed(\\N{LATIN SMALL LETTER A})', $named->accept($visitor));

        $control = new ControlCharNode('A', 1, 0, 0);
        $this->assertSame('ControlChar(A)', $control->accept($visitor));
    }
}
