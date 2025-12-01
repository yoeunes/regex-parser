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

use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\OctalLegacyNode;
use RegexParser\Node\OctalNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;

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
     * @param RegexNode $node the root node of the entire regex
     *
     * @return TReturn the result of visiting this node
     */
    public function visitRegex(RegexNode $node);

    /**
     * Logic to execute when visiting an `AlternationNode`.
     *
     * @param AlternationNode $node the node representing an alternation (`|`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitAlternation(AlternationNode $node);

    /**
     * Logic to execute when visiting a `SequenceNode`.
     *
     * @param SequenceNode $node the node representing a sequence of other nodes
     *
     * @return TReturn the result of visiting this node
     */
    public function visitSequence(SequenceNode $node);

    /**
     * Logic to execute when visiting a `GroupNode`.
     *
     * @param GroupNode $node The node representing any type of group (capturing, lookaround, etc.).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitGroup(GroupNode $node);

    /**
     * Logic to execute when visiting a `QuantifierNode`.
     *
     * @param QuantifierNode $node The node representing a quantifier (`*`, `+`, `{n,m}`, etc.).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitQuantifier(QuantifierNode $node);

    /**
     * Logic to execute when visiting a `LiteralNode`.
     *
     * @param LiteralNode $node the node representing a literal character or string
     *
     * @return TReturn the result of visiting this node
     */
    public function visitLiteral(LiteralNode $node);

    /**
     * Logic to execute when visiting a `CharTypeNode`.
     *
     * @param CharTypeNode $node The node representing a character type escape (`\d`, `\s`, etc.).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitCharType(CharTypeNode $node);

    /**
     * Logic to execute when visiting a `DotNode`.
     *
     * @param DotNode $node The node representing the `.` wildcard.
     *
     * @return TReturn the result of visiting this node
     */
    public function visitDot(DotNode $node);

    /**
     * Logic to execute when visiting an `AnchorNode`.
     *
     * @param AnchorNode $node the node representing an anchor (`^`, `$`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitAnchor(AnchorNode $node);

    /**
     * Logic to execute when visiting an `AssertionNode`.
     *
     * @param AssertionNode $node The node representing a zero-width assertion (`\b`, `\A`, etc.).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitAssertion(AssertionNode $node);

    /**
     * Logic to execute when visiting a `KeepNode`.
     *
     * @param KeepNode $node the node representing the `\K` "keep" assertion
     *
     * @return TReturn the result of visiting this node
     */
    public function visitKeep(KeepNode $node);

    /**
     * Logic to execute when visiting a `CharClassNode`.
     *
     * @param CharClassNode $node The node representing a character class (`[...]`).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitCharClass(CharClassNode $node);

    /**
     * Logic to execute when visiting a `RangeNode`.
     *
     * @param RangeNode $node the node representing a range inside a character class (`a-z`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitRange(RangeNode $node);

    /**
     * Logic to execute when visiting a `BackrefNode`.
     *
     * @param BackrefNode $node the node representing a backreference (`\1`, `\k<name>`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitBackref(BackrefNode $node);

    /**
     * Logic to execute when visiting a `UnicodeNode`.
     *
     * @param UnicodeNode $node the node representing a Unicode character escape (`\xHH`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitUnicode(UnicodeNode $node);

    /**
     * Logic to execute when visiting a `UnicodePropNode`.
     *
     * @param UnicodePropNode $node the node representing a Unicode property escape (`\p{L}`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitUnicodeProp(UnicodePropNode $node);

    /**
     * Logic to execute when visiting an `OctalNode`.
     *
     * @param OctalNode $node The node representing a modern octal escape (`\o{...}`).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitOctal(OctalNode $node);

    /**
     * Logic to execute when visiting an `OctalLegacyNode`.
     *
     * @param OctalLegacyNode $node the node representing a legacy octal escape (`\077`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitOctalLegacy(OctalLegacyNode $node);

    /**
     * Logic to execute when visiting a `PosixClassNode`.
     *
     * @param PosixClassNode $node the node representing a POSIX character class (`[:alpha:]`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitPosixClass(PosixClassNode $node);

    /**
     * Logic to execute when visiting a `CommentNode`.
     *
     * @param CommentNode $node The node representing an inline comment (`(?#...)`).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitComment(CommentNode $node);

    /**
     * Logic to execute when visiting a `ConditionalNode`.
     *
     * @param ConditionalNode $node The node representing a conditional subpattern (`(?(cond)...)`).
     *
     * @return TReturn the result of visiting this node
     */
    public function visitConditional(ConditionalNode $node);

    /**
     * Logic to execute when visiting a `SubroutineNode`.
     *
     * @param SubroutineNode $node the node representing a subroutine call (`(?R)`, `(?&name)`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitSubroutine(SubroutineNode $node);

    /**
     * Logic to execute when visiting a `PcreVerbNode`.
     *
     * @param PcreVerbNode $node the node representing a PCRE verb (`(*FAIL)`)
     *
     * @return TReturn the result of visiting this node
     */
    public function visitPcreVerb(PcreVerbNode $node);

    /**
     * Logic to execute when visiting a `DefineNode`.
     *
     * @param DefineNode $node The node representing a `(?(DEFINE)...)` block.
     *
     * @return TReturn the result of visiting this node
     */
    public function visitDefine(DefineNode $node);
}
