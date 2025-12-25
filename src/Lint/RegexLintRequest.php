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

namespace RegexParser\Lint;

/**
 * Input parameters for a lint run.
 *
 * @internal
 */
final readonly class RegexLintRequest
{
    /**
     * @param list<string> $paths
     * @param list<string> $excludePaths
     * @param list<string> $disabledSources
     */
    public function __construct(
        public array $paths,
        public array $excludePaths,
        public int $minSavings,
        private array $disabledSources = [],
        public bool $checkValidation = true,
        public bool $checkRedos = true,
        public bool $checkOptimizations = true,
        public int $analysisWorkers = 1,
    ) {}

    /**
     * @return list<string>
     */
    public function getDisabledSources(): array
    {
        return $this->disabledSources;
    }

    public function isSourceEnabled(string $name): bool
    {
        return !\in_array($name, $this->disabledSources, true);
    }
}
