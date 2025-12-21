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
 * Provides regex pattern occurrences from a specific source.
 *
 * @internal
 */
interface RegexPatternSourceInterface
{
    public function getName(): string;

    public function isSupported(): bool;

    /**
     * @return list<RegexPatternOccurrence>
     */
    public function extract(RegexPatternSourceContext $context): array;
}
