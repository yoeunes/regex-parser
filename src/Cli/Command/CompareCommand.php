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

namespace RegexParser\Cli\Command;

use RegexParser\Automata\Minimization\MinimizationAlgorithm;
use RegexParser\Automata\Options\MatchMode;
use RegexParser\Automata\Options\SolverOptions;
use RegexParser\Automata\Solver\RegexSolver;
use RegexParser\Cli\ConsoleStyle;
use RegexParser\Cli\Input;
use RegexParser\Cli\Output;
use RegexParser\Exception\ComplexityException;

final class CompareCommand extends AbstractCommand
{
    private const METHOD_INTERSECTION = 'intersection';
    private const METHOD_SUBSET = 'subset';
    private const METHOD_EQUIVALENCE = 'equivalence';

    public function getName(): string
    {
        return 'compare';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getDescription(): string
    {
        return 'Compare two regex patterns using automata logic';
    }

    public function run(Input $input, Output $output): int
    {
        $parsed = $this->parseArguments($input->args);
        if (null !== $parsed['error']) {
            $output->write($output->error('Error: '.$parsed['error']."\n"));
            $output->write("Usage: regex compare <pattern1> <pattern2> [--method intersection|subset|equivalence] [--minimizer hopcroft|moore]\n");

            return 1;
        }

        $regex = $this->createRegex($output, $input->regexOptions);
        if (null === $regex) {
            return 1;
        }

        $solver = new RegexSolver($regex);
        $options = new SolverOptions(
            matchMode: MatchMode::FULL,
            minimizationAlgorithm: MinimizationAlgorithm::from($parsed['minimizer']),
        );

        $style = new ConsoleStyle($output, $input->globalOptions->visuals);
        $meta = [
            'Method' => $output->warning($parsed['method']),
            'Minimizer' => $output->warning($parsed['minimizer']),
        ];
        if (null !== $input->globalOptions->phpVersion) {
            $meta['Target PHP'] = $output->warning('PHP '.$input->globalOptions->phpVersion);
        }

        $style->renderBanner('compare', $meta, 'Automata-based regex comparison.');
        $style->renderPattern($parsed['pattern1'], 'Pattern 1');
        $style->renderPattern($parsed['pattern2'], 'Pattern 2');
        $output->write("\n");

        try {
            return match ($parsed['method']) {
                self::METHOD_INTERSECTION => $this->handleIntersection($solver, $options, $parsed, $output),
                self::METHOD_SUBSET => $this->handleSubset($solver, $options, $parsed, $output),
                self::METHOD_EQUIVALENCE => $this->handleEquivalence($solver, $options, $parsed, $output),
                default => 1,
            };
        } catch (ComplexityException) {
            $output->write($output->error('Comparison not supported: Pattern contains advanced features (e.g., lookarounds).')."\n");

            return 1;
        } catch (\Throwable $exception) {
            $output->write($output->error('Comparison failed: '.$exception->getMessage())."\n");

            return 1;
        }
    }

    /**
     * @param array{pattern1: string, pattern2: string, method: string, minimizer: string, error: ?string} $parsed
     */
    private function handleIntersection(RegexSolver $solver, SolverOptions $options, array $parsed, Output $output): int
    {
        $result = $solver->intersection($parsed['pattern1'], $parsed['pattern2'], $options);

        if ($result->isEmpty) {
            $output->write('  '.$output->badge('PASS', Output::WHITE, Output::BG_GREEN).' '.$output->success('No intersection found. These regexes are disjoint.')."\n");

            return 0;
        }

        $output->write('  '.$output->badge('FAIL', Output::WHITE, Output::BG_RED).' '.$output->error('Conflict detected!')."\n");
        $output->write("\n");
        $this->writeDetail($output, 'Example', $this->formatExample($result->example ?? ''));

        return 1;
    }

    /**
     * @param array{pattern1: string, pattern2: string, method: string, minimizer: string, error: ?string} $parsed
     */
    private function handleSubset(RegexSolver $solver, SolverOptions $options, array $parsed, Output $output): int
    {
        $result = $solver->subsetOf($parsed['pattern1'], $parsed['pattern2'], $options);

        if ($result->isSubset) {
            $output->write('  '.$output->badge('PASS', Output::WHITE, Output::BG_GREEN).' '.$output->success('Pattern 1 is a strict subset of Pattern 2.')."\n");

            return 0;
        }

        $output->write('  '.$output->badge('FAIL', Output::WHITE, Output::BG_RED).' '.$output->error('Pattern 1 allows strings that Pattern 2 forbids.')."\n");
        $output->write("\n");
        $this->writeDetail($output, 'Counter-example', $this->formatExample($result->counterExample ?? ''));

        return 1;
    }

    /**
     * @param array{pattern1: string, pattern2: string, method: string, minimizer: string, error: ?string} $parsed
     */
    private function handleEquivalence(RegexSolver $solver, SolverOptions $options, array $parsed, Output $output): int
    {
        $result = $solver->equivalent($parsed['pattern1'], $parsed['pattern2'], $options);

        if ($result->isEquivalent) {
            $output->write('  '.$output->badge('PASS', Output::WHITE, Output::BG_GREEN).' '.$output->success('Patterns are mathematically equivalent.')."\n");

            return 0;
        }

        $output->write('  '.$output->badge('FAIL', Output::WHITE, Output::BG_RED).' '.$output->error('Patterns are different.')."\n");
        $hasDetails = null !== $result->leftOnlyExample || null !== $result->rightOnlyExample;
        if ($hasDetails) {
            $output->write("\n");
        }
        if (null !== $result->leftOnlyExample) {
            $this->writeDetail($output, 'Pattern 1 only', $this->formatExample($result->leftOnlyExample));
        }
        if (null !== $result->rightOnlyExample) {
            $this->writeDetail($output, 'Pattern 2 only', $this->formatExample($result->rightOnlyExample));
        }

        return 1;
    }

    private function formatExample(string $example): string
    {
        if ('' === $example) {
            return '"" (empty string)';
        }

        $escaped = '';
        $length = \strlen($example);
        for ($i = 0; $i < $length; $i++) {
            $byte = \ord($example[$i]);
            $escaped .= match ($byte) {
                0x0A => '\\n',
                0x0D => '\\r',
                0x09 => '\\t',
                0x5C => '\\\\',
                0x22 => '\\"',
                default => ($byte < 0x20 || $byte > 0x7E)
                    ? \sprintf('\\x%02X', $byte)
                    : $example[$i],
            };
        }

        return '"'.$escaped.'"';
    }

    private function writeDetail(Output $output, string $label, string $value): void
    {
        $labelText = $label.':';
        $paddedLabel = \str_pad($labelText, 18);
        $output->write('  '.$output->bold($paddedLabel).' '.$value."\n");
    }

    /**
     * @param array<int, string> $args
     *
     * @return array{pattern1: string, pattern2: string, method: string, minimizer: string, error: ?string}
     */
    private function parseArguments(array $args): array
    {
        $method = self::METHOD_INTERSECTION;
        $minimizer = MinimizationAlgorithm::HOPCROFT->value;
        $patterns = [];
        $stopParsing = false;

        for ($i = 0; $i < \count($args); $i++) {
            $arg = $args[$i];

            if (!$stopParsing && '--' === $arg) {
                $stopParsing = true;

                continue;
            }

            if (!$stopParsing && str_starts_with($arg, '--method=')) {
                $method = \strtolower(substr($arg, \strlen('--method=')));

                continue;
            }

            if (!$stopParsing && '--method' === $arg) {
                $value = $args[$i + 1] ?? '';
                if ('' === $value || str_starts_with($value, '-')) {
                    return $this->errorResult('Missing value for --method.');
                }
                $method = \strtolower($value);
                $i++;

                continue;
            }

            if (!$stopParsing && str_starts_with($arg, '--minimizer=')) {
                $minimizer = \strtolower(substr($arg, \strlen('--minimizer=')));

                continue;
            }

            if (!$stopParsing && '--minimizer' === $arg) {
                $value = $args[$i + 1] ?? '';
                if ('' === $value || str_starts_with($value, '-')) {
                    return $this->errorResult('Missing value for --minimizer.');
                }
                $minimizer = \strtolower($value);
                $i++;

                continue;
            }

            if (!$stopParsing && str_starts_with($arg, '--')) {
                return $this->errorResult('Unknown option: '.$arg);
            }

            $patterns[] = $arg;
        }

        if (!\in_array($method, [self::METHOD_INTERSECTION, self::METHOD_SUBSET, self::METHOD_EQUIVALENCE], true)) {
            return $this->errorResult('Invalid value for --method.');
        }

        if (!\in_array($minimizer, [MinimizationAlgorithm::HOPCROFT->value, MinimizationAlgorithm::MOORE->value], true)) {
            return $this->errorResult('Invalid value for --minimizer. Use hopcroft or moore.');
        }

        if (\count($patterns) < 2) {
            return $this->errorResult('Missing required patterns.');
        }

        if (\count($patterns) > 2) {
            return $this->errorResult('Too many arguments provided.');
        }

        return [
            'pattern1' => $patterns[0],
            'pattern2' => $patterns[1],
            'method' => $method,
            'minimizer' => $minimizer,
            'error' => null,
        ];
    }

    /**
     * @return array{pattern1: string, pattern2: string, method: string, minimizer: string, error: string}
     */
    private function errorResult(string $error): array
    {
        return [
            'pattern1' => '',
            'pattern2' => '',
            'method' => self::METHOD_INTERSECTION,
            'minimizer' => MinimizationAlgorithm::HOPCROFT->value,
            'error' => $error,
        ];
    }
}
