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

namespace RegexParser;

/**
 * Captures generated test cases for a regex pattern.
 */
final readonly class TestCaseGenerationResult
{
    /**
     * @param list<string> $matching
     * @param list<string> $nonMatching
     * @param list<string> $notes
     */
    public function __construct(
        public array $matching,
        public array $nonMatching,
        public array $notes = [],
    ) {}
}
