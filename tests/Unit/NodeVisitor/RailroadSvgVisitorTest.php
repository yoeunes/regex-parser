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

use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\RailroadSvgVisitor;
use RegexParser\Regex;

final class RailroadSvgVisitorTest extends TestCase
{
    public function test_svg_renders_basic_diagram(): void
    {
        $ast = Regex::create()->parse('/^a+$/');
        /** @var string $svg */
        $svg = $ast->accept(new RailroadSvgVisitor());

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
        $this->assertStringContainsString('class="node literal"', $svg);
        $this->assertStringContainsString('class="node anchor"', $svg);
        $this->assertStringContainsString('class="quantifier-label"', $svg);
    }
}
