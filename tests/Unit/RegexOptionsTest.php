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
use RegexParser\Exception\InvalidRegexOptionException;
use RegexParser\Regex;

final class RegexOptionsTest extends TestCase
{
    public function testCreateWithUnknownOptionThrows(): void
    {
        $this->expectException(InvalidRegexOptionException::class);
        Regex::create(['unknown_option' => true]);
    }

    public function testCreateWithValidOptions(): void
    {
        $regex = Regex::create([
            'max_pattern_length' => 50_000,
            'cache' => new NullCache(),
            'redos_ignored_patterns' => ['/safe/'],
        ]);
        $this->assertSame(50_000, (new \ReflectionProperty($regex, 'maxPatternLength'))->getValue($regex));
    }
}
