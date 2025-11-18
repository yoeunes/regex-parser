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

namespace RegexParser\Builder;

use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\SequenceNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;

/**
 * A fluent, object-oriented builder for programmatically creating regex patterns.
 * This helps create complex, readable, and safe regexes without manual escaping.
 *
 * @property RegexBuilder $or Adds an alternation.
 */
class RegexBuilder
{
    /**
     * @var NodeInterface[]
     */
    private array $nodes = [];

    /**
     * @var array<NodeInterface[]>
     */
    private array $alternatives = [];

    private string $flags = '';
    private string $delimiter = '/';

    public function __construct()
    {
        // The builder starts with one "alternative" branch
        $this->newAlternative();
    }

    /**
     * Magic getter for fluent alternation.
     */
    public function __get(string $name): self
    {
        if ('or' === $name) {
            return $this->newAlternative();
        }

        throw new \BadMethodCallException(\sprintf('Property "%s" does not exist.', $name));
    }

    /**
     * Adds a literal string, escaping all meta-characters.
     */
    public function literal(string $value): self
    {
        if ('' === $value) {
            return $this;
        }

        // We only escape meta-characters that are *not* escaped by the compiler.
        // The compiler handles [, ], (, ), etc.
        // We just need to add the literals.
        foreach (mb_str_split($value) as $char) {
            $this->nodes[] = new LiteralNode($char, 0, 0);
        }

        return $this;
    }

    /**
     * Adds a raw, unescaped literal. Use with caution.
     */
    public function raw(string $value): self
    {
        $this->nodes[] = new LiteralNode($value, 0, 0);

        return $this;
    }

    public function digit(): self
    {
        $this->nodes[] = new CharTypeNode('d', 0, 0);

        return $this;
    }

    public function notDigit(): self
    {
        $this->nodes[] = new CharTypeNode('D', 0, 0);

        return $this;
    }

    public function whitespace(): self
    {
        $this->nodes[] = new CharTypeNode('s', 0, 0);

        return $this;
    }

    public function notWhitespace(): self
    {
        $this->nodes[] = new CharTypeNode('S', 0, 0);

        return $this;
    }

    public function word(): self
    {
        $this->nodes[] = new CharTypeNode('w', 0, 0);

        return $this;
    }

    public function notWord(): self
    {
        $this->nodes[] = new CharTypeNode('W', 0, 0);

        return $this;
    }

    public function any(): self
    {
        $this->nodes[] = new DotNode(0, 0);

        return $this;
    }

    public function startOfLine(): self
    {
        $this->nodes[] = new AnchorNode('^', 0, 0);

        return $this;
    }

    public function endOfLine(): self
    {
        $this->nodes[] = new AnchorNode('$', 0, 0);

        return $this;
    }

    public function wordBoundary(): self
    {
        $this->nodes[] = new AssertionNode('b', 0, 0);

        return $this;
    }

    /**
     * @param \Closure(self): void $callback
     */
    public function group(\Closure $callback, bool $capture = true): self
    {
        $builder = new self();
        $callback($builder);

        $this->nodes[] = new GroupNode(
            $builder->build(),
            $capture ? GroupType::T_GROUP_CAPTURING : GroupType::T_GROUP_NON_CAPTURING,
            null,
            null,
            0,
            0
        );

        return $this;
    }

    /**
     * @param \Closure(self): void $callback
     */
    public function namedGroup(string $name, \Closure $callback): self
    {
        $builder = new self();
        $callback($builder);

        $this->nodes[] = new GroupNode(
            $builder->build(),
            GroupType::T_GROUP_NAMED,
            $name,
            null,
            0,
            0
        );

        return $this;
    }

    /**
     * @param \Closure(CharClassBuilder): void $callback
     */
    public function charClass(\Closure $callback, bool $negated = false): self
    {
        $builder = new CharClassBuilder();
        $callback($builder);

        $this->nodes[] = new CharClassNode($builder->build(), $negated, 0, 0);

        return $this;
    }

    public function zeroOrMore(bool $lazy = false): self
    {
        return $this->quantify('*', $lazy ? QuantifierType::T_LAZY : QuantifierType::T_GREEDY);
    }

    public function oneOrMore(bool $lazy = false): self
    {
        return $this->quantify('+', $lazy ? QuantifierType::T_LAZY : QuantifierType::T_GREEDY);
    }

    public function optional(bool $lazy = false): self
    {
        return $this->quantify('?', $lazy ? QuantifierType::T_LAZY : QuantifierType::T_GREEDY);
    }

    public function exactly(int $count): self
    {
        return $this->quantify(\sprintf('{%d}', $count), QuantifierType::T_GREEDY);
    }

    public function atLeast(int $count, bool $lazy = false): self
    {
        return $this->quantify(\sprintf('{%d,}', $count), $lazy ? QuantifierType::T_LAZY : QuantifierType::T_GREEDY);
    }

    public function between(int $min, int $max, bool $lazy = false): self
    {
        return $this->quantify(\sprintf('{%d,%d}', $min, $max), $lazy ? QuantifierType::T_LAZY : QuantifierType::T_GREEDY);
    }

    public function withFlags(string $flags): self
    {
        $this->flags = $flags;

        return $this;
    }

    public function withDelimiter(string $delimiter): self
    {
        if (1 !== \strlen($delimiter)) {
            throw new \InvalidArgumentException('Delimiter must be a single character.');
        }
        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * Compiles the built AST into a regex string.
     */
    public function compile(): string
    {
        $patternNode = $this->build();

        // Use the library's own compiler
        $compiler = new CompilerNodeVisitor();

        // We can't use $compiler->visitRegex() as that expects a RegexNode.
        // We compile the pattern part and wrap it manually.
        $patternString = $patternNode->accept($compiler);

        $map = [')' => '(', ']' => '[', '}' => '{', '>' => '<'];
        $closingDelimiter = $map[$this->delimiter] ?? $this->delimiter;

        return $this->delimiter.$patternString.$closingDelimiter.$this->flags;
    }

    private function newAlternative(): self
    {
        // Build the current sequence
        if (0 !== \count($this->nodes)) {
            $this->alternatives[] = $this->nodes;
        }
        // Start a new sequence
        $this->nodes = [];

        return $this;
    }

    /**
     * Applies a quantifier to the previous node.
     */
    private function quantify(string $quantifier, QuantifierType $type): self
    {
        $lastNode = array_pop($this->nodes);
        if (null === $lastNode) {
            throw new \LogicException('Cannot apply quantifier to an empty expression.');
        }

        $this->nodes[] = new QuantifierNode($lastNode, $quantifier, $type, 0, 0);

        return $this;
    }

    /**
     * Builds the final AST node for the current builder state.
     */
    private function build(): NodeInterface
    {
        // Add the last sequence of nodes
        $this->alternatives[] = $this->nodes;

        // Filter out empty alternative branches
        $this->alternatives = array_filter($this->alternatives, fn ($nodes) => 0 !== \count($nodes));

        $sequences = [];
        foreach ($this->alternatives as $nodes) {
            $sequences[] = 1 === \count($nodes) ? $nodes[0] : new SequenceNode($nodes, 0, 0);
        }

        if (0 === \count($sequences)) {
            return new LiteralNode('', 0, 0); // Empty pattern
        }

        if (1 === \count($sequences)) {
            return $sequences[0]; // Single sequence
        }

        return new AlternationNode($sequences, 0, 0);
    }
}
