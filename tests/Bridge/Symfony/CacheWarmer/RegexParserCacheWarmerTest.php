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
use RegexParser\Bridge\Symfony\Analyzer\ValidatorRegexAnalyzer;
use RegexParser\Bridge\Symfony\CacheWarmer\RegexParserCacheWarmer;
use RegexParser\Regex;
use RegexParser\Tests\Bridge\Symfony\Analyzer\FakeLoader;
use RegexParser\Tests\Bridge\Symfony\Analyzer\FakeValidator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\Regex as SymfonyRegex;

final class RegexParserCacheWarmerTest extends TestCase
{
    public function test_warm_up_logs_issues(): null
    {
        $logger = new InMemoryLogger();
        $analyzer = new RouteRequirementAnalyzer(Regex::create(), 0, 0);
        $warmup = new RegexParserCacheWarmer($analyzer, new RouteCollectionRouterWithIssue(), $logger);

        $warmup->warmUp(sys_get_temp_dir());

        $this->assertNotEmpty($logger->records);
        $this->assertSame('error', $logger->records[0]['level']);
    }

    public function test_warm_up_is_optional(): null
    {
        $analyzer = new RouteRequirementAnalyzer(Regex::create(), 0, 0);
        $warmup = new RegexParserCacheWarmer($analyzer, null, null);

        $this->assertTrue($warmup->isOptional());
        $this->assertSame([], $warmup->warmUp(sys_get_temp_dir()));
    }

    public function test_warm_up_logs_validator_issues(): null
    {
        $logger = new InMemoryLogger();
        $validatorAnalyzer = new ValidatorRegexAnalyzer(Regex::create(), 0, 1000);
        $validator = new FakeValidator([new SymfonyRegex(pattern: '(')]);
        $loader = new FakeLoader([FakeValidator::class]);

        $warmup = new RegexParserCacheWarmer(
            new RouteRequirementAnalyzer(Regex::create(), 0, 0),
            null,
            $logger,
            $validatorAnalyzer,
            $validator,
            $loader,
        );

        $warmup->warmUp(sys_get_temp_dir());

        $this->assertNotEmpty($logger->records);
        $this->assertSame('error', $logger->records[0]['level']);
    }
}

final class RouteCollectionRouterWithIssue implements RouterInterface
{
    public function setContext(RequestContext $context): null {}

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

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $context
     */
    public function emergency(string|\Stringable $message, array $context = []): null
    {
        $this->log('emergency', $message, $context);
        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function alert(string|\Stringable $message, array $context = []): null
    {
        $this->log('alert', $message, $context);
        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function critical(string|\Stringable $message, array $context = []): null
    {
        $this->log('critical', $message, $context);
        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string|\Stringable $message, array $context = []): null
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string|\Stringable $message, array $context = []): null
    {
        $this->log('warning', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function notice(string|\Stringable $message, array $context = []): null
    {
        $this->log('notice', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string|\Stringable $message, array $context = []): null
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string|\Stringable $message, array $context = []): null
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): null
    {
        $levelString = \is_scalar($level) || $level instanceof \Stringable ? (string) $level : get_debug_type($level);
        $messageString = \is_scalar($message) || $message instanceof \Stringable ? (string) $message : get_debug_type($message);
        $this->records[] = [
            'level' => $levelString,
            'message' => $messageString,
        ];
    }
}
