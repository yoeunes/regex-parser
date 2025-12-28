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
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharLiteralType;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\RegexNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;

final class CompilerNodeVisitorCoverageTest extends TestCase
{
    public function test_pretty_group_prefixes_are_rendered(): void
    {
        $visitor = new CompilerNodeVisitor(true);
        $child = new LiteralNode('a', 0, 0);

        $named = new GroupNode($child, GroupType::T_GROUP_NAMED, 'name', null, 0, 0);
        $this->assertStringContainsString('<name>', $named->accept($visitor));

        $lookahead = new GroupNode($child, GroupType::T_GROUP_LOOKAHEAD_POSITIVE, null, null, 0, 0);
        $this->assertStringContainsString('(=', $lookahead->accept($visitor));

        $lookbehind = new GroupNode($child, GroupType::T_GROUP_LOOKBEHIND_NEGATIVE, null, null, 0, 0);
        $this->assertStringContainsString('(<!', $lookbehind->accept($visitor));

        $atomic = new GroupNode($child, GroupType::T_GROUP_ATOMIC, null, null, 0, 0);
        $this->assertStringContainsString('(>', $atomic->accept($visitor));

        $branchReset = new GroupNode($child, GroupType::T_GROUP_BRANCH_RESET, null, null, 0, 0);
        $this->assertStringContainsString('(|', $branchReset->accept($visitor));

        $inlineFlags = new GroupNode($child, GroupType::T_GROUP_INLINE_FLAGS, null, 'im', 0, 0);
        $this->assertStringContainsString('(im:', $inlineFlags->accept($visitor));
    }

    public function test_char_literal_control_escapes_use_f_and_e(): void
    {
        $visitor = new CompilerNodeVisitor();

        $formFeed = new CharLiteralNode("\x0C", 12, CharLiteralType::UNICODE, 0, 0);
        $this->assertSame('\\f', $formFeed->accept($visitor));

        $escape = new CharLiteralNode("\x1B", 27, CharLiteralType::UNICODE, 0, 0);
        $this->assertSame('\\e', $escape->accept($visitor));
    }

    public function test_literal_control_characters_escape_string(): void
    {
        $visitor = new CompilerNodeVisitor();

        $formFeed = new LiteralNode("\x0C", 0, 0);
        $this->assertSame('\\f', $formFeed->accept($visitor));

        $escape = new LiteralNode("\x1B", 0, 0);
        $this->assertSame('\\e', $escape->accept($visitor));
    }

    public function test_collapse_extended_comments_in_extended_mode(): void
    {
        $visitor = new CompilerNodeVisitor(false, true);
        $regex = new RegexNode(new CommentNode('comment', 0, 0), 'x', '/', 0, 0);

        $this->assertSame('/(?#...)/x', $regex->accept($visitor));
    }

    public function test_pretty_inline_comment_multiline_formats_lines(): void
    {
        $visitor = new CompilerNodeVisitor(true);
        $comment = new CommentNode("line1\nline2\n", 0, 0);
        $regex = new RegexNode($comment, '', '/', 0, 0);

        $compiled = $regex->accept($visitor);

        $this->assertStringContainsString('# line1', $compiled);
        $this->assertStringContainsString('# line2', $compiled);
    }

    public function test_pretty_inline_comment_hash_is_indented(): void
    {
        $visitor = new CompilerNodeVisitor(true);
        $comment = new CommentNode('# note', 0, 0);
        $regex = new RegexNode($comment, '', '/', 0, 0);

        $this->assertSame('/# note/', $regex->accept($visitor));
    }

    public function test_pretty_define_block_includes_newlines(): void
    {
        $visitor = new CompilerNodeVisitor(true);
        $define = new DefineNode(new LiteralNode('a', 0, 0), 0, 0);

        $compiled = $define->accept($visitor);

        $this->assertStringContainsString('(?(DEFINE)', $compiled);
        $this->assertStringContainsString("\n", $compiled);
    }

    public function test_pretty_conditional_without_else_branch(): void
    {
        $visitor = new CompilerNodeVisitor(true);
        $conditional = new ConditionalNode(
            new BackrefNode('1', 0, 0),
            new LiteralNode('a', 0, 0),
            new LiteralNode('', 0, 0),
            0,
            0,
        );

        $compiled = $conditional->accept($visitor);

        $this->assertStringContainsString('(?(', $compiled);
        $this->assertStringNotContainsString('|', $compiled);
    }

    public function test_pretty_conditional_with_else_branch(): void
    {
        $visitor = new CompilerNodeVisitor(true);
        $conditional = new ConditionalNode(
            new BackrefNode('1', 0, 0),
            new LiteralNode('a', 0, 0),
            new LiteralNode('b', 0, 0),
            0,
            0,
        );

        $compiled = $conditional->accept($visitor);

        $this->assertStringContainsString('|', $compiled);
    }
}
