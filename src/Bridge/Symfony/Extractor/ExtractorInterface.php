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

/**
 * Interface for regex pattern extraction implementations.
 *
 * @internal
 */
interface ExtractorInterface
{
    /**
     * Extract regex patterns from the given paths.
     *
     * @param list<string> $paths
     *
     * @return list<RegexPatternOccurrence>
     */
    public function extract(array $paths): array;
}
