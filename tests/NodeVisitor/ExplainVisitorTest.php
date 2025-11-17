<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\NodeVisitor\HtmlExplainVisitor;
use RegexParser\Parser;

class ExplainVisitorTest extends TestCase
{
    public function testTextExplain(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/^a|b$/i');
        $visitor = new ExplainVisitor();
        
        $output = $ast->accept($visitor);
        
        $this->assertStringContainsString('Regex matches (with flags: i)', $output);
        $this->assertStringContainsString('EITHER:', $output);
        // Correction : On cherche la traduction humaine générée par le code
        $this->assertStringContainsString('Anchor: the start of the string', $output); 
        $this->assertStringContainsString('OR:', $output);
    }

    public function testHtmlExplainEscaping(): void
    {
        $parser = new Parser();
        // Le parser découpe "<script>" en plusieurs littéraux : "<", "s", "c", ...
        $ast = $parser->parse('/<script>/');
        $visitor = new HtmlExplainVisitor();
        
        $output = $ast->accept($visitor);
        
        // On vérifie que les caractères spéciaux HTML sont bien échappés dans le rendu
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;', $output);
        $this->assertStringContainsString('&gt;', $output);
    }
}
