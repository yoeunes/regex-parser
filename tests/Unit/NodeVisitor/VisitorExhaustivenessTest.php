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

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Exception\RegexException;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\UnicodeNode;
use RegexParser\NodeVisitor\NodeVisitorInterface;
use RegexParser\Regex;

/**
 * Guards against visitor/interface drift: every concrete visitor must handle
 * every node type the parser can produce without hitting the null-returning
 * default of AbstractNodeVisitor (which explodes as a TypeError in visitors
 * with typed returns).
 */
final class VisitorExhaustivenessTest extends TestCase
{
    /**
     * Patterns chosen so that, together, they produce every node type the
     * parser can emit (UnicodeNode is currently unreachable from the parser).
     *
     * @return iterable<string, array{pattern: string}>
     */
    public static function provide_node_covering_patterns(): iterable
    {
        $patterns = [
            '/ab/',
            '/a|b/',
            '/(a)+?/',
            '/[a-z]/',
            '/\d./',
            '/^a$/',
            '/\ba\K/',
            '/(a)\1/',
            '/[a&&b]/',
            '/\cA/',
            '/(*sr:\p{Greek}+)/u',
            '/(?(VERSION>=10.4)a|b)/',
            '/\p{L}/u',
            '/[[:alpha:]]/',
            '/(?#c)a/',
            '/(?(1)a|b)(x)/',
            '/(a)(?1)/',
            '/(*FAIL)/',
            '/(?(DEFINE)(?<d>\d))(?&d)/',
            '/(*LIMIT_MATCH=10)a/',
            '/(?C1)a/',
            '/\x{1F600}/u',
        ];

        foreach ($patterns as $pattern) {
            yield $pattern => ['pattern' => $pattern];
        }
    }

    #[Test]
    #[DataProvider('provide_node_covering_patterns')]
    public function test_every_visitor_handles_every_parser_producible_node(string $pattern): void
    {
        $ast = Regex::create()->parse($pattern);

        $visited = 0;
        foreach (self::instantiableVisitors() as $class => $visitor) {
            try {
                $ast->accept($visitor);
            } catch (RegexException) {
                // Domain errors (e.g. semantic validation) are acceptable here;
                // this test only guards against engine-level crashes.
            } catch (\Error $e) {
                $this->fail(\sprintf(
                    '%s crashed on %s: %s',
                    $class,
                    $pattern,
                    $e->getMessage(),
                ));
            }
            $visited++;
        }

        $this->assertGreaterThan(0, $visited);
    }

    /**
     * Constructs one instance of EVERY concrete node type (including ones the
     * parser cannot currently emit, like UnicodeNode) and runs every visitor
     * over it, so a new node type cannot silently fall through to the null
     * default of AbstractNodeVisitor in any typed visitor.
     */
    #[Test]
    public function test_every_visitor_handles_synthetic_instances_of_all_node_types(): void
    {
        $literal = new \RegexParser\Node\LiteralNode('a', 0, 1);
        $nodes = [
            new \RegexParser\Node\AlternationNode([$literal], 0, 1),
            new \RegexParser\Node\AnchorNode('^', 0, 1),
            new \RegexParser\Node\AssertionNode('b', 0, 2),
            new \RegexParser\Node\BackrefNode('\\1', 0, 2),
            new \RegexParser\Node\CalloutNode(1, false, 0, 5),
            new \RegexParser\Node\CharClassNode($literal, false, 0, 3),
            new \RegexParser\Node\CharLiteralNode('\\x41', 65, \RegexParser\Node\CharLiteralType::UNICODE, 0, 4),
            new \RegexParser\Node\CharTypeNode('d', 0, 2),
            new \RegexParser\Node\ClassOperationNode(\RegexParser\Node\ClassOperationType::INTERSECTION, $literal, $literal, 0, 6),
            new \RegexParser\Node\CommentNode('c', 0, 5),
            new \RegexParser\Node\ConditionalNode($literal, $literal, $literal, 0, 9),
            new \RegexParser\Node\ControlCharNode('A', 1, 0, 3),
            new \RegexParser\Node\DefineNode($literal, 0, 12),
            new \RegexParser\Node\DotNode(0, 1),
            new \RegexParser\Node\GroupNode($literal, \RegexParser\Node\GroupType::T_GROUP_CAPTURING, null, null, 0, 3),
            new \RegexParser\Node\KeepNode(0, 2),
            new \RegexParser\Node\LimitMatchNode(10, 0, 16),
            $literal,
            new \RegexParser\Node\PcreVerbNode('FAIL', 0, 7),
            new \RegexParser\Node\PosixClassNode('alpha', 0, 9),
            new \RegexParser\Node\QuantifierNode($literal, '+', \RegexParser\Node\QuantifierType::T_GREEDY, 0, 2),
            new \RegexParser\Node\RangeNode($literal, new \RegexParser\Node\LiteralNode('z', 2, 3), 0, 3),
            new \RegexParser\Node\ScriptRunNode('Greek', 0, 12),
            new \RegexParser\Node\SequenceNode([$literal], 0, 1),
            new \RegexParser\Node\SubroutineNode('1', 'g', 0, 5),
            new \RegexParser\Node\UnicodeNode('0041', 0, 6),
            new \RegexParser\Node\UnicodePropNode('L', false, 0, 3),
            new \RegexParser\Node\VersionConditionNode('>=', '10.4', 0, 16),
        ];
        $nodes[] = new \RegexParser\Node\RegexNode($literal, '', '/', 0, 3);

        // Every concrete node type must appear above.
        $covered = array_map(static fn (NodeInterface $n): string => $n::class, $nodes);
        foreach (glob(__DIR__.'/../../../src/Node/*Node.php') ?: [] as $file) {
            $class = 'RegexParser\Node\\'.basename($file, '.php');
            if (!is_subclass_of($class, NodeInterface::class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            $this->assertContains($class, $covered, 'Add a synthetic instance for '.$class);
        }

        foreach (self::instantiableVisitors() as $class => $visitor) {
            foreach ($nodes as $node) {
                try {
                    $node->accept($visitor);
                } catch (RegexException|\LogicException|\RuntimeException) {
                    // Domain errors are fine; we only guard against engine-level crashes.
                } catch (\Error $e) {
                    $this->fail(\sprintf('%s crashed on %s: %s', $class, $node::class, $e->getMessage()));
                }
            }
        }

        $this->assertGreaterThan(0, \count($nodes));
    }

    #[Test]
    public function test_pattern_corpus_covers_all_parser_producible_node_types(): void
    {
        $seen = [];
        $regex = Regex::create();

        foreach (self::provide_node_covering_patterns() as ['pattern' => $pattern]) {
            $this->collectNodeTypes($regex->parse($pattern), $seen);
        }

        $missing = [];
        foreach (glob(__DIR__.'/../../../src/Node/*Node.php') ?: [] as $file) {
            $class = 'RegexParser\Node\\'.basename($file, '.php');
            if (!is_subclass_of($class, NodeInterface::class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            // The parser currently never emits UnicodeNode (\u escapes become CharLiteralNode).
            if (UnicodeNode::class === $class) {
                continue;
            }
            if (!isset($seen[$class])) {
                $missing[] = $class;
            }
        }

        $this->assertSame([], $missing, 'Corpus does not cover these node types; extend the pattern list.');
    }

    /**
     * @return iterable<class-string, NodeVisitorInterface<mixed>>
     */
    private static function instantiableVisitors(): iterable
    {
        foreach (glob(__DIR__.'/../../../src/NodeVisitor/*Visitor.php') ?: [] as $file) {
            $class = 'RegexParser\NodeVisitor\\'.basename($file, '.php');
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract() || !$reflection->implementsInterface(NodeVisitorInterface::class)) {
                continue;
            }
            if (($reflection->getConstructor()?->getNumberOfRequiredParameters() ?? 0) > 0) {
                continue;
            }

            $visitor = $reflection->newInstance();
            Assert::assertInstanceOf(NodeVisitorInterface::class, $visitor);

            yield $class => $visitor;
        }
    }

    /**
     * @param array<class-string, true> $seen
     */
    private function collectNodeTypes(NodeInterface $node, array &$seen): void
    {
        $seen[$node::class] = true;

        foreach ((array) $node as $value) {
            if ($value instanceof NodeInterface) {
                $this->collectNodeTypes($value, $seen);
            } elseif (\is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof NodeInterface) {
                        $this->collectNodeTypes($item, $seen);
                    }
                }
            }
        }
    }
}
