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
use RegexParser\Node\OctalNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;

final class ValidatorImpossibleTest extends TestCase
{
    public function test_unicode_out_of_bounds_manual(): void
    {
        $validator = new ValidatorNodeVisitor();

        // \u{110000} (Too large for Unicode, max is 10FFFF)
        // We pass the raw string that matches the regex check inside Validator
        $node = new UnicodeNode('\u{110000}', 0, 0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('out of range');
        $node->accept($validator);
    }

    public function test_octal_out_of_bounds_manual(): void
    {
        $validator = new ValidatorNodeVisitor();

        // \o{4000000} (Too large)
        $node = new OctalNode('\o{4000000}', 0, 0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid octal codepoint');
        $node->accept($validator);
    }

    public function test_octal_invalid_format_manual(): void
    {
        $validator = new ValidatorNodeVisitor();

        // \o{8} (Invalid octal digit, Parser regex usually catches this, but Validator has a check too)
        $node = new OctalNode('\o{9}', 0, 0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid octal codepoint');
        $node->accept($validator);
    }
}
