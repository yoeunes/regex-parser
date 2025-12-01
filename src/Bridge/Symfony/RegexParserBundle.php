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
 * Purpose: This class is the main entry point for integrating the `regex-parser`
 * library with the Symfony framework. Its primary role is to register the bundle
 * with the Symfony kernel. The actual dependency injection, service configuration,
 * and integration with the Web Profiler are handled by the `RegexParserExtension`
 * and other classes within this bridge.
 *
 * @see \RegexParser\Bridge\Symfony\DependencyInjection\RegexParserExtension
 * @see \RegexParser\Bridge\Symfony\DependencyInjection\Configuration
 */
class RegexParserBundle extends Bundle
{
    /**
     * Overrides the default bundle path to correctly locate resources.
     *
     * Purpose: Symfony's bundle system needs to know the root directory of the bundle
     * to correctly locate templates, configuration files, and other resources. This
     * method overrides the default behavior to point to the correct directory structure
     * for this bridge, ensuring that assets like the Web Profiler templates are found.
     *
     * @return string The absolute path to the bundle's root directory.
     */
    #[\Override]
    public function getPath(): string
    {
        return __DIR__;
    }
}
