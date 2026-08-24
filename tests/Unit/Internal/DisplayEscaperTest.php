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

namespace RegexParser\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Internal\DisplayEscaper;

final class DisplayEscaperTest extends TestCase
{
    #[Test]
    #[DataProvider('provideEscapedText')]
    public function test_escape_only_rewrites_unprintable_bytes(string $text, string $expected): void
    {
        $this->assertSame($expected, DisplayEscaper::escape($text));
    }

    #[Test]
    public function test_escape_keeps_utf8_patterns_equivalent(): void
    {
        $pattern = '/《붉은별》/iu';

        $this->assertSame($pattern, DisplayEscaper::escape($pattern));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideEscapedText(): iterable
    {
        yield 'ascii is untouched' => ['/[a-z]+/i', '/[a-z]+/i'];
        yield 'control chars are escaped' => ["/a\tb\nc/", '/a\tb\nc/'];
        yield 'del is escaped' => ["/a\177b/", '/a\177b/'];
        yield 'utf8 is preserved' => ['/«»“”/u', '/«»“”/u'];
        yield 'invalid utf8 is escaped' => ["/x\x80\xFEy/", '/x\200\376y/'];
    }
}
