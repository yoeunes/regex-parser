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
    private string $queueKey;

    private string $errorMsgQueueKey;

    protected function setUp(): void
    {
        $this->queueKey = '__regex_preg_match_queue_'.spl_object_id($this);
        $this->errorMsgQueueKey = '__regex_preg_last_error_msg_queue_'.spl_object_id($this);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS[$this->queueKey],
            $GLOBALS[$this->errorMsgQueueKey],
        );
    }

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
        $method->invoke($lexer, '/[a-/');
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
