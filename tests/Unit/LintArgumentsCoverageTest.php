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
use RegexParser\Lint\Command\LintArguments;

final class LintArgumentsCoverageTest extends TestCase
{
    public function test_from_defaults_normalizes_min_savings_and_jobs(): void
    {
        $arguments = LintArguments::fromDefaults([
            'paths' => ['src'],
            'exclude' => [],
            'minSavings' => ['invalid'],
            'jobs' => ['invalid'],
        ]);

        $this->assertSame(1, $arguments->minSavings);
        $this->assertSame(-1, $arguments->jobs);
    }
}
