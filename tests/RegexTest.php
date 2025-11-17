<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests;

use PHPUnit\Framework\TestCase;
use RegexParser\Regex;
use RegexParser\ValidationResult;

class RegexTest extends TestCase
{
    public function testCreateAndParse(): void
    {
        $regex = Regex::create();
        $ast = $regex->parse('/abc/');
        $this->assertNotNull($ast);
    }

    public function testValidate(): void
    {
        $regex = Regex::create();
        
        $valid = $regex->validate('/abc/');
        $this->assertTrue($valid->isValid);
        
        $invalid = $regex->validate('/(abc/'); // Parenthèse non fermée
        $this->assertFalse($invalid->isValid);
        $this->assertNotNull($invalid->error);
    }

    public function testOptimize(): void
    {
        $regex = Regex::create();
        // Doit optimiser [0-9] en \d
        $optimized = $regex->optimize('/[0-9]/');
        
        // Note: le CompilerNodeVisitor ajoute le \ devant d
        $this->assertSame('/\d/', $optimized);
    }
    
    public function testGenerate(): void
    {
        $regex = Regex::create();
        $sample = $regex->generate('/\d{3}/');
        $this->assertMatchesRegularExpression('/\d{3}/', $sample);
    }
}
