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

namespace RegexParser\Tests\Unit\Automata;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Automata\Options\MatchMode;
use RegexParser\Automata\Options\SolverOptions;
use RegexParser\Automata\Solver\RegexSolver;
use RegexParser\Automata\Unicode\CodePointHelper;
use RegexParser\Exception\LexerException;

final class UnicodeSupportTest extends TestCase
{
    #[Test]
    public function test_emoji_range_intersection(): void
    {
        $solver = new RegexSolver();
        $options = $this->fullMatchOptions();

        $result = $solver->intersection('/[🥵-🥶]/u', '/[🥳-🥶]/u', $options);

        $this->assertFalse($result->isEmpty);
        $this->assertNotNull($result->example);
        $this->assertMatchesRegularExpression('/[🥵-🥶]/u', $result->example ?? '');
        $this->assertMatchesRegularExpression('/[🥳-🥶]/u', $result->example ?? '');
    }

    #[Test]
    public function test_dot_matches_single_code_point_in_unicode(): void
    {
        if (!\function_exists('mb_strlen')) {
            $this->markTestSkipped('mbstring is required for unicode length assertions.');
        }

        $solver = new RegexSolver();
        $options = $this->fullMatchOptions();

        $result = $solver->intersection('/./u', '/🙂/u', $options);

        $this->assertFalse($result->isEmpty);
        $this->assertNotNull($result->example);
        $this->assertSame(1, \mb_strlen($result->example ?? '', 'UTF-8'));
        $this->assertMatchesRegularExpression('/./u', $result->example ?? '');
    }

    #[Test]
    public function test_unicode_word_class_matches_arabic(): void
    {
        $solver = new RegexSolver();
        $options = $this->fullMatchOptions();

        $result = $solver->intersection('/\\w+/u', '/مرحبا/u', $options);

        $this->assertFalse($result->isEmpty);
        $this->assertSame('مرحبا', $result->example);
    }

    #[Test]
    public function test_unicode_boundary_code_point(): void
    {
        $solver = new RegexSolver();
        $options = $this->fullMatchOptions();

        $boundaryChar = CodePointHelper::toString(0x10FFFF);
        $this->assertNotNull($boundaryChar);

        $result = $solver->intersection('/\\x{10FFFF}/u', '/./u', $options);

        $this->assertFalse($result->isEmpty);
        $this->assertSame($boundaryChar, $result->example);
    }

    #[Test]
    public function test_invalid_utf8_literal_is_rejected(): void
    {
        $solver = new RegexSolver();
        $options = $this->fullMatchOptions();

        $this->expectException(LexerException::class);
        $solver->intersection("/\xFF/u", '/./u', $options);
    }

    private function fullMatchOptions(): SolverOptions
    {
        return new SolverOptions(matchMode: MatchMode::FULL);
    }
}
