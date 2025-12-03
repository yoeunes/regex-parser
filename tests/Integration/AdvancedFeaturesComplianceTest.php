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
    public function test_sample_generator_handles_recursion(int $seed, string $expectedSample): void
    {
        $regex = Regex::create()->parse('/a(?R)?z/');
        $generator = new SampleGeneratorNodeVisitor();
        $generator->setSeed($seed);

        $this->assertSame($expectedSample, $regex->accept($generator));
    }

    public static function provideRecursionSeeds(): \Iterator
    {
        yield 'single depth' => [2, 'az'];
        yield 'double depth' => [5, 'aazz'];
        yield 'triple depth' => [1, 'aaazzz'];
    }

    #[DataProvider('provideControlVerbPatterns')]
    public function test_redos_analyzer_respects_commit(string $pattern, ReDoSSeverity $expected): void
    {
        $regex = Regex::create()->parse($pattern);
        $visitor = new ReDoSProfileNodeVisitor();
        $regex->accept($visitor);
        $result = $visitor->getResult();

        $this->assertSame($expected, $result['severity']);
    }

    public static function provideControlVerbPatterns(): \Iterator
    {
        yield 'commit' => ['/(a+(*COMMIT))+/', ReDoSSeverity::SAFE];
        yield 'prune' => ['/(a+(*PRUNE))+/', ReDoSSeverity::SAFE];
        yield 'skip' => ['/(a+(*SKIP))+/', ReDoSSeverity::SAFE];
    }

    #[DataProvider('provideRecursiveConditionPatterns')]
    public function test_recursive_condition_validation(string $pattern): void
    {
        $regex = Regex::create()->parse($pattern);
        $validator = new ValidatorNodeVisitor();

        $regex->accept($validator); // Validation passed without exception.

        $this->assertGreaterThan(0, \strlen($pattern));
    }

    public static function provideRecursiveConditionPatterns(): \Iterator
    {
        yield 'explicit group recursion' => ['/(?(R1)a|b)/'];
        yield 'root recursion check' => ['/(?(R)a|b)/'];
    }
}
