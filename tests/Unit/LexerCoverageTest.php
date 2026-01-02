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
use RegexParser\Exception\LexerException;
use RegexParser\Lexer;

final class LexerCoverageTest extends TestCase
{
    public function test_match_at_position_with_invalid_regex_throws(): void
    {
        $lexer = new Lexer();
        $ref = new \ReflectionClass($lexer);

        $pattern = $ref->getProperty('pattern');
        $pattern->setValue($lexer, 'abc');
        $position = $ref->getProperty('position');
        $position->setValue($lexer, 0);

        $method = $ref->getMethod('matchAtPosition');

        $this->expectException(LexerException::class);
        set_error_handler(static fn (int $errno): bool => \E_WARNING === $errno);

        try {
            $method->invoke($lexer, '/[a-/');
        } finally {
            restore_error_handler();
        }
    }

    public function test_create_token_without_matching_map_throws(): void
    {
        $lexer = new Lexer();
        $ref = new \ReflectionClass($lexer);

        $pattern = $ref->getProperty('pattern');
        $pattern->setValue($lexer, 'abc');

        $method = $ref->getMethod('createToken');

        $this->expectException(LexerException::class);
        $method->invoke($lexer, ['T_UNKNOWN'], [], 'a', 0, []);
    }
}
