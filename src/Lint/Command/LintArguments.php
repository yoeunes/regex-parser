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

    /**
     * @param array<string, mixed> $defaults
     */
    public static function fromDefaults(array $defaults): self
    {
        $paths = self::normalizeStringList($defaults['paths'] ?? []);
        $exclude = self::normalizeStringList($defaults['exclude'] ?? []);

        $minSavings = $defaults['minSavings'] ?? 1;
        if (!\is_int($minSavings)) {
            if (\is_string($minSavings) && ctype_digit($minSavings)) {
                $minSavings = (int) $minSavings;
            } else {
                $minSavings = 1;
            }
        }
        if ($minSavings < 1) {
            $minSavings = 1;
        }

        $verbosity = $defaults['verbosity'] ?? OutputConfiguration::VERBOSITY_NORMAL;
        if (!\is_string($verbosity) || '' === $verbosity) {
            $verbosity = OutputConfiguration::VERBOSITY_NORMAL;
        }

        $format = $defaults['format'] ?? 'console';
        if (!\is_string($format) || '' === $format) {
            $format = 'console';
        }

        $quiet = $defaults['quiet'] ?? false;
        if (!\is_bool($quiet)) {
            $quiet = false;
        }

        $checkRedos = $defaults['checkRedos'] ?? true;
        if (!\is_bool($checkRedos)) {
            $checkRedos = true;
        }

        $checkValidation = $defaults['checkValidation'] ?? true;
        if (!\is_bool($checkValidation)) {
            $checkValidation = true;
        }

        $checkOptimizations = $defaults['checkOptimizations'] ?? true;
        if (!\is_bool($checkOptimizations)) {
            $checkOptimizations = true;
        }

        $jobs = $defaults['jobs'] ?? 1;
        if (!\is_int($jobs)) {
            if (\is_string($jobs) && ctype_digit($jobs)) {
                $jobs = (int) $jobs;
            } else {
                $jobs = 1;
            }
        }
        if ($jobs < 1) {
            $jobs = 1;
        }

        return new self(
            $paths,
            $exclude,
            $minSavings,
            $verbosity,
            $format,
            $quiet,
            $checkRedos,
            $checkValidation,
            $checkOptimizations,
            $jobs,
        );
    }

    /**
     * @return array<int, string>
     */
    private static function normalizeStringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $entry) {
            if (\is_string($entry) && '' !== $entry) {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }
}
