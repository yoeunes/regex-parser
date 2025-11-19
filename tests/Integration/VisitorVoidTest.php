<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\LiteralNode;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;

class VisitorVoidTest extends TestCase
{
    /**
     * Couvre les méthodes vides du ValidatorNodeVisitor (visitLiteral, visitDot, etc.)
     * qui sont techniquement du code exécuté même si elles ne font rien.
     */
    #[DoesNotPerformAssertions]
    public function test_validator_visits_simple_nodes(): void
    {
        $validator = new ValidatorNodeVisitor();

        // On appelle manuellement accept() pour être sûr que la méthode visit* est déclenchée
        (new LiteralNode('a', 0, 0))->accept($validator);
        (new DotNode(0, 0))->accept($validator);
        (new AnchorNode('^', 0, 0))->accept($validator);
        (new CharTypeNode('d', 0, 0))->accept($validator);
        (new CommentNode('comment', 0, 0))->accept($validator);
    }
}
