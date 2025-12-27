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

namespace RegexParser\Cli;

final class GlobalOptionsParser
{
    /**
     * @param array<int, string> $args
     */
    public function parse(array $args): ParsedGlobalOptions
    {
        $quiet = false;
        $ansi = null;
        $help = false;
        $phpVersion = null;
        $error = null;
        $remaining = [];

        for ($i = 0; $i < \count($args); $i++) {
            $arg = $args[$i];

            if ('-q' === $arg || '--quiet' === $arg || '--silent' === $arg) {
                $quiet = true;

                continue;
            }

            if ('--ansi' === $arg) {
                $ansi = true;

                continue;
            }

            if ('--no-ansi' === $arg) {
                $ansi = false;

                continue;
            }

            if ('--help' === $arg || '-h' === $arg) {
                $help = true;

                continue;
            }

            if (str_starts_with($arg, '--php-version=')) {
                $phpVersion = substr($arg, \strlen('--php-version='));

                continue;
            }

            if ('--php-version' === $arg) {
                $value = $args[$i + 1] ?? '';
                if ('' === $value || str_starts_with($value, '-')) {
                    $error = 'Missing value for --php-version.';

                    break;
                }
                $phpVersion = $value;
                $i++;

                continue;
            }

            $remaining[] = $arg;
        }

        $options = new GlobalOptions($quiet, $ansi, $help, $phpVersion, $error);

        return new ParsedGlobalOptions($options, $remaining);
    }
}
