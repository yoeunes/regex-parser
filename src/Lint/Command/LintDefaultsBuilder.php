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

        // Handle new "checks" configuration format (preferred)
        if (isset($config['checks']) && \is_array($config['checks'])) {
            if (\array_key_exists('validation', $config['checks'])) {
                $defaults['checkValidation'] = $config['checks']['validation'];
            }

            // Handle checks.redos (can be boolean or object)
            if (\array_key_exists('redos', $config['checks'])) {
                $redos = $config['checks']['redos'];
                if (\is_bool($redos)) {
                    $defaults['checkRedos'] = $redos;
                } elseif (\is_array($redos)) {
                    if (\array_key_exists('enabled', $redos)) {
                        $defaults['checkRedos'] = $redos['enabled'];
                    }
                    if (\array_key_exists('mode', $redos)) {
                        $defaults['redosMode'] = $redos['mode'];
                    }
                    if (\array_key_exists('threshold', $redos)) {
                        $defaults['redosThreshold'] = $redos['threshold'];
                    }
                    if (\array_key_exists('noJit', $redos)) {
                        $defaults['redosNoJit'] = $redos['noJit'];
                    }
                }
            }

            // Handle checks.optimizations (can be boolean or object)
            if (\array_key_exists('optimizations', $config['checks'])) {
                $optimizations = $config['checks']['optimizations'];
                if (\is_bool($optimizations)) {
                    $defaults['checkOptimizations'] = $optimizations;
                } elseif (\is_array($optimizations)) {
                    if (\array_key_exists('enabled', $optimizations)) {
                        $defaults['checkOptimizations'] = $optimizations['enabled'];
                    }
                    if (\array_key_exists('minSavings', $optimizations)) {
                        $defaults['minSavings'] = $optimizations['minSavings'];
                    }
                    if (\array_key_exists('options', $optimizations) && \is_array($optimizations['options'])) {
                        $defaults['optimizations'] = $optimizations['options'];
                    }
                }
            }
        }

        // Handle deprecated "rules" configuration format (for BC)
        if (isset($config['rules']) && \is_array($config['rules'])) {
            if (\array_key_exists('redos', $config['rules']) && !isset($defaults['checkRedos'])) {
                $defaults['checkRedos'] = $config['rules']['redos'];
            }
            if (\array_key_exists('validation', $config['rules']) && !isset($defaults['checkValidation'])) {
                $defaults['checkValidation'] = $config['rules']['validation'];
            }
            if (\array_key_exists('optimization', $config['rules']) && !isset($defaults['checkOptimizations'])) {
                $defaults['checkOptimizations'] = $config['rules']['optimization'];
            }
        }

        if (isset($config['ide'])) {
            $defaults['ide'] = $config['ide'];
        }

        // Handle deprecated top-level redos options (for BC, only if not set by checks)
        if (isset($config['redosMode']) && !isset($defaults['redosMode'])) {
            $defaults['redosMode'] = $config['redosMode'];
        }

        if (isset($config['redosThreshold']) && !isset($defaults['redosThreshold'])) {
            $defaults['redosThreshold'] = $config['redosThreshold'];
        }

        if (isset($config['redosNoJit']) && !isset($defaults['redosNoJit'])) {
            $defaults['redosNoJit'] = $config['redosNoJit'];
        }

        if (isset($config['optimizations']) && !isset($defaults['optimizations'])) {
            $defaults['optimizations'] = $config['optimizations'];
        }

        return $defaults;
    }
}
