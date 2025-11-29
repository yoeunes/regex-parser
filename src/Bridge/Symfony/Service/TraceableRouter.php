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

namespace RegexParser\Bridge\Symfony\Service;

use RegexParser\Bridge\Symfony\DataCollector\RegexCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Decorates the Symfony Router to trace regex usage.
 *
 * Implements WarmableInterface to properly delegate cache warmup
 * and ResetInterface for Swoole/FrankenPHP compatibility.
 */
final readonly class TraceableRouter implements RouterInterface, RequestMatcherInterface, WarmableInterface, ResetInterface
{
    public function __construct(
        private RouterInterface $router,
        private RegexCollector $collector,
    ) {}

    #[\Override]
    public function setContext(RequestContext $context): void
    {
        $this->router->setContext($context);
    }

    #[\Override]
    public function getContext(): RequestContext
    {
        return $this->router->getContext();
    }

    #[\Override]
    public function getRouteCollection(): RouteCollection
    {
        return $this->router->getRouteCollection();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[\Override]
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return $this->router->generate($name, $parameters, $referenceType);
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function match(string $pathinfo): array
    {
        try {
            /** @var array<string, mixed> $result */
            $result = $this->router->match($pathinfo);
            $routeName = $result['_route'] ?? null;
            $this->collectRouteRegex(\is_string($routeName) ? $routeName : null, $pathinfo, true);

            return $result;
        } catch (RouteNotFoundException $e) {
            $this->collectRouteRegex(null, $pathinfo, false);

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function matchRequest(Request $request): array
    {
        if (!$this->router instanceof RequestMatcherInterface) {
            return $this->match($request->getPathInfo());
        }

        try {
            /** @var array<string, mixed> $result */
            $result = $this->router->matchRequest($request);
            $routeName = $result['_route'] ?? null;
            $this->collectRouteRegex(\is_string($routeName) ? $routeName : null, $request->getPathInfo(), true);

            return $result;
        } catch (RouteNotFoundException $e) {
            $this->collectRouteRegex(null, $request->getPathInfo(), false);

            throw $e;
        }
    }

    /**
     * Warms up the cache by delegating to the inner router if it supports it.
     *
     * @param string $cacheDir The cache directory
     * @param string|null $buildDir The build directory (Symfony 6.1+)
     *
     * @return list<string> A list of classes to preload
     */
    #[\Override]
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ($this->router instanceof WarmableInterface) {
            return $this->router->warmUp($cacheDir, $buildDir);
        }

        return [];
    }

    /**
     * Resets the router state for long-running processes (Swoole/FrankenPHP).
     */
    #[\Override]
    public function reset(): void
    {
        if ($this->router instanceof ResetInterface) {
            $this->router->reset();
        }
    }

    private function collectRouteRegex(?string $routeName, string $subject, bool $matchResult): void
    {
        if (null === $routeName) {
            return;
        }

        try {
            $route = $this->getRouteCollection()->get($routeName);
            if (null === $route) {
                return;
            }

            // 1. Collect route requirement regexes
            foreach ($route->getRequirements() as $key => $requirement) {
                if (!\is_scalar($requirement)) {
                    continue;
                }
                $requirementStr = (string) $requirement;

                // Collect all requirements - the collector will handle validation
                $this->collector->collectRegex(
                    $this->normalizePattern($requirementStr),
                    \sprintf('Router: %s (requirement: %s)', $routeName, $key),
                    $subject,
                    $matchResult,
                );
            }

            // 2. Collect the compiled route regex
            $compiled = $route->compile();
            $compiledRegex = $compiled->getRegex();
            if ('' !== $compiledRegex) {
                $this->collector->collectRegex(
                    $compiledRegex,
                    \sprintf('Router: %s (compiled)', $routeName),
                    $subject,
                    $matchResult,
                );
            }
        } catch (\Throwable) {
            // Never crash the application due to regex collection
        }
    }

    /**
     * Normalizes a pattern to ensure it has delimiters.
     */
    private function normalizePattern(string $pattern): string
    {
        // If the pattern already has delimiters, return as-is
        $delimiters = ['/', '#', '~', '%', '!', '@'];
        if ('' !== $pattern && \in_array($pattern[0], $delimiters, true)) {
            return $pattern;
        }

        // Wrap the pattern with delimiters
        return '/'.$pattern.'/';
    }
}
