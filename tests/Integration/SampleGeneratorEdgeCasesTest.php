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
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;

final class SampleGeneratorEdgeCasesTest extends TestCase
{
    /**
     * Teste la méthode privée getRandomChar avec un tableau vide (impossible via l'API publique).
     * Couvre le fallback "return '?';".
     */
    public function test_get_random_char_with_empty_array(): void
    {
        $generator = new SampleGeneratorNodeVisitor();
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('getRandomChar');

        $result = $method->invoke($generator, []);
        $this->assertSame('?', $result);
    }

    /**
     * Teste le fallback de mt_rand pour les quantificateurs.
     * On ne peut pas facilement forcer mt_rand à échouer, mais on peut passer des bornes invalides
     * à la méthode privée parseQuantifierRange pour voir comment elle réagit.
     */
    public function test_parse_quantifier_range_fallback(): void
    {
        $generator = new SampleGeneratorNodeVisitor();
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('parseQuantifierRange');

        // Cas invalide qui ne matche aucune regex connue
        $result = $method->invoke($generator, 'INVALID');
        // Le code retourne [0, 0] par défaut via le match
        $this->assertSame([0, 0], $result);
    }
}
