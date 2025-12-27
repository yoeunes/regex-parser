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

namespace RegexParser\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use RegexParser\Cli\VersionResolver;

final class VersionResolverTest extends TestCase
{
    public function test_resolve_returns_version_or_fallback(): void
    {
        $resolver = new VersionResolver();
        $versionFile = $resolver->getVersionFile();

        $this->assertFileExists($versionFile);

        $contents = file_get_contents($versionFile);
        $this->assertIsString($contents);

        $decoded = json_decode($contents, true);
        $expected = null;
        if (\is_array($decoded)) {
            $version = $decoded['version'] ?? null;
            if (\is_string($version) && '' !== $version) {
                $expected = $version;
            }
        }

        $result = $resolver->resolve('fallback');

        if (null !== $expected) {
            $this->assertSame($expected, $result);
        } else {
            $this->assertSame('fallback', $result);
        }
    }
}
