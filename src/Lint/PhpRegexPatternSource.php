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
 * Extracts regex patterns from PHP source files.
 *
 * @internal
 */
final readonly class PhpRegexPatternSource implements RegexPatternSourceInterface
{
    public function __construct(private RegexPatternExtractor $extractor) {}

    public function getName(): string
    {
        return 'php';
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function extract(RegexPatternSourceContext $context): array
    {
        $progress = \is_callable($context->progress) ? $context->progress : null;

        return array_values($this->extractor->extract(
            $context->paths,
            $context->excludePaths,
            $progress,
            $context->workers,
        ));
    }
}
