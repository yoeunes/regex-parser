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

namespace RegexParser\Tests\Unit\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\TestCaseGeneratorNodeVisitor;
use RegexParser\Regex;

final class TestCaseGeneratorNodeVisitorTest extends TestCase
{
    private TestCaseGeneratorNodeVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new TestCaseGeneratorNodeVisitor();
    }

    public function test_literal(): void
    {
        $ast = Regex::create()->parse('/abc/');
        $cases = $ast->accept($this->visitor);

        $this->assertContains('abc', $cases['matching']);
        $this->assertNotEmpty($cases['non_matching']);
    }

    public function test_quantifier(): void
    {
        $ast = Regex::create()->parse('/a+/');
        $cases = $ast->accept($this->visitor);

        $this->assertContains('a', $cases['matching']);
        $this->assertContains('aa', $cases['matching']);
        $this->assertContains('', $cases['non_matching']); // Too few
    }

    public function test_alternation(): void
    {
        $ast = Regex::create()->parse('/(a|b)/');
        $cases = $ast->accept($this->visitor);

        $this->assertContains('a', $cases['matching']);
        $this->assertNotEmpty($cases['non_matching']);
    }

    public function test_char_class(): void
    {
        $ast = Regex::create()->parse('/[abc]/');
        $cases = $ast->accept($this->visitor);

        $this->assertNotEmpty($cases['matching']);
        $this->assertContains('!', $cases['non_matching']);
    }

    public function test_dot(): void
    {
        $ast = Regex::create()->parse('/./');
        $cases = $ast->accept($this->visitor);

        $this->assertContains('a', $cases['matching']);
        $this->assertContains("\n", $cases['non_matching']);
    }
}
