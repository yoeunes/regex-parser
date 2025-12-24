<?php

declare(strict_types=1);

/*
 * This file is part of RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Cache\PsrCacheAdapter;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;

final class AdditionalCacheAndFormatterCoverageTest extends TestCase
{
    public function test_psr_cache_adapter_get_timestamp(): void
    {
        $mockPool = new class implements CacheItemPoolInterface {
            public function getItem(string $key): CacheItemInterface|false
            {
                $item = new class implements CacheItemInterface {
                    public function get(): mixed
                    {
                        return 'test value';
                    }
                    public function isHit(): bool
                    {
                        return false;
                    }
                    public function key(): string
                    {
                        return $key;
                    }
                    public function expiresAt(): ?int
                    {
                        return null;
                    }
                    public function getMetadata(): array
                    {
                        return [];
                    }
                };

                return $item;
            }
            public function getItems(array $keys = []): iterable
            {
                return [];
            }
            public function clear(string $prefix = ''): bool
            {
                return true;
            }
            public function deleteItem(string $key): bool
            {
                return true;
            }
            public function deleteItems(array $keys): bool
            {
                return true;
            }
            public function save(CacheItemInterface $item): bool
            {
                return true;
            }
            public function hasItem(string $key): bool
            {
                return false;
            }
            public function saveDeferred(CacheItemInterface $item): bool
            {
                return true;
            }
            public function commit(): bool
            {
                return true;
            }
            public function rollBack(): bool
            {
                return true;
            }
        };

        $adapter = new PsrCacheAdapter($mockPool);

        $this->assertSame(0, $adapter->getTimestamp('/test/'));
    }

    public function test_regex_tokenize(): void
    {
        $tokens = \RegexParser\Regex::tokenize('/test[a-z]+/');

        $this->assertNotEmpty($tokens->getTokens());
        $this->assertCount(5, $tokens->getTokens());
    }

    public function test_regex_tokenize_with_flags(): void
    {
        $tokens = \RegexParser\Regex::tokenize('/test[a-z]+/i');

        $this->assertNotEmpty($tokens->getTokens());
        $this->assertCount(5, $tokens->getTokens());
    }

    public function test_regex_tokenize_complex_pattern(): void
    {
        $tokens = \RegexParser\Regex::tokenize('/^test\d{3}-\d{2}-\d{4}$/');

        $this->assertNotEmpty($tokens->getTokens());
        $this->assertGreaterThan(5, count($tokens->getTokens()));
    }

    public function test_redos_analyzer_with_ignored_patterns(): void
    {
        $analyzer = new \RegexParser\ReDoS\ReDoSAnalyzer(
            ignoredPatterns: ['/safe-1/', '/safe-2/']
        );

        $result1 = $analyzer->analyze('/safe-1/');
        $result2 = $analyzer->analyze('/safe-2/');
        $result3 = $analyzer->analyze('/other-pattern/');

        $this->assertSame(\RegexParser\ReDoS\ReDoSSeverity::SAFE, $result1->severity);
        $this->assertSame(\RegexParser\ReDoS\ReDoSSeverity::SAFE, $result2->severity);
        $this->assertNotSame(\RegexParser\ReDoS\ReDoSSeverity::SAFE, $result3->severity);
    }
}
