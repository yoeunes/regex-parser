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

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RegexParser\RegexPattern;

final class RegexPatternTest extends TestCase
{
    public function test_construct(): void
    {
        $pattern = new RegexPattern('foo', 'i', '#');
        $this->assertSame('foo', $pattern->pattern);
        $this->assertSame('i', $pattern->flags);
        $this->assertSame('#', $pattern->delimiter);
    }

    public function test_to_string(): void
    {
        $pattern = new RegexPattern('foo', 'i', '#');
        $this->assertSame('#foo#i', $pattern->toString());
        $this->assertSame('#foo#i', (string) $pattern);
    }

    public function test_from_raw(): void
    {
        $pattern = RegexPattern::fromRaw('foo', 'i', '#');
        $this->assertSame('foo', $pattern->pattern);
        $this->assertSame('i', $pattern->flags);
        $this->assertSame('#', $pattern->delimiter);
    }

    public function test_from_delimited(): void
    {
        $pattern = RegexPattern::fromDelimited('/foo/i');
        $this->assertSame('foo', $pattern->pattern);
        $this->assertSame('i', $pattern->flags);
        $this->assertSame('/', $pattern->delimiter);
    }

    public function test_from_delimited_with_hash(): void
    {
        $pattern = RegexPattern::fromDelimited('#bar#m');
        $this->assertSame('bar', $pattern->pattern);
        $this->assertSame('m', $pattern->flags);
        $this->assertSame('#', $pattern->delimiter);
    }

    public function test_from_delimited_no_flags(): void
    {
        $pattern = RegexPattern::fromDelimited('/baz/');
        $this->assertSame('baz', $pattern->pattern);
        $this->assertSame('', $pattern->flags);
        $this->assertSame('/', $pattern->delimiter);
    }
}
