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

namespace RegexParser\Lint\Command;

final class LintDefaultsBuilder
{
    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function build(array $config): array
    {
        $defaults = [];

        if (isset($config['paths'])) {
            $defaults['paths'] = $config['paths'];
        }

        if (isset($config['exclude'])) {
            $defaults['exclude'] = $config['exclude'];
        }

        if (isset($config['jobs'])) {
            $defaults['jobs'] = $config['jobs'];
        }

        if (isset($config['minSavings'])) {
            $defaults['minSavings'] = $config['minSavings'];
        }

        if (isset($config['format'])) {
            $defaults['format'] = $config['format'];
        }

        if (isset($config['rules']) && \is_array($config['rules'])) {
            if (\array_key_exists('redos', $config['rules'])) {
                $defaults['checkRedos'] = $config['rules']['redos'];
            }
            if (\array_key_exists('validation', $config['rules'])) {
                $defaults['checkValidation'] = $config['rules']['validation'];
            }
            if (\array_key_exists('optimization', $config['rules'])) {
                $defaults['checkOptimizations'] = $config['rules']['optimization'];
            }
        }

        if (isset($config['ide'])) {
            $defaults['ide'] = $config['ide'];
        }

        if (isset($config['optimizations'])) {
            $defaults['optimizations'] = $config['optimizations'];
        }

        return $defaults;
    }
}
