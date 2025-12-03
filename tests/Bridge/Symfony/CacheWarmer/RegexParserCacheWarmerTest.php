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

namespace RegexParser\Tests\Bridge\Symfony\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use RegexParser\Bridge\Symfony\CacheWarmer\RegexParserCacheWarmer;
use RegexParser\Regex;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class RegexParserCacheWarmerTest extends TestCase
{
    public function test_warm_up_logs_issues(): void
    {
        $logger = new InMemoryLogger();
        $analyzer = new RouteRequirementAnalyzer(Regex::create(), 0, 0);
        $warmup = new RegexParserCacheWarmer($analyzer, new RouteCollectionRouterWithIssue(), $logger);

        $warmup->warmUp(sys_get_temp_dir());

        $this->assertNotEmpty($logger->records);
        $this->assertSame('error', $logger->records[0]['level']);
    }

    public function test_warm_up_is_optional(): void
    {
        $analyzer = new RouteRequirementAnalyzer(Regex::create(), 0, 0);
        $warmup = new RegexParserCacheWarmer($analyzer, null, null);

        $this->assertTrue($warmup->isOptional());
        $this->assertSame([], $warmup->warmUp(sys_get_temp_dir()));
    }
}

final class RouteCollectionRouterWithIssue implements RouterInterface
{
    public function setContext(RequestContext $context): void {}

    public function getContext(): RequestContext
    {
        return new RequestContext();
    }

    public function getRouteCollection(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo', [], ['slug' => '(']));

        return $collection;
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return '';
    }

    public function match(string $pathinfo): array
    {
        return [];
    }
}

final class InMemoryLogger implements LoggerInterface
{
    /**
     * @var array<int, array{level: string, message: string}>
     */
    public array $records = [];

    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
        ];
    }
}
