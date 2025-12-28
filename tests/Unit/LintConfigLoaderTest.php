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
use RegexParser\Lint\Command\LintConfigLoader;

final class LintConfigLoaderTest extends TestCase
{
    public function test_config_loader_class_instantiation(): void
    {
        $loader = new LintConfigLoader();
        $this->assertInstanceOf(LintConfigLoader::class, $loader);

        $result = $loader->load();
        $this->assertNotNull($result);
        $this->assertNull($result->error);
    }
}
