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

namespace RegexParser\Tests\Functional\Cli;

use PHPUnit\Framework\TestCase;
use RegexParser\Cli\VersionResolver;

final class VersionResolverTest extends TestCase
{
    private string $composerPath;

    private ?string $backupContent = null;

    protected function setUp(): void
    {
        $resolver = new VersionResolver();
        $this->composerPath = $resolver->getVersionFile();
        if (file_exists($this->composerPath)) {
            $this->backupContent = file_get_contents($this->composerPath) ?: null;
        }
    }

    protected function tearDown(): void
    {
        if (null !== $this->backupContent) {
            file_put_contents($this->composerPath, $this->backupContent);
        } elseif (file_exists($this->composerPath)) {
            unlink($this->composerPath);
        }
    }

    public function test_resolve_returns_null_when_version_file_does_not_exist(): void
    {
        if (file_exists($this->composerPath)) {
            rename($this->composerPath, $this->composerPath.'.bak');
        }

        $resolver = new VersionResolver();
        $result = $resolver->resolve();
        $this->assertNull($result);

        if (file_exists($this->composerPath.'.bak')) {
            rename($this->composerPath.'.bak', $this->composerPath);
        }
    }

    public function test_resolve_returns_null_when_json_decode_fails(): void
    {
        file_put_contents($this->composerPath, 'invalid json');

        $resolver = new VersionResolver();
        $result = $resolver->resolve();
        $this->assertNull($result);
    }

    public function test_resolve_returns_fallback_when_version_not_string(): void
    {
        file_put_contents($this->composerPath, '{"version": 123}');

        $resolver = new VersionResolver();
        $result = $resolver->resolve('fallback');
        $this->assertSame('fallback', $result);
    }

    public function test_resolve_returns_fallback_when_version_empty(): void
    {
        file_put_contents($this->composerPath, '{"version": ""}');

        $resolver = new VersionResolver();
        $result = $resolver->resolve('fallback');
        $this->assertSame('fallback', $result);
    }

    public function test_resolve_returns_version_when_valid(): void
    {
        file_put_contents($this->composerPath, '{"version": "1.0.0"}');

        $resolver = new VersionResolver();
        $result = $resolver->resolve('fallback');
        $this->assertSame('1.0.0', $result);
    }

    public function test_resolve_returns_null_when_file_get_contents_fails(): void
    {
        file_put_contents($this->composerPath, '{"version": "1.0.0"}');

        // Make the file unreadable
        @chmod($this->composerPath, 0o000);

        try {
            $resolver = new VersionResolver();
            $result = $resolver->resolve();
            $this->assertNull($result);
        } finally {
            // Restore permissions for cleanup
            @chmod($this->composerPath, 0o644);
        }
    }
}
