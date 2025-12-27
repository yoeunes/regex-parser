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

use RegexParser\Lint\Formatter\OutputConfiguration;

final class LintArgumentParser
{
    /**
     * @param array<int, string>   $args
     * @param array<string, mixed> $defaults
     */
    public function parse(array $args, array $defaults = []): LintParseResult
    {
        $arguments = LintArguments::fromDefaults($defaults);
        $pathsProvided = false;

        for ($i = 0; $i < \count($args); $i++) {
            $arg = $args[$i];

            if ('--help' === $arg || '-h' === $arg) {
                return new LintParseResult(null, null, true);
            }

            if ('--quiet' === $arg || '-q' === $arg) {
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $arguments->minSavings,
                    OutputConfiguration::VERBOSITY_QUIET,
                    $arguments->format,
                    true,
                    $arguments->checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->jobs,
                );

                continue;
            }

            if ('--verbose' === $arg || '-v' === $arg) {
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $arguments->minSavings,
                    OutputConfiguration::VERBOSITY_VERBOSE,
                    $arguments->format,
                    $arguments->quiet,
                    $arguments->checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->jobs,
                );

                continue;
            }

            if ('--debug' === $arg) {
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $arguments->minSavings,
                    OutputConfiguration::VERBOSITY_DEBUG,
                    $arguments->format,
                    $arguments->quiet,
                    $arguments->checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->jobs,
                );

                continue;
            }

            if ('--no-redos' === $arg) {
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $arguments->minSavings,
                    $arguments->verbosity,
                    $arguments->format,
                    $arguments->quiet,
                    false,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->jobs,
                );

                continue;
            }

            if ('--no-validate' === $arg) {
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $arguments->minSavings,
                    $arguments->verbosity,
                    $arguments->format,
                    $arguments->quiet,
                    $arguments->checkRedos,
                    false,
                    $arguments->checkOptimizations,
                    $arguments->jobs,
                );

                continue;
            }

            if ('--no-optimize' === $arg) {
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $arguments->minSavings,
                    $arguments->verbosity,
                    $arguments->format,
                    $arguments->quiet,
                    $arguments->checkRedos,
                    $arguments->checkValidation,
                    false,
                    $arguments->jobs,
                );

                continue;
            }

            if (str_starts_with($arg, '--format=')) {
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $arguments->minSavings,
                    $arguments->verbosity,
                    substr($arg, \strlen('--format=')),
                    $arguments->quiet,
                    $arguments->checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->jobs,
                );

                continue;
            }

            if ('--format' === $arg) {
                $value = $args[$i + 1] ?? '';
                if ('' === $value || str_starts_with($value, '-')) {
                    return new LintParseResult(null, 'Missing value for --format.');
                }
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $arguments->minSavings,
                    $arguments->verbosity,
                    $value,
                    $arguments->quiet,
                    $arguments->checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->jobs,
                );
                $i++;

                continue;
            }

            if (str_starts_with($arg, '--exclude=')) {
                $exclude = $arguments->exclude;
                $exclude[] = substr($arg, \strlen('--exclude='));
                $arguments = new LintArguments(
                    $arguments->paths,
                    $exclude,
                    $arguments->minSavings,
                    $arguments->verbosity,
                    $arguments->format,
                    $arguments->quiet,
                    $arguments->checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->jobs,
                );

                continue;
            }

            if ('--exclude' === $arg) {
                $value = $args[$i + 1] ?? '';
                if ('' === $value || str_starts_with($value, '-')) {
                    return new LintParseResult(null, 'Missing value for --exclude.');
                }
                $exclude = $arguments->exclude;
                $exclude[] = $value;
                $arguments = new LintArguments(
                    $arguments->paths,
                    $exclude,
                    $arguments->minSavings,
                    $arguments->verbosity,
                    $arguments->format,
                    $arguments->quiet,
                    $arguments->checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->jobs,
                );
                $i++;

                continue;
            }

            if (str_starts_with($arg, '--min-savings=')) {
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    (int) substr($arg, \strlen('--min-savings=')),
                    $arguments->verbosity,
                    $arguments->format,
                    $arguments->quiet,
                    $arguments->checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->jobs,
                );

                continue;
            }

            if (str_starts_with($arg, '--jobs=')) {
                $jobs = (int) substr($arg, \strlen('--jobs='));
                if ($jobs < 1) {
                    return new LintParseResult(null, 'The --jobs value must be a positive integer.');
                }
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $arguments->minSavings,
                    $arguments->verbosity,
                    $arguments->format,
                    $arguments->quiet,
                    $arguments->checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $jobs,
                );

                continue;
            }

            if ('--min-savings' === $arg) {
                $value = $args[$i + 1] ?? '';
                if ('' === $value || str_starts_with($value, '-')) {
                    return new LintParseResult(null, 'Missing value for --min-savings.');
                }
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    (int) $value,
                    $arguments->verbosity,
                    $arguments->format,
                    $arguments->quiet,
                    $arguments->checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->jobs,
                );
                $i++;

                continue;
            }

            if ('--jobs' === $arg || '-j' === $arg) {
                $value = $args[$i + 1] ?? '';
                if ('' === $value || str_starts_with($value, '-')) {
                    return new LintParseResult(null, 'Missing value for --jobs.');
                }
                $jobs = (int) $value;
                if ($jobs < 1) {
                    return new LintParseResult(null, 'The --jobs value must be a positive integer.');
                }
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $arguments->minSavings,
                    $arguments->verbosity,
                    $arguments->format,
                    $arguments->quiet,
                    $arguments->checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $jobs,
                );
                $i++;

                continue;
            }

            if (str_starts_with($arg, '-')) {
                return new LintParseResult(null, 'Unknown option: '.$arg);
            }

            $paths = $arguments->paths;
            if (!$pathsProvided) {
                $paths = [];
                $pathsProvided = true;
            }
            $paths[] = $arg;
            $arguments = new LintArguments(
                $paths,
                $arguments->exclude,
                $arguments->minSavings,
                $arguments->verbosity,
                $arguments->format,
                $arguments->quiet,
                $arguments->checkRedos,
                $arguments->checkValidation,
                $arguments->checkOptimizations,
                $arguments->jobs,
            );
        }

        return new LintParseResult($arguments);
    }
}
