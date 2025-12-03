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

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\ReDoSProfileNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;

final class AdvancedFeaturesComplianceTest extends TestCase
{
    #[DataProvider('provideRecursionSeeds')]
    public function testSampleGeneratorHandlesRecursion(int $seed, string $expectedSample): void
    {
        $regex = Regex::create()->parse('/a(?R)?z/');
        $generator = new SampleGeneratorNodeVisitor();
        $generator->setSeed($seed);

        self::assertSame($expectedSample, $regex->accept($generator));
    }

    public static function provideRecursionSeeds(): array
    {
        return [
            'single depth' => [2, 'az'],
            'double depth' => [5, 'aazz'],
            'triple depth' => [1, 'aaazzz'],
        ];
    }

    #[DataProvider('provideControlVerbPatterns')]
    public function testRedosAnalyzerRespectsCommit(string $pattern, ReDoSSeverity $expected): void
    {
        $regex = Regex::create()->parse($pattern);
        $visitor = new ReDoSProfileNodeVisitor();
        $regex->accept($visitor);
        $result = $visitor->getResult();

        self::assertSame($expected, $result['severity']);
    }

    public static function provideControlVerbPatterns(): array
    {
        return [
            'commit' => ['/(a+(*COMMIT))+/', ReDoSSeverity::SAFE],
            'prune' => ['/(a+(*PRUNE))+/', ReDoSSeverity::SAFE],
            'skip' => ['/(a+(*SKIP))+/', ReDoSSeverity::SAFE],
        ];
    }

    #[DataProvider('provideRecursiveConditionPatterns')]
    public function testRecursiveConditionValidation(string $pattern): void
    {
        $regex = Regex::create()->parse($pattern);
        $validator = new ValidatorNodeVisitor();

        $regex->accept($validator);

        self::assertTrue(true); // Validation passed without exception.
    }

    public static function provideRecursiveConditionPatterns(): array
    {
        return [
            'explicit group recursion' => ['/(?(R1)a|b)/'],
            'root recursion check' => ['/(?(R)a|b)/'],
        ];
    }
}
