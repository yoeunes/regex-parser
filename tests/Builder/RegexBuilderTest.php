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

namespace RegexParser\Tests\Builder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\Builder\RegexBuilder;

class RegexBuilderTest extends TestCase
{
    public function test_build_simple_sequence(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->startOfLine()
            ->literal('http')
            ->optional()
            ->literal('://')
            ->any()
            ->oneOrMore()
            ->endOfLine()
            ->compile();

        // The builder automatically escapes literals, so does / become \/ unless the delimiter changes?
        // Your current compiler doesn't escape '/' by default if it's not the delimiter.
        // Let's check the expected result.

        // ^http?://.+$
        // Note: your literals escape all meta chars. : and / are not meta.
        $this->assertSame('/^http?:\/\/.+$/', $regex);
    }

    public function test_build_alternation(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->literal('cat')
            ->or
            ->literal('dog')
            ->compile();

        $this->assertSame('/cat|dog/', $regex);
    }

    public function test_build_char_class(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->charClass(function ($c): void {
                $c->range('a', 'z')
                    ->digit();
            })
            ->oneOrMore()
            ->compile();

        $this->assertSame('/[a-z\d]+/', $regex);
    }

    public function test_build_named_group(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->namedGroup('id', function ($b): void {
                $b->digit()->oneOrMore();
            })
            ->withFlags('i')
            ->compile();

        $this->assertSame('/(?<id>\d+)/i', $regex);
    }

    public function test_safe_escaping_in_literal(): void
    {
        $builder = new RegexBuilder();
        // literal() must escape special characters
        $regex = $builder->literal('a.b*c')->compile();

        $this->assertSame('/a\.b\*c/', $regex);
    }

    public function test_raw_method_does_not_escape_meta_chars(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder->raw('\w+?')->compile(); // Should compile literally

        $this->assertSame('/\w+?/', $regex);
    }

    public function test_raw_method_adds_literal_for_simple_string(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder->raw('raw string')->compile();

        $this->assertSame('/raw string/', $regex);
    }

    public function test_group_without_capture(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder->group(function ($b): void {
            $b->literal('test');
        }, false)->compile();

        $this->assertStringContainsString('(?:test)', $regex);
        $this->assertStringNotContainsString('(?<', $regex);
    }

    /**
     * @param array<int|bool> $args
     */
    #[DataProvider('quantifierProvider')]
    public function test_specific_quantifiers(
        string $method,
        array $args,
        string $expectedQuantifier,
        string $expectedRegex
    ): void {
        $builder = new RegexBuilder();
        $builder->literal('x');

        $builder->{$method}(...$args);
        $regex = $builder->compile();

        $this->assertSame($expectedRegex, $regex);

        // Assertions plus détaillées sur la structure AST (si nécessaire, mais souvent suffisant de vérifier la chaîne)
        if ('?' !== $expectedQuantifier) {
            $this->assertStringContainsString($expectedQuantifier, $regex);
        }
    }

    public static function quantifierProvider(): \Iterator
    {
        // Méthodes pour zeroOrMore, oneOrMore, optional sont déjà testées implicitement. Ajout de tests pour les formes spécifiques.
        yield 'zero_or_more_lazy' => ['zeroOrMore', [true], '*?', '/x*?/'];
        yield 'one_or_more_lazy' => ['oneOrMore', [true], '+?', '/x+?/'];
        yield 'optional_lazy' => ['optional', [true], '??', '/x??/'];

        yield 'exactly_3' => ['exactly', [3], '{3}', '/x{3}/'];
        yield 'at_least_2' => ['atLeast', [2], '{2,}', '/x{2,}/'];
        yield 'at_least_2_lazy' => ['atLeast', [2, true], '{2,}?', '/x{2,}?/'];
        yield 'between_1_and_5' => ['between', [1, 5], '{1,5}', '/x{1,5}/'];
        yield 'between_1_and_5_possessive' => ['between', [1, 5, false], '{1,5}', '/x{1,5}/'];
    }

    public function test_with_delimiter(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->withDelimiter('#')
            ->literal('a|b')
            ->compile();

        $this->assertSame('#a|b#', $regex);
    }

    public function test_with_flags(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->withFlags('ms')
            ->literal('a')
            ->compile();

        $this->assertSame('/a/ms', $regex);
    }

    public function test_quantify_throws_on_empty_nodes(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot apply quantifier to an empty expression.');

        $builder = new RegexBuilder();
        $builder->oneOrMore();
    }

    public function test_all_fluent_methods(): void
    {
        $builder = new RegexBuilder();

        $regex = $builder
            ->startOfLine()
            ->digit()
            ->notDigit()
            ->whitespace()
            ->notWhitespace()
            ->word()
            ->notWord()
            ->any()
            ->wordBoundary()
            ->literal('a')
            ->exactly(3)
            ->literal('b')
            ->atLeast(2, true) // Lazy
            ->literal('c')
            ->between(1, 3, true) // Lazy
            ->endOfLine()
            ->compile();

        $this->assertSame('/^\d\D\s\S\w\W.\ba{3}b{2,}?c{1,3}?$/', $regex);
    }

    public function test_char_class_builder_coverage(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->charClass(function ($c) {
                $c->digit()
                  ->notDigit()
                  ->whitespace()
                  ->notWhitespace()
                  ->word()
                  ->notWord()
                  ->posix('alnum');
            })
            ->compile();

        $this->assertSame('/[\d\D\s\S\w\W[[:alnum:]]]/', $regex);
    }

    public function test_alternation_via_property(): void
    {
        $builder = new RegexBuilder();
        $regex = $builder
            ->literal('a')
            ->or // Magic getter
            ->literal('b')
            ->compile();

        $this->assertSame('/a|b/', $regex);
    }

    public function test_getter_throws_on_unknown_property(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $builder = new RegexBuilder();
        $builder->invalidProperty;
    }

    public function test_invalid_delimiter_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $builder = new RegexBuilder();
        $builder->withDelimiter('XX');
    }
}
