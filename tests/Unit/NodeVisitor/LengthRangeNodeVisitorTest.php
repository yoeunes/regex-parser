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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\LengthRangeNodeVisitor;
use RegexParser\Regex;

final class LengthRangeNodeVisitorTest extends TestCase
{
    private LengthRangeNodeVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new LengthRangeNodeVisitor();
    }

    #[DataProvider('lengthRangeProvider')]
    public function test_length_range(string $regex, int $expectedMin, ?int $expectedMax): void
    {
        $ast = Regex::create()->parse($regex);
        [$min, $max] = $ast->accept($this->visitor);

        $this->assertSame($expectedMin, $min);
        $this->assertSame($expectedMax, $max);
    }

    /**
     * @return iterable<array{string, int, int|null}>
     */
    public static function lengthRangeProvider(): iterable
    {
        yield ['/a/', 1, 1];
        yield ['/abc/', 3, 3];
        yield ['/a?/', 0, 1];
        yield ['/a*/', 0, null];
        yield ['/a+/', 1, null];
        yield ['/a{2,5}/', 2, 5];
        yield ['/a{3,}/', 3, null];
        yield ['/a{4}/', 4, 4];
        yield ['/(a|b)/', 1, 1];
        yield ['/(aa|bbb)/', 2, 3];
        yield ['/a*b/', 1, null]; // a* + b
        yield ['/^a$/', 1, 1]; // anchors don't add length
        yield ['/\bword\b/', 4, 4]; // assertions don't add length
        yield ['/[abc]/', 1, 1];
        yield ['/\d/', 1, 1];
        yield ['/./', 1, 1];
        yield ['/\w+/', 1, null];
        yield ['/(?=a)b/', 1, 1]; // lookahead zero-width
        yield ['/(?#comment)a/', 1, 1]; // comment zero-width
    }
}
