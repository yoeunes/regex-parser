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
 * Holds visualization output formats for a regex pattern.
 */
final readonly class VisualizationResult
{
    public function __construct(
        public string $mermaid,
        public ?string $svg = null,
        public ?string $renderedHtml = null,
    ) {}
}
