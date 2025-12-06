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

namespace RegexParser\Tests\Unit\Bridge\Rector;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use Rector\NodeNameResolver\NodeNameResolver;
use RegexParser\Bridge\Rector\RegexOptimizationRector;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;

final class RegexOptimizationRectorTest extends TestCase
{
    public function test_refactors_configured_function_call(): void
    {
        $rector = new RegexOptimizationRector(new OptimizerNodeVisitor());
        $rector->configure([RegexOptimizationRector::EXTRA_FUNCTIONS => ['my_func']]);
        $this->initializeDependencies($rector);

        $parser = new ParserFactory()->createForNewestSupportedVersion();
        $stmts = $parser->parse('<?php my_func("/[a-zA-Z0-9_]+/", $s);');
        $funcCall = $this->findNode($stmts, FuncCall::class);

        $this->assertInstanceOf(FuncCall::class, $funcCall);
        $modified = $rector->refactor($funcCall);

        $this->assertNotInstanceOf(\PhpParser\Node::class, $modified);
        $this->assertInstanceOf(\PhpParser\Node\Expr\FuncCall::class, $funcCall);
        $this->assertSame('/[a-zA-Z0-9_]+/', $funcCall->getArgs()[0]->value->value);
    }

    public function test_refactors_configured_class_constant(): void
    {
        $rector = new RegexOptimizationRector(new OptimizerNodeVisitor());
        $rector->configure([RegexOptimizationRector::EXTRA_CONSTANTS => ['MY_REGEX']]);
        $this->initializeDependencies($rector);

        $parser = new ParserFactory()->createForNewestSupportedVersion();
        $stmts = $parser->parse('<?php class A { public const MY_REGEX = "/[a-zA-Z0-9_]+/"; }');
        $const = $this->findNode($stmts, ClassConst::class);

        $this->assertInstanceOf(ClassConst::class, $const);
        $modified = $rector->refactor($const);

        $this->assertNotInstanceOf(\PhpParser\Node::class, $modified);
        $this->assertSame('/[a-zA-Z0-9_]+/', $const->consts[0]->value->value);
    }

    /**
     * @template T of \PhpParser\Node
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function findNode(array $stmts, string $class): ?\PhpParser\Node
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof $class) {
                return $stmt;
            }

            foreach ($stmt->getSubNodeNames() as $name) {
                $child = $stmt->$name ?? null;
                if (\is_array($child)) {
                    foreach ($child as $node) {
                        if ($node instanceof $class) {
                            return $node;
                        }
                    }
                } elseif ($child instanceof $class) {
                    return $child;
                }
            }
        }

        return null;
    }

    private function initializeDependencies(RegexOptimizationRector $rector): void
    {
        if (!class_exists(NodeNameResolver::class)) {
            $this->markTestSkipped('Rector is not available in this environment.');
        }

        try {
            $resolver = new \ReflectionClass(NodeNameResolver::class)->newInstanceWithoutConstructor();
        } catch (\Throwable) {
            $this->markTestSkipped('Unable to create NodeNameResolver stub.');

        }

        $ref = new \ReflectionProperty($rector, 'nodeNameResolver');
        $ref->setValue($rector, $resolver);
    }
}
