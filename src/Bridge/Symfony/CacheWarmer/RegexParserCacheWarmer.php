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

namespace RegexParser\Bridge\Symfony\CacheWarmer;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RegexParser\Bridge\Symfony\Analyzer\RouteIssue;
use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Surfaces regex warnings during Symfony cache warmup.
 *
 * @internal
 */
final readonly class RegexParserCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private RouteRequirementAnalyzer $analyzer,
        private ?RouterInterface $router = null,
        private ?LoggerInterface $logger = null,
    ) {}

    #[\Override]
    public function isOptional(): bool
    {
        return true;
    }

    #[\Override]
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if (null === $this->router) {
            return [];
        }

        $issues = $this->analyzer->analyze($this->router->getRouteCollection());

        foreach ($issues as $issue) {
            $this->log($issue);
        }

        return [];
    }

    private function log(RouteIssue $issue): void
    {
        if (null !== $this->logger) {
            $this->logger->log(
                $issue->isError ? LogLevel::ERROR : LogLevel::WARNING,
                $issue->message,
            );

            return;
        }

        error_log($issue->message);
    }
}
