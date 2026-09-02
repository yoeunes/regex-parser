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
use RegexParser\Lint\Extraction\PhpParserExtractionStrategy;
use RegexParser\Lint\Extraction\TokenBasedExtractionStrategy;
use RegexParser\Lint\RegexPatternExtractor;

final class LintExtractorFactory
{
    public function create(): RegexPatternExtractor
    {
        $parserFactoryClass = ParserFactory::class;

        if (class_exists($parserFactoryClass)) {
            return new RegexPatternExtractor(new PhpParserExtractionStrategy());
        }

        return new RegexPatternExtractor(new TokenBasedExtractionStrategy());
    }
}
