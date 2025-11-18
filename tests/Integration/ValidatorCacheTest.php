<?php

declare(strict_types=1);

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Parser;

class ValidatorCacheTest extends TestCase
{
    public function test_unicode_property_cache_hit(): void
    {
        $parser = new Parser();
        $validator = new ValidatorNodeVisitor();

        // 1ère passe : remplit le cache
        $ast1 = $parser->parse('/\p{L}/');
        $ast1->accept($validator);

        // 2ème passe : utilise le cache (couvre la branche "cache hit")
        $ast2 = $parser->parse('/\p{L}/');
        $ast2->accept($validator);

        // Assertion implicite : pas d'exception levée
        $this->addToAssertionCount(1);
    }
}
