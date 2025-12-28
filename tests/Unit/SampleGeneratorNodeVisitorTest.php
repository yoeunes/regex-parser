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

final class SampleGeneratorNodeVisitorTest extends TestCase
{
    public function test_sample_generator_visitor_class_instantiation(): void
    {
        $visitor = new SampleGeneratorNodeVisitor();
        $this->assertInstanceOf(SampleGeneratorNodeVisitor::class, $visitor);
    }
}
