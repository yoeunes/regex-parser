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
 * Decorates the Symfony Router to trace regex usage in the Web Profiler.
 *
 * This decorator intercepts route matching operations and collects all regex patterns
 * used by the routing system, including:
 * - Route requirement patterns (e.g., `[a-z0-9-]+` for a slug parameter)
 * - Compiled route patterns (the full PCRE pattern used internally by Symfony)
 *
 * Implements WarmableInterface to ensure the inner router is warmed up correctly
 * during `cache:warmup`, and ResetInterface for long-running process compatibility
 * (Swoole, FrankenPHP, RoadRunner).
 */
final readonly class TraceableRouter implements RouterInterface, RequestMatcherInterface, WarmableInterface, ResetInterface
{
    /**
     * Common PCRE pattern delimiters.
     *
     * These are the standard delimiters recognized by PHP's PCRE functions.
     * When a pattern starts with one of these characters, it is assumed to
     * already be a complete PCRE pattern with proper delimiters.
     */
    private const array PATTERN_DELIMITERS = ['/', '#', '~', '%'];

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
     * This is essential for production deployments where routes are compiled
     * and cached. Without proper delegation, the cache warmup would fail.
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
     * Resets the router state for long-running processes.
     *
     * This ensures proper cleanup between requests in environments like
     * Swoole, FrankenPHP, or RoadRunner where the same PHP process handles
     * multiple requests.
     */
    #[\Override]
    public function reset(): void
    {
        if ($this->router instanceof ResetInterface) {
            $this->router->reset();
        }
    }

    /**
     * Collects all regex patterns associated with a matched route.
     *
     * This method extracts regex patterns from two sources:
     * 1. Route requirements - user-defined patterns for route parameters
     * 2. Compiled route regex - the full PCRE pattern generated by Symfony
     *
     * All patterns are normalized to ensure they have proper PCRE delimiters
     * before being passed to the collector for parsing and analysis.
     */
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

            // Collect all route requirement regexes.
            // Symfony route requirements are often regex fragments (e.g., `[a-z0-9-]+`, `\d+`)
            // without PCRE delimiters. We normalize them to make them parseable.
            foreach ($route->getRequirements() as $parameterName => $requirement) {
                if (!\is_scalar($requirement)) {
                    continue;
                }

                $requirementPattern = (string) $requirement;
                if ('' === $requirementPattern) {
                    continue;
                }

                $this->collector->collectRegex(
                    $this->ensureDelimiters($requirementPattern),
                    \sprintf('Router (Requirement "%s")', $parameterName),
                    $subject,
                    $matchResult,
                );
            }

            // Collect the compiled route regex.
            // This is the full PCRE pattern that Symfony generates internally
            // and already includes proper delimiters and modifiers.
            $compiled = $route->compile();
            $compiledRegex = $compiled->getRegex();
            if ('' !== $compiledRegex) {
                $this->collector->collectRegex(
                    $compiledRegex,
                    \sprintf('Router (Compiled "%s")', $routeName),
                    $subject,
                    $matchResult,
                );
            }
        } catch (\Throwable) {
            // Never crash the application due to regex collection.
            // The profiler is a debugging tool and should not affect application stability.
        }
    }

    /**
     * Ensures a regex pattern has proper PCRE delimiters.
     *
     * Symfony route requirements are typically regex fragments without delimiters.
     * For example, a route like `/blog/posts/{slug}` might have a requirement
     * `[a-z0-9-]+` for the slug parameter. This is not a valid PCRE pattern
     * because it lacks delimiters.
     *
     * This method wraps such fragments with `#` delimiters to make them parseable
     * by the RegexParser. We use `#` instead of `/` because route patterns often
     * contain forward slashes (e.g., path segments), and using `/` as delimiter
     * would require escaping all slashes within the pattern.
     *
     * @param string $pattern The regex pattern or fragment to normalize
     *
     * @return string A valid PCRE pattern with delimiters
     */
    private function ensureDelimiters(string $pattern): string
    {
        if ('' === $pattern) {
            return '#(?:)#';
        }

        // Check if the pattern already starts with a recognized delimiter.
        // If so, assume it's already a complete PCRE pattern and return as-is.
        $firstChar = $pattern[0];
        if (\in_array($firstChar, self::PATTERN_DELIMITERS, true)) {
            return $pattern;
        }

        // Wrap the fragment with `#` delimiters to make it a valid PCRE pattern.
        // Using `#` avoids conflicts with patterns containing forward slashes,
        // which are common in URL-related regex patterns.
        return '#' . $pattern . '#';
    }
}
