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

namespace RegexParser\NodeVisitor;

use RegexParser\Node;

/**
 * Lints regex patterns for semantic issues like useless flags.
 */
final class LinterNodeVisitor extends AbstractNodeVisitor
{
    /** @var list<string> */
    private array $warnings = [];
    private string $flags = '';

    /**
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    #[\Override]
    public function visitRegex(Node\RegexNode $node): Node\NodeInterface
    {
        $this->flags = $node->flags;
        $this->warnings = [];

        // Visit the pattern
        $node->pattern->accept($this);

        // Check flags
        $this->checkUselessFlags();

        return $node;
    }

    private function checkUselessFlags(): void
    {
        if (str_contains($this->flags, 'i') && !$this->hasCaseSensitiveChars) {
            $this->warnings[] = "Flag 'i' is useless: the pattern contains no case-sensitive characters.";
        }

        if (str_contains($this->flags, 's') && !$this->hasDots) {
            $this->warnings[] = "Flag 's' is useless: the pattern contains no dots.";
        }

        if (str_contains($this->flags, 'm') && !$this->hasAnchors) {
            $this->warnings[] = "Flag 'm' is useless: the pattern contains no anchors.";
        }
    }

    private bool $hasCaseSensitiveChars = false;
    private bool $hasDots = false;
    private bool $hasAnchors = false;

    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): Node\NodeInterface
    {
        if (preg_match('/[a-zA-Z]/', $node->value) > 0) {
            $this->hasCaseSensitiveChars = true;
        }
        return $node;
    }

    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): Node\NodeInterface
    {
        // Check if char class contains letters
        $expression = $node->expression;
        if ($expression instanceof Node\AlternationNode) {
            foreach ($expression->alternatives as $alt) {
                if ($this->charClassPartHasLetters($alt)) {
                    $this->hasCaseSensitiveChars = true;
                }
            }
        } elseif ($this->charClassPartHasLetters($expression)) {
            $this->hasCaseSensitiveChars = true;
        }
        return $node;
    }

    private function charClassPartHasLetters(Node\NodeInterface $node): bool
    {
        if ($node instanceof Node\LiteralNode && preg_match('/[a-zA-Z]/', $node->value) > 0) {
            return true;
        }
        if ($node instanceof Node\RangeNode) {
            return $this->rangeHasLetters($node);
        }
        // Other types like CharTypeNode might have letters, but for simplicity, assume not
        return false;
    }

    private function rangeHasLetters(Node\RangeNode $node): bool
    {
        $start = $node->start instanceof Node\LiteralNode ? $node->start->value : '';
        $end = $node->end instanceof Node\LiteralNode ? $node->end->value : '';
        return preg_match('/[a-zA-Z]/', $start . $end) > 0;
    }

    #[\Override]
    public function visitDot(Node\DotNode $node): Node\NodeInterface
    {
        $this->hasDots = true;
        return $node;
    }

    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): Node\NodeInterface
    {
        if ($node->value === '^' || $node->value === '$') {
            $this->hasAnchors = true;
        }
        return $node;
    }

    // Implement other visit methods as no-op
    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): Node\NodeInterface
    {
        foreach ($node->alternatives as $alt) {
            $alt->accept($this);
        }
        return $node;
    }

    #[\Override]
    public function visitSequence(Node\SequenceNode $node): Node\NodeInterface
    {
        foreach ($node->children as $child) {
            $child->accept($this);
        }
        return $node;
    }

    #[\Override]
    public function visitGroup(Node\GroupNode $node): Node\NodeInterface
    {
        $node->child->accept($this);
        return $node;
    }

    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): Node\NodeInterface
    {
        $node->node->accept($this);
        return $node;
    }

    // Add other visit methods as needed, default to no-op
}