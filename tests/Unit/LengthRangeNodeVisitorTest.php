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
use RegexParser\NodeVisitor\LengthRangeNodeVisitor;
use RegexParser\Regex;

final class LengthRangeNodeVisitorTest extends TestCase
{
    public function test_length_range_visitor_reports_quantifier_bounds(): void
    {
        $regex = Regex::create();
        $ast = $regex->parse('/a{2,3}/');
        $visitor = new LengthRangeNodeVisitor();

        $range = $ast->accept($visitor);

        $this->assertSame([2, 3], $range);
    }
}
