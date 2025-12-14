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

namespace RegexParser\Tests\Bridge\Psalm\Fixtures;

final class PregFunctions
{
    public function run(): void
    {
        preg_match('/foo', 'bar');
        preg_match('/(a+)+$/', 'bar');
        preg_match('/[0-9]+/', 'bar');

        preg_replace_callback_array([
            '/foo' => static fn (array $matches) => $matches[0],
            '/bar/' => static fn (array $matches) => $matches[0],
        ], 'bar');
    }
}
