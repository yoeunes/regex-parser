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

namespace RegexParser\Lint\Command;

use RegexParser\Lint\Formatter\OutputConfiguration;

final readonly class LintArguments
{
    /**
     * @param array<int, string> $paths
     * @param array<int, string> $exclude
     */
    public function __construct(
        public array $paths,
        public array $exclude,
        public int $minSavings,
        public string $verbosity,
        public string $format,
        public bool $quiet,
        public bool $checkRedos,
        public bool $checkValidation,
        public bool $checkOptimizations,
        public int $jobs,
    ) {}

    public static function fromDefaults(array $defaults): self
    {
        return new self(
            $defaults['paths'] ?? [],
            $defaults['exclude'] ?? [],
            $defaults['minSavings'] ?? 1,
            $defaults['verbosity'] ?? OutputConfiguration::VERBOSITY_NORMAL,
            $defaults['format'] ?? 'console',
            $defaults['quiet'] ?? false,
            $defaults['checkRedos'] ?? true,
            $defaults['checkValidation'] ?? true,
            $defaults['checkOptimizations'] ?? true,
            $defaults['jobs'] ?? 1,
        );
    }
}
