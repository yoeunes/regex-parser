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
 * Extracts regex patterns from PHP source files using the configured extractor.
 *
 * @internal
 */
final class RegexPatternExtractor
{
    public function __construct(private ExtractorInterface $extractor)
    {
    }

    /**
     * @param list<string> $paths
     *
     * @return list<RegexPatternOccurrence>
     */
    public function extract(array $paths): array
    {
        return $this->extractor->extract($paths);
    }
}
