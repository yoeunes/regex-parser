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

use RegexParser\Bridge\Symfony\Routing\RouteControllerFileResolver;
use RegexParser\Bridge\Symfony\Routing\RouteRequirementNormalizer;
use RegexParser\Lint\RegexPatternOccurrence;
use RegexParser\Lint\RegexPatternSourceContext;
use RegexParser\Lint\RegexPatternSourceInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Extracts regex patterns from Symfony route requirements.
 *
 * @internal
 */
final readonly class RouteRegexPatternSource implements RegexPatternSourceInterface
{
    public function __construct(
        private RouteRequirementNormalizer $patternNormalizer,
        private RouteControllerFileResolver $fileResolver,
        private ?RouterInterface $router = null,
    ) {}

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
        if (null === $this->router) {
            return [];
        }

        $router = $this->router;
        $patterns = [];
        $line = 1;

        foreach ($router->getRouteCollection() as $name => $route) {
            $file = $this->fileResolver->resolve($route) ?? 'Symfony Router';

            foreach ($route->getRequirements() as $parameter => $requirement) {
                if (!\is_scalar($requirement)) {
                    continue;
                }

                $pattern = trim((string) $requirement);
                if ('' === $pattern) {
                    continue;
                }

                $normalized = $this->patternNormalizer->normalize($pattern);
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
}
