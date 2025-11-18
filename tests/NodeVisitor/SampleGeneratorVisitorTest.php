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

namespace RegexParser\Tests\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;
use RegexParser\Parser;

class SampleGeneratorVisitorTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function test_generate_simple(): void
    {
        $this->assertSampleMatches('/abc/');
    }

    public function test_generate_alternation(): void
    {
        $this->assertSampleMatches('/a|b|c/');
    }

    public function test_generate_quantifiers(): void
    {
        $this->assertSampleMatches('/a{2,5}/');
        $this->assertSampleMatches('/b*/'); // Can generate empty string
        $this->assertSampleMatches('/c+/');
    }

    public function test_generate_char_classes(): void
    {
        $this->assertSampleMatches('/[a-z]/');
        $this->assertSampleMatches('/[0-9]{3}/');
        $this->assertSampleMatches('/[a-zA-Z0-9]/');
    }

    public function test_generate_special_types(): void
    {
        $this->assertSampleMatches('/\d\s\w/');
    }

    public function test_generate_groups_and_backrefs(): void
    {
        // Complex test: backreference. The generator must remember what it generated.
        // (a|b)\1 must generate "aa" or "bb", but never "ab"
        $parser = new Parser();
        $ast = $parser->parse('/(a|b)\1/');
        $generator = new SampleGeneratorVisitor();

        $sample = $ast->accept($generator);
        $this->assertContains($sample, ['aa', 'bb']);
    }

    public function test_seeding(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/[a-z]{10}/');
        $generator = new SampleGeneratorVisitor();

        $generator->setSeed(12345);
        $sample1 = $ast->accept($generator);

        $generator->setSeed(12345);
        $sample2 = $ast->accept($generator);

        $this->assertSame($sample1, $sample2, 'Seeding should produce deterministic results');
    }

    private function assertSampleMatches(string $regex): void
    {
        $ast = $this->parser->parse($regex);
        $generator = new SampleGeneratorVisitor();

        for ($i = 0; $i < 5; $i++) {
            $sample = $ast->accept($generator);
            $this->assertMatchesRegularExpression($regex, $sample);
        }
    }

    public function test_generate_all_char_types(): void
    {
        // Test char types that are typically complex to mock
        $regex = '/\D\S\W\h\H\v\V\R/';
        $this->assertSampleMatches($regex);
    }

    public function test_generate_unicode_and_octal_escapes(): void
    {
        // \xNN, \u{NNNN}, \o{NNN}, \0NN
        // Note: PHP PCRE doesn't support \u{} and \o{} syntax, so we test the generated output directly
        $regex = '/\x41\u{00E9}\o{40}\010/';
        $ast = $this->parser->parse($regex);
        $generator = new SampleGeneratorVisitor();
        $sample = $ast->accept($generator);
        
        // Expected: \x41 = 'A', \u{00E9} = 'é', \o{40} = ' ' (space, octal 40 = decimal 32), \010 = backspace (octal 10 = decimal 8)
        $expected = "A\xc3\xa9 \x08"; // 'A' + UTF-8 'é' + space + backspace
        $this->assertSame($expected, $sample);
    }

    public function test_generate_complex_backrefs(): void
    {
        // Named backref (\k<name>)
        $this->assertSampleMatches('/(?<n1>\d{1,2})foo\k<n1>/'); // \k<name>
        
        // Note: Optional groups with backrefs are tricky because if the group doesn't match,
        // the backref fails the entire match in PCRE. The generator randomly chooses 0 or 1
        // for '?', so we test this separately to ensure it can generate a valid match.
        $ast = $this->parser->parse('/(?<name>a)?\k<name>/');
        $generator = new SampleGeneratorVisitor();
        
        // Try multiple times - at least one should generate 'aa' (when group matches)
        $validSampleFound = false;
        for ($i = 0; $i < 10; $i++) {
            $sample = $ast->accept($generator);
            if ($sample === 'aa') {
                $validSampleFound = true;
                break;
            }
        }
        $this->assertTrue($validSampleFound, 'Should be able to generate valid sample "aa" for optional group with backref');
    }

    public function test_generate_conditional_always_chooses_a_branch(): void
    {
        // If conditional doesn't exist, it randomly chooses yes/no.
        // Ensures the random path in `visitConditional` is hit.
        $parser = new Parser();
        $ast = $parser->parse('/(?(?=\d)Y|N)/');
        $generator = new SampleGeneratorVisitor();

        $output = $ast->accept($generator);
        $this->assertTrue(in_array($output, ['Y', 'N']));
    }

    public function test_generate_negated_char_class_safe_char(): void
    {
        // [^a] uses '!' as a safe char.
        $sample = $this->generateSample('/[^a]/');
        $this->assertSame('!', $sample);
    }

    public function test_generate_throws_on_subroutine(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Sample generation for subroutines is not supported.');
        $this->generateSample('/(?R)/');
    }

    public function test_generate_throws_on_empty_char_class(): void
    {
        // Empty character class /[]/ is actually a lexer error (unclosed class with ] as literal)
        $this->expectException(\RegexParser\Exception\LexerException::class);
        $this->expectExceptionMessage('Unclosed character class');
        $this->generateSample('/[]/');
    }

    private function generateSample(string $regex): string
    {
        $ast = $this->parser->parse($regex);
        $generator = new SampleGeneratorVisitor();
        return $ast->accept($generator);
    }
}
