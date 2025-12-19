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

use RegexParser\Bridge\Symfony\Validator\ValidatorPatternProvider;

/**
 * Extracts regex patterns from Symfony Validator metadata.
 *
 * @internal
 */
final readonly class ValidatorRegexPatternSource implements RegexPatternSourceInterface
{
    public function __construct(private ValidatorPatternProvider $patternProvider) {}

    public function getName(): string
    {
        return 'validators';
    }

    public function isSupported(): bool
    {
        return $this->patternProvider->isSupported();
    }

    public function extract(RegexPatternSourceContext $context): array
    {
        if (!$this->patternProvider->isSupported()) {
            return [];
        }

        $patterns = [];
        $line = 1;

        foreach ($this->patternProvider->collect() as $pattern) {
            $file = $pattern->file ?? 'Symfony Validator';
            $patterns[] = new RegexPatternOccurrence(
                $pattern->pattern,
                $file,
                $line++,
                'validator:'.$pattern->source,
            );
        }

        return $patterns;
    }
}
