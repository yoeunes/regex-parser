<?php

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
 * A DTO representing the result of a validation pass.
 */
class ValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly ?string $error = null,
    ) {
    }
}
