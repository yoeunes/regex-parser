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

final class PregReplaceCallbackArray
{
    public function a(): void
    {
        preg_replace_callback_array(
            [
                '/foo' => fn ($m) => '', // Line 9: Missing delimiter -> regex.syntax.delimiter
                '/(a+)+$/' => fn ($m) => '', // Line 10: ReDoS -> regex.redos.critical
                '/valid/' => fn ($m) => '',
            ],
            'subject',
        );
    }
}
