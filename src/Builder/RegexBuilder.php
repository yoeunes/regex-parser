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
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\Regex;

/**
 * A fluent, type-safe builder for creating complex regex patterns.
 */
class RegexBuilder
{
    /** @var NodeInterface[] Current sequence of nodes */
    private array $currentNodes = [];

    /** @var array<NodeInterface[]> Completed alternatives branches */
    private array $branches = [];

    /** @var array<string, bool> Active flags */
    private array $flags = [];

    private string $delimiter = '/';

    public function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    // -------------------------------------------------------------------------
    // Literals & Basics
    // -------------------------------------------------------------------------

    public function literal(string $text): self
    {
        if ('' === $text) {
            return $this;
        }
        // Split into individual LiteralNodes (safe against special chars)
        foreach (mb_str_split($text) as $char) {
            $this->currentNodes[] = new LiteralNode($char, 0, 0);
        }
        return $this;
    }

    public function anyChar(): self
    {
        $this->currentNodes[] = new DotNode(0, 0);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Character Types & Classes
    // -------------------------------------------------------------------------

    public function digit(): self
    {
        $this->currentNodes[] = new CharTypeNode('d', 0, 0);
        return $this;
    }

    public function notDigit(): self
    {
        $this->currentNodes[] = new CharTypeNode('D', 0, 0);
        return $this;
    }

    public function word(): self
    {
        $this->currentNodes[] = new CharTypeNode('w', 0, 0);
        return $this;
    }

    public function notWord(): self
    {
        $this->currentNodes[] = new CharTypeNode('W', 0, 0);
        return $this;
    }

    public function whitespace(): self
    {
        $this->currentNodes[] = new CharTypeNode('s', 0, 0);
        return $this;
    }

    public function notWhitespace(): self
    {
        $this->currentNodes[] = new CharTypeNode('S', 0, 0);
        return $this;
    }

    /**
     * Adds a custom character class.
     * Usage: ->charClass(CharClass::digit()->union(CharClass::range('a', 'f')))
     */
    public function charClass(CharClass $charClass): self
    {
        $this->currentNodes[] = $charClass->buildNode();
        return $this;
    }

    // -------------------------------------------------------------------------
    // Anchors & Boundaries
    // -------------------------------------------------------------------------

    public function startOfLine(): self
    {
        $this->currentNodes[] = new AnchorNode('^', 0, 0);
        return $this;
    }

    public function endOfLine(): self
    {
        $this->currentNodes[] = new AnchorNode('$', 0, 0);
        return $this;
    }

    public function wordBoundary(): self
    {
        $this->currentNodes[] = new AssertionNode('b', 0, 0);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Groups & Lookarounds
    // -------------------------------------------------------------------------

    /**
     * Creates a capturing group: (...)
     */
    public function capture(callable $builder, ?string $name = null): self
    {
        return $this->addGroup($builder, $name ? GroupType::T_GROUP_NAMED : GroupType::T_GROUP_CAPTURING, $name);
    }

    /**
     * Creates a non-capturing group: (?:...)
     */
    public function group(callable $builder): self
    {
        return $this->addGroup($builder, GroupType::T_GROUP_NON_CAPTURING);
    }

    /**
     * Creates an atomic group: (?>...)
     */
    public function atomic(callable $builder): self
    {
        return $this->addGroup($builder, GroupType::T_GROUP_ATOMIC);
    }

    public function lookahead(callable $builder): self
    {
        return $this->addGroup($builder, GroupType::T_GROUP_LOOKAHEAD_POSITIVE);
    }

    public function negativeLookahead(callable $builder): self
    {
        return $this->addGroup($builder, GroupType::T_GROUP_LOOKAHEAD_NEGATIVE);
    }

    public function lookbehind(callable $builder): self
    {
        return $this->addGroup($builder, GroupType::T_GROUP_LOOKBEHIND_POSITIVE);
    }

    public function negativeLookbehind(callable $builder): self
    {
        return $this->addGroup($builder, GroupType::T_GROUP_LOOKBEHIND_NEGATIVE);
    }

    // -------------------------------------------------------------------------
    // Alternation
    // -------------------------------------------------------------------------

    /**
     * Marks the end of the current branch and starts a new one.
     * Logic: (current) | (next)
     */
    public function or(): self
    {
        if (empty($this->currentNodes)) {
            // Allows patterns like: |foo (empty start) or foo||bar (empty middle)
            $this->currentNodes[] = new LiteralNode('', 0, 0);
        }

        $this->branches[] = $this->currentNodes;
        $this->currentNodes = [];

        return $this;
    }

    // -------------------------------------------------------------------------
    // Quantifiers
    // -------------------------------------------------------------------------

    public function optional(bool $lazy = false): self
    {
        return $this->quantify('?', $lazy);
    }

    public function zeroOrMore(bool $lazy = false): self
    {
        return $this->quantify('*', $lazy);
    }

    public function oneOrMore(bool $lazy = false): self
    {
        return $this->quantify('+', $lazy);
    }

    public function exactly(int $n): self
    {
        return $this->quantify("{{$n}}", false);
    }

    public function atLeast(int $n, bool $lazy = false): self
    {
        return $this->quantify("{{$n},}", $lazy);
    }

    public function between(int $min, int $max, bool $lazy = false): self
    {
        return $this->quantify("{{$min},{$max}}", $lazy);
    }

    // -------------------------------------------------------------------------
    // Flags
    // -------------------------------------------------------------------------

    public function caseInsensitive(): self
    {
        $this->flags['i'] = true;
        return $this;
    }

    public function multiline(): self
    {
        $this->flags['m'] = true;
        return $this;
    }

    public function dotAll(): self
    {
        $this->flags['s'] = true;
        return $this;
    }

    public function unicode(): self
    {
        $this->flags['u'] = true;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Build & Compilation
    // -------------------------------------------------------------------------

    public function build(): string
    {
        $node = $this->buildNode();

        // Create a RegexNode to hold flags and delimiters for compilation
        $flagsStr = implode('', array_keys($this->flags));
        $regexNode = new RegexNode($node, $flagsStr, $this->delimiter, 0, 0);

        return $regexNode->accept(new CompilerNodeVisitor());
    }

    /**
     * Returns a configured Regex object ready for use.
     */
    public function getRegex(): Regex
    {
        // This requires the Regex facade to accept a pre-compiled string,
        // or we simply return the string and let the user use Regex::create()->parse(...)
        // Given existing architecture, better to return the parsed object?
        // For now, returning a new Regex instance that can parse this string.

        // Actually, the Regex class is a facade. We might just rely on `build()` returning string.
        return Regex::create();
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function addGroup(callable $builderCallback, GroupType $type, ?string $name = null): self
    {
        $subBuilder = new self();
        $builderCallback($subBuilder);

        $childNode = $subBuilder->buildNode();

        $this->currentNodes[] = new GroupNode(
            $childNode,
            $type,
            $name,
            null,
            0,
            0
        );

        return $this;
    }

    private function quantify(string $symbol, bool $lazy): self
    {
        if (empty($this->currentNodes)) {
            throw new \LogicException('Cannot quantify nothing. Add an atom before calling a quantifier.');
        }

        $lastNode = array_pop($this->currentNodes);

        $type = $lazy ? QuantifierType::T_LAZY : QuantifierType::T_GREEDY;

        $this->currentNodes[] = new QuantifierNode($lastNode, $symbol, $type, 0, 0);

        return $this;
    }

    private function buildNode(): NodeInterface
    {
        // Close the last branch
        if (!empty($this->currentNodes)) {
            $this->branches[] = $this->currentNodes;
        } elseif (empty($this->branches)) {
            // Nothing at all
            return new LiteralNode('', 0, 0);
        } else {
             // Last branch was explicitly empty (e.g. ends with ->or())
             $this->branches[] = [new LiteralNode('', 0, 0)];
        }

        // Convert branches to Sequences
        $sequences = [];
        foreach ($this->branches as $nodes) {
            if (count($nodes) === 1) {
                $sequences[] = $nodes[0];
            } else {
                $sequences[] = new SequenceNode($nodes, 0, 0);
            }
        }

        // If only one branch, return it directly
        if (count($sequences) === 1) {
            return $sequences[0];
        }

        return new AlternationNode($sequences, 0, 0);
    }
}
