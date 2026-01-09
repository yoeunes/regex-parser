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

namespace RegexParser\Transpiler;

use RegexParser\Regex;
use RegexParser\Transpiler\Target\TargetRegistry;
use RegexParser\Transpiler\Target\TranspileTargetInterface;

/**
 * Transpiles PCRE regex literals to other target dialects.
 */
final readonly class RegexTranspiler
{
    private TargetRegistry $targets;

    public function __construct(
        private Regex $regex,
        ?TargetRegistry $targets = null,
    ) {
        $this->targets = $targets ?? new TargetRegistry();
    }

    public function transpile(string $pattern, string $target, ?TranspileOptions $options = null): TranspileResult
    {
        $options ??= new TranspileOptions();
        $dialect = $this->targets->get($target);
        $ast = $this->regex->parse($pattern);

        $context = new TranspileContext($pattern, $ast->flags, $options);
        $compiled = $dialect->compile($ast, $context);
        $flags = $dialect->mapFlags($ast->flags, $context);

        return new TranspileResult(
            $pattern,
            $dialect->getName(),
            $compiled,
            $flags,
            $this->buildLiteral($compiled, $flags, $dialect),
            $this->buildConstructor($compiled, $flags),
            $context->getWarnings(),
            $context->getNotes(),
        );
    }

    private function buildLiteral(string $pattern, string $flags, TranspileTargetInterface $dialect): string
    {
        $delimiter = $dialect->getDefaultDelimiter();

        return $delimiter.$pattern.$delimiter.$flags;
    }

    private function buildConstructor(string $pattern, string $flags): string
    {
        $escaped = $this->escapeForJsString($pattern);
        $escapedFlags = $this->escapeForJsString($flags);

        return 'new RegExp("'.$escaped.'", "'.$escapedFlags.'")';
    }

    private function escapeForJsString(string $value): string
    {
        $escaped = str_replace(
            ['\\', '"', "\n", "\r", "\t", "\u{2028}", "\u{2029}"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t', '\\u2028', '\\u2029'],
            $value,
        );

        return $escaped;
    }
}
