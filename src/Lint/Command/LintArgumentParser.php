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
use RegexParser\ReDoS\ReDoSMode;
use RegexParser\ReDoS\ReDoSSeverity;

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
                $arguments = $this->withVerbosity($arguments, OutputConfiguration::VERBOSITY_QUIET, true);

                continue;
            }

            if ('--verbose' === $arg || '-v' === $arg) {
                $arguments = $this->withVerbosity($arguments, OutputConfiguration::VERBOSITY_VERBOSE);

                continue;
            }

            if ('--debug' === $arg) {
                $arguments = $this->withVerbosity($arguments, OutputConfiguration::VERBOSITY_DEBUG);

                continue;
            }

            if ('--lint' === $arg) {
                $arguments = $this->withCheckLint($arguments, true);

                continue;
            }

            if ('--no-lint' === $arg) {
                $arguments = $this->withCheckLint($arguments, false);

                continue;
            }

            if (str_starts_with($arg, '--enable-rule=')) {
                $ruleId = substr($arg, \strlen('--enable-rule='));
                $arguments = $this->withLintRule($arguments, $ruleId, true);

                continue;
            }

            if (str_starts_with($arg, '--disable-rule=')) {
                $ruleId = substr($arg, \strlen('--disable-rule='));
                $arguments = $this->withLintRule($arguments, $ruleId, false);

                continue;
            }

            if ('--redos' === $arg) {
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $arguments->minSavings,
                    $arguments->verbosity,
                    $arguments->format,
                    $arguments->quiet,
                    true,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
                );

                continue;
            }

            if (str_starts_with($arg, '--redos-mode=')) {
                $value = strtolower(substr($arg, \strlen('--redos-mode=')));
                $mode = ReDoSMode::tryFrom($value);
                if (null === $mode) {
                    return new LintParseResult(null, 'Invalid value for --redos-mode.');
                }
                $checkRedos = ReDoSMode::OFF !== $mode;
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $arguments->minSavings,
                    $arguments->verbosity,
                    $arguments->format,
                    $arguments->quiet,
                    $checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $mode->value,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
                );

                continue;
            }

            if ('--redos-mode' === $arg) {
                $value = $args[$i + 1] ?? '';
                if ('' === $value || str_starts_with($value, '-')) {
                    return new LintParseResult(null, 'Missing value for --redos-mode.');
                }
                $mode = ReDoSMode::tryFrom(strtolower($value));
                if (null === $mode) {
                    return new LintParseResult(null, 'Invalid value for --redos-mode.');
                }
                $checkRedos = ReDoSMode::OFF !== $mode;
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $arguments->minSavings,
                    $arguments->verbosity,
                    $arguments->format,
                    $arguments->quiet,
                    $checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $mode->value,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
                );
                $i++;

                continue;
            }

            if (str_starts_with($arg, '--redos-threshold=')) {
                $value = strtolower(substr($arg, \strlen('--redos-threshold=')));
                if (null === ReDoSSeverity::tryFrom($value)) {
                    return new LintParseResult(null, 'Invalid value for --redos-threshold.');
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $value,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
                );

                continue;
            }

            if ('--redos-threshold' === $arg) {
                $value = $args[$i + 1] ?? '';
                if ('' === $value || str_starts_with($value, '-')) {
                    return new LintParseResult(null, 'Missing value for --redos-threshold.');
                }
                $value = strtolower($value);
                if (null === ReDoSSeverity::tryFrom($value)) {
                    return new LintParseResult(null, 'Invalid value for --redos-threshold.');
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $value,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
                );
                $i++;

                continue;
            }

            if ('--redos-no-jit' === $arg) {
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    true,
                    $arguments->lintRules,
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
                );

                continue;
            }

            if (str_starts_with($arg, '--generate-baseline=')) {
                $generateBaseline = substr($arg, \strlen('--generate-baseline='));
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
                );

                continue;
            }

            if (str_starts_with($arg, '--baseline=')) {
                $baseline = substr($arg, \strlen('--baseline='));
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
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
                    $arguments->checkLint,
                    $jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
                );

                continue;
            }

            if ('--min-savings' === $arg) {
                $value = $args[$i + 1] ?? '';
                if ('' === $value || str_starts_with($value, '-')) {
                    return new LintParseResult(null, 'Missing value for --min-savings.');
                }
                $minSavings = (int) $value;
                $arguments = new LintArguments(
                    $arguments->paths,
                    $arguments->exclude,
                    $minSavings,
                    $arguments->verbosity,
                    $arguments->format,
                    $arguments->quiet,
                    $arguments->checkRedos,
                    $arguments->checkValidation,
                    $arguments->checkOptimizations,
                    $arguments->checkLint,
                    $arguments->jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
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
                    $arguments->checkLint,
                    $jobs,
                    $arguments->output,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
                );
                $i++;

                continue;
            }

            if (str_starts_with($arg, '--output=')) {
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    substr($arg, \strlen('--output=')),
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
                );

                continue;
            }

            if ('--output' === $arg) {
                $value = $args[$i + 1] ?? '';
                if ('' === $value || str_starts_with($value, '-')) {
                    return new LintParseResult(null, 'Missing value for --output.');
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
                    $arguments->checkLint,
                    $arguments->jobs,
                    $value,
                    $arguments->baseline,
                    $arguments->generateBaseline,
                    $arguments->ide,
                    $arguments->optimizations,
                    $arguments->redosMode,
                    $arguments->redosThreshold,
                    $arguments->redosNoJit,
                    $arguments->lintRules,
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
                $arguments->checkLint,
                $arguments->jobs,
                $arguments->output,
                $arguments->baseline,
                $arguments->generateBaseline,
                $arguments->ide,
                $arguments->optimizations,
                $arguments->redosMode,
                $arguments->redosThreshold,
                $arguments->redosNoJit,
                $arguments->lintRules,
            );
        }

        return new LintParseResult($arguments);
    }

    /**
     * @param "debug"|"normal"|"quiet"|"verbose" $verbosity
     */
    private function withVerbosity(LintArguments $arguments, string $verbosity, bool $quiet = false): LintArguments
    {
        return new LintArguments(
            $arguments->paths,
            $arguments->exclude,
            $arguments->minSavings,
            $verbosity,
            $arguments->format,
            $quiet ?: $arguments->quiet,
            $arguments->checkRedos,
            $arguments->checkValidation,
            $arguments->checkOptimizations,
            $arguments->checkLint,
            $arguments->jobs,
            $arguments->output,
            $arguments->baseline,
            $arguments->generateBaseline,
            $arguments->ide,
            $arguments->optimizations,
            $arguments->redosMode,
            $arguments->redosThreshold,
            $arguments->redosNoJit,
            $arguments->lintRules,
        );
    }

    private function withCheckLint(LintArguments $arguments, bool $checkLint): LintArguments
    {
        return new LintArguments(
            $arguments->paths,
            $arguments->exclude,
            $arguments->minSavings,
            $arguments->verbosity,
            $arguments->format,
            $arguments->quiet,
            $arguments->checkRedos,
            $arguments->checkValidation,
            $arguments->checkOptimizations,
            $checkLint,
            $arguments->jobs,
            $arguments->output,
            $arguments->baseline,
            $arguments->generateBaseline,
            $arguments->ide,
            $arguments->optimizations,
            $arguments->redosMode,
            $arguments->redosThreshold,
            $arguments->redosNoJit,
            $arguments->lintRules,
        );
    }

    private function withLintRule(LintArguments $arguments, string $ruleId, bool $enabled): LintArguments
    {
        $lintRules = $arguments->lintRules;
        $lintRules[$ruleId] = $enabled;

        return new LintArguments(
            $arguments->paths,
            $arguments->exclude,
            $arguments->minSavings,
            $arguments->verbosity,
            $arguments->format,
            $arguments->quiet,
            $arguments->checkRedos,
            $arguments->checkValidation,
            $arguments->checkOptimizations,
            $arguments->checkLint,
            $arguments->jobs,
            $arguments->output,
            $arguments->baseline,
            $arguments->generateBaseline,
            $arguments->ide,
            $arguments->optimizations,
            $arguments->redosMode,
            $arguments->redosThreshold,
            $arguments->redosNoJit,
            $lintRules,
        );
    }
}
