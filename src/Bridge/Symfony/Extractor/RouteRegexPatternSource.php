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

namespace RegexParser\Bridge\Symfony\Extractor;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Extracts regex patterns from Symfony route requirements.
 *
 * @internal
 */
final readonly class RouteRegexPatternSource implements RegexPatternSourceInterface
{
    private const PATTERN_DELIMITERS = ['/', '#', '~', '%'];

    public function __construct(private ?RouterInterface $router = null) {}

    public function getName(): string
    {
        return 'routes';
    }

    public function isSupported(): bool
    {
        return null !== $this->router;
    }

    public function extract(RegexPatternSourceContext $context): array
    {
        if (!$this->isSupported()) {
            return [];
        }

        $patterns = [];
        $line = 1;

        foreach ($this->router->getRouteCollection() as $name => $route) {
            $file = $this->getRouteFile($route) ?? 'Symfony Router';

            foreach ($route->getRequirements() as $parameter => $requirement) {
                if (!\is_scalar($requirement)) {
                    continue;
                }

                $pattern = trim((string) $requirement);
                if ('' === $pattern) {
                    continue;
                }

                $normalized = $this->normalizePattern($pattern);
                $patterns[] = new RegexPatternOccurrence(
                    $normalized,
                    $file,
                    $line++,
                    'route:'.$name.':'.$parameter,
                    $pattern,
                );
            }
        }

        return $patterns;
    }

    private function normalizePattern(string $pattern): string
    {
        $firstChar = $pattern[0] ?? '';

        if (\in_array($firstChar, self::PATTERN_DELIMITERS, true)) {
            return $pattern;
        }

        if (str_starts_with($pattern, '^') && str_ends_with($pattern, '$')) {
            return '#'.$pattern.'#';
        }

        $delimiter = '#';
        $body = str_replace($delimiter, '\\'.$delimiter, $pattern);

        return $delimiter.'^'.$body.'$'.$delimiter;
    }

    private function getRouteFile(Route $route): ?string
    {
        $controller = $route->getDefault('_controller');
        if (!\is_string($controller)) {
            return null;
        }

        if (str_contains($controller, '::')) {
            [$class] = explode('::', $controller, 2);
        } else {
            $class = $controller;
        }

        if (!class_exists($class)) {
            return null;
        }

        $reflection = new \ReflectionClass($class);

        return $reflection->getFileName();
    }
}
