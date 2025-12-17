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
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;

final class ManualFallbackTest extends TestCase
{
    /**
     * Tests the SampleGenerator fallback for an unknown character type.
     * Impossible via the parser because it rejects unknown types.
     */
    public function test_sample_generator_unknown_char_type(): void
    {
        // We inject a node with an invalid type 'z'
        $node = new CharTypeNode('z', 0, 0);
        $generator = new SampleGeneratorNodeVisitor();

        // Must return '?' (the default of the switch)
        $this->assertSame('?', $node->accept($generator));
    }

    /**
     * Tests the Compiler fallback for an unknown subroutine syntax.
     * The parser normalizes the syntaxes, so we inject a manual node.
     */
    public function test_compiler_subroutine_default_syntax(): void
    {
        // Syntaxe vide '' déclenche le default dans visitSubroutine
        $node = new SubroutineNode('1', 'UNKNOWN_SYNTAX', 0, 0);
        $compiler = new CompilerNodeVisitor();

        // The default returns '(?reference)'
        $this->assertSame('(?1)', $node->accept($compiler));
    }

    /**
     * Tests the fallback of the private parseQuantifierRange method in SampleGeneratorVisitor
     * via Reflection, for an unknown quantifier.
     */
    public function test_sample_generator_parse_quantifier_fallback(): void
    {
        $generator = new SampleGeneratorNodeVisitor();
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('parseQuantifierRange');

        // Call with an invalid quantifier that doesn't match any case
        $result = $method->invoke($generator, 'INVALID');

        // The default returns [0, 0] (via @codeCoverageIgnore, but let's test it anyway)
        $this->assertSame([0, 0], $result);
    }
}
