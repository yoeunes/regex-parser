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
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\LiteralNode;
use RegexParser\NodeVisitor\ExplainNodeVisitor;

final class ExplainNodeVisitorCoverageTest extends TestCase
{
    public function test_inline_flags_group_is_explained(): void
    {
        $visitor = new ExplainNodeVisitor();
        $group = new GroupNode(new LiteralNode('a', 0, 0), GroupType::T_GROUP_INLINE_FLAGS, null, 'im', 0, 0);

        $this->assertStringContainsString("Inline flags 'im'", $group->accept($visitor));
    }

    public function test_control_char_is_explained(): void
    {
        $visitor = new ExplainNodeVisitor();
        $control = new ControlCharNode('A', 1, 0, 0);

        $this->assertStringContainsString('\\cA', $control->accept($visitor));
    }

    public function test_unicode_named_character_extracts_name(): void
    {
        $visitor = new ExplainNodeVisitor();
        $node = new CharLiteralNode('\\N{LATIN SMALL LETTER A}', 0, CharLiteralType::UNICODE_NAMED, 0, 0);

        $this->assertStringContainsString('LATIN SMALL LETTER A', $node->accept($visitor));
    }

    public function test_unicode_named_character_falls_back_to_representation(): void
    {
        $visitor = new ExplainNodeVisitor();
        $node = new CharLiteralNode('\\N{', 0, CharLiteralType::UNICODE_NAMED, 0, 0);

        $this->assertStringContainsString('\\N{', $node->accept($visitor));
    }
}
