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

namespace RegexParser\Bridge\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony Bundle for the RegexParser library.
 *
 * Provides integration with the Symfony Web Profiler for analyzing
 * regex patterns used in your application (via Router and Validator).
 *
 * Configuration is handled by RegexParserExtension. No logic should
 * be placed in this class.
 *
 * @see \RegexParser\Bridge\Symfony\DependencyInjection\RegexParserExtension
 * @see \RegexParser\Bridge\Symfony\DependencyInjection\Configuration
 */
final class RegexParserBundle extends Bundle
{
    #[\Override]
    public function getPath(): string
    {
        return __DIR__.'/../../Bridge/Symfony';
    }
}
