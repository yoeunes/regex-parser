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
use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Exception\SemanticErrorException;
use RegexParser\Lexer;
use RegexParser\NodeVisitor\ExplainNodeVisitor;
use RegexParser\NodeVisitor\HtmlExplainNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Regex;

/**
 * Tests to achieve 100% code coverage.
 */
final class FullCoverageTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    public function test_lexer_trailing_backslash(): void
    {
        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Unable to tokenize');

        (new Lexer())->tokenize('abc\\');
    }

    public function test_lexer_unclosed_character_class(): void
    {
        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Unclosed character class');

        (new Lexer())->tokenize('[abc');
    }

    public function test_lexer_unclosed_comment(): void
    {
        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Unclosed comment');

        (new Lexer())->tokenize('(?#comment without closing');
    }

    public function test_lexer_comment_at_end_of_string(): void
    {
        // Test comment mode that reaches end of string
        try {
            (new Lexer())->tokenize('abc(?#test');
            $this->fail('Expected LexerException');
        } catch (LexerException $e) {
            $this->assertStringContainsString('Unclosed comment', $e->getMessage());
        }
    }

    public function test_parser_conditional_with_invalid_syntax_after_question(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid conditional condition');

        $this->regexService->parse('/(?(?x)yes|no)/');
    }

    public function test_parser_conditional_with_bare_atom(): void
    {
        // Test conditional with an atom that's not a valid condition type
        // This should trigger the fallback parsing and validation error
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid conditional construct');

        $this->regexService->parse('/(?([a-z])yes|no)/');
    }

    public function test_parser_group_name_missing_closing_single_quote(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected closing quote');

        $this->regexService->parse("/(?P<'name>x)/");
    }

    public function test_parser_group_name_missing_closing_double_quote(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected closing quote');

        $this->regexService->parse('/(?P<"name>x)/');
    }

    public function test_explain_visitor_with_unicode_prop_negated(): void
    {
        // Test ExplainVisitor with negated unicode property
        $ast = $this->regexService->parse('/\P{L}/');

        $visitor = new ExplainNodeVisitor();
        $result = $ast->accept($visitor);

        $this->assertNotEmpty($result);
    }

    public function test_explain_visitor_with_octal_legacy(): void
    {
        // Test ExplainVisitor with octal legacy
        $ast = $this->regexService->parse('/\07/');

        $visitor = new ExplainNodeVisitor();
        $result = $ast->accept($visitor);

        $this->assertNotEmpty($result);
    }

    public function test_html_explain_visitor_with_pcre_verb(): void
    {
        // Test HtmlExplainVisitor with PCRE verb
        $ast = $this->regexService->parse('/(*FAIL)/');

        $visitor = new HtmlExplainNodeVisitor();
        $result = $ast->accept($visitor);

        $this->assertNotEmpty($result);
    }

    public function test_html_explain_visitor_with_keep(): void
    {
        // Test HtmlExplainVisitor with \K (keep)
        $ast = $this->regexService->parse('/test\Kmore/');

        $visitor = new HtmlExplainNodeVisitor();
        $result = $ast->accept($visitor);

        $this->assertNotEmpty($result);
    }

    public function test_html_explain_visitor_with_subroutine(): void
    {
        // Test HtmlExplainVisitor with subroutine
        $ast = $this->regexService->parse('/(?<group>test)(?&group)/');

        $visitor = new HtmlExplainNodeVisitor();
        $result = $ast->accept($visitor);

        $this->assertNotEmpty($result);
    }

    public function test_sample_generator_with_conditional(): void
    {
        // Test SampleGeneratorVisitor with conditional
        $ast = $this->regexService->parse('/(x)(?(1)y|z)/');

        $visitor = new SampleGeneratorNodeVisitor();
        $sample = $ast->accept($visitor);

        $this->assertIsString($sample);
    }

    public function test_sample_generator_with_pcre_verb(): void
    {
        // Test SampleGeneratorVisitor with PCRE verb
        $ast = $this->regexService->parse('/(*ACCEPT)test/');

        $visitor = new SampleGeneratorNodeVisitor();
        $sample = $ast->accept($visitor);

        $this->assertIsString($sample);
    }

    public function test_sample_generator_with_keep(): void
    {
        // Test SampleGeneratorVisitor with \K
        $ast = $this->regexService->parse('/prefix\Ksuffix/');

        $visitor = new SampleGeneratorNodeVisitor();
        $sample = $ast->accept($visitor);

        $this->assertIsString($sample);
    }

    public function test_sample_generator_with_octal_legacy(): void
    {
        // Test SampleGeneratorVisitor with octal legacy
        $ast = $this->regexService->parse('/\07/');

        $visitor = new SampleGeneratorNodeVisitor();
        $sample = $ast->accept($visitor);

        $this->assertIsString($sample);
    }

    public function test_sample_generator_with_unicode(): void
    {
        // Test SampleGeneratorVisitor with unicode sequences
        $ast = $this->regexService->parse('/\u{41}/');

        $visitor = new SampleGeneratorNodeVisitor();
        $sample = $ast->accept($visitor);

        $this->assertIsString($sample);
    }

    public function test_validator_with_invalid_backref(): void
    {
        // Test ValidatorNodeVisitor with invalid backreference
        $this->expectException(SemanticErrorException::class);

        $ast = $this->regexService->parse('/\1/');

        $visitor = new ValidatorNodeVisitor();
        $ast->accept($visitor);
    }

    public function test_validator_with_invalid_subroutine(): void
    {
        // Test ValidatorNodeVisitor with invalid subroutine
        $this->expectException(SemanticErrorException::class);

        $ast = $this->regexService->parse('/(?&nonexistent)/');

        $visitor = new ValidatorNodeVisitor();
        $ast->accept($visitor);
    }
}
