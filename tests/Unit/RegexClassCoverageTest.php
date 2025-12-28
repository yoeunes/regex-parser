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

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RegexParser\Cache\NullCache;
use RegexParser\Regex;

final class RegexClassCoverageTest extends TestCase
{
    public function test_regex_class_instantiation(): void
    {
        $regex = Regex::create();
        $this->assertInstanceOf(Regex::class, $regex);
    }

    public function test_regex_can_be_created_with_options(): void
    {
        $regex = Regex::create([
            'max_pattern_length' => 1000,
            'max_lookbehind_length' => 100,
            'cache' => new NullCache(),
            'redos_ignored_patterns' => [],
            'runtime_pcre_validation' => false,
            'max_recursion_depth' => 100,
            'php_version' => 80200,
        ]);
        $this->assertInstanceOf(Regex::class, $regex);
    }

    public function test_regex_basic_functionality(): void
    {
        $regex = Regex::create();

        // Test basic parsing
        $ast = $regex->parse('/test/');
        $this->assertNotNull($ast);

        // Test validation
        $validation = $regex->validate('/test/');
        $this->assertTrue($validation->isValid);

        // Test explanation
        $explanation = $regex->explain('/test/');
        $this->assertIsString($explanation);
        $this->assertNotEmpty($explanation);
    }
}
