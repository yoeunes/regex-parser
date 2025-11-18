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

namespace RegexParser\Tests;

use PHPUnit\Framework\TestCase;
use RegexParser\Regex;

class RegexTest extends TestCase
{
    public function test_create_and_parse(): void
    {
        $regex = Regex::create();

        $ast = $regex->parse('/abc/');
        $this->assertSame(0, $ast->startPos);
        $this->assertSame(3, $ast->endPos);
    }

    public function test_validate(): void
    {
        $regex = Regex::create();

        $valid = $regex->validate('/abc/');
        $this->assertTrue($valid->isValid);

        $invalid = $regex->validate('/(abc/'); // Unclosed parenthesis
        $this->assertFalse($invalid->isValid);
        $this->assertNotNull($invalid->error);
    }

    public function test_optimize(): void
    {
        $regex = Regex::create();
        // Should optimize [0-9] to \d
        $optimized = $regex->optimize('/[0-9]/');

        // Note: the CompilerNodeVisitor adds the \ before d
        $this->assertSame('/\d/', $optimized);
    }

    public function test_generate(): void
    {
        $regex = Regex::create();
        $sample = $regex->generate('/\d{3}/');
        $this->assertMatchesRegularExpression('/\d{3}/', $sample);
    }
}
