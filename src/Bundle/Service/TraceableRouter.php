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

namespace RegexParser\Bundle\Service;

use RegexParser\Bundle\DataCollector\RegexCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * Decorates the Symfony Router to trace regex usage.
 */
class TraceableRouter implements RouterInterface, RequestMatcherInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly RegexCollector $collector,
    ) {
    }

    public function setContext(RequestContext $context): void
    {
        $this->router->setContext($context);
    }

    public function getContext(): RequestContext
    {
        return $this->router->getContext();
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->router->getRouteCollection();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return $this->router->generate($name, $parameters, $referenceType);
    }

    /**
     * @return array<string, mixed>
     */
    public function match(string $pathinfo): array
    {
        try {
            $result = $this->router->match($pathinfo);
            /** @var array<string, mixed> $result */
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
    public function matchRequest(Request $request): array
    {
        if (!$this->router instanceof RequestMatcherInterface) {
            // Fallback for routers that don't implement this
            return $this->match($request->getPathInfo());
        }

        try {
            $result = $this->router->matchRequest($request);
            /** @var array<string, mixed> $result */
            $routeName = $result['_route'] ?? null;
            $this->collectRouteRegex(\is_string($routeName) ? $routeName : null, $request->getPathInfo(), true);

            return $result;
        } catch (RouteNotFoundException $e) {
            $this->collectRouteRegex(null, $request->getPathInfo(), false);
            throw $e;
        }
    }

    private function collectRouteRegex(?string $routeName, string $subject, bool $matchResult): void
    {
        if (null === $routeName) {
            return;
        }

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

            // We only collect requirements that are actual regex patterns
            if ($this->isRegex($requirementStr)) {
                $this->collector->collectRegex(
                    $requirementStr,
                    \sprintf('Router (Requirement: %s)', $key),
                    $subject,
                    $matchResult
                );
            }
        }

        // 2. Collect the compiled route regex
        $compiled = $route->compile();
        if ($compiled->getRegex()) {
            $this->collector->collectRegex(
                $compiled->getRegex(),
                \sprintf('Router (Route: %s)', $routeName),
                $subject,
                $matchResult
            );
        }
    }

    private function isRegex(string $pattern): bool
    {
        // Basic check to avoid collecting simple requirements like "123"
        $delimiters = ['/', '#', '~', '%'];

        return \in_array($pattern[0] ?? '', $delimiters, true);
    }
}
