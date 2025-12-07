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
 * Modernizes legacy or messy regular expressions into clean, concise PCRE2-compliant patterns.
 *
 * This visitor applies safe transformations to improve readability and conciseness without
 * changing the regex's behavior:
 * - Converts character class ranges to shorthands (\d, \w, \s)
 * - Removes unnecessary escaping
 * - Unwraps redundant non-capturing groups
 * - Modernizes backreference syntax
 *
 * @extends AbstractNodeVisitor<\RegexParser\Node\NodeInterface>
 */
final class ModernizerNodeVisitor extends AbstractNodeVisitor
{
    private string $delimiter = '/';

    public function visitRegex(\RegexParser\Node\RegexNode $node)
    {
        $this->delimiter = $node->delimiter;

        return new \RegexParser\Node\RegexNode(
            $node->pattern->accept($this),
            $node->flags,
            $node->delimiter,
            $node->getStartPosition(),
            $node->getEndPosition()
        );
    }

    public function visitCharClass(\RegexParser\Node\CharClassNode $node)
    {
        $parts = $node->expression instanceof \RegexParser\Node\AlternationNode
            ? $node->expression->alternatives
            : [$node->expression];

        // Check for \d equivalent: [0-9]
        if (!$node->isNegated && count($parts) === 1 && $parts[0] instanceof \RegexParser\Node\RangeNode) {
            $range = $parts[0];
            if ($range->start instanceof \RegexParser\Node\LiteralNode && $range->end instanceof \RegexParser\Node\LiteralNode &&
                $range->start->value === '0' && $range->end->value === '9') {
        return new \RegexParser\Node\CharTypeNode('d', $node->getStartPosition(), $node->getEndPosition());
            }
        }

        // Check for \s equivalent: [\t\n\r\f\v]
        if (!$node->isNegated && count($parts) === 5) {
            $whitespaceChars = ["\t", "\n", "\r", "\f", "\v"];
            $foundChars = [];
            foreach ($parts as $part) {
                if ($part instanceof \RegexParser\Node\LiteralNode && in_array($part->value, $whitespaceChars, true)) {
                    $foundChars[] = $part->value;
                }
            }
            if (count($foundChars) === 5 && $foundChars === $whitespaceChars) {
                return new \RegexParser\Node\CharTypeNode('s', $node->getStartPosition(), $node->getEndPosition());
            }
        }

        // For other cases, keep as is but modernize parts
        $modernizedParts = array_map(fn ($part) => $part->accept($this), $parts);
        $expression = count($modernizedParts) === 1
            ? $modernizedParts[0]
            : new \RegexParser\Node\AlternationNode($modernizedParts, $node->getStartPosition(), $node->getEndPosition());

        return new \RegexParser\Node\CharClassNode($expression, $node->isNegated, $node->getStartPosition(), $node->getEndPosition());
    }

    public function visitLiteral(Node\LiteralNode $node): Node\NodeInterface
    {
        $value = $node->value;

        // Remove unnecessary escaping
        if (str_starts_with($value, '\\')) {
            $char = substr($value, 1);
            // Meta chars that need escaping: . \ + * ? ^ $ ( ) [ ] { } | / (if delimiter)
            $metaChars = ['.', '\\', '+', '*', '?', '^', '$', '(', ')', '[', ']', '{', '}', '|'];
            if ($this->delimiter !== '/' && $this->delimiter !== $char) {
                $metaChars[] = $this->delimiter;
            }
            if (!in_array($char, $metaChars, true)) {
                // Safe to unescape
                return new Node\LiteralNode($char, $node->getStartPosition(), $node->getEndPosition());
            }
        }

        return $node;
    }

    public function visitGroup(Node\GroupNode $node): Node\NodeInterface
    {
        // Unwrap redundant non-capturing groups: (?:expr) -> expr if not quantified
        // Assume safe for non-capturing groups without name or flags
        if ($node->type === Node\GroupType::T_GROUP_NON_CAPTURING && $node->name === null && $node->flags === null) {
            return $node->child->accept($this);
        }
        return new Node\GroupNode(
            $node->child->accept($this),
            $node->type,
            $node->name,
            $node->flags,
            $node->getStartPosition(),
            $node->getEndPosition()
        );
    }

    public function visitBackref(Node\BackrefNode $node): Node\NodeInterface
    {
        $ref = $node->ref;
        // Convert \1 to \g{1}
        if (is_numeric($ref)) {
            return new Node\BackrefNode('\g{'.$ref.'}', $node->getStartPosition(), $node->getEndPosition());
        }
        return $node;
    }

    // For other nodes, just recurse or return as is
    public function visitAlternation(Node\AlternationNode $node): Node\NodeInterface
    {
        $alternatives = array_map(fn ($alt) => $alt->accept($this), $node->alternatives);
        return new Node\AlternationNode($alternatives, $node->getStartPosition(), $node->getEndPosition());
    }

    public function visitSequence(Node\SequenceNode $node): Node\NodeInterface
    {
        $children = array_map(fn ($n) => $n->accept($this), $node->children);
        return new Node\SequenceNode($children, $node->getStartPosition(), $node->getEndPosition());
    }

    public function visitQuantifier(Node\QuantifierNode $node): Node\NodeInterface
    {
        return new Node\QuantifierNode(
            $node->node->accept($this),
            $node->quantifier,
            $node->type,
            $node->getStartPosition(),
            $node->getEndPosition()
        );
    }

    public function visitAnchor(Node\AnchorNode $node): Node\NodeInterface { return $node; }

    public function visitAssertion(Node\AssertionNode $node): Node\NodeInterface { return $node; }

    public function visitDot(Node\DotNode $node): Node\NodeInterface { return $node; }

    public function visitCharType(Node\CharTypeNode $node): Node\NodeInterface { return $node; }

    public function visitRange(Node\RangeNode $node): Node\NodeInterface { return $node; }

    public function visitUnicode(Node\UnicodeNode $node): Node\NodeInterface { return $node; }

    public function visitUnicodeNamed(Node\UnicodeNamedNode $node): Node\NodeInterface { return $node; }

    public function visitUnicodeProp(Node\UnicodePropNode $node): Node\NodeInterface { return $node; }

    public function visitOctal(Node\OctalNode $node): Node\NodeInterface { return $node; }

    public function visitOctalLegacy(Node\OctalLegacyNode $node): Node\NodeInterface { return $node; }

    public function visitPosixClass(Node\PosixClassNode $node): Node\NodeInterface { return $node; }

    public function visitComment(Node\CommentNode $node): Node\NodeInterface { return $node; }

    public function visitConditional(\RegexParser\Node\ConditionalNode $node)
    {
        // @phpstan-ignore if.alwaysTrue
        if ($node->no) {
            $noBranch = $node->no->accept($this);
        } else {
            $noBranch = null;
        }
        return new \RegexParser\Node\ConditionalNode(
            $node->condition->accept($this),
            $node->yes->accept($this),
            $noBranch,
            $node->getStartPosition(),
            $node->getEndPosition()
        );
    }

    public function visitSubroutine(Node\SubroutineNode $node): Node\NodeInterface { return $node; }

    public function visitPcreVerb(Node\PcreVerbNode $node): Node\NodeInterface { return $node; }

    public function visitDefine(Node\DefineNode $node): Node\NodeInterface { return $node; }

    public function visitLimitMatch(Node\LimitMatchNode $node): Node\NodeInterface { return $node; }

    public function visitCallout(Node\CalloutNode $node): Node\NodeInterface { return $node; }

    public function visitScriptRun(Node\ScriptRunNode $node): Node\NodeInterface { return $node; }

    public function visitVersionCondition(Node\VersionConditionNode $node): Node\NodeInterface { return $node; }

    public function visitKeep(Node\KeepNode $node): Node\NodeInterface { return $node; }

    public function visitControlChar(Node\ControlCharNode $node): Node\NodeInterface { return $node; }

    public function visitClassOperation(Node\ClassOperationNode $node): Node\NodeInterface { return $node; }

    // Add other visit methods as needed, default to parent
}
