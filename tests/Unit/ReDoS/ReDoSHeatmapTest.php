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
use RegexParser\ReDoS\ReDoSHeatmap;
use RegexParser\ReDoS\ReDoSHotspot;
use RegexParser\ReDoS\ReDoSSeverity;

final class ReDoSHeatmapTest extends TestCase
{
    public function test_heatmap_preserves_body_text(): void
    {
        $heatmap = new ReDoSHeatmap();
        $hotspot = new ReDoSHotspot(0, 2, ReDoSSeverity::HIGH, 'a+');

        $rendered = $heatmap->highlight('a+b', [$hotspot], true);
        $stripped = preg_replace('/\e\[[0-9;]*m/', '', $rendered);

        $this->assertSame('a+b', $stripped);
        $this->assertStringContainsString("\033[", $rendered);
    }

    public function test_heatmap_returns_plain_when_ansi_disabled(): void
    {
        $heatmap = new ReDoSHeatmap();
        $hotspot = new ReDoSHotspot(0, 2, ReDoSSeverity::HIGH, 'a+');

        $rendered = $heatmap->highlight('a+b', [$hotspot], false);

        $this->assertSame('a+b', $rendered);
    }
}
