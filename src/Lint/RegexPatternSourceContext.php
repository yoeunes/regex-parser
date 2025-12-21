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
 * Context passed to pattern sources during extraction.
 *
 * @internal
 */
final readonly class RegexPatternSourceContext
{
    /**
     * @var callable(int, int): void|null
     */
    public mixed $progress;

    /**
     * @param list<string> $paths
     * @param list<string> $excludePaths
     * @param list<string> $disabledSources
     * @param callable(int, int): void|null $progress
     */
    public function __construct(
        public array $paths,
        public array $excludePaths,
        private array $disabledSources = [],
        ?callable $progress = null,
    ) {
        $this->progress = $progress;
    }

    public function isSourceEnabled(string $name): bool
    {
        return !\in_array($name, $this->disabledSources, true);
    }
}
