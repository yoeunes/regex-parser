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
use RegexParser\Cache\FilesystemCache;
use RegexParser\Cache\NullCache;
use RegexParser\Exception\InvalidRegexOptionException;
use RegexParser\Regex;
use RegexParser\RegexOptions;

final class RegexOptionsTest extends TestCase
{
    public function test_create_with_unknown_option_throws(): void
    {
        $this->expectException(InvalidRegexOptionException::class);
        Regex::create(['unknown_option' => true]);
    }

    public function test_create_with_valid_options(): void
    {
        $regex = Regex::create([
            'max_pattern_length' => 50_000,
            'max_lookbehind_length' => 512,
            'cache' => new NullCache(),
            'redos_ignored_patterns' => ['/safe/'],
        ]);
        $this->assertSame(50_000, (new \ReflectionProperty($regex, 'maxPatternLength'))->getValue($regex));
        $this->assertSame(512, (new \ReflectionProperty($regex, 'maxLookbehindLength'))->getValue($regex));
    }
}
