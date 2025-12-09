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

namespace RegexParser\Tests\Bridge\Symfony;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RegexParser\Bridge\Symfony\CacheWarmer\RegexParserCacheWarmer;
use RegexParser\Bridge\Symfony\Command\RegexParserValidateCommand;
use RegexParser\Bridge\Symfony\DependencyInjection\RegexParserExtension;
use RegexParser\Cache\FilesystemCache;
use RegexParser\Regex;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class RegexParserBundleTest extends TestCase
{
    public function test_regex_service_registered_with_filesystem_cache(): void
    {
        $cacheDir = sys_get_temp_dir().'/regex_parser_'.uniqid();
        $container = $this->createContainer([
            'enabled' => true,
            'max_pattern_length' => 5000,
            'cache' => $cacheDir,
        ]);
        $container->compile();

        /** @var Regex $regex */
        $regex = $container->get('regex_parser.regex');
        $regex->parse('/abc/');

        $cache = new FilesystemCache($cacheDir);
        $cacheFile = $cache->generateKey('/abc/');

        $this->assertFileExists($cacheFile);

        $cache->clear();
    }

    public function test_cache_warmer_logs_route_issues(): void
    {
        $routes = new RouteCollection();
        $routes->add('broken', new Route('/broken', [], ['slug' => '(']));

        $logger = new InMemoryLogger();

        $container = $this->createContainer([
            'enabled' => true,
            'analysis' => [
                'warning_threshold' => 1,
                'redos_threshold' => 1,
            ],
        ]);

        $container->register(LoggerInterface::class, InMemoryLogger::class)->setPublic(true);
        $container->setAlias('logger', LoggerInterface::class)->setPublic(true);
        $container->register('router', RouteCollectionRouter::class)
            ->setArguments([$routes])
            ->setPublic(true);
        $container->register(RouterInterface::class, RouteCollectionRouter::class)
            ->setArguments([$routes])
            ->setPublic(true);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if ($container->hasDefinition('regex_parser.cache_warmer')) {
                    $container->getDefinition('regex_parser.cache_warmer')->setPublic(true);
                }
            }
        });
        $container->compile();

        /** @var RegexParserCacheWarmer $warmer */
        $warmer = $container->get('regex_parser.cache_warmer');
        /** @var InMemoryLogger $loggerService */
        $loggerService = $container->get(LoggerInterface::class);
        $warmer->warmUp(sys_get_temp_dir());

        $this->assertNotEmpty($loggerService->records);
        $this->assertSame('error', $loggerService->records[0]['level']);
        $this->assertStringContainsString('broken', $loggerService->records[0]['message']);
    }

    public function test_command_is_registered_as_console_service(): void
    {
        $container = $this->createContainer(['enabled' => true]);
        $container->compile();

        $this->assertTrue($container->hasDefinition('regex_parser.command.validate'));
        $definition = $container->getDefinition('regex_parser.command.validate');
        $this->assertSame(RegexParserValidateCommand::class, $definition->getClass());
        $this->assertArrayHasKey('console.command', $definition->getTags());

        /** @var RegexParserValidateCommand $command */
        $command = $container->get('regex_parser.command.validate');
        $this->assertSame('regex-parser:check', $command->getName());
    }

    public function test_bundle_can_be_disabled(): void
    {
        $container = $this->createContainer(['enabled' => false]);
        $container->compile();

        $this->assertFalse($container->has('regex_parser.regex'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createContainer(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);

        $extension = new RegexParserExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias(), $config);

        return $container;
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
    public function emergency(mixed $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function alert(mixed $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function critical(mixed $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(mixed $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(mixed $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function notice(mixed $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(mixed $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(mixed $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, mixed $message, array $context = []): void
    {
        $levelString = \is_scalar($level) || $level instanceof \Stringable ? (string) $level : get_debug_type($level);
        $messageString = \is_scalar($message) || $message instanceof \Stringable ? (string) $message : get_debug_type($message);
        $this->records[] = [
            'level' => $levelString,
            'message' => $messageString,
        ];
    }
}

final readonly class RouteCollectionRouter implements RouterInterface
{
    public function __construct(private RouteCollection $routes) {}

    public function setContext(RequestContext $context): void {}

    public function getContext(): RequestContext
    {
        return new RequestContext();
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->routes;
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
