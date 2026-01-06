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
        $visuals = true;
        $phpVersion = null;
        $error = null;
        $remaining = [];

        for ($i = 0; $i < \count($args); $i++) {
            $arg = $args[$i];

            if ($this->isQuietOption($arg)) {
                $quiet = true;

                continue;
            }

            if ($this->isAnsiOption($arg)) {
                $ansi = '--ansi' === $arg;

                continue;
            }

            if ($this->isHelpOption($arg)) {
                $help = true;

                continue;
            }

            if ($this->isVisualsOption($arg)) {
                $visuals = false;

                continue;
            }

            if ($this->isPhpVersionOption($arg, $args, $i, $phpVersion, $error)) {
                if (null !== $error) {
                    break;
                }

                continue;
            }

            $remaining[] = $arg;
        }

        $options = new GlobalOptions($quiet, $ansi, $help, $visuals, $phpVersion, $error);

        return new ParsedGlobalOptions($options, $remaining);
    }

    private function isQuietOption(string $arg): bool
    {
        return '-q' === $arg || '--quiet' === $arg || '--silent' === $arg;
    }

    private function isAnsiOption(string $arg): bool
    {
        return '--ansi' === $arg || '--no-ansi' === $arg;
    }

    private function isHelpOption(string $arg): bool
    {
        return '--help' === $arg || '-h' === $arg;
    }

    private function isVisualsOption(string $arg): bool
    {
        return '--no-visuals' === $arg || '--no-art' === $arg || '--no-splash' === $arg;
    }

    /**
     * @param array<int, string> $args
     */
    private function isPhpVersionOption(string $arg, array $args, int &$i, ?string &$phpVersion, ?string &$error): bool
    {
        if (str_starts_with($arg, '--php-version=')) {
            $phpVersion = substr($arg, \strlen('--php-version='));

            return true;
        }

        if ('--php-version' !== $arg) {
            return false;
        }

        $value = $args[$i + 1] ?? '';

        if ('' === $value || str_starts_with($value, '-')) {
            $error = 'Missing value for --php-version.';

            return true;
        }

        $phpVersion = $value;
        $i++;

        return true;
    }
}
