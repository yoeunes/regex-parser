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

namespace RegexParser\Bridge\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use RegexParser\AnalysisReport;
use RegexParser\LiteralExtractionResult;
use RegexParser\Node\RegexNode;
use RegexParser\OptimizationResult;
use RegexParser\ReDoS\ReDoSAnalysis;
use RegexParser\ReDoS\ReDoSConfirmOptions;
use RegexParser\ReDoS\ReDoSMode;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\TolerantParseResult;
use RegexParser\Transpiler\TranspileOptions;
use RegexParser\Transpiler\TranspileResult;
use RegexParser\ValidationResult;

/**
 * Laravel Facade for the RegexParser library.
 *
 * @method static RegexNode|TolerantParseResult parse(string $regex, bool $tolerant = false)
 * @method static AnalysisReport                analyze(string $regex)
 * @method static ValidationResult              validate(string $regex)
 * @method static ReDoSAnalysis                 redos(string $regex, ?ReDoSSeverity $threshold = null, ReDoSMode $mode = ReDoSMode::THEORETICAL, ?ReDoSConfirmOptions $confirmOptions = null)
 * @method static OptimizationResult            optimize(string $regex, array $options = [])
 * @method static TranspileResult               transpile(string $regex, string $target, ?TranspileOptions $options = null)
 * @method static string                        explain(string $regex, string $format = 'text')
 * @method static string                        highlight(string $regex, string $format = 'console')
 * @method static LiteralExtractionResult       literals(string $regex)
 * @method static string                        generate(string $regex)
 * @method static RegexNode                     parsePattern(string $pattern, string $flags = '', string $delimiter = '/')
 *
 * @see \RegexParser\Regex
 */
final class Regex extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \RegexParser\Regex::class;
    }
}
