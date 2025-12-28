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

namespace RegexParser\NodeVisitor;

if (!\function_exists(__NAMESPACE__.'\\str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        $queue = $GLOBALS['__nodevisitor_str_starts_with_queue'] ?? [];
        if (\is_array($queue) && [] !== $queue) {
            $next = array_shift($queue);
            $GLOBALS['__nodevisitor_str_starts_with_queue'] = $queue;

            return (bool) $next;
        }

        return \str_starts_with($haystack, $needle);
    }
}

if (!\function_exists(__NAMESPACE__.'\\str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        $queue = $GLOBALS['__nodevisitor_str_ends_with_queue'] ?? [];
        if (\is_array($queue) && [] !== $queue) {
            $next = array_shift($queue);
            $GLOBALS['__nodevisitor_str_ends_with_queue'] = $queue;

            return (bool) $next;
        }

        return \str_ends_with($haystack, $needle);
    }
}

namespace RegexParser\Tests\Unit\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\LimitMatchNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\SequenceNode;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;

final class OptimizerNodeVisitorCoverageTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__nodevisitor_str_starts_with_queue'], $GLOBALS['__nodevisitor_str_ends_with_queue']);
    }

    public function test_sequence_flattens_nested_sequences(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $inner = new SequenceNode([new LiteralNode('a', 0, 1), new LiteralNode('b', 1, 2)], 0, 2);
        $outer = new SequenceNode([$inner], 0, 2);

        $result = $outer->accept($optimizer);

        $this->assertInstanceOf(LiteralNode::class, $result);
        $this->assertSame('ab', $result->value);
    }

    public function test_sequence_with_empty_literal_returns_empty_literal(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $sequence = new SequenceNode([new LiteralNode('', 0, 0)], 0, 0);

        $result = $sequence->accept($optimizer);

        $this->assertInstanceOf(LiteralNode::class, $result);
        $this->assertSame('', $result->value);
    }

    public function test_sequence_flattens_nested_sequences_with_non_literal_children(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $inner = new SequenceNode([new CharTypeNode('d', 0, 0), new LiteralNode('a', 0, 0)], 0, 0);
        $outer = new SequenceNode([$inner, new LiteralNode('b', 0, 0)], 0, 0);

        $result = $outer->accept($optimizer);

        $this->assertInstanceOf(SequenceNode::class, $result);
        $this->assertCount(2, $result->children);
        $this->assertInstanceOf(CharTypeNode::class, $result->children[0]);
        $this->assertInstanceOf(LiteralNode::class, $result->children[1]);
        $this->assertSame('ab', $result->children[1]->value);
    }

    public function test_conditional_rebuilds_when_child_optimized(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $condition = new SequenceNode([new LiteralNode('', 0, 0)], 0, 0);
        $node = new ConditionalNode($condition, new LiteralNode('a', 0, 0), new LiteralNode('b', 0, 0), 0, 0);

        $result = $node->accept($optimizer);

        $this->assertInstanceOf(ConditionalNode::class, $result);
        $this->assertNotSame($node, $result);
    }

    public function test_define_and_limit_match_are_visited(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        $define = new DefineNode(new LiteralNode('a', 0, 0), 0, 0);
        $defineResult = $define->accept($optimizer);
        $this->assertInstanceOf(DefineNode::class, $defineResult);
        $this->assertNotSame($define, $defineResult);

        $limit = new LimitMatchNode(100, 0, 0);
        $this->assertSame($limit, $limit->accept($optimizer));
    }

    public function test_normalize_char_class_parts_reverses_ranges_and_updates_scalar(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $parts = [
            new LiteralNode('b', 0, 1),
            new RangeNode(new LiteralNode('d', 1, 2), new LiteralNode('a', 2, 3), 1, 3),
        ];

        $result = $this->invokePrivate($optimizer, 'normalizeCharClassParts', [$parts]);
        $this->assertIsArray($result);
        /* @var array{0: array<\RegexParser\Node\NodeInterface>, 1: bool} $result */
        [$normalized, $changed] = $result;

        $this->assertTrue($changed);
        $this->assertNotEmpty($normalized);
    }

    public function test_visit_alternation_deduplicates_after_change(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $alts = new AlternationNode([
            new SequenceNode([new LiteralNode('a', 0, 0), new LiteralNode('', 0, 0)], 0, 0),
            new LiteralNode('a', 0, 0),
            new CharTypeNode('d', 0, 0),
        ], 0, 0);

        $result = $alts->accept($optimizer);

        $this->assertInstanceOf(AlternationNode::class, $result);
        $this->assertCount(2, $result->alternatives);
    }

    public function test_factorize_alternation_returns_prefix_only_when_suffixes_empty(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $alts = [new LiteralNode('ab', 0, 2), new LiteralNode('ab', 0, 2)];

        $result = $this->invokePrivate($optimizer, 'factorizeAlternation', [$alts]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function test_factorize_alternation_with_without_prefix_returns_factored_and_rest(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $GLOBALS['__nodevisitor_str_starts_with_queue'] = [true, false, true, true, false, false, true, true];
        $alts = [
            new LiteralNode('pre_a', 0, 5),
            new LiteralNode('pre_b', 0, 5),
            new LiteralNode('pre_c', 0, 5),
        ];

        $result = $this->invokePrivate($optimizer, 'factorizeAlternation', [$alts]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_factorize_alternation_returns_alts_when_with_prefix_too_small(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $GLOBALS['__nodevisitor_str_starts_with_queue'] = [true, false, true, false, true, false];
        $alts = [
            new LiteralNode('ab', 0, 2),
            new LiteralNode('ac', 0, 2),
        ];

        $result = $this->invokePrivate($optimizer, 'factorizeAlternation', [$alts]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_factorize_suffix_all_suffix_only(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $alts = [new LiteralNode('ab', 0, 2), new LiteralNode('ab', 0, 2)];

        $result = $this->invokePrivate($optimizer, 'factorizeSuffix', [$alts]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function test_factorize_suffix_single_prefix_branch(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $alts = [new LiteralNode('ab', 0, 2), new LiteralNode('cab', 0, 3)];

        $result = $this->invokePrivate($optimizer, 'factorizeSuffix', [$alts]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function test_factorize_suffix_with_without_suffix_returns_factored_and_rest(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $GLOBALS['__nodevisitor_str_ends_with_queue'] = [false, true, true];
        $alts = [
            new LiteralNode('xend', 0, 4),
            new LiteralNode('yend', 0, 4),
            new LiteralNode('zend', 0, 4),
        ];

        $result = $this->invokePrivate($optimizer, 'factorizeSuffix', [$alts]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_factorize_suffix_returns_alts_when_with_suffix_too_small(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $GLOBALS['__nodevisitor_str_ends_with_queue'] = [false, true];
        $alts = [
            new LiteralNode('abx', 0, 3),
            new LiteralNode('cbx', 0, 3),
        ];

        $result = $this->invokePrivate($optimizer, 'factorizeSuffix', [$alts]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_find_common_prefix_empty_returns_empty_string(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        $result = $this->invokePrivate($optimizer, 'findCommonPrefix', [[]]);

        $this->assertSame('', $result);
    }

    public function test_merge_char_classes_and_char_types_merges_alternations(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $class = new CharClassNode(
            new AlternationNode([new LiteralNode('a', 0, 1), new LiteralNode('b', 1, 2)], 0, 2),
            false,
            0,
            2,
        );

        $result = $this->invokePrivate($optimizer, 'mergeCharClassesAndCharTypes', [[
            $class,
            new CharTypeNode('d', 2, 3),
        ]]);

        $this->assertInstanceOf(CharClassNode::class, $result);
    }

    public function test_try_convert_alternation_to_char_class_range_and_null(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        $range = $this->invokePrivate($optimizer, 'tryConvertAlternationToCharClass', [[
            new LiteralNode('a', 0, 1),
            new LiteralNode('b', 1, 2),
            new LiteralNode('c', 2, 3),
        ], 0, 3]);

        $this->assertInstanceOf(CharClassNode::class, $range);

        $null = $this->invokePrivate($optimizer, 'tryConvertAlternationToCharClass', [[
            new LiteralNode('a', 0, 1),
            new LiteralNode('c', 1, 2),
            new LiteralNode('d', 2, 3),
        ], 0, 3]);

        $this->assertNull($null);
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invokePrivate(object $target, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionClass($target);
        $refMethod = $ref->getMethod($method);

        return $refMethod->invokeArgs($target, $args);
    }
}
