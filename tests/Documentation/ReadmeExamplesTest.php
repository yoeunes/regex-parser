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

namespace RegexParser\Tests\Documentation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Cache\FilesystemCache;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\NodeVisitor\AbstractNodeVisitor;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\ConsoleHighlighterVisitor;
use RegexParser\NodeVisitor\HtmlHighlighterVisitor;
use RegexParser\NodeVisitor\ModernizerNodeVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;

final class ReadmeExamplesTest extends TestCase
{
    private Regex $regex;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
    }

    #[Test]
    public function validate_regex_example(): void
    {
        $result = $this->regex->validate('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i');

        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    #[Test]
    public function explain_regex_example(): void
    {
        $explanation = $this->regex->explain('/^([a-z]+)\.([a-z]{2,})$/');

        $this->assertStringContainsString('Anchor: the beginning of a line', $explanation);
        $this->assertStringContainsString('Anchor: the end of a line', $explanation);
    }

    #[Test]
    public function redos_example_reports_critical(): void
    {
        $analysis = $this->regex->redos('/(a+)+b/');

        $this->assertSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertFalse($analysis->isSafe());
    }

    #[Test]
    public function optimize_example_rewrites_digits(): void
    {
        $optimized = $this->regex->optimize('/[0-9]+/');

        $this->assertSame('/[0-9]+/', $optimized->original);
        $this->assertSame('/\d+/', $optimized->optimized);
    }

    #[Test]
    public function generate_samples_example(): void
    {
        $sample = $this->regex->generate('/[a-z]{3}\d{2}/');

        $this->assertMatchesRegularExpression('/^[a-z]{3}\d{2}$/', $sample);
    }

    #[Test]
    public function caret_diagnostics_example(): void
    {
        $strict = Regex::create(['runtime_pcre_validation' => true]);
        $result = $strict->validate('/(?<=a+)\w/');

        $this->assertFalse($result->isValid());
        $this->assertNotNull($result->getCaretSnippet());
        $this->assertNotNull($result->getHint());
    }

    #[Test]
    public function ast_iteration_example(): void
    {
        $ast = $this->regex->parse('/foo|bar/');

        $pattern = $ast->pattern;
        $this->assertInstanceOf(AlternationNode::class, $pattern);

        $branches = [];
        foreach ($pattern->alternatives as $alternative) {
            $this->assertInstanceOf(SequenceNode::class, $alternative);

            $literal = '';
            foreach ($alternative->children as $child) {
                if ($child instanceof LiteralNode) {
                    $literal .= $child->value;
                }
            }

            $branches[] = $literal;
        }

        sort($branches);
        $this->assertSame(['bar', 'foo'], $branches);
    }

    #[Test]
    public function custom_visitor_example(): void
    {
        $ast = $this->regex->parse('/ab(c|d)+/');

        $visitor = new LiteralCountVisitor();
        $count = $ast->accept($visitor);

        $this->assertSame(4, $count);
    }

    #[Test]
    public function optimize_and_recompile_example(): void
    {
        $ast = $this->regex->parse('/(a|a)/');

        $optimizer = new OptimizerNodeVisitor();
        $optimizedAst = $ast->accept($optimizer);
        $compiler = new CompilerNodeVisitor();
        $optimizedPattern = $optimizedAst->accept($compiler);

        $this->assertSame('/([a])/', $optimizedPattern);
    }

    #[Test]
    public function auto_modernize_example(): void
    {
        $ast = $this->regex->parse('/[0-9]+\-[a-z]+\@(?:gmail)\.com/');

        $modernizedAst = $ast->accept(new ModernizerNodeVisitor());
        $modern = $modernizedAst->accept(new CompilerNodeVisitor());

        $this->assertSame('/\d+-[a-z]+@gmail\.com/', $modern);
    }

    #[Test]
    public function syntax_highlighting_example(): void
    {
        $ast = $this->regex->parse('/^[0-9]+(\w+)$/');

        $console = $ast->accept(new ConsoleHighlighterVisitor());
        $html = $ast->accept(new HtmlHighlighterVisitor());

        $this->assertStringContainsString("\033[", $console);
        $this->assertStringContainsString('regex-anchor', $html);
        $this->assertStringContainsString('regex-meta', $html);
    }

    #[Test]
    public function configuration_options_example(): void
    {
        $cacheDir = sys_get_temp_dir().'/regex-parser-cache-'.bin2hex(random_bytes(6));

        $regex = Regex::create([
            'cache' => $cacheDir,
            'max_pattern_length' => 100_000,
            'max_lookbehind_length' => 255,
            'runtime_pcre_validation' => false,
            'redos_ignored_patterns' => [
                '/^([0-9]{4}-[0-9]{2}-[0-9]{2})$/',
            ],
        ]);

        $result = $regex->validate('/^([0-9]{4}-[0-9]{2}-[0-9]{2})$/');
        $this->assertTrue($result->isValid());

        (new FilesystemCache($cacheDir))->clear();
    }
}

/**
 * @extends AbstractNodeVisitor<int>
 */
final class LiteralCountVisitor extends AbstractNodeVisitor
{
    public function visitRegex(RegexNode $node): int
    {
        return $node->pattern->accept($this);
    }

    public function visitLiteral(LiteralNode $node): int
    {
        return 1;
    }

    public function visitCharLiteral(CharLiteralNode $node): int
    {
        return 1;
    }

    public function visitSequence(SequenceNode $node): int
    {
        $sum = 0;
        foreach ($node->children as $child) {
            $sum += $child->accept($this);
        }

        return $sum;
    }

    public function visitAlternation(AlternationNode $node): int
    {
        $sum = 0;
        foreach ($node->alternatives as $alternative) {
            $sum += $alternative->accept($this);
        }

        return $sum;
    }

    public function visitGroup(GroupNode $node): int
    {
        return $node->child->accept($this);
    }

    public function visitQuantifier(QuantifierNode $node): int
    {
        return $node->node->accept($this);
    }

    protected function defaultReturn(): int
    {
        return 0;
    }
}
