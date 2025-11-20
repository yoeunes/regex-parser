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

namespace RegexParser\Tests\Parser;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Parser;

class ParserErrorTest extends TestCase
{
    public function test_throws_on_too_short_regex(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('No closing delimiter "/" found.');

        $parser = $this->createParser();
        $parser->parse('/a');
    }

    public function test_throws_on_missing_closing_delimiter_non_standard(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('No closing delimiter "#" found.');

        $parser = $this->createParser();
        $parser->parse('#foo');
    }

    public function test_throws_on_missing_closing_delimiter_brace(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('No closing delimiter "}" found.');

        $parser = $this->createParser();
        $parser->parse('{foo');
    }

    public function test_throws_on_unknown_flag(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown modifier "z"');

        $parser = $this->createParser();
        $parser->parse('/abc/z');
    }

    public function test_throws_on_escaped_delimiter_as_last_char(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('No closing delimiter "/" found.');

        $parser = $this->createParser();
        // Le parser voit ceci comme "/foo\/flags", sans délimiteur de fin non échappé.
        $parser->parse('/foo\/');
    }

    public function test_throws_on_quantifying_anchor(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Quantifier "*" cannot be applied to assertion or verb "^" at position 0');

        $parser = $this->createParser();
        $parser->parse('/^*a/');
    }

    public function test_throws_on_quantifying_assertion(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Quantifier "+" cannot be applied to assertion or verb "\A" at position 0');

        $parser = $this->createParser();
        $parser->parse('/\A+a/');
    }

    public function test_throws_on_quantifying_keep_node(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Quantifier "?" cannot be applied to assertion or verb "\K" at position 1');

        $parser = $this->createParser();
        $parser->parse('/a\K?/');
    }

    public function test_throws_on_incomplete_python_group(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid syntax after (?P at position 2');

        $parser = $this->createParser();
        $parser->parse('/(?P)/');
    }

    public function test_throws_on_unsupported_python_backref(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Backreferences (?P=name) are not supported yet.');

        $parser = $this->createParser();
        $parser->parse('/(?P=name)/');
    }

    public function test_throws_on_invalid_token_in_group_name(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unexpected token "|" in group name');

        $parser = $this->createParser();
        $parser->parse('/(?<a|b>)/');
    }

    private function createParser(): Parser
    {
        return new Parser();
    }
}
