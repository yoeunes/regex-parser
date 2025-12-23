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

namespace RegexParser\Tests\Integration\Bridge\PHPStan\Fixtures;

final class MyClass
{
    public function a(): void
    {
        // Syntax Errors
        preg_match('/foo', 'bar'); // Line 10: Missing delimiter -> regex.syntax.delimiter
        preg_match('/a{2,1}/', 'bar'); // Line 11: Invalid quantifier -> regex.syntax.invalid
        preg_match('/(a+)+$/', 'bar'); // Line 12: ReDoS -> regex.redos.critical
        preg_match('/a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*a*b/', 'bar'); // Line 13: ReDoS -> regex.redos.high

        // Valid
        preg_match('/a/i', 'bar');
        preg_match('/[0-9]+/', 'bar'); // Line 14: Optimization suggestion -> regex.optimization
        preg_split('/a/', 'bar');
        preg_grep('/a/', ['bar']);
        preg_filter('/a/', 'b', ['bar']);

        // Dynamic / un-analyzable
        $pattern = '/foo'.random_int(1, 10);
        preg_match($pattern, 'bar'); // Should be ignored
    }
}
