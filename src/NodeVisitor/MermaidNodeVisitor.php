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
 * Generates a Mermaid.js flowchart to visualize the regex structure.
 *
 * Purpose: This visitor is a powerful debugging and documentation tool that translates
 * the AST into a visual flowchart using Mermaid.js syntax. It's the engine behind the
 * `Regex::parse()` + MermaidNodeVisitor usage. For contributors, this class is an excellent example of
 * how to perform a complex transformation on the AST to produce a structured, text-based
 * output. Each `visit` method is responsible for creating the correct Mermaid.js syntax
 * for a specific AST node.
 *
 * @extends AbstractNodeVisitor<string>
 */
final class MermaidNodeVisitor extends AbstractNodeVisitor
{
    private int $nodeCounter = 0;

    /**
     * @var array<string>
     */
    private array $lines = [];

    /**
     * Generates the graph for the root `RegexNode`.
     *
     * Purpose: This is the entry point for the graph generation. It initializes the
     * Mermaid.js graph definition, creates the root node representing the entire
     * regex (including its flags), and then recursively calls the visitor on the
     * main pattern.
     *
     * @param Node\RegexNode $node the root node of the AST
     *
     * @return string The complete Mermaid.js graph definition.
     */
    #[\Override]
    public function visitRegex(Node\RegexNode $node): string
    {
        $this->nodeCounter = 0;
        $this->lines = [];
        $this->lines[] = 'graph TD;';

        $nodeId = $this->nextNodeId();
        $flags = $node->flags ?: 'none';
        $this->lines[] = \sprintf('    %s["Regex: %s"]', $nodeId, $this->escape($flags));

        $childId = $node->pattern->accept($this);
        $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);

        return implode("\n", $this->lines);
    }

    /**
     * Generates the graph node for an `AlternationNode`.
     *
     * Purpose: This method creates a diamond-shaped "Alternation" node in the graph
     * and then draws arrows from it to each of its alternative branches, clearly
     * visualizing the "either/or" logic.
     *
     * @param Node\AlternationNode $node the alternation node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s{"Alternation"}', $nodeId);

        foreach ($node->alternatives as $child) {
            $childId = $child->accept($this);
            $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);
        }

        return $nodeId;
    }

    /**
     * Generates the graph node for a `SequenceNode`.
     *
     * Purpose: This method creates a "Sequence" node and then draws arrows to each
     * of its children in order, visualizing the sequential nature of the components.
     *
     * @param Node\SequenceNode $node the sequence node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitSequence(Node\SequenceNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Sequence"]', $nodeId);

        foreach ($node->children as $child) {
            $childId = $child->accept($this);
            $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);
        }

        return $nodeId;
    }

    /**
     * Generates the graph node for a `GroupNode`.
     *
     * Purpose: This method creates a node representing a group, labeling it with its
     * type (e.g., capturing, lookahead) and name, and then connects it to the
     * subgraph representing the group's contents.
     *
     * @param Node\GroupNode $node the group node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitGroup(Node\GroupNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $label = \sprintf('Group: %s', $node->type->value);
        $name = $node->name ? ' ('.$node->name.')' : '';
        $this->lines[] = \sprintf('    %s("%s%s")', $nodeId, $this->escape($label), $this->escape($name));

        $childId = $node->child->accept($this);
        $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);

        return $nodeId;
    }

    /**
     * Generates the graph node for a `QuantifierNode`.
     *
     * Purpose: This method creates a node representing a quantifier (e.g., `*`, `+`),
     * and connects it to the node that it modifies.
     *
     * @param Node\QuantifierNode $node the quantifier node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $label = \sprintf('Quantifier: %s', $node->quantifier);
        $this->lines[] = \sprintf('    %s["%s"]', $nodeId, $this->escape($label));

        $childId = $node->node->accept($this);
        $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);

        return $nodeId;
    }

    /**
     * Generates the graph node for a `LiteralNode`.
     *
     * Purpose: This method creates a simple node representing a literal character or string.
     *
     * @param Node\LiteralNode $node the literal node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $value = '' === $node->value ? '(empty)' : $node->value;
        $this->lines[] = \sprintf('    %s["Literal: %s"]', $nodeId, $this->escape($value));

        return $nodeId;
    }

    /**
     * Generates the graph node for a `CharTypeNode`.
     *
     * Purpose: This method creates a node for a character type like `\d` or `\s`.
     *
     * @param Node\CharTypeNode $node the character type node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitCharType(Node\CharTypeNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["CharType: \\%s"]', $nodeId, $this->escape($node->value));

        return $nodeId;
    }

    /**
     * Generates the graph node for a `DotNode`.
     *
     * Purpose: This method creates a node for the "any character" wildcard (`.`).
     *
     * @param Node\DotNode $node the dot node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitDot(Node\DotNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Dot: any char"]', $nodeId);

        return $nodeId;
    }

    /**
     * Generates the graph node for an `AnchorNode`.
     *
     * Purpose: This method creates a circular node for an anchor like `^` or `$`.
     *
     * @param Node\AnchorNode $node the anchor node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s(("Anchor: %s"))', $nodeId, $this->escape($node->value));

        return $nodeId;
    }

    /**
     * Generates the graph node for an `AssertionNode`.
     *
     * Purpose: This method creates a node for a zero-width assertion like `\b`.
     *
     * @param Node\AssertionNode $node the assertion node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitAssertion(Node\AssertionNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Assertion: %s"]', $nodeId, $this->escape($node->value));

        return $nodeId;
    }

    /**
     * Generates the graph node for a `KeepNode`.
     *
     * Purpose: This method creates a node for the `\K` (keep) assertion.
     *
     * @param Node\KeepNode $node the keep node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitKeep(Node\KeepNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Keep: \\K"]', $nodeId);

        return $nodeId;
    }

    /**
     * Generates the graph node for a `CharClassNode`.
     *
     * Purpose: This method creates a node for a character class `[...]`, indicating if
     * it is negated, and then connects it to the nodes representing its contents.
     *
     * @param Node\CharClassNode $node the character class node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $label = 'CharClass'.($node->isNegated ? ' [NOT]' : '');
        $this->lines[] = \sprintf('    %s["%s"]', $nodeId, $label);

        $parts = $node->expression instanceof Node\AlternationNode ? $node->expression->alternatives : [$node->expression];
        foreach ($parts as $child) {
            $childId = $child->accept($this);
            $this->lines[] = \sprintf('    %s --> %s', $nodeId, $childId);
        }

        return $nodeId;
    }

    /**
     * Generates the graph node for a `RangeNode`.
     *
     * Purpose: This method creates a "Range" node and connects it to the start and
     * end points of the range (e.g., `a` and `z` in `a-z`).
     *
     * @param Node\RangeNode $node the range node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitRange(Node\RangeNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Range"]', $nodeId);

        $startId = $node->start->accept($this);
        $endId = $node->end->accept($this);
        $this->lines[] = \sprintf('    %s -->|from| %s', $nodeId, $startId);
        $this->lines[] = \sprintf('    %s -->|to| %s', $nodeId, $endId);

        return $nodeId;
    }

    /**
     * Generates the graph node for a `BackrefNode`.
     *
     * Purpose: This method creates a node for a backreference like `\1`.
     *
     * @param Node\BackrefNode $node the backreference node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitBackref(Node\BackrefNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Backref: %s"]', $nodeId, $this->escape($node->ref));

        return $nodeId;
    }

    /**
     * Generates the graph node for a `CharLiteralNode`.
     *
     * Purpose: This method creates a node for a character literal (unicode, octal, etc.).
     *
     * @param Node\CharLiteralNode $node the character literal node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitCharLiteral(Node\CharLiteralNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $label = $node->type->label().': '.$node->originalRepresentation;
        $this->lines[] = \sprintf('    %s["%s"]', $nodeId, $this->escape($label));

        return $nodeId;
    }

    /**
     * Generates the graph node for a `UnicodeNode`.
     *
     * Purpose: This method creates a node for a Unicode character escape.
     *
     * @param Node\UnicodeNode $node the Unicode node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Unicode: %s"]', $nodeId, $this->escape($node->code));

        return $nodeId;
    }

    /**
     * Generates the graph node for a `UnicodePropNode`.
     *
     * Purpose: This method creates a node for a Unicode property escape like `\p{L}`.
     *
     * @param Node\UnicodePropNode $node the Unicode property node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitUnicodeProp(Node\UnicodePropNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["UnicodeProp: %s"]', $nodeId, $this->escape($node->prop));

        return $nodeId;
    }

    /**
     * Generates the graph node for a `PosixClassNode`.
     *
     * Purpose: This method creates a node for a POSIX character class like `[:alpha:]`.
     *
     * @param Node\PosixClassNode $node the POSIX class node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["PosixClass: %s"]', $nodeId, $this->escape($node->class));

        return $nodeId;
    }

    /**
     * Generates the graph node for a `CommentNode`.
     *
     * Purpose: This method creates a node representing an inline comment.
     *
     * @param Node\CommentNode $node the comment node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitComment(Node\CommentNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $comment = substr($node->comment, 0, 20);
        $this->lines[] = \sprintf('    %s["Comment: %s"]', $nodeId, $this->escape($comment));

        return $nodeId;
    }

    /**
     * Generates the graph nodes for a `ConditionalNode`.
     *
     * Purpose: This method creates a diamond-shaped "Conditional" node and connects it
     * to its three branches: the condition, the "yes" pattern, and the "no" pattern.
     *
     * @param Node\ConditionalNode $node the conditional node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s{{"Conditional"}}', $nodeId);

        $condId = $node->condition->accept($this);
        $this->lines[] = \sprintf('    %s -->|condition| %s', $nodeId, $condId);

        $yesId = $node->yes->accept($this);
        $this->lines[] = \sprintf('    %s -->|yes| %s', $nodeId, $yesId);

        $noId = $node->no->accept($this);
        $this->lines[] = \sprintf('    %s -->|no| %s', $nodeId, $noId);

        return $nodeId;
    }

    /**
     * Generates the graph node for a `SubroutineNode`.
     *
     * Purpose: This method creates a node for a subroutine call like `(?R)`.
     *
     * @param Node\SubroutineNode $node the subroutine node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Subroutine: %s"]', $nodeId, $this->escape($node->reference));

        return $nodeId;
    }

    /**
     * Generates the graph node for a `PcreVerbNode`.
     *
     * Purpose: This method creates a node for a PCRE verb like `(*FAIL)`.
     *
     * @param Node\PcreVerbNode $node the PCRE verb node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitPcreVerb(Node\PcreVerbNode $node): string
    {
        $nodeId = $this->nextNodeId();

        // Special handling for LIMIT_MATCH verb
        if (str_starts_with($node->verb, 'LIMIT_MATCH=')) {
            $limit = substr($node->verb, 12); // Remove 'LIMIT_MATCH='
            $this->lines[] = \sprintf('    %s["LimitMatch: %s"]', $nodeId, $this->escape($limit));
        } else {
            $this->lines[] = \sprintf('    %s["PcreVerb: %s"]', $nodeId, $this->escape($node->verb));
        }

        return $nodeId;
    }

    /**
     * Generates the graph node for a `DefineNode`.
     *
     * Purpose: This method creates a node for a `(?(DEFINE)...)` block.
     *
     * @param Node\DefineNode $node the define node to visualize
     *
     * @return string the unique ID of the generated graph node
     */
    #[\Override]
    public function visitDefine(Node\DefineNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["DEFINE Block"]', $nodeId);

        $contentId = $node->content->accept($this);
        $this->lines[] = \sprintf('    %s --> %s', $nodeId, $contentId);

        return $nodeId;
    }

    #[\Override]
    public function visitLimitMatch(Node\LimitMatchNode $node): string
    {
        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["LimitMatch: %d"]', $nodeId, $node->limit);

        return $nodeId;
    }

    #[\Override]
    public function visitCallout(Node\CalloutNode $node): string
    {
        if (null === $node->identifier) {
            $label = '(?C)';
        } else {
            $label = match (true) {
                \is_int($node->identifier) => '(?C'.$node->identifier.')',
                $node->isStringIdentifier => '(?C"'.$node->identifier.'")',
                default => '(?C'.$node->identifier.')',
            };
        }

        $nodeId = $this->nextNodeId();
        $this->lines[] = \sprintf('    %s["Callout: %s"]', $nodeId, $this->escape($label));

        return $nodeId;
    }

    private function nextNodeId(): string
    {
        return 'node'.($this->nodeCounter++);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }
}
