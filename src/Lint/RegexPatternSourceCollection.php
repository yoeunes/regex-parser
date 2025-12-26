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
 * Aggregates pattern sources into a single extractor.
 *
 * @internal
 */
final readonly class RegexPatternSourceCollection
{
    /**
     * @param iterable<RegexPatternSourceInterface> $sources
     */
    public function __construct(private iterable $sources) {}

    /**
     * @return array<RegexPatternOccurrence>
     */
    public function collect(RegexPatternSourceContext $context): array
    {
        $patterns = [];

        foreach ($this->sources as $source) {
            if (!$context->isSourceEnabled($source->getName())) {
                continue;
            }

            if (!$source->isSupported()) {
                continue;
            }

            foreach ($source->extract($context) as $pattern) {
                $patterns[] = $pattern;
            }
        }

        return $patterns;
    }
}
