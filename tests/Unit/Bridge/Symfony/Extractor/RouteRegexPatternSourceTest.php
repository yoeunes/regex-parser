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

namespace RegexParser\Tests\Unit\Bridge\Symfony\Extractor;

use PHPUnit\Framework\TestCase;

final class RouteRegexPatternSourceTest extends TestCase
{
    private \RegexParser\Bridge\Symfony\Routing\RouteRequirementNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new \RegexParser\Bridge\Symfony\Routing\RouteRequirementNormalizer();
    }

    public function test_construct(): void
    {
        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer);
        // Source created successfully
    }

    public function test_construct_with_router(): void
    {
        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
    }

    public function test_get_name(): void
    {
        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer);
        $this->assertSame('routes', $source->getName());
    }

    public function test_is_supported_returns_false_when_no_router(): void
    {
        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer);
        $this->assertFalse($source->isSupported());
    }

    public function test_is_supported_returns_true_when_router_present(): void
    {
        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
        $this->assertTrue($source->isSupported());
    }

    public function test_extract_returns_empty_array_when_no_router(): void
    {
        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertSame([], $result);
    }

    public function test_extract_with_empty_route_collection(): void
    {
        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $collection = new \Symfony\Component\Routing\RouteCollection();
        $router->method('getRouteCollection')->willReturn($collection);

        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertSame([], $result);
    }

    public function test_extract_with_route_having_requirements(): void
    {
        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $collection = new \Symfony\Component\Routing\RouteCollection();

        $route = new \Symfony\Component\Routing\Route('/test/{id}');
        $route->setRequirements(['id' => '\d+']);
        $collection->add('test_route', $route);

        $router->method('getRouteCollection')->willReturn($collection);

        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertCount(1, $result);
        $this->assertSame('#^\d+$#', $result[0]->pattern);
        $this->assertSame('Symfony routes', $result[0]->file);
        $this->assertSame('route:test_route:id', $result[0]->source);
        $this->assertSame('\d+', $result[0]->displayPattern);
        $this->assertStringContainsString('Route "test_route"', (string) $result[0]->location);
    }

    public function test_extract_with_route_having_controller(): void
    {
        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $collection = new \Symfony\Component\Routing\RouteCollection();

        $route = new \Symfony\Component\Routing\Route('/test/{id}');
        $route->setRequirements(['id' => '\d+']);
        $route->setDefault('_controller', 'App\\Controller\\TestController::index');
        $collection->add('test_route', $route);

        $router->method('getRouteCollection')->willReturn($collection);

        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('controller: App\\Controller\\TestController::index', (string) $result[0]->location);
    }

    public function test_extract_with_multiple_requirements(): void
    {
        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $collection = new \Symfony\Component\Routing\RouteCollection();

        $route = new \Symfony\Component\Routing\Route('/test/{id}/{slug}');
        $route->setRequirements([
            'id' => '\d+',
            'slug' => '[a-z-]+',
        ]);
        $collection->add('test_route', $route);

        $router->method('getRouteCollection')->willReturn($collection);

        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertCount(2, $result);
        $patterns = array_map(fn ($occurrence) => $occurrence->pattern, $result);
        $this->assertContains('#^\d+$#', $patterns);
        $this->assertContains('#^[a-z-]+$#', $patterns);
    }

    public function test_extract_ignores_empty_requirements(): void
    {
        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $collection = new \Symfony\Component\Routing\RouteCollection();

        $route = new \Symfony\Component\Routing\Route('/test/{id}/{slug}');
        $route->setRequirements([
            'id' => '\d+',
            'slug' => '   ', // Whitespace-only requirement (will be trimmed to empty)
        ]);
        $collection->add('test_route', $route);

        $router->method('getRouteCollection')->willReturn($collection);

        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertCount(1, $result);
        $this->assertSame('#^\d+$#', $result[0]->pattern);
    }

    public function test_extract_with_yaml_resources(): void
    {
        $tempYaml = tempnam(sys_get_temp_dir(), 'routes');
        file_put_contents($tempYaml, 'test: content');

        try {
            $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
            $collection = new \Symfony\Component\Routing\RouteCollection();

            $route = new \Symfony\Component\Routing\Route('/test/{id}');
            $route->setRequirements(['id' => '\d+']);
            $collection->add('test_route', $route);

            // Add a YAML resource
            $yamlResource = new \Symfony\Component\Config\Resource\FileResource($tempYaml);
            $collection->addResource($yamlResource);

            $router->method('getRouteCollection')->willReturn($collection);

            $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
            $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

            $result = $source->extract($context);

            $this->assertCount(1, $result);
            // Should extract the pattern
            $this->assertSame('#^\d+$#', $result[0]->pattern);
        } finally {
            unlink($tempYaml);
        }
    }

    public function test_extract_with_multiple_yaml_files(): void
    {
        $tempYaml1 = tempnam(sys_get_temp_dir(), 'routes1');
        $tempYaml2 = tempnam(sys_get_temp_dir(), 'routes2');
        file_put_contents($tempYaml1, 'test: content1');
        file_put_contents($tempYaml2, 'test: content2');

        try {
            $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
            $collection = new \Symfony\Component\Routing\RouteCollection();

            $route = new \Symfony\Component\Routing\Route('/test/{id}');
            $route->setRequirements(['id' => '\d+']);
            $collection->add('test_route', $route);

            // Add multiple YAML resources
            $collection->addResource(new \Symfony\Component\Config\Resource\FileResource($tempYaml1));
            $collection->addResource(new \Symfony\Component\Config\Resource\FileResource($tempYaml2));

            $router->method('getRouteCollection')->willReturn($collection);

            $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
            $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

            $result = $source->extract($context);

            $this->assertCount(1, $result);
            $this->assertSame('Symfony routes', $result[0]->file); // Multiple YAML files fall back to default
        } finally {
            unlink($tempYaml1);
            unlink($tempYaml2);
        }
    }

    public function test_extract_ignores_non_yaml_resources(): void
    {
        $tempPhp = tempnam(sys_get_temp_dir(), 'routes');
        $tempXml = tempnam(sys_get_temp_dir(), 'routes');
        file_put_contents($tempPhp, '<?php echo "test";');
        file_put_contents($tempXml, '<routes></routes>');

        try {
            $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
            $collection = new \Symfony\Component\Routing\RouteCollection();

            $route = new \Symfony\Component\Routing\Route('/test/{id}');
            $route->setRequirements(['id' => '\d+']);
            $collection->add('test_route', $route);

            // Add non-YAML resources
            $collection->addResource(new \Symfony\Component\Config\Resource\FileResource($tempPhp));
            $collection->addResource(new \Symfony\Component\Config\Resource\FileResource($tempXml));

            $router->method('getRouteCollection')->willReturn($collection);

            $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
            $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

            $result = $source->extract($context);

            $this->assertCount(1, $result);
            $this->assertSame('Symfony routes', $result[0]->file);
        } finally {
            unlink($tempPhp);
            unlink($tempXml);
        }
    }

    public function test_extract_with_already_delimited_patterns(): void
    {
        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $collection = new \Symfony\Component\Routing\RouteCollection();

        $route = new \Symfony\Component\Routing\Route('/test/{id}');
        $route->setRequirements(['id' => '/\d+/']); // Already delimited
        $collection->add('test_route', $route);

        $router->method('getRouteCollection')->willReturn($collection);

        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertCount(1, $result);
        $this->assertSame('/\d+/', $result[0]->pattern); // Should remain unchanged
    }

    public function test_extract_with_anchor_patterns(): void
    {
        $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
        $collection = new \Symfony\Component\Routing\RouteCollection();

        $route = new \Symfony\Component\Routing\Route('/test/{slug}');
        $route->setRequirements(['slug' => '^[\w-]+$']); // Starts and ends with anchors
        $collection->add('test_route', $route);

        $router->method('getRouteCollection')->willReturn($collection);

        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertCount(1, $result);
        $this->assertSame('#^[\w-]+$#', $result[0]->pattern); // Should be wrapped with #
    }

    public function test_extract_with_yaml_route_definitions(): void
    {
        $tempYaml = tempnam(sys_get_temp_dir(), 'routes').'.yaml';
        $yamlContent = <<<YAML
            test_route:
              path: /test/{id}/{slug}
              requirements:
                id: '\d+'
                slug: '[a-z-]+'
            YAML;
        file_put_contents($tempYaml, $yamlContent);

        try {
            $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
            $collection = new \Symfony\Component\Routing\RouteCollection();

            // Add a route with the same name as in YAML
            $route = new \Symfony\Component\Routing\Route('/test/{id}/{slug}');
            $collection->add('test_route', $route);

            // Add the YAML resource
            $yamlResource = new \Symfony\Component\Config\Resource\FileResource($tempYaml);
            $collection->addResource($yamlResource);

            $router->method('getRouteCollection')->willReturn($collection);

            $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
            $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

            $result = $source->extract($context);

            // Should extract patterns from YAML
            $this->assertGreaterThanOrEqual(2, \count($result));
            $patterns = array_map(fn ($occurrence) => $occurrence->pattern, $result);
            $this->assertContains('#^\d+$#', $patterns);
            $this->assertContains('#^[a-z-]+$#', $patterns);
        } finally {
            unlink($tempYaml);
        }
    }

    public function test_extract_with_yaml_complex_route_definitions(): void
    {
        $tempYaml = tempnam(sys_get_temp_dir(), 'routes').'.yaml';
        $yamlContent = <<<YAML
            when@dev:
              test_route:
                path: /dev/test/{id}
                requirements:
                  id: '\d{3,}'

            test_route:
              path: /test/{slug}
              requirements:
                slug: '^[a-z0-9_-]+$'
            YAML;
        file_put_contents($tempYaml, $yamlContent);

        try {
            $router = $this->createMock(\Symfony\Component\Routing\RouterInterface::class);
            $collection = new \Symfony\Component\Routing\RouteCollection();

            // Add a route with the same name as in YAML
            $route = new \Symfony\Component\Routing\Route('/test/{slug}');
            $collection->add('test_route', $route);

            // Add the YAML resource
            $yamlResource = new \Symfony\Component\Config\Resource\FileResource($tempYaml);
            $collection->addResource($yamlResource);

            $router->method('getRouteCollection')->willReturn($collection);

            $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer, $router);
            $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

            $result = $source->extract($context);

            // Should extract patterns from YAML, handling when@ conditions
            $this->assertGreaterThanOrEqual(1, \count($result));
            $patterns = array_map(fn ($occurrence) => $occurrence->pattern, $result);
            $this->assertContains('#^[a-z0-9_-]+$#', $patterns);
        } finally {
            unlink($tempYaml);
        }
    }

    public function test_extract_skips_when_symfony_not_available(): void
    {
        if (class_exists(\Symfony\Component\Routing\Route::class)) {
            $this->markTestSkipped('Symfony Routing is available, skipping test');
        }

        // This test will only run when Symfony is not available
        $source = new \RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource($this->normalizer);
        $this->assertFalse($source->isSupported());
        $this->assertSame('routes', $source->getName());
    }
}
