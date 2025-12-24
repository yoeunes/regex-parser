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

namespace RegexParser\Tests\Unit\ReDoS;

use PHPUnit\Framework\TestCase;
use RegexParser\ReDoS\CharSet;

final class CharSetTest extends TestCase
{
    public function test_factory_methods_and_flags(): void
    {
        $empty = CharSet::empty();
        $this->assertTrue($empty->isEmpty());
        $this->assertFalse($empty->isUnknown());

        $unknown = CharSet::unknown();
        $this->assertTrue($unknown->isUnknown());
        $this->assertFalse($unknown->isEmpty());

        $full = CharSet::full();
        $this->assertFalse($full->isEmpty());
    }

    public function test_union_merges_ranges_and_handles_unknown(): void
    {
        $rangeA = CharSet::fromRange(0, 2);
        $rangeB = CharSet::fromRange(3, 5);
        $merged = $rangeA->union($rangeB);

        $this->assertFalse($merged->isEmpty());
        $this->assertTrue($merged->intersects(CharSet::fromRange(4, 4)));

        $unknown = CharSet::unknown()->union($rangeA);
        $this->assertTrue($unknown->isUnknown());
    }

    public function test_complement_and_intersects(): void
    {
        $digits = CharSet::fromRange(\ord('0'), \ord('9'));
        $complement = $digits->complement();

        $this->assertTrue($complement->intersects(CharSet::fromChar('A')));
        $this->assertFalse($complement->intersects($digits));
    }
}
