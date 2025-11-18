<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Node\OctalNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;

class ValidatorImpossibleTest extends TestCase
{
    public function test_unicode_out_of_bounds_manual(): void
    {
        $validator = new ValidatorNodeVisitor();

        // Manual construction of a node that Parser would normally reject or parse differently
        // \u{110000} (Too large for Unicode)
        // We pass the raw string that matches the regex check inside Visitor
        $node = new UnicodeNode('\u{110000}', 0, 0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid Unicode codepoint');
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

        // \o{9} (Invalid octal digit)
        $node = new OctalNode('\o{9}', 0, 0);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid octal codepoint');
        $node->accept($validator);
    }
}
