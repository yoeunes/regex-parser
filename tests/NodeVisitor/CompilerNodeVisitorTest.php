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

namespace RegexParser\Tests\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\Regex;

final class CompilerNodeVisitorTest extends TestCase
{
    public function test_compile_simple(): void
    {
        $this->assertSame('/foo/', $this->compile('/foo/'));
    }

    public function test_compile_group_and_alternation(): void
    {
        $this->assertSame('/(foo|bar)?/', $this->compile('/(foo|bar)?/'));
    }

    public function test_compile_precedence(): void
    {
        $this->assertSame('/ab*c/', $this->compile('/ab*c/'));
    }

    public function test_compile_escaped(): void
    {
        // The compiler must re-escape special characters
        $this->assertSame('/a\*c/', $this->compile('/a\*c/'));
    }

    public function test_compile_new_nodes_and_flags(): void
    {
        $regex = '/^.\d\S(foo|bar)+$/imsU';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function test_compile_quantified_sequence(): void
    {
        // This test ensures a *capturing* group remains a *capturing* group
        $this->assertSame('/(abc)+/', $this->compile('/(abc)+/'));
    }

    public function test_compile_char_class(): void
    {
        // Handles negation, ranges, char types, and literals (like '-')
        $regex = '/[a-z\d\-]/';
        $this->assertSame($regex, $this->compile($regex));

        $regex = '/[^a-z]/';
        $this->assertSame($regex, $this->compile($regex));

        // Ensures class meta-characters are escaped
        $regex = '/[]\^-]/'; // "]", "\", "^", "-"
        // The parser sees "]", "\", "^", "-" as literals because of their position.
        // The compiler should only escape the backslash.
        $this->assertSame('/[\]\^\-]/', $this->compile($regex));
    }

    // Add new tests for new features
    public function test_compile_new_features(): void
    {
        $regex = '#(?<name>foo)+?|(?!=bar)#i';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function test_compile_assertion(): void
    {
        $regex = '/\Afoo\b/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function test_compile_unicode_prop(): void
    {
        $regex = '/\pL\p{^L}\pL/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function test_compile_octal(): void
    {
        $regex = '/\o{777}/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function test_compile_octal_legacy(): void
    {
        $regex = '/\077/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function test_compile_named_backref(): void
    {
        $regex = '/\k<name>/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function test_compile_comment(): void
    {
        $regex = '/(?#test)/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function test_compile_conditional(): void
    {
        $regex = '/(?(1)a|b)/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function test_compile_inline_flags(): void
    {
        $regex = '/(?i:foo)/';
        $this->assertSame($regex, $this->compile($regex));
    }

    public function test_compile_subroutines(): void
    {
        $this->assertSame('/(?R)/', $this->compile('/(?R)/'));
        $this->assertSame('/(?1)/', $this->compile('/(?1)/'));
        $this->assertSame('/(?-1)/', $this->compile('/(?-1)/'));
        $this->assertSame('/(?&name)/', $this->compile('/(?&name)/'));
        $this->assertSame('/(?P>name)/', $this->compile('/(?P>name)/'));
    }

    public function test_compiler_escapes_control_characters(): void
    {
        // Test that control characters are properly escaped in output
        $this->assertSame('/[\t\n\r\x07]/', $this->compile('/[\t\n\r\x07]/'));
    }

    public function test_optimizer_preserves_escaped_pipe(): void
    {
        // Test that escaped pipe is preserved outside char class
        $this->assertSame('/foo\|bar/', $this->compile('/foo\|bar/'));

        // Test that pipe in char class is handled correctly (not special there)
        $this->assertSame('/[|]/', $this->compile('/[|]/'));
    }

    public function test_compile_pretty_mode(): void
    {
        $pattern = "/\n# comment\nfoo|bar/x";
        $compiled = $this->compilePretty($pattern);
        $this->assertStringContainsString("\n", $compiled);
        $this->assertStringContainsString('# comment', $compiled);
    }

    private function compile(string $pattern): string
    {
        $regex = Regex::create();
        $ast = $regex->parse($pattern);
        $visitor = new CompilerNodeVisitor();

        return $ast->accept($visitor);
    }

    private function compilePretty(string $pattern): string
    {
        $regex = Regex::create();
        $ast = $regex->parse($pattern);
        $visitor = new CompilerNodeVisitor(true);

        return $ast->accept($visitor);
    }
}
