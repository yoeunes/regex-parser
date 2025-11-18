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
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Parser;

class ValidatorCacheTest extends TestCase
{
    public function test_unicode_property_cache_hit(): void
    {
        $this->expectNotToPerformAssertions();
        $parser = new Parser();
        $validator = new ValidatorNodeVisitor();

        // 1ère passe : remplit le cache
        $ast1 = $parser->parse('/\p{L}/');
        $ast1->accept($validator);

        // 2ème passe : utilise le cache (couvre la branche "cache hit")
        $ast2 = $parser->parse('/\p{L}/');
        $ast2->accept($validator);
    }
}
