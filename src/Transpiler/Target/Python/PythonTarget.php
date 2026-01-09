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

namespace RegexParser\Transpiler\Target\Python;

use RegexParser\Exception\TranspileException;
use RegexParser\Node\RegexNode;
use RegexParser\Transpiler\Target\TranspileTargetInterface;
use RegexParser\Transpiler\TranspileContext;

/**
 * Transpile target for Python 're' module.
 */
final readonly class PythonTarget implements TranspileTargetInterface
{
    // Python re flags: ASCII (a), IGNORECASE (i), LOCALE (L), MULTILINE (m), DOTALL (s), VERBOSE (x)
    // We map PCRE flags to these.
    private const SUPPORTED_FLAGS = ['i', 'm', 's', 'x'];

    public function getName(): string
    {
        return 'python';
    }

    public function getAliases(): array
    {
        return ['py'];
    }

    public function getDefaultDelimiter(): string
    {
        return "'";
    }

    public function compile(RegexNode $ast, TranspileContext $context): string
    {
        $visitor = new PythonCompilerVisitor($context);

        return $ast->accept($visitor);
    }

    public function mapFlags(string $flags, TranspileContext $context): string
    {
        $normalized = '';
        $unsupported = [];

        foreach (str_split($flags) as $flag) {
            if (\in_array($flag, self::SUPPORTED_FLAGS, true)) {
                $normalized .= $flag;

                continue;
            }

            if ('u' === $flag) {
                // Python 3 implies unicode by default for str regexes.
                // We don't need to add a flag, but we should probably note it.
                continue;
            }

            if ('U' === $flag) {
                // Ungreedy in PCRE. Python doesn't have a global flag, only modifiers on quantifiers.
                // The parser should have handled this by changing quantifiers or we need to handle it in visitor?
                // Current parser likely keeps it global.
                // We will warn and drop it, but it might change semantics if not handled.
                // For now, let's treat it as unsupported or dropped with warning.
                $context->addWarning('Dropped /U (ungreedy) flag; Python requires per-quantifier ungreedy (?).');

                continue;
            }

            $unsupported[] = $flag;
        }

        if ([] !== $unsupported) {
            throw new TranspileException('Unsupported PCRE flag(s) for Python: '.implode(', ', $unsupported).'.');
        }

        return $this->normalizeFlagOrder($normalized);
    }

    public function formatLiteral(string $pattern, string $flags, TranspileContext $context): string
    {
        // Python doesn't really have "regex literals" like JS.
        // We'll return a raw string representation: r'pattern'
        return "r'".$pattern."'";
    }

    public function formatConstructor(string $pattern, string $flags, TranspileContext $context): string
    {
        // re.compile(r'pattern', flags)
        $flagConstants = [];
        if (str_contains($flags, 'i')) {
            $flagConstants[] = 're.IGNORECASE';
        }
        if (str_contains($flags, 'm')) {
            $flagConstants[] = 're.MULTILINE';
        }
        if (str_contains($flags, 's')) {
            $flagConstants[] = 're.DOTALL';
        }
        if (str_contains($flags, 'x')) {
            $flagConstants[] = 're.VERBOSE';
        }

        $flagsStr = [] === $flagConstants ? '0' : implode(' | ', $flagConstants);

        // Escape single quotes if necessary for the raw string
        $escaped = str_replace("'", "\\'", $pattern);

        return "re.compile(r'".$escaped."', ".$flagsStr.')';
    }

    private function normalizeFlagOrder(string $flags): string
    {
        $ordered = [];
        foreach (self::SUPPORTED_FLAGS as $flag) {
            if (str_contains($flags, $flag)) {
                $ordered[] = $flag;
            }
        }

        return implode('', $ordered);
    }
}
