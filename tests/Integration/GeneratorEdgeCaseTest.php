<?php

namespace RegexParser\Tests\Integration;



use PHPUnit\Framework\TestCase;
use RegexParser\Node\CharTypeNode;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;

class GeneratorEdgeCaseTest extends TestCase
{
    /**
     * Teste le cas par défaut pour un type de caractère inconnu.
     * Le parser bloque cela normalement, donc on injecte le nœud manuellement.
     */
    public function test_generate_unknown_char_type(): void
    {
        $generator = new SampleGeneratorVisitor();
        // 'Z' n'existe pas comme type de caractère standard
        $node = new CharTypeNode('Z', 0, 0);

        $result = $node->accept($generator);

        // Le default du match est '?'
        $this->assertSame('?', $result);
    }

    /**
     * Teste le parseQuantifierRange avec une chaîne qui ne matche rien.
     * (Code défensif via Reflection)
     */
    public function test_parse_quantifier_range_fallback(): void
    {
        $generator = new SampleGeneratorVisitor();
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('parseQuantifierRange');

        // Valeur invalide qui ne matche ni *, +, ? ni {n,m}
        $result = $method->invoke($generator, 'INVALID');

        // Le default retourne [0, 0]
        $this->assertSame([0, 0], $result);
    }
}
