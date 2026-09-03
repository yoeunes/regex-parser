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

use PhpParser\ParserFactory;
use RegexParser\Lint\Extraction\PatternFunctionRegistry;
use RegexParser\Lint\Extraction\PhpParserExtractionStrategy;
use RegexParser\Lint\Extraction\TokenBasedExtractionStrategy;
use RegexParser\Lint\RegexPatternExtractor;

final class LintExtractorFactory
{
    public function create(?LintArguments $arguments = null): RegexPatternExtractor
    {
        $registry = null === $arguments
            ? PatternFunctionRegistry::defaults()
            : PatternFunctionRegistry::create($arguments->interop, $arguments->patternFunctions);

        $parserFactoryClass = ParserFactory::class;

        if (class_exists($parserFactoryClass)) {
            return new RegexPatternExtractor(new PhpParserExtractionStrategy([], $registry));
        }

        return new RegexPatternExtractor(new TokenBasedExtractionStrategy([], $registry));
    }
}
