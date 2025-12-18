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

namespace RegexParser\Bridge\Symfony\Service;

use RegexParser\Bridge\Symfony\Analyzer\ValidatorRegexAnalyzer;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validates regex usage in Symfony validators.
 */
final class ValidatorValidationService
{
    public function __construct(
        private readonly ?ValidatorRegexAnalyzer $analyzer = null,
        private readonly ?ValidatorInterface $validator = null,
        private readonly ?LoaderInterface $validatorLoader = null,
    ) {
    }

    public function isSupported(): bool
    {
        return null !== $this->analyzer && null !== $this->validator && null !== $this->validatorLoader;
    }

    public function analyze(): array
    {
        if (!$this->isSupported()) {
            return [];
        }

        return $this->analyzer->analyze($this->validator, $this->validatorLoader);
    }
}
