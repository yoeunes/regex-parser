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
use RegexParser\Lexer;

final class LexerClassCoverageTest extends TestCase
{
    public function test_lexer_class_instantiation(): void
    {
        $lexer = new Lexer();
        $this->assertInstanceOf(Lexer::class, $lexer);

        // Test basic tokenization to ensure the class is used
        $tokenStream = $lexer->tokenize('test');
        $this->assertNotNull($tokenStream);

        // Test that we can get tokens from the stream
        $tokens = $tokenStream->getTokens();
        $this->assertIsArray($tokens);
        $this->assertNotEmpty($tokens);
    }
}
