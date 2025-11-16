<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\AnchorNode;
use RegexParser\Ast\BackrefNode;
use RegexParser\Ast\CharClassNode;
use RegexParser\Ast\CharTypeNode;
use RegexParser\Ast\DotNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\GroupType;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\PosixClassNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\QuantifierType;
use RegexParser\Ast\RangeNode;
use RegexParser\Ast\RegexNode;
use RegexParser\Ast\SequenceNode;
use RegexParser\Ast\UnicodeNode;
use RegexParser\Exception\ParserException;

/**
 * A visitor that validates semantic rules of the regex AST.
 * (e.g., quantifier ranges, catastrophic backtracking).
 *
 * @implements VisitorInterface<void>
 */
class ValidatorVisitor implements VisitorInterface
{
    /**
     * Tracks the depth of nested quantifiers to detect catastrophic backtracking.
     */
    private int $quantifierDepth = 0;

    /**
     * Tracks if we are inside a lookbehind, where quantifiers are limited.
     */
    private bool $inLookbehind = false;

    public function visitRegex(RegexNode $node): void
    {
        $node->pattern->accept($this);

        // Flags are now pre-validated by the Parser's extractPatternAndFlags
    }

    public function visitAlternation(AlternationNode $node): void
    {
        foreach ($node->alternatives as $alt) {
            $alt->accept($this);
        }
    }

    public function visitSequence(SequenceNode $node): void
    {
        foreach ($node->children as $child) {
            $child->accept($this);
        }
    }

    public function visitGroup(GroupNode $node): void
    {
        $wasInLookbehind = $this->inLookbehind;

        if (\in_array($node->type, [GroupType::T_GROUP_LOOKBEHIND_POSITIVE, GroupType::T_GROUP_LOOKBEHIND_NEGATIVE], true)) {
            $this->inLookbehind = true;
        }

        $node->child->accept($this);

        $this->inLookbehind = $wasInLookbehind; // Restore state
    }

    public function visitQuantifier(QuantifierNode $node): void
    {
        // 1. Validate quantifier syntax
        if (!\in_array($node->quantifier, ['*', '+', '?'], true)) {
            if (preg_match('/^{\d+(,\d*)?}$/', $node->quantifier)) {
                // Check n <= m
                $parts = explode(',', trim($node->quantifier, '{}'));
                if (2 === \count($parts) && '' !== $parts[1] && (int) $parts[0] > (int) $parts[1]) {
                    throw new ParserException(\sprintf('Invalid quantifier range "%s": min > max', $node->quantifier));
                }
            } else {
                // This should be caught by the lexer, but good to double-check
                throw new ParserException('Invalid quantifier: '.$node->quantifier);
            }
        }

        // 2. Validate quantifiers inside lookbehinds
        if ($this->inLookbehind && QuantifierType::T_GREEDY !== $node->type && preg_match('/^[\*\+]$|{.*,}/', $node->quantifier)) {
            throw new ParserException(\sprintf('Variable-length quantifiers (%s) are not allowed in lookbehinds.', $node->quantifier));
        }

        // 3. Check for Catastrophic Backtracking (Nested Quantifiers)
        if ($this->quantifierDepth > 0) {
            // This is a simple but effective check for (a+)*, (a*)*, (a|b*)* etc.
            throw new ParserException('Potential catastrophic backtracking: nested quantifiers detected.');
        }

        ++$this->quantifierDepth;
        $node->node->accept($this);
        --$this->quantifierDepth;
    }

    public function visitLiteral(LiteralNode $node): void
    {
        // No validation needed for literals
    }

    public function visitCharType(CharTypeNode $node): void
    {
        // No validation needed for char types
    }

    public function visitDot(DotNode $node): void
    {
        // No validation needed for dot
    }

    public function visitAnchor(AnchorNode $node): void
    {
        // No validation needed for anchors
    }

    public function visitCharClass(CharClassNode $node): void
    {
        foreach ($node->parts as $part) {
            // Note: We don't need to check for nested quantifiers here,
            // as the grammar (and PCRE) forbids them inside [].
            $part->accept($this);
        }
    }

    public function visitRange(RangeNode $node): void
    {
        // A range must be between two literals
        if (!$node->start instanceof LiteralNode || !$node->end instanceof LiteralNode) {
            throw new ParserException('Invalid range: ranges must be between literal characters (e.g., "a-z").');
        }

        // Check ASCII values
        if (\ord($node->start->value) > \ord($node->end->value)) {
            throw new ParserException(\sprintf('Invalid range "%s-%s": start character comes after end character.', $node->start->value, $node->end->value));
        }
    }

    public function visitBackref(BackrefNode $node): void
    {
        // TODO: Check if ref number <= group count (requires group counting in visitor)
        // For v1.0, just accept
    }

    public function visitUnicode(UnicodeNode $node): void
    {
        // Validate hex code is valid Unicode
        if (preg_match('/^\\\\x([0-9a-fA-F]{2})$/', $node->code, $matches)) {
            $code = hexdec($matches[1]);
            if ($code > 0x10FFFF) {
                throw new ParserException('Invalid Unicode codepoint');
            }
        } // Similar for \u
    }

    public function visitPosixClass(PosixClassNode $node): void
    {
        $valid = ['alnum', 'alpha', 'ascii', 'blank', 'cntrl', 'digit', 'graph', 'lower', 'print', 'punct', 'space', 'upper', 'word', 'xdigit'];
        if (!in_array(strtolower($node->class), $valid)) {
            throw new ParserException('Invalid POSIX class: ' . $node->class);
        }
    }
}
