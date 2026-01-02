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
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;
use RegexParser\Regex;

final class SampleGeneratorNodeVisitorTest extends TestCase
{
    public function test_sample_generator_produces_matching_text(): void
    {
        $regex = Regex::create();
        $ast = $regex->parse('/a[bc]/');
        $visitor = new SampleGeneratorNodeVisitor();
        $visitor->setSeed(123);

        $sample = $ast->accept($visitor);

        $this->assertMatchesRegularExpression('/a[bc]/', $sample);
    }
}
