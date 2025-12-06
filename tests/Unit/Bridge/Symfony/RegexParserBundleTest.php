<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Bridge\Symfony;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\RegexParserBundle;

final class RegexParserBundleTest extends TestCase
{
    public function testGetPathReturnsBundleDirectory(): void
    {
        $bundle = new RegexParserBundle();

        self::assertSame(\dirname(__DIR__, 4).'/src/Bridge/Symfony', $bundle->getPath());
    }
}
