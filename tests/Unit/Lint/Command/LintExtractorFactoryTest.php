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

namespace RegexParser\Tests\Unit\Lint\Command;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\Command\LintExtractorFactory;
use RegexParser\Lint\TokenBasedExtractionStrategy;
use RegexParser\Tests\Support\LintFunctionOverrides;

final class LintExtractorFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        LintFunctionOverrides::reset();
    }

    public function test_create_falls_back_to_token_extractor_when_php_parser_missing(): void
    {
        LintFunctionOverrides::queueClassExists(false);

        $factory = new LintExtractorFactory();
        $extractor = $factory->create();

        $ref = new \ReflectionClass($extractor);
        $property = $ref->getProperty('extractor');

        $inner = $property->getValue($extractor);

        $this->assertInstanceOf(TokenBasedExtractionStrategy::class, $inner);
    }
}
