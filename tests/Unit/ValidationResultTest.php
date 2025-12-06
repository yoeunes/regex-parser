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
use RegexParser\ValidationResult;

final class ValidationResultTest extends TestCase
{
    public function test_accessors_mirror_properties(): void
    {
        $result = new ValidationResult(false, 'error', 123);

        self::assertFalse($result->isValid);
        self::assertSame('error', $result->error);
        self::assertSame(123, $result->complexityScore);

        self::assertFalse($result->isValid());
        self::assertSame('error', $result->getErrorMessage());
        self::assertSame(123, $result->getComplexityScore());
    }
}
