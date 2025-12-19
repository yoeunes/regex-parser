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

use RegexParser\Bridge\Symfony\Analyzer\AnalysisIssue;
use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use Symfony\Component\Routing\RouterInterface;

/**
 * Provides route requirement analysis issues.
 */
final readonly class RouteRequirementIssueProvider implements RegexLintIssueProviderInterface
{
    public function __construct(
        private RouteRequirementAnalyzer $analyzer,
        private ?RouterInterface $router = null,
    ) {}

    public function isSupported(): bool
    {
        return null !== $this->router;
    }

    /**
     * @return array<AnalysisIssue>
     */
    public function analyze(): array
    {
        if (!$this->isSupported() || null === $this->router) {
            return [];
        }

        return $this->analyzer->analyze($this->router->getRouteCollection());
    }

    public function getName(): string
    {
        return 'routes';
    }

    public function getLabel(): string
    {
        return 'Symfony Routes';
    }
}
