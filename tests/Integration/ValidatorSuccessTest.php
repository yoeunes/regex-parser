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
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharLiteralType;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;

final class ValidatorSuccessTest extends TestCase
{
    private ValidatorNodeVisitor $validator;

    protected function setUp(): void
    {
        $this->validator = new ValidatorNodeVisitor();
    }

    public function test_valid_backreferences_pass(): void
    {
        $this->expectNotToPerformAssertions();

        // Need to simulate group count context, but Validator tracks it internally via visitGroup.
        // Since we are manually constructing nodes, we can't easily update the internal groupCount
        // without visiting a GroupNode first.

        // Strategy: Accept a GroupNode to increment counter, then accept Backref.
        // However, we can test the "Named" backref logic if we mock the name registration or
        // rely on the fact that numeric 0 is invalid logic etc.

        // Let's rely on the fact that visitBackref returns early if valid.
        // The easiest way is actually via Parser because it sets up the context.

        // 1. Valid \g{0} (entire match)
        $node = new BackrefNode('\g{0}', 0, 0);
        $node->accept($this->validator);
    }

    public function test_valid_unicode_and_octal_pass(): void
    {
        $this->expectNotToPerformAssertions();

        // Valid Unicode
        (new CharLiteralNode('\x41', 0x41, CharLiteralType::UNICODE, 0, 0))->accept($this->validator);
        (new CharLiteralNode('\u{00E9}', 0xE9, CharLiteralType::UNICODE, 0, 0))->accept($this->validator);

        // Valid Octal
        (new CharLiteralNode('\o{77}', 0o77, CharLiteralType::OCTAL, 0, 0))->accept($this->validator);

        // Valid Legacy Octal
        (new CharLiteralNode('012', 0o12, CharLiteralType::OCTAL_LEGACY, 0, 0))->accept($this->validator);

        // Valid Unicode Prop (cached)
        (new UnicodePropNode('L', 0, 0))->accept($this->validator);
    }

    public function test_valid_subroutines_pass(): void
    {
        $this->expectNotToPerformAssertions();

        // (?R) and (?0) are always valid
        (new SubroutineNode('R', '', 0, 0))->accept($this->validator);
        (new SubroutineNode('0', '', 0, 0))->accept($this->validator);
    }
}
