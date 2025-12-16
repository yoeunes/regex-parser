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
use RegexParser\Exception\ParserException;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharLiteralType;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;

final class ValidatorImpossibleTest extends TestCase
{
    public function test_unicode_out_of_bounds_manual(): void
    {
        $validator = new ValidatorNodeVisitor();

        // \u{110000} (Too large for Unicode, max is 10FFFF)
        // We pass the raw string that matches the regex check inside Validator
        $node = new CharLiteralNode('\u{110000}', 0x110000, CharLiteralType::UNICODE, 0, 0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('out of range');
        $node->accept($validator);
    }

    public function test_octal_out_of_bounds_manual(): void
    {
        $validator = new ValidatorNodeVisitor();

        // \o{4000000} (Too large)
        $node = new CharLiteralNode('\o{4000000}', 0x4000000, CharLiteralType::OCTAL, 0, 0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid octal codepoint');
        $node->accept($validator);
    }

    public function test_octal_invalid_format_manual(): void
    {
        $validator = new ValidatorNodeVisitor();

        // \o{9} (Invalid octal digit, but since parser validates, use large value)
        $node = new CharLiteralNode('\o{9}', 0x100, CharLiteralType::OCTAL, 0, 0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid octal codepoint');
        $node->accept($validator);
    }
}
