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
 * Defines the contract for a visitor that traverses the regex Abstract Syntax Tree (AST).
 *
 * Purpose: This interface is the core of the Visitor design pattern used throughout the
 * library. Each AST node has an `accept(NodeVisitorInterface $visitor)` method, which in
 * turn calls the appropriate `visit...()` method on the visitor. This allows for clean
 * separation of concerns: AST nodes are pure data structures, and all logic (compiling,
 * validating, explaining, etc.) is implemented in classes that implement this interface.
 *
 * As a contributor, if you add a new AST node, you MUST add a corresponding `visit...()`
 * method to this interface and implement it in all existing visitor classes.
 *
 * @template-covariant TReturn The return type of the visitor's methods (e.g., `string`
 *                             for `CompilerNodeVisitor`, `void` for `ValidatorNodeVisitor`).
 */
interface NodeVisitorInterface
{
    /**
     * Logic to execute when visiting a `RegexNode`.
     *
     * @param Node\RegexNode $node the root node of the entire regex
     *
     * @return TReturn the result of visiting this node
     */
    public function visitRegex(Node\RegexNode $node);

    /**
     * Logic to execute when visiting an `AlternationNode`.
     *
     * @param Node\AlternationNode $node the node representing an alternation (`|`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitAlternation(Node\AlternationNode $node);

    /**
     * Logic to execute when visiting a `SequenceNode`.
     *
     * @param Node\SequenceNode $node the node representing a sequence of other nodes
     *
     * @return TReturn the result of visiting this node
     */
    public function visitSequence(Node\SequenceNode $node);

    /**
     * Logic to execute when visiting a `GroupNode`.
     *
     * @param Node\GroupNode $node The node representing any type of group (capturing, lookaround, etc.).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitGroup(Node\GroupNode $node);

    /**
     * Logic to execute when visiting a `QuantifierNode`.
     *
     * @param Node\QuantifierNode $node The node representing a quantifier (`*`, `+`, `{n,m}`, etc.).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitQuantifier(Node\QuantifierNode $node);

    /**
     * Logic to execute when visiting a `LiteralNode`.
     *
     * @param Node\LiteralNode $node the node representing a literal character or string
     *
     * @return TReturn the result of visiting this node
     */
    public function visitLiteral(Node\LiteralNode $node);

    /**
     * Logic to execute when visiting a `CharTypeNode`.
     *
     * @param Node\CharTypeNode $node The node representing a character type escape (`\d`, `\s`, etc.).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitCharType(Node\CharTypeNode $node);

    /**
     * Logic to execute when visiting a `DotNode`.
     *
     * @param Node\DotNode $node The node representing the `.` wildcard.
     *
     * @return TReturn the result of visiting this node
     */
    public function visitDot(Node\DotNode $node);

    /**
     * Logic to execute when visiting an `AnchorNode`.
     *
     * @param Node\AnchorNode $node the node representing an anchor (`^`, `$`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitAnchor(Node\AnchorNode $node);

    /**
     * Logic to execute when visiting an `AssertionNode`.
     *
     * @param Node\AssertionNode $node The node representing a zero-width assertion (`\b`, `\A`, etc.).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitAssertion(Node\AssertionNode $node);

    /**
     * Logic to execute when visiting a `KeepNode`.
     *
     * @param Node\KeepNode $node the node representing the `\K` "keep" assertion
     *
     * @return TReturn the result of visiting this node
     */
    public function visitKeep(Node\KeepNode $node);

    /**
     * Logic to execute when visiting a `CharClassNode`.
     *
     * @param Node\CharClassNode $node The node representing a character class (`[...]`).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitCharClass(Node\CharClassNode $node);

    /**
     * Logic to execute when visiting a `RangeNode`.
     *
     * @param Node\RangeNode $node the node representing a range inside a character class (`a-z`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitRange(Node\RangeNode $node);

    /**
     * Logic to execute when visiting a `BackrefNode`.
     *
     * @param Node\BackrefNode $node the node representing a backreference (`\1`, `\k<name>`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitBackref(Node\BackrefNode $node);

    /**
     * Logic to execute when visiting a `UnicodeNode`.
     *
     * @param Node\UnicodeNode $node the node representing a Unicode character escape (`\xHH`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitUnicode(Node\UnicodeNode $node);

    /**
     * Logic to execute when visiting a `UnicodePropNode`.
     *
     * @param Node\UnicodePropNode $node the node representing a Unicode property escape (`\p{L}`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitUnicodeProp(Node\UnicodePropNode $node);

    /**
     * Logic to execute when visiting an `OctalNode`.
     *
     * @param Node\OctalNode $node The node representing a modern octal escape (`\o{...}`).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitOctal(Node\OctalNode $node);

    /**
     * Logic to execute when visiting an `OctalLegacyNode`.
     *
     * @param Node\OctalLegacyNode $node the node representing a legacy octal escape (`\077`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitOctalLegacy(Node\OctalLegacyNode $node);

    /**
     * Logic to execute when visiting a `PosixClassNode`.
     *
     * @param Node\PosixClassNode $node the node representing a POSIX character class (`[:alpha:]`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitPosixClass(Node\PosixClassNode $node);

    /**
     * Logic to execute when visiting a `CommentNode`.
     *
     * @param Node\CommentNode $node The node representing an inline comment (`(?#...)`).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitComment(Node\CommentNode $node);

    /**
     * Logic to execute when visiting a `ConditionalNode`.
     *
     * @param Node\ConditionalNode $node The node representing a conditional subpattern (`(?(cond)...)`).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitConditional(Node\ConditionalNode $node);

    /**
     * Logic to execute when visiting a `SubroutineNode`.
     *
     * @param Node\SubroutineNode $node the node representing a subroutine call (`(?R)`, `(?&name)`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitSubroutine(Node\SubroutineNode $node);

    /**
     * Logic to execute when visiting a `PcreVerbNode`.
     *
     * @param Node\PcreVerbNode $node the node representing a PCRE verb (`(*FAIL)`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitPcreVerb(Node\PcreVerbNode $node);

    /**
     * Logic to execute when visiting a `DefineNode`.
     *
     * @param Node\DefineNode $node The node representing a `(?(DEFINE)...)` block.
     *
     * @return TReturn the result of visiting this node
     */
    public function visitDefine(Node\DefineNode $node);

    /**
     * Logic to execute when visiting a `LimitMatchNode`.
     *
     * @param Node\LimitMatchNode $node the node representing a `(*LIMIT_MATCH=d)` verb
     *
     * @return TReturn the result of visiting this node
     */
    public function visitLimitMatch(Node\LimitMatchNode $node);

    /**
     * Logic to execute when visiting a `CalloutNode`.
     *
     * @param Node\CalloutNode $node the node representing a callout (`(?C...)`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitCallout(Node\CalloutNode $node);
}
