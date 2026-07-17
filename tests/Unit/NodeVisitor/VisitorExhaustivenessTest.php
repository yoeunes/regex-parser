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
