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

namespace RegexParser\Rector\Rule\PHPUnit;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPUnit\Framework\Assert;
use Rector\PHPUnit\CodeQuality\NodeAnalyser\AssertMethodAnalyzer;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Converts PHPUnit assertions from $this->assert*() and static::assert*() to explicit Assert::assert*() static calls.
 */
final class PreferStaticPHPUnitAssertRector extends AbstractRector
{
    public function __construct(private AssertMethodAnalyzer $assertMethodAnalyzer) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Changes PHPUnit assertion calls from $this->assert*() or static::assert*() to explicit Assert::assert*() static calls.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
                        use PHPUnit\Framework\TestCase;

                        final class SomeTest extends TestCase {
                            public function testSomething() {
                                $this->assertEquals(1, 2);
                                static::assertSame('foo', 'bar');
                            }
                        }
                        CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
                        use PHPUnit\Framework\TestCase;
                        use PHPUnit\Framework\Assert;

                        final class SomeTest extends TestCase {
                            public function testSomething() {
                                Assert::assertEquals(1, 2);
                                Assert::assertSame('foo', 'bar');
                            }
                        }
                        CODE_SAMPLE
                ),
            ],
        );
    }

    public function getNodeTypes(): array
    {
        return [MethodCall::class, StaticCall::class];
    }

    /**
     * @param MethodCall|StaticCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node->isFirstClassCallable()) {
            return null;
        }

        if ($node instanceof MethodCall && !$this->assertMethodAnalyzer->detectTestCaseCallForStatic($node)) {
            return null;
        }

        if ($node instanceof StaticCall && !$this->assertMethodAnalyzer->detectTestCaseCall($node)) {
            return null;
        }

        $methodName = $this->getName($node->name);
        if (null === $methodName || !str_starts_with($methodName, 'assert')) {
            return null;
        }

        return $this->nodeFactory->createStaticCall(
            Assert::class,
            $methodName,
            $node->getArgs(),
        );
    }
}
