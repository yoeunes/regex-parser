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

namespace RegexParser\Tests\Unit\Transpiler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Exception\TranspileException;
use RegexParser\Regex;

final class PythonTranspilerTest extends TestCase
{
    #[Test]
    public function test_transpiles_basic_pattern_to_python_raw_string(): void
    {
        $result = Regex::create()->transpile('/foo/im', 'python');

        $this->assertSame('python', $result->target);
        $this->assertSame('foo', $result->pattern);
        $this->assertSame('im', $result->flags);
        $this->assertSame("r'foo'", $result->literal);
    }

    #[Test]
    public function test_keeps_python_style_named_groups_and_backrefs(): void
    {
        $result = Regex::create()->transpile('/(?P<w>\w+)(?P=w)/', 'python');

        $this->assertSame("r'(?P<w>\\w+)(?P=w)'", $result->literal);
    }

    #[Test]
    public function test_converts_g_backreference_to_numeric(): void
    {
        $result = Regex::create()->transpile('/(a)\g{1}/', 'python');

        $this->assertSame("r'(a)\\1'", $result->literal);
    }

    #[Test]
    public function test_keeps_lookarounds(): void
    {
        $regex = Regex::create();

        $this->assertSame("r'a(?=b)'", $regex->transpile('/a(?=b)/', 'python')->literal);
        $this->assertSame("r'(?<=x)y'", $regex->transpile('/(?<=x)y/', 'python')->literal);
    }

    #[Test]
    public function test_emulates_atomic_groups_with_lookahead_capture(): void
    {
        $result = Regex::create()->transpile('/(?>x)y/', 'python');

        $this->assertSame("r'(?=(?P<tmp>x))(?P=tmp)y'", $result->literal);
    }

    #[Test]
    public function test_converts_horizontal_whitespace_to_character_class(): void
    {
        $result = Regex::create()->transpile('/\h+/', 'python');

        $this->assertStringStartsWith("r'[\\x09\\x20", $result->literal);
        $this->assertNotEmpty($result->notes);
    }

    #[Test]
    public function test_rejects_keep_escape(): void
    {
        $this->expectException(TranspileException::class);
        $this->expectExceptionMessage('\K is not supported in Python re.');

        Regex::create()->transpile('/a\Kb/', 'python');
    }

    #[Test]
    public function test_rejects_possessive_quantifiers(): void
    {
        $this->expectException(TranspileException::class);

        Regex::create()->transpile('/a++b/', 'python');
    }
}
