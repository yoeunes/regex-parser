<?php

declare(strict_types=1);

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Node\GroupType;
use RegexParser\Node\QuantifierType;
use RegexParser\Parser;
use RegexParser\Regex;

class PcreFeatureCompletenessTest extends TestCase
{
    private Parser $parser;
    private Regex $regex;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->regex = Regex::create();
    }

    public function testAtomicGroups(): void
    {
        $patterns = [
            '/(?>foo)bar/',
            '/(?>a+)b/',
            '/(?>[a-z]+)\d/',
            '/(?>test|testing)s/',
            '/(?>(?>a)b)c/',
            '/(?>abc|ab)c/',
            '/a(?>bc|b)c/',
            '/(?>x+)x/',
            '/(?>a{2,5})a/',
            '/(?>(?:foo|bar))baz/',
            '/(?>(a|b))c/',
            '/(?>test(?:ing)?)s/',
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->parser->parse($pattern);
                $this->assertNotNull($ast, "Pattern should parse: {$pattern}");
                
                $hasAtomic = $this->hasGroupType($ast, GroupType::T_GROUP_ATOMIC);
                $this->assertTrue($hasAtomic, "Pattern should contain atomic group: {$pattern}");
            } catch (ParserException $e) {
                $this->fail("Atomic group pattern should parse: {$pattern}. Error: {$e->getMessage()}");
            }
        }
    }

    public function testPossessiveQuantifiers(): void
    {
        $patterns = [
            '/a++/',
            '/a*+/',
            '/a?+/',
            '/a{2,5}+/',
            '/[a-z]++/',
            '/\d*+/',
            '/\w?+/',
            '/(foo|bar)++/',
            '/[^abc]*+/',
            '/\s{1,3}+/',
            '/.++/',
            '/(?:test)?+/',
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->parser->parse($pattern);
                $this->assertNotNull($ast, "Pattern should parse: {$pattern}");
                
                $hasPossessive = $this->hasQuantifierType($ast, QuantifierType::T_POSSESSIVE);
                $this->assertTrue($hasPossessive, "Pattern should contain possessive quantifier: {$pattern}");
            } catch (ParserException $e) {
                $this->fail("Possessive quantifier pattern should parse: {$pattern}. Error: {$e->getMessage()}");
            }
        }
    }

    public function testConditionalPatterns(): void
    {
        $patterns = [
            '/(a)(?(1)b|c)/',
            '/(test)?(?(1)yes)/',
            '/(?<name>a)(?(name)b|c)/',
            '/(a)b(?(1)c)/',
            '/(a)(b)?(?(2)c|d)/',
            '/(?(?=test)a|b)/',
            '/(?(?!test)a|b)/',
            '/(?(?<=a)b|c)/',
            '/(?(?<!a)b|c)/',
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->parser->parse($pattern);
                $this->assertNotNull($ast, "Conditional pattern should parse: {$pattern}");
            } catch (ParserException $e) {
                $this->fail("Conditional pattern parsing failed: {$pattern}. Error: {$e->getMessage()}");
            }
        }
    }

    public function testConditionalPatterns_AdvancedFeatures(): void
    {
        $advancedPatterns = [
            '/(?(1)yes|no)/',
            '/(a)(?(DEFINE)(?<foo>bar))(?(1)\k<foo>)/',
        ];

        foreach ($advancedPatterns as $pattern) {
            try {
                $ast = $this->parser->parse($pattern);
                $this->assertNotNull($ast, "Advanced conditional pattern should parse: {$pattern}");
            } catch (ParserException $e) {
                $this->fail("Advanced conditional pattern failed: {$pattern}. Error: {$e->getMessage()}");
            }
        }
    }

    public function testNamedGroups(): void
    {
        $patterns = [
            '/(?<word>\w+)/',
            '/(?<year>\d{4})/',
            '/(?P<name>[a-z]+)/',
            '/(?P<test>foo|bar)/',
            '/(?<first>a)(?<second>b)/',
            '/(?P<group1>\d+)(?P<group2>\w+)/',
            '/(?<outer>(?<inner>test))/',
            '/(?<name>[a-z]+)\k<name>/',
            '/(?P<x>a)(?P<y>b)\k<x>\k<y>/',
            '/(?<digits>\d+)-(?<letters>[a-z]+)/',
            '/(?<tag><(?<name>\w+)>)/',
            '/(?<test>(?:foo|bar))/',
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->parser->parse($pattern);
                $this->assertNotNull($ast, "Named group pattern should parse: {$pattern}");
                
                $hasNamed = $this->hasGroupType($ast, GroupType::T_GROUP_NAMED);
                $this->assertTrue($hasNamed, "Pattern should contain named group: {$pattern}");
            } catch (ParserException $e) {
                $this->fail("Named group pattern should parse: {$pattern}. Error: {$e->getMessage()}");
            }
        }
    }

    public function testUnicodeProperties(): void
    {
        $patterns = [
            '/\p{L}+/',
            '/\p{N}/',
            '/\p{Lu}/',
            '/\p{Ll}/',
            '/\P{L}/',
            '/\p{Greek}/',
            '/\p{Latin}/',
            '/\p{Nd}+/',
            '/\p{Zs}/',
            '/\p{Sc}\d+/',
            '/[\p{L}\p{N}]+/',
            '/\p{Arabic}+/u',
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->parser->parse($pattern);
                $this->assertNotNull($ast, "Unicode property pattern should parse: {$pattern}");
            } catch (ParserException $e) {
                $this->fail("Unicode property pattern should parse: {$pattern}. Error: {$e->getMessage()}");
            }
        }
    }

    public function testSubroutinesAndRecursion(): void
    {
        $patterns = [
            '/(?R)/',
            '/(a(?R)?b)/',
            '/(test)(?1)/',
            '/(?<group>test)(?&group)/',
            '/\((?:[^()]++|(?R))*\)/',
            '/(a)(?1)(?1)/',
            '/(?<digit>\d)(?&digit)/',
            '/(?<x>a|(?&x)b)/',
            '/(foo|(?R))/',
            '/(?<name>[a-z]+)(?&name)/',
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->parser->parse($pattern);
                $this->assertNotNull($ast, "Subroutine pattern should parse: {$pattern}");
            } catch (ParserException $e) {
                $this->fail("Subroutine pattern parsing failed: {$pattern}. Error: {$e->getMessage()}");
            }
        }
    }

    public function testComments(): void
    {
        $patterns = [
            '/test(?#this is a comment)/',
            '/(?#comment at start)foo/',
            '/a(?#middle comment)b/',
            '/(?#first)a(?#second)b(?#third)/',
            '/[a-z](?#character class followed by comment)/',
            '/\d+(?#digits)/',
            '/(?#comment)\w+/',
            '/test(?#)end/',
            '/(?#special chars: @#$%^&*)pattern/',
            '/a(?#first)b(?#second)c/',
            '/(?#unicode: \u{1F600})test/',
            '/pattern(?#important note)more/',
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->parser->parse($pattern);
                $this->assertNotNull($ast, "Comment pattern should parse: {$pattern}");
            } catch (ParserException $e) {
                $this->fail("Comment pattern should parse: {$pattern}. Error: {$e->getMessage()}");
            }
        }
    }

    public function testAssertions(): void
    {
        $patterns = [
            '/(?=test)/',
            '/(?!test)/',
            '/(?<=foo)/',
            '/(?<!bar)/',
            '/\w+(?=\d)/',
            '/(?!abc)\w+/',
            '/(?<=start)test/',
            '/(?<!end)test/',
            '/(?=a)(?=b)/',
            '/(?!x)(?!y)/',
            '/test(?=ing|ed)/',
            '/(?<=foo|bar)test/',
            '/(?<!do|re)mi/',
            '/\w+(?!\d)/',
            '/(?<=\d{3})test/',
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->parser->parse($pattern);
                $this->assertNotNull($ast, "Assertion pattern should parse: {$pattern}");
                
                $hasLookaround = $this->hasGroupType($ast, GroupType::T_GROUP_LOOKAHEAD_POSITIVE)
                    || $this->hasGroupType($ast, GroupType::T_GROUP_LOOKAHEAD_NEGATIVE)
                    || $this->hasGroupType($ast, GroupType::T_GROUP_LOOKBEHIND_POSITIVE)
                    || $this->hasGroupType($ast, GroupType::T_GROUP_LOOKBEHIND_NEGATIVE);
                    
                $this->assertTrue($hasLookaround, "Pattern should contain assertion: {$pattern}");
            } catch (ParserException $e) {
                $this->fail("Assertion pattern should parse: {$pattern}. Error: {$e->getMessage()}");
            }
        }
    }

    public function testExtendedMode(): void
    {
        $patterns = [
            '/a b c/x',
            "/test  # comment\ning/x",
            "/\n  \w+  # word\n  \d+  # digit\n/x",
            '/a   b   c/x',
            "/(\n  foo  # first\n  |\n  bar  # second\n)/x",
            '/[ ] /x',
            '/\  /x',
            "/test\n\n\npattern/x",
            '/(?x: a b c )/',
            "/# start\ntest\n# end/x",
            "/\d+  # digits\n-\n\w+  # word/x",
            '/a#comment b/x',
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->parser->parse($pattern);
                $this->assertNotNull($ast, "Extended mode pattern should parse: {$pattern}");
            } catch (ParserException $e) {
                $this->fail("Extended mode pattern parsing failed: {$pattern}. Error: {$e->getMessage()}");
            }
        }
    }

    public function testPcreVerbs(): void
    {
        $patterns = [
            '/(*FAIL)/',
            '/(*ACCEPT)/',
            '/(*COMMIT)/',
            '/test(*SKIP)/',
            '/foo(*PRUNE)bar/',
            '/(*THEN)/',
            '/a(*MARK:label)b/',
            '/(*UTF8)pattern/',
            '/(*UCP)test/',
            '/(*CR)/',
            '/(*LF)/',
            '/(*CRLF)/',
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->parser->parse($pattern);
                $this->assertNotNull($ast, "PCRE verb pattern should parse: {$pattern}");
            } catch (ParserException $e) {
                $this->fail("PCRE verb pattern should parse: {$pattern}. Error: {$e->getMessage()}");
            }
        }
    }

    private function hasGroupType($node, GroupType $type): bool
    {
        if ($node instanceof \RegexParser\Node\GroupNode && $node->type === $type) {
            return true;
        }

        $properties = get_object_vars($node);
        foreach ($properties as $prop) {
            if ($prop instanceof \RegexParser\Node\NodeInterface) {
                if ($this->hasGroupType($prop, $type)) {
                    return true;
                }
            } elseif (is_array($prop)) {
                foreach ($prop as $item) {
                    if ($item instanceof \RegexParser\Node\NodeInterface) {
                        if ($this->hasGroupType($item, $type)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function hasQuantifierType($node, QuantifierType $type): bool
    {
        if ($node instanceof \RegexParser\Node\QuantifierNode && $node->type === $type) {
            return true;
        }

        $properties = get_object_vars($node);
        foreach ($properties as $prop) {
            if ($prop instanceof \RegexParser\Node\NodeInterface) {
                if ($this->hasQuantifierType($prop, $type)) {
                    return true;
                }
            } elseif (is_array($prop)) {
                foreach ($prop as $item) {
                    if ($item instanceof \RegexParser\Node\NodeInterface) {
                        if ($this->hasQuantifierType($item, $type)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }
}
