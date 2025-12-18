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
use Symfony\Component\DependencyInjection\Reference;
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

        $loggedRecords = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('log')->willReturnCallback(function ($level, $message, $context = []) use (&$loggedRecords): void {
            $loggedRecords[] = ['level' => (string) $level, 'message' => (string) $message];
        });

        $container = $this->createContainer([
            'enabled' => true,
            'analysis' => [
                'warning_threshold' => 1,
                'redos_threshold' => 1,
            ],
        ], false);

        $container->set('logger', $logger);
        $container->loadFromExtension('regex_parser', [
            'enabled' => true,
            'analysis' => [
                'warning_threshold' => 1,
                'redos_threshold' => 1,
            ],
        ]);

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
                    $container->getDefinition('regex_parser.cache_warmer')->replaceArgument('$logger', new Reference('logger'));
                }
            }
        });
        $container->compile();

        /** @var RegexParserCacheWarmer $warmer */
        $warmer = $container->get('regex_parser.cache_warmer');
        $warmer->warmUp(sys_get_temp_dir());

        $this->assertNotEmpty($loggedRecords);
        $this->assertSame('error', $loggedRecords[0]['level']);
        $this->assertStringContainsString('broken', $loggedRecords[0]['message']);
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
        $this->assertSame('regex:check', $command->getName());
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
    private function createContainer(array $config, bool $loadExtension = true): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);

        $extension = new RegexParserExtension();
        $container->registerExtension($extension);

        if ($loadExtension) {
            $container->loadFromExtension($extension->getAlias(), $config);
        }

        return $container;
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
