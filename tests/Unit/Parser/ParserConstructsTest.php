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

namespace RegexParser\Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Node\RegexNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\Regex;

/**
 * One case per PCRE construct the parser claims to read.
 *
 * Reading a construct is only half of it: what the parser understood is
 * checked by writing the pattern back out. A construct that is silently
 * dropped, or spelled differently on the way out, shows up here.
 */
final class ParserConstructsTest extends TestCase
{
    #[Test]
    #[DataProvider('provideConstructs')]
    public function test_a_construct_is_read_and_written_back(string $pattern, string $recompiled): void
    {
        $ast = Regex::create()->parse($pattern);

        self::assertInstanceOf(RegexNode::class, $ast);
        $this->assertSame($recompiled, $ast->accept(new CompilerNodeVisitor()));
    }

    #[Test]
    #[DataProvider('provideConstructsPcreAccepts')]
    public function test_what_is_written_back_is_still_a_pattern_pcre_accepts(string $pattern): void
    {
        $recompiled = Regex::create()->parse($pattern)->accept(new CompilerNodeVisitor());

        set_error_handler(static fn (): bool => true);
        $compiles = false !== @preg_match($recompiled, '');
        restore_error_handler();

        $this->assertTrue($compiles, \sprintf('PCRE rejects "%s", recompiled from "%s".', $recompiled, $pattern));
    }

    #[Test]
    public function test_a_recursion_condition_is_written_back_as_a_condition(): void
    {
        // "(?(R)" asks whether the pattern is recursing. Compiling it as a
        // subroutine call, "(?((?R))", gives a pattern PCRE refuses.
        $recompiled = Regex::create()->parse('/(?(R)yes|no)/')->accept(new CompilerNodeVisitor());

        $this->assertSame('/(?(R)yes|no)/', $recompiled);
    }

    /**
     * @return iterable<string, array{pattern: string, recompiled: string}>
     */
    public static function provideConstructs(): iterable
    {
        yield 'python named group single quotes: /(?P\'name\'test)/' => [
            'pattern' => '/(?P\'name\'test)/',
            'recompiled' => '/(?P<name>test)/',
        ];

        yield 'python named group double quotes: /(?P"name"test)/' => [
            'pattern' => '/(?P"name"test)/',
            'recompiled' => '/(?P<name>test)/',
        ];

        yield 'python named group angle brackets: /(?P<name>test)/' => [
            'pattern' => '/(?P<name>test)/',
            'recompiled' => '/(?P<name>test)/',
        ];

        yield 'python subroutine call: /(?P<name>test)(?P>name)/' => [
            'pattern' => '/(?P<name>test)(?P>name)/',
            'recompiled' => '/(?P<name>test)(?P>name)/',
        ];

        yield 'positive lookbehind: /(?<=test)abc/' => [
            'pattern' => '/(?<=test)abc/',
            'recompiled' => '/(?<=test)abc/',
        ];

        yield 'negative lookbehind: /(?<!test)abc/' => [
            'pattern' => '/(?<!test)abc/',
            'recompiled' => '/(?<!test)abc/',
        ];

        yield 'conditional with number: /(test)(?(1)yes|no)/' => [
            'pattern' => '/(test)(?(1)yes|no)/',
            'recompiled' => '/(test)(?(1)yes|no)/',
        ];

        yield 'conditional with named group: /(?<name>test)(?(<name>)yes|no)/' => [
            'pattern' => '/(?<name>test)(?(<name>)yes|no)/',
            'recompiled' => '/(?<name>test)(?(name)yes|no)/',
        ];

        yield 'conditional with lookahead positive: /(?((?=test))yes|no)/' => [
            'pattern' => '/(?((?=test))yes|no)/',
            'recompiled' => '/(?(?=test)yes|no)/',
        ];

        yield 'conditional with lookahead negative: /(?((?!test))yes|no)/' => [
            'pattern' => '/(?((?!test))yes|no)/',
            'recompiled' => '/(?(?!test)yes|no)/',
        ];

        yield 'conditional with lookbehind positive: /(?((?<=test))yes|no)/' => [
            'pattern' => '/(?((?<=test))yes|no)/',
            'recompiled' => '/(?(?<=test)yes|no)/',
        ];

        yield 'conditional with lookbehind negative: /(?((?<!test))yes|no)/' => [
            'pattern' => '/(?((?<!test))yes|no)/',
            'recompiled' => '/(?(?<!test)yes|no)/',
        ];

        yield 'conditional non define resets token stream: /(?(DEFINEX)yes|no)/' => [
            'pattern' => '/(?(DEFINEX)yes|no)/',
            'recompiled' => '/(?(DEFINEX)yes|no)/',
        ];

        yield 'conditional lookaround condition: /(?(?=a)yes|no)/' => [
            'pattern' => '/(?(?=a)yes|no)/',
            'recompiled' => '/(?(?=a)yes|no)/',
        ];

        yield 'conditional without else: /(test)(?(1)yes)/' => [
            'pattern' => '/(test)(?(1)yes)/',
            'recompiled' => '/(test)(?(1)yes)/',
        ];

        yield 'subroutine call by number: /(test)(?1)/' => [
            'pattern' => '/(test)(?1)/',
            'recompiled' => '/(test)(?1)/',
        ];

        yield 'subroutine call relative: /(test)(?-1)/' => [
            'pattern' => '/(test)(?-1)/',
            'recompiled' => '/(test)(?-1)/',
        ];

        yield 'subroutine call named: /(?<name>test)(?&name)/' => [
            'pattern' => '/(?<name>test)(?&name)/',
            'recompiled' => '/(?<name>test)(?&name)/',
        ];

        yield 'atomic group: /(?>test)/' => [
            'pattern' => '/(?>test)/',
            'recompiled' => '/(?>test)/',
        ];

        yield 'non capturing group: /(?:test)/' => [
            'pattern' => '/(?:test)/',
            'recompiled' => '/(?:test)/',
        ];

        yield 'group with flags i: /(?i:test)/' => [
            'pattern' => '/(?i:test)/',
            'recompiled' => '/(?i:test)/',
        ];

        yield 'group with multiple flags: /(?im:test)/' => [
            'pattern' => '/(?im:test)/',
            'recompiled' => '/(?im:test)/',
        ];

        yield 'group with negative flags: /(?-i:test)/' => [
            'pattern' => '/(?-i:test)/',
            'recompiled' => '/(?-i:test)/',
        ];

        yield 'group with mixed flags: /(?i-m:test)/' => [
            'pattern' => '/(?i-m:test)/',
            'recompiled' => '/(?i-m:test)/',
        ];

        yield 'char class with range: /[a-z]/' => [
            'pattern' => '/[a-z]/',
            'recompiled' => '/[a-z]/',
        ];

        yield 'char class with multiple ranges: /[a-zA-Z0-9]/' => [
            'pattern' => '/[a-zA-Z0-9]/',
            'recompiled' => '/[a-zA-Z0-9]/',
        ];

        yield 'negated char class: /[^a-z]/' => [
            'pattern' => '/[^a-z]/',
            'recompiled' => '/[^a-z]/',
        ];

        yield 'posix char class: /[[:alnum:]]/' => [
            'pattern' => '/[[:alnum:]]/',
            'recompiled' => '/[[:alnum:]]/',
        ];

        yield 'negated posix char class: /[[:^alnum:]]/' => [
            'pattern' => '/[[:^alnum:]]/',
            'recompiled' => '/[[:^alnum:]]/',
        ];

        yield 'octal legacy: /\\101/' => [
            'pattern' => '/\\101/',
            'recompiled' => '/\\101/',
        ];

        yield 'octal modern: /\\o{101}/' => [
            'pattern' => '/\\o{101}/',
            'recompiled' => '/\\o{101}/',
        ];

        yield 'unicode escape hex: /\\x41/' => [
            'pattern' => '/\\x41/',
            'recompiled' => '/\\x41/',
        ];

        yield 'unicode escape braces: /\\u{0041}/' => [
            'pattern' => '/\\u{0041}/',
            'recompiled' => '/\\u{0041}/',
        ];

        yield 'pcre verb fail: /(*FAIL)/' => [
            'pattern' => '/(*FAIL)/',
            'recompiled' => '/(*FAIL)/',
        ];

        yield 'pcre verb mark: /(*MARK:test)/' => [
            'pattern' => '/(*MARK:test)/',
            'recompiled' => '/(*MARK:test)/',
        ];

        yield 'keep: /test\\K/' => [
            'pattern' => '/test\\K/',
            'recompiled' => '/test\\K/',
        ];

        yield 'anchors: /^test/' => [
            'pattern' => '/^test/',
            'recompiled' => '/^test/',
        ];

        yield 'anchors: /test$/' => [
            'pattern' => '/test$/',
            'recompiled' => '/test$/',
        ];

        yield 'anchors: /^test$/' => [
            'pattern' => '/^test$/',
            'recompiled' => '/^test$/',
        ];

        yield 'assertions: /\\A/' => [
            'pattern' => '/\\A/',
            'recompiled' => '/\\A/',
        ];

        yield 'assertions: /\\z/' => [
            'pattern' => '/\\z/',
            'recompiled' => '/\\z/',
        ];

        yield 'assertions: /\\Z/' => [
            'pattern' => '/\\Z/',
            'recompiled' => '/\\Z/',
        ];

        yield 'assertions: /\\G/' => [
            'pattern' => '/\\G/',
            'recompiled' => '/\\G/',
        ];

        yield 'assertions: /\\b/' => [
            'pattern' => '/\\b/',
            'recompiled' => '/\\b/',
        ];

        yield 'assertions: /\\B/' => [
            'pattern' => '/\\B/',
            'recompiled' => '/\\B/',
        ];

        yield 'char types: /\\d/' => [
            'pattern' => '/\\d/',
            'recompiled' => '/\\d/',
        ];

        yield 'char types: /\\D/' => [
            'pattern' => '/\\D/',
            'recompiled' => '/\\D/',
        ];

        yield 'char types: /\\s/' => [
            'pattern' => '/\\s/',
            'recompiled' => '/\\s/',
        ];

        yield 'char types: /\\S/' => [
            'pattern' => '/\\S/',
            'recompiled' => '/\\S/',
        ];

        yield 'char types: /\\w/' => [
            'pattern' => '/\\w/',
            'recompiled' => '/\\w/',
        ];

        yield 'char types: /\\W/' => [
            'pattern' => '/\\W/',
            'recompiled' => '/\\W/',
        ];

        yield 'char types: /\\h/' => [
            'pattern' => '/\\h/',
            'recompiled' => '/\\h/',
        ];

        yield 'char types: /\\H/' => [
            'pattern' => '/\\H/',
            'recompiled' => '/\\H/',
        ];

        yield 'char types: /\\v/' => [
            'pattern' => '/\\v/',
            'recompiled' => '/\\v/',
        ];

        yield 'char types: /\\V/' => [
            'pattern' => '/\\V/',
            'recompiled' => '/\\V/',
        ];

        yield 'char types: /\\R/' => [
            'pattern' => '/\\R/',
            'recompiled' => '/\\R/',
        ];

        yield 'backref numbered: /(test)\\1/' => [
            'pattern' => '/(test)\\1/',
            'recompiled' => '/(test)\\1/',
        ];

        yield 'backref named k angle: /(?<name>test)\\k<name>/' => [
            'pattern' => '/(?<name>test)\\k<name>/',
            'recompiled' => '/(?<name>test)\\k<name>/',
        ];

        yield 'backref named k brace: /(?<name>test)\\k{name}/' => [
            'pattern' => '/(?<name>test)\\k{name}/',
            'recompiled' => '/(?<name>test)\\k{name}/',
        ];

        yield 'g reference number: /(test)\\g1/' => [
            'pattern' => '/(test)\\g1/',
            'recompiled' => '/(test)\\g1/',
        ];

        yield 'g reference relative: /(test)\\g-1/' => [
            'pattern' => '/(test)\\g-1/',
            'recompiled' => '/(test)\\g-1/',
        ];

        yield 'g reference angle: /(?<name>test)\\g<name>/' => [
            'pattern' => '/(?<name>test)\\g<name>/',
            'recompiled' => '/(?<name>test)\\g<name>/',
        ];

        yield 'g reference brace: /(?<name>test)\\g{name}/' => [
            'pattern' => '/(?<name>test)\\g{name}/',
            'recompiled' => '/(?<name>test)\\g<name>/',
        ];

        yield 'dot: /./' => [
            'pattern' => '/./',
            'recompiled' => '/./',
        ];

        yield 'quantifiers: /a*/' => [
            'pattern' => '/a*/',
            'recompiled' => '/a*/',
        ];

        yield 'quantifiers: /a+/' => [
            'pattern' => '/a+/',
            'recompiled' => '/a+/',
        ];

        yield 'quantifiers: /a?/' => [
            'pattern' => '/a?/',
            'recompiled' => '/a?/',
        ];

        yield 'quantifiers: /a{2}/' => [
            'pattern' => '/a{2}/',
            'recompiled' => '/a{2}/',
        ];

        yield 'quantifiers: /a{2,}/' => [
            'pattern' => '/a{2,}/',
            'recompiled' => '/a{2,}/',
        ];

        yield 'quantifiers: /a{2,5}/' => [
            'pattern' => '/a{2,5}/',
            'recompiled' => '/a{2,5}/',
        ];

        yield 'quantifiers: /a*?/' => [
            'pattern' => '/a*?/',
            'recompiled' => '/a*?/',
        ];

        yield 'quantifiers: /a+?/' => [
            'pattern' => '/a+?/',
            'recompiled' => '/a+?/',
        ];

        yield 'quantifiers: /a??/' => [
            'pattern' => '/a??/',
            'recompiled' => '/a??/',
        ];

        yield 'quantifiers: /a{2,5}?/' => [
            'pattern' => '/a{2,5}?/',
            'recompiled' => '/a{2,5}?/',
        ];

        yield 'quantifiers: /a*+/' => [
            'pattern' => '/a*+/',
            'recompiled' => '/a*+/',
        ];

        yield 'quantifiers: /a++/' => [
            'pattern' => '/a++/',
            'recompiled' => '/a++/',
        ];

        yield 'quantifiers: /a?+/' => [
            'pattern' => '/a?+/',
            'recompiled' => '/a?+/',
        ];

        yield 'quantifiers: /a{2,5}+/' => [
            'pattern' => '/a{2,5}+/',
            'recompiled' => '/a{2,5}+/',
        ];

        yield 'comment: /(?#this is a comment)test/' => [
            'pattern' => '/(?#this is a comment)test/',
            'recompiled' => '/(?#this is a comment)test/',
        ];

        yield 'alternation: /foo|bar|baz/' => [
            'pattern' => '/foo|bar|baz/',
            'recompiled' => '/foo|bar|baz/',
        ];

        yield 'empty alternation branches: /foo||bar/' => [
            'pattern' => '/foo||bar/',
            'recompiled' => '/foo||bar/',
        ];

        yield 'complex nested: /(?:(?<name>test)|(?P<other>foo)){2,5}/' => [
            'pattern' => '/(?:(?<name>test)|(?P<other>foo)){2,5}/',
            'recompiled' => '/(?:(?<name>test)|(?P<other>foo)){2,5}/',
        ];

        yield 'various delimiters: /test/' => [
            'pattern' => '/test/',
            'recompiled' => '/test/',
        ];

        yield 'various delimiters: #test#' => [
            'pattern' => '#test#',
            'recompiled' => '#test#',
        ];

        yield 'various delimiters: ~test~' => [
            'pattern' => '~test~',
            'recompiled' => '~test~',
        ];

        yield 'various delimiters: @test@' => [
            'pattern' => '@test@',
            'recompiled' => '@test@',
        ];

        yield 'various delimiters: !test!' => [
            'pattern' => '!test!',
            'recompiled' => '!test!',
        ];

        yield 'various delimiters: %test%' => [
            'pattern' => '%test%',
            'recompiled' => '%test%',
        ];

        yield 'various delimiters: {test}' => [
            'pattern' => '{test}',
            'recompiled' => '{test}',
        ];

        yield 'extract pattern and flags: /test/i' => [
            'pattern' => '/test/i',
            'recompiled' => '/test/i',
        ];

        yield 'extract pattern and flags: /test/im' => [
            'pattern' => '/test/im',
            'recompiled' => '/test/im',
        ];

        yield 'extract pattern and flags: /test/ims' => [
            'pattern' => '/test/ims',
            'recompiled' => '/test/ims',
        ];

        yield 'extract pattern and flags: /test/imsx' => [
            'pattern' => '/test/imsx',
            'recompiled' => '/test/imsx',
        ];

        yield 'extract pattern and flags: /test/imsxu' => [
            'pattern' => '/test/imsxu',
            'recompiled' => '/test/imsxu',
        ];

        yield 'extract pattern and flags: /test/imsxuD' => [
            'pattern' => '/test/imsxuD',
            'recompiled' => '/test/imsxuD',
        ];

        yield 'extract pattern and flags: /test/imsxuDU' => [
            'pattern' => '/test/imsxuDU',
            'recompiled' => '/test/imsxuDU',
        ];

        yield 'extract pattern and flags: /test/imsxuDUA' => [
            'pattern' => '/test/imsxuDUA',
            'recompiled' => '/test/imsxuDUA',
        ];

        yield 'extract pattern and flags: /test/imsxuDUAJ' => [
            'pattern' => '/test/imsxuDUAJ',
            'recompiled' => '/test/imsxuDUAJ',
        ];

        yield 'char class quote mode empty returns literal: /[\\Q\\E]/' => [
            'pattern' => '/[\\Q\\E]/',
            'recompiled' => '/[]/',
        ];

    }

    /**
     * The three patterns left out are ones PCRE itself refuses; the parser
     * reads them on purpose, so there is nothing to compile back.
     *
     * @return iterable<string, array{pattern: string}>
     */
    public static function provideConstructsPcreAccepts(): iterable
    {
        yield 'python named group single quotes: /(?P\'name\'test)/' => ['pattern' => '/(?P\'name\'test)/'];
        yield 'python named group double quotes: /(?P"name"test)/' => ['pattern' => '/(?P"name"test)/'];
        yield 'python named group angle brackets: /(?P<name>test)/' => ['pattern' => '/(?P<name>test)/'];
        yield 'python subroutine call: /(?P<name>test)(?P>name)/' => ['pattern' => '/(?P<name>test)(?P>name)/'];
        yield 'positive lookbehind: /(?<=test)abc/' => ['pattern' => '/(?<=test)abc/'];
        yield 'negative lookbehind: /(?<!test)abc/' => ['pattern' => '/(?<!test)abc/'];
        yield 'conditional with number: /(test)(?(1)yes|no)/' => ['pattern' => '/(test)(?(1)yes|no)/'];
        yield 'conditional with named group: /(?<name>test)(?(<name>)yes|no)/' => ['pattern' => '/(?<name>test)(?(<name>)yes|no)/'];
        yield 'conditional with lookahead positive: /(?((?=test))yes|no)/' => ['pattern' => '/(?((?=test))yes|no)/'];
        yield 'conditional with lookahead negative: /(?((?!test))yes|no)/' => ['pattern' => '/(?((?!test))yes|no)/'];
        yield 'conditional with lookbehind positive: /(?((?<=test))yes|no)/' => ['pattern' => '/(?((?<=test))yes|no)/'];
        yield 'conditional with lookbehind negative: /(?((?<!test))yes|no)/' => ['pattern' => '/(?((?<!test))yes|no)/'];
        yield 'conditional lookaround condition: /(?(?=a)yes|no)/' => ['pattern' => '/(?(?=a)yes|no)/'];
        yield 'conditional without else: /(test)(?(1)yes)/' => ['pattern' => '/(test)(?(1)yes)/'];
        yield 'subroutine call by number: /(test)(?1)/' => ['pattern' => '/(test)(?1)/'];
        yield 'subroutine call relative: /(test)(?-1)/' => ['pattern' => '/(test)(?-1)/'];
        yield 'subroutine call named: /(?<name>test)(?&name)/' => ['pattern' => '/(?<name>test)(?&name)/'];
        yield 'atomic group: /(?>test)/' => ['pattern' => '/(?>test)/'];
        yield 'non capturing group: /(?:test)/' => ['pattern' => '/(?:test)/'];
        yield 'group with flags i: /(?i:test)/' => ['pattern' => '/(?i:test)/'];
        yield 'group with multiple flags: /(?im:test)/' => ['pattern' => '/(?im:test)/'];
        yield 'group with negative flags: /(?-i:test)/' => ['pattern' => '/(?-i:test)/'];
        yield 'group with mixed flags: /(?i-m:test)/' => ['pattern' => '/(?i-m:test)/'];
        yield 'char class with range: /[a-z]/' => ['pattern' => '/[a-z]/'];
        yield 'char class with multiple ranges: /[a-zA-Z0-9]/' => ['pattern' => '/[a-zA-Z0-9]/'];
        yield 'negated char class: /[^a-z]/' => ['pattern' => '/[^a-z]/'];
        yield 'posix char class: /[[:alnum:]]/' => ['pattern' => '/[[:alnum:]]/'];
        yield 'negated posix char class: /[[:^alnum:]]/' => ['pattern' => '/[[:^alnum:]]/'];
        yield 'octal legacy: /\\101/' => ['pattern' => '/\\101/'];
        yield 'octal modern: /\\o{101}/' => ['pattern' => '/\\o{101}/'];
        yield 'unicode escape hex: /\\x41/' => ['pattern' => '/\\x41/'];
        yield 'pcre verb fail: /(*FAIL)/' => ['pattern' => '/(*FAIL)/'];
        yield 'pcre verb mark: /(*MARK:test)/' => ['pattern' => '/(*MARK:test)/'];
        yield 'keep: /test\\K/' => ['pattern' => '/test\\K/'];
        yield 'anchors: /^test/' => ['pattern' => '/^test/'];
        yield 'anchors: /test$/' => ['pattern' => '/test$/'];
        yield 'anchors: /^test$/' => ['pattern' => '/^test$/'];
        yield 'assertions: /\\A/' => ['pattern' => '/\\A/'];
        yield 'assertions: /\\z/' => ['pattern' => '/\\z/'];
        yield 'assertions: /\\Z/' => ['pattern' => '/\\Z/'];
        yield 'assertions: /\\G/' => ['pattern' => '/\\G/'];
        yield 'assertions: /\\b/' => ['pattern' => '/\\b/'];
        yield 'assertions: /\\B/' => ['pattern' => '/\\B/'];
        yield 'char types: /\\d/' => ['pattern' => '/\\d/'];
        yield 'char types: /\\D/' => ['pattern' => '/\\D/'];
        yield 'char types: /\\s/' => ['pattern' => '/\\s/'];
        yield 'char types: /\\S/' => ['pattern' => '/\\S/'];
        yield 'char types: /\\w/' => ['pattern' => '/\\w/'];
        yield 'char types: /\\W/' => ['pattern' => '/\\W/'];
        yield 'char types: /\\h/' => ['pattern' => '/\\h/'];
        yield 'char types: /\\H/' => ['pattern' => '/\\H/'];
        yield 'char types: /\\v/' => ['pattern' => '/\\v/'];
        yield 'char types: /\\V/' => ['pattern' => '/\\V/'];
        yield 'char types: /\\R/' => ['pattern' => '/\\R/'];
        yield 'backref numbered: /(test)\\1/' => ['pattern' => '/(test)\\1/'];
        yield 'backref named k angle: /(?<name>test)\\k<name>/' => ['pattern' => '/(?<name>test)\\k<name>/'];
        yield 'backref named k brace: /(?<name>test)\\k{name}/' => ['pattern' => '/(?<name>test)\\k{name}/'];
        yield 'g reference number: /(test)\\g1/' => ['pattern' => '/(test)\\g1/'];
        yield 'g reference relative: /(test)\\g-1/' => ['pattern' => '/(test)\\g-1/'];
        yield 'g reference angle: /(?<name>test)\\g<name>/' => ['pattern' => '/(?<name>test)\\g<name>/'];
        yield 'g reference brace: /(?<name>test)\\g{name}/' => ['pattern' => '/(?<name>test)\\g{name}/'];
        yield 'dot: /./' => ['pattern' => '/./'];
        yield 'quantifiers: /a*/' => ['pattern' => '/a*/'];
        yield 'quantifiers: /a+/' => ['pattern' => '/a+/'];
        yield 'quantifiers: /a?/' => ['pattern' => '/a?/'];
        yield 'quantifiers: /a{2}/' => ['pattern' => '/a{2}/'];
        yield 'quantifiers: /a{2,}/' => ['pattern' => '/a{2,}/'];
        yield 'quantifiers: /a{2,5}/' => ['pattern' => '/a{2,5}/'];
        yield 'quantifiers: /a*?/' => ['pattern' => '/a*?/'];
        yield 'quantifiers: /a+?/' => ['pattern' => '/a+?/'];
        yield 'quantifiers: /a??/' => ['pattern' => '/a??/'];
        yield 'quantifiers: /a{2,5}?/' => ['pattern' => '/a{2,5}?/'];
        yield 'quantifiers: /a*+/' => ['pattern' => '/a*+/'];
        yield 'quantifiers: /a++/' => ['pattern' => '/a++/'];
        yield 'quantifiers: /a?+/' => ['pattern' => '/a?+/'];
        yield 'quantifiers: /a{2,5}+/' => ['pattern' => '/a{2,5}+/'];
        yield 'comment: /(?#this is a comment)test/' => ['pattern' => '/(?#this is a comment)test/'];
        yield 'alternation: /foo|bar|baz/' => ['pattern' => '/foo|bar|baz/'];
        yield 'empty alternation branches: /foo||bar/' => ['pattern' => '/foo||bar/'];
        yield 'complex nested: /(?:(?<name>test)|(?P<other>foo)){2,5}/' => ['pattern' => '/(?:(?<name>test)|(?P<other>foo)){2,5}/'];
        yield 'various delimiters: /test/' => ['pattern' => '/test/'];
        yield 'various delimiters: #test#' => ['pattern' => '#test#'];
        yield 'various delimiters: ~test~' => ['pattern' => '~test~'];
        yield 'various delimiters: @test@' => ['pattern' => '@test@'];
        yield 'various delimiters: !test!' => ['pattern' => '!test!'];
        yield 'various delimiters: %test%' => ['pattern' => '%test%'];
        yield 'various delimiters: {test}' => ['pattern' => '{test}'];
        yield 'extract pattern and flags: /test/i' => ['pattern' => '/test/i'];
        yield 'extract pattern and flags: /test/im' => ['pattern' => '/test/im'];
        yield 'extract pattern and flags: /test/ims' => ['pattern' => '/test/ims'];
        yield 'extract pattern and flags: /test/imsx' => ['pattern' => '/test/imsx'];
        yield 'extract pattern and flags: /test/imsxu' => ['pattern' => '/test/imsxu'];
        yield 'extract pattern and flags: /test/imsxuD' => ['pattern' => '/test/imsxuD'];
        yield 'extract pattern and flags: /test/imsxuDU' => ['pattern' => '/test/imsxuDU'];
        yield 'extract pattern and flags: /test/imsxuDUA' => ['pattern' => '/test/imsxuDUA'];
        yield 'extract pattern and flags: /test/imsxuDUAJ' => ['pattern' => '/test/imsxuDUAJ'];
    }
}
