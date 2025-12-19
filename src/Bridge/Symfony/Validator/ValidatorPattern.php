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

namespace RegexParser\Bridge\Symfony\Validator;

/**
 * Represents a regex pattern extracted from Symfony Validator metadata.
 *
 * @internal
 */
final readonly class ValidatorPattern
{
    public function __construct(
        public string $pattern,
        public string $source,
        public ?string $file = null,
    ) {}
}
