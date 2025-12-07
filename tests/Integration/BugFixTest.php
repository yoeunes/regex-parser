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

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\RangeNode;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;

final class BugFixTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    public function test_parser_handles_hyphen_as_range_end(): void
    {
        // [a-] should be parsed as LiteralNode('a'), LiteralNode('-')
        $ast = $this->regexService->parse('/[a-]/');
        $charClass = $ast->pattern;

        $this->assertInstanceOf(CharClassNode::class, $charClass);
        $this->assertInstanceOf(AlternationNode::class, $charClass->expression);
        $this->assertCount(2, $charClass->expression->alternatives);
        $this->assertInstanceOf(LiteralNode::class, $charClass->expression->alternatives[0]);
        $this->assertSame('a', $charClass->expression->alternatives[0]->value);
        $this->assertInstanceOf(LiteralNode::class, $charClass->expression->alternatives[1]);
        $this->assertSame('-', $charClass->expression->alternatives[1]->value);
    }

    public function test_parser_handles_hyphen_range(): void
    {
        // [a-z] should be parsed as Range(a, z)
        $ast = $this->regexService->parse('/[a-z]/');
        $charClass = $ast->pattern;

        $this->assertInstanceOf(CharClassNode::class, $charClass);
        $this->assertInstanceOf(RangeNode::class, $charClass->expression);
        $range = $charClass->expression;
        $this->assertInstanceOf(LiteralNode::class, $range->start);
        $this->assertInstanceOf(LiteralNode::class, $range->end);
        $this->assertSame('a', $range->start->value);
        $this->assertSame('z', $range->end->value);
    }

    public function test_re_do_s_analyzer_detects_dot_overlap(): void
    {
        // (a|.)* should be CRITICAL
        $analysis = $this->regexService->analyzeReDoS('/(a|.)*/');
        $this->assertSame(ReDoSSeverity::CRITICAL, $analysis->severity);
    }

    public function test_re_do_s_analyzer_detects_char_class_overlap(): void
    {
        // ([a-z]|[0-9])* -> disjoint branches, should not be critical
        $analysis = $this->regexService->analyzeReDoS('/([a-z]|[0-9])*/');
        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotSame(ReDoSSeverity::HIGH, $analysis->severity);

        // ([a-z]|[a-f])* -> Critical (overlap)
        // With overlap detection, this should remain critical.
        // Given the request to "fix bugs", false positive is acceptable for v1.0 safety.

        $analysis = $this->regexService->analyzeReDoS('/([a-z]|[a-f])*/');
        $this->assertSame(ReDoSSeverity::CRITICAL, $analysis->severity);
    }
}
