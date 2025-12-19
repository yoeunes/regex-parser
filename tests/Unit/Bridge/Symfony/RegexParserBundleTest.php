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

namespace RegexParser\Tests\Unit\Bridge\Symfony;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\RegexParserBundle;

final class RegexParserBundleTest extends TestCase
{
    public function test_get_path_returns_bundle_directory(): void
    {
        $bundle = new RegexParserBundle();

        $this->assertSame(\dirname(__DIR__, 4).'/src/Bridge/Symfony', $bundle->getPath());
    }
}
