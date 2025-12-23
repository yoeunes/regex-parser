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
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Regex;

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

        $regex = Regex::create()->parse('/(a)\1/');
        $regex->accept($this->validator);
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
