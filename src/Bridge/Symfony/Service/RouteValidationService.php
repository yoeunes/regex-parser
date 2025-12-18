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

use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use Symfony\Component\Routing\RouterInterface;

/**
 * Validates regex usage in Symfony routes.
 */
final readonly class RouteValidationService
{
    public function __construct(
        private ?RouteRequirementAnalyzer $analyzer = null,
        private ?RouterInterface $router = null,
    ) {}

    public function isSupported(): bool
    {
        return null !== $this->analyzer && null !== $this->router;
    }

    public function analyze(): array
    {
        if (!$this->isSupported()) {
            return [];
        }

        return $this->analyzer->analyze($this->router->getRouteCollection());
    }
}
