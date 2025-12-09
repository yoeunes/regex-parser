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
use RegexParser\Node\GroupType;

/**
 * A visitor that calculates a numeric "complexity score" for the regex.
 * This score can be used to heuristically identify overly complex patterns
 * that may be inefficient or difficult to maintain.
 *
 * Purpose: This visitor traverses the Abstract Syntax Tree (AST) of a regular expression
 * to compute a quantitative measure of its complexity. The score is based on various
 * factors such as the presence of quantifiers, alternations, groups, and other advanced
 * constructs. A higher score indicates a potentially more difficult-to-understand,
 * maintain, or optimize regex. This is useful for code quality analysis and identifying
 * patterns that might be prone to errors or performance issues.
 *
 * @extends AbstractNodeVisitor<int>
 */
final class ComplexityScoreNodeVisitor extends AbstractNodeVisitor
{
    /**
     * Base score for a node.
     */
    private const BASE_SCORE = 1;
    /**
     * Score multiplier for unbounded quantifiers (*, +, {n,}).
     */
    private const UNBOUNDED_QUANTIFIER_SCORE = 10;
    /**
     * Score for complex constructs like lookarounds or backreferences.
     */
    private const COMPLEX_CONSTRUCT_SCORE = 5;
    /**
     * Exponential multiplier for nested quantifiers.
     */
    private const NESTING_MULTIPLIER = 2;

    /**
     * Tracks the depth of nested quantifiers.
     */
    private int $quantifierDepth = 0;

    /**
     * Visits a RegexNode and calculates its complexity score.
     *
     * Purpose: This method serves as the entry point for calculating the complexity
     * of an entire regular expression. It initializes the internal state (like
     * quantifier depth) and then delegates the scoring to the main pattern node,
     * effectively returning the total complexity score of the regex.
     *
     * @param Node\RegexNode $node the `RegexNode` representing the entire regular expression
     *
     * @return int the total complexity score of the regex pattern
     *
     * @example
     * ```php
     * // Assuming $regexNode is the root of your parsed AST for '/a(b|c)+d/'
     * $visitor = new ComplexityScoreNodeVisitor();
     * $score = $regexNode->accept($visitor); // $score will be a numeric value
     * ```
     */
    #[\Override]
    public function visitRegex(Node\RegexNode $node): int
    {
        // Reset state for this run
        $this->quantifierDepth = 0;

        // The score of a regex is the score of its pattern
        return $node->pattern->accept($this);
    }

    /**
     * Visits an AlternationNode and calculates its complexity score.
     *
     * Purpose: Alternations (`|`) introduce branching logic, which increases complexity.
     * This method adds a base score for the alternation itself and then sums the
     * complexity scores of all its alternative branches, reflecting the increased
     * cognitive load and potential for different matching paths.
     *
     * @param Node\AlternationNode $node the `AlternationNode` representing a choice between patterns
     *
     * @return int the complexity score of the alternation node, including its alternatives
     *
     * @example
     * ```php
     * // For an alternation like `(a|b|c)`
     * $alternationNode->accept($visitor); // Score will be BASE_SCORE + score(a) + score(b) + score(c)
     * ```
     */
    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): int
    {
        // Score is the sum of all alternatives, plus a base score for the alternation itself
        $score = self::BASE_SCORE;
        foreach ($node->alternatives as $alt) {
            $score += $alt->accept($this);
        }

        return $score;
    }

    /**
     * Visits a SequenceNode and calculates its complexity score.
     *
     * Purpose: A sequence of elements (e.g., `abc`) contributes to complexity by
     * combining multiple individual components. This method simply sums the
     * complexity scores of all its child nodes, reflecting the linear accumulation
     * of complexity.
     *
     * @param Node\SequenceNode $node the `SequenceNode` representing a series of regex components
     *
     * @return int the total complexity score of the sequence node
     *
     * @example
     * ```php
     * // For a sequence `abc`
     * $sequenceNode->accept($visitor); // Score will be score(a) + score(b) + score(c)
     * ```
     */
    #[\Override]
    public function visitSequence(Node\SequenceNode $node): int
    {
        // Score is the sum of all children
        $score = 0;
        foreach ($node->children as $child) {
            $score += $child->accept($this);
        }

        return $score;
    }

    /**
     * Visits a GroupNode and calculates its complexity score.
     *
     * Purpose: Groups (`(...)`) encapsulate sub-patterns, adding a layer of structure
     * and potential for advanced features. This method adds a base score for the group
     * itself and a higher score for complex group types like lookarounds, which
     * significantly increase cognitive complexity due to their zero-width assertion behavior.
     *
     * @param Node\GroupNode $node the `GroupNode` representing a specific grouping construct
     *
     * @return int the complexity score of the group node, including its child
     *
     * @example
     * ```php
     * // For a simple capturing group `(abc)`
     * $groupNode->accept($visitor); // Score will be BASE_SCORE + score(abc)
     *
     * // For a positive lookahead `(?=abc)`
     * $groupNode->accept($visitor); // Score will be COMPLEX_CONSTRUCT_SCORE + score(abc)
     * ```
     */
    #[\Override]
    public function visitGroup(Node\GroupNode $node): int
    {
        $childScore = $node->child->accept($this);

        // Lookarounds are considered complex
        if (\in_array(
            $node->type,
            [
                GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
                GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
                GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
                GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
            ],
            true,
        )) {
            return self::COMPLEX_CONSTRUCT_SCORE + $childScore;
        }

        return self::BASE_SCORE + $childScore;
    }

    /**
     * Visits a QuantifierNode and calculates its complexity score.
     *
     * Purpose: Quantifiers (`*`, `+`, `?`, `{n,m}`) introduce repetition, which is a
     * major source of regex complexity and potential performance issues. This method
     * assigns a significantly higher score to unbounded quantifiers and exponentially
     * increases the penalty for nested unbounded quantifiers, reflecting the increased
     * backtracking complexity.
     *
     * @param Node\QuantifierNode $node the `QuantifierNode` representing a repetition operator
     *
     * @return int the complexity score of the quantifier node, including its quantified element
     *
     * @example
     * ```php
     * // For a simple quantifier `a?`
     * $quantifierNode->accept($visitor); // Score will be BASE_SCORE + score(a)
     *
     * // For an unbounded quantifier `a*`
     * $quantifierNode->accept($visitor); // Score will be UNBOUNDED_QUANTIFIER_SCORE + score(a)
     *
     * // For nested unbounded quantifiers `(a*)*`
     * // The inner `a*` will get UNBOUNDED_QUANTIFIER_SCORE + score(a)
     * // The outer `*` will get UNBOUNDED_QUANTIFIER_SCORE * NESTING_MULTIPLIER + score(inner_quantifier)
     * ```
     */
    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): int
    {
        $quant = $node->quantifier;
        $isUnbounded = \in_array($quant, ['*', '+'], true) || preg_match('/^\{\d++,\}$/', $quant);
        $score = 0;

        if ($isUnbounded) {
            $score += self::UNBOUNDED_QUANTIFIER_SCORE;
            if ($this->quantifierDepth > 0) {
                // Exponentially penalize nested unbounded quantifiers
                $score *= (self::NESTING_MULTIPLIER * $this->quantifierDepth);
            }
            $this->quantifierDepth++;
        } else {
            // Bounded quantifiers are simpler
            $score += self::BASE_SCORE;
        }

        // Add the score of the quantified node
        $score += $node->node->accept($this);

        if ($isUnbounded) {
            $this->quantifierDepth--;
        }

        return $score;
    }

    /**
     * Visits a CharClassNode and calculates its complexity score.
     *
     * Purpose: Character classes (`[...]`) define a set of characters to match,
     * which adds a level of abstraction. This method assigns a base score for the
     * class itself and sums the scores of all its constituent parts (literals, ranges, etc.),
     * reflecting the complexity of the character set definition.
     *
     * @param Node\CharClassNode $node the `CharClassNode` representing a character class
     *
     * @return int the complexity score of the character class node
     *
     * @example
     * ```php
     * // For a character class `[a-zA-Z0-9]`
     * $charClassNode->accept($visitor); // Score will be BASE_SCORE + score(a) + score(z) + score(A) + score(Z) + score(0) + score(9)
     * ```
     */
    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): int
    {
        // Score is the sum of parts inside the class
        $score = self::BASE_SCORE;
        $parts = $node->expression instanceof Node\AlternationNode ? $node->expression->alternatives : [$node->expression];
        foreach ($parts as $part) {
            $score += $part->accept($this);
        }

        return $score;
    }

    /**
     * Visits a BackrefNode and calculates its complexity score.
     *
     * Purpose: Backreferences (`\1`, `\k<name>`) refer to previously captured text,
     * introducing dependencies within the regex. This makes the pattern harder to
     * reason about and potentially more prone to backtracking issues. Therefore,
     * they are assigned a higher complexity score.
     *
     * @param Node\BackrefNode $node the `BackrefNode` representing a backreference
     *
     * @return int the complexity score for a backreference
     *
     * @example
     * ```php
     * // For a backreference `\1`
     * $backrefNode->accept($visitor); // Score will be COMPLEX_CONSTRUCT_SCORE
     * ```
     */
    #[\Override]
    public function visitBackref(Node\BackrefNode $node): int
    {
        return self::COMPLEX_CONSTRUCT_SCORE;
    }

    /**
     * Visits a ConditionalNode and calculates its complexity score.
     *
     * Purpose: Conditional patterns (`(?(condition)yes|no)`) introduce significant
     * branching logic, making the regex flow much more intricate. This method assigns
     * a very high base score for the conditional construct itself and sums the scores
     * of its condition, 'yes' branch, and 'no' branch, reflecting the substantial
     * increase in complexity.
     *
     * @param Node\ConditionalNode $node the `ConditionalNode` representing a conditional sub-pattern
     *
     * @return int the complexity score of the conditional node
     *
     * @example
     * ```php
     * // For a conditional `(?(1)foo|bar)`
     * $conditionalNode->accept($visitor); // Score will be (COMPLEX_CONSTRUCT_SCORE * 2) + score(condition) + score(foo) + score(bar)
     * ```
     */
    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): int
    {
        // Conditionals are highly complex
        $score = self::COMPLEX_CONSTRUCT_SCORE * 2;
        $score += $node->condition->accept($this);
        $score += $node->yes->accept($this);
        $score += $node->no->accept($this);

        return $score;
    }

    /**
     * Visits a SubroutineNode and calculates its complexity score.
     *
     * Purpose: Subroutines (`(?&name)`) and recursive patterns introduce non-linear
     * control flow and can be very difficult to trace and understand. This method
     * assigns a very high complexity score to reflect the advanced nature and
     * potential for intricate behavior associated with subroutines.
     *
     * @param Node\SubroutineNode $node the `SubroutineNode` representing a subroutine call
     *
     * @return int the complexity score for a subroutine node
     *
     * @example
     * ```php
     * // For a subroutine call `(?&my_pattern)`
     * $subroutineNode->accept($visitor); // Score will be COMPLEX_CONSTRUCT_SCORE * 2
     * ```
     */
    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): int
    {
        // Subroutines/recursion are highly complex
        return self::COMPLEX_CONSTRUCT_SCORE * 2;
    }

    /**
     * Visits a LiteralNode and calculates its complexity score.
     *
     * Purpose: Literal characters (e.g., 'a', 'hello') are the simplest elements
     * in a regex, matching themselves directly without any special logic or backtracking.
     * They contribute a minimal base score to the overall complexity.
     *
     * @param Node\LiteralNode $node the `LiteralNode` representing a literal character or string
     *
     * @return int the base complexity score for a literal
     *
     * @example
     * ```php
     * // For a literal `x`
     * $literalNode->accept($visitor); // Score will be BASE_SCORE
     * ```
     */
    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): int
    {
        return self::BASE_SCORE;
    }

    /**
     * Visits a CharTypeNode and calculates its complexity score.
     *
     * Purpose: Predefined character types (e.g., `\d`, `\s`, `\w`) represent a small,
     * well-defined set of characters. While more abstract than a literal, they are
     * still relatively simple and contribute a minimal base score to complexity.
     *
     * @param Node\CharTypeNode $node the `CharTypeNode` representing a predefined character type
     *
     * @return int the base complexity score for a character type
     *
     * @example
     * ```php
     * // For a character type `\d`
     * $charTypeNode->accept($visitor); // Score will be BASE_SCORE
     * ```
     */
    #[\Override]
    public function visitCharType(Node\CharTypeNode $node): int
    {
        return self::BASE_SCORE;
    }

    /**
     * Visits a DotNode and calculates its complexity score.
     *
     * Purpose: The wildcard dot (`.`) matches almost any single character. While broad,
     * its behavior is straightforward. It contributes a minimal base score to complexity.
     *
     * @param Node\DotNode $node the `DotNode` representing the wildcard dot character
     *
     * @return int the base complexity score for a dot
     *
     * @example
     * ```php
     * // For a dot `.`
     * $dotNode->accept($visitor); // Score will be BASE_SCORE
     * ```
     */
    #[\Override]
    public function visitDot(Node\DotNode $node): int
    {
        return self::BASE_SCORE;
    }

    /**
     * Visits an AnchorNode and calculates its complexity score.
     *
     * Purpose: Positional anchors (e.g., `^`, `$`, `\b`) assert a position in the string
     * without consuming characters. Their behavior is well-defined and they do not
     * introduce significant complexity. They contribute a minimal base score.
     *
     * @param Node\AnchorNode $node the `AnchorNode` representing a positional anchor
     *
     * @return int the base complexity score for an anchor
     *
     * @example
     * ```php
     * // For an anchor `^`
     * $anchorNode->accept($visitor); // Score will be BASE_SCORE
     * ```
     */
    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): int
    {
        return self::BASE_SCORE;
    }

    /**
     * Visits an AssertionNode and calculates its complexity score.
     *
     * Purpose: Zero-width assertions (e.g., `\b`, `\A`) check for conditions without
     * consuming characters. Similar to anchors, their behavior is specific and
     * they do not add substantial complexity. They contribute a minimal base score.
     *
     * @param Node\AssertionNode $node the `AssertionNode` representing a zero-width assertion
     *
     * @return int the base complexity score for an assertion
     *
     * @example
     * ```php
     * // For an assertion `\b`
     * $assertionNode->accept($visitor); // Score will be BASE_SCORE
     * ```
     */
    #[\Override]
    public function visitAssertion(Node\AssertionNode $node): int
    {
        return self::BASE_SCORE;
    }

    /**
     * Visits a KeepNode and calculates its complexity score.
     *
     * Purpose: The `\K` assertion resets the starting point of the match. While it
     * affects the final matched string, its direct contribution to the pattern's
     * complexity is minimal. It contributes a base score.
     *
     * @param Node\KeepNode $node the `KeepNode` representing the `\K` assertion
     *
     * @return int the base complexity score for a keep assertion
     *
     * @example
     * ```php
     * // For a keep assertion `\K`
     * $keepNode->accept($visitor); // Score will be BASE_SCORE
     * ```
     */
    #[\Override]
    public function visitKeep(Node\KeepNode $node): int
    {
        return self::BASE_SCORE;
    }

    /**
     * Visits a RangeNode and calculates its complexity score.
     *
     * Purpose: Character ranges (e.g., `a-z`) within a character class define a
     * continuous set of characters. This method assigns a base score for the range
     * itself and sums the scores of its start and end literal nodes, reflecting
     * the definition of the range.
     *
     * @param Node\RangeNode $node the `RangeNode` representing a character range
     *
     * @return int the complexity score of the range node
     *
     * @example
     * ```php
     * // For a range `a-z`
     * $rangeNode->accept($visitor); // Score will be BASE_SCORE + score(a) + score(z)
     * ```
     */
    #[\Override]
    public function visitRange(Node\RangeNode $node): int
    {
        return self::BASE_SCORE + $node->start->accept($this) + $node->end->accept($this);
    }

    /**
     * Visits a UnicodeNode and calculates its complexity score.
     *
     * Purpose: Unicode character escapes (e.g., `\x{2603}`) represent a single,
     * specific character. While they might appear complex due to their hexadecimal
     * notation, their matching behavior is straightforward. They contribute a minimal base score.
     *
     * @param Node\UnicodeNode $node the `UnicodeNode` representing a Unicode character escape
     *
     * @return int the base complexity score for a Unicode character
     *
     * @example
     * ```php
     * // For a Unicode character `\x{2603}`
     * $unicodeNode->accept($visitor); // Score will be BASE_SCORE
     * ```
     */
    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): int
    {
        return self::BASE_SCORE;
    }

    #[\Override]
    public function visitUnicodeNamed(Node\UnicodeNamedNode $node): int
    {
        return self::BASE_SCORE;
    }

    /**
     * Visits a UnicodePropNode and calculates its complexity score.
     *
     * Purpose: Unicode character properties (e.g., `\p{L}`) match characters based
     * on their linguistic or script properties. Similar to character types, they
     * represent a defined set and contribute a minimal base score to complexity.
     *
     * @param Node\UnicodePropNode $node the `UnicodePropNode` representing a Unicode property
     *
     * @return int the base complexity score for a Unicode property
     *
     * @example
     * ```php
     * // For a Unicode property `\p{L}`
     * $unicodePropNode->accept($visitor); // Score will be BASE_SCORE
     * ```
     */
    #[\Override]
    public function visitUnicodeProp(Node\UnicodePropNode $node): int
    {
        return self::BASE_SCORE;
    }

    /**
     * Visits an OctalNode and calculates its complexity score.
     *
     * Purpose: Modern octal character escapes (e.g., `\o{101}`) represent a single,
     * specific character. Their matching behavior is direct, and they contribute a
     * minimal base score to complexity.
     *
     * @param Node\OctalNode $node the `OctalNode` representing a modern octal escape
     *
     * @return int the base complexity score for an octal character
     *
     * @example
     * ```php
     * // For an octal escape `\o{101}`
     * $octalNode->accept($visitor); // Score will be BASE_SCORE
     * ```
     */
    #[\Override]
    public function visitOctal(Node\OctalNode $node): int
    {
        return self::BASE_SCORE;
    }

    /**
     * Visits an OctalLegacyNode and calculates its complexity score.
     *
     * Purpose: Legacy octal character escapes (e.g., `\012`) represent a single,
     * specific character. Despite their older syntax, their matching behavior is
     * direct, and they contribute a minimal base score to complexity.
     *
     * @param Node\OctalLegacyNode $node the `OctalLegacyNode` representing a legacy octal escape
     *
     * @return int the base complexity score for a legacy octal character
     *
     * @example
     * ```php
     * // For a legacy octal escape `\012`
     * $octalLegacyNode->accept($visitor); // Score will be BASE_SCORE
     * ```
     */
    #[\Override]
    public function visitOctalLegacy(Node\OctalLegacyNode $node): int
    {
        return self::BASE_SCORE;
    }

    /**
     * Visits a PosixClassNode and calculates its complexity score.
     *
     * Purpose: POSIX character classes (e.g., `[:alpha:]`) match characters from
     * a predefined set. Similar to other character types, they are well-defined
     * and contribute a minimal base score to complexity.
     *
     * @param Node\PosixClassNode $node the `PosixClassNode` representing a POSIX character class
     *
     * @return int the base complexity score for a POSIX class
     *
     * @example
     * ```php
     * // For a POSIX class `[:digit:]`
     * $posixClassNode->accept($visitor); // Score will be BASE_SCORE
     * ```
     */
    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): int
    {
        return self::BASE_SCORE;
    }

    /**
     * Visits a CommentNode and calculates its complexity score.
     *
     * Purpose: Comments within a regex (`(?#comment)`) are ignored by the regex engine
     * and do not affect the matching logic. Therefore, they do not contribute to the
     * functional complexity of the pattern and are assigned a score of zero.
     *
     * @param Node\CommentNode $node the `CommentNode` representing an inline comment
     *
     * @return int always 0, as comments do not add to functional complexity
     *
     * @example
     * ```php
     * // For a comment `(?# This is a comment)`
     * $commentNode->accept($visitor); // Score will be 0
     * ```
     */
    #[\Override]
    public function visitComment(Node\CommentNode $node): int
    {
        // Comments do not add to complexity
        return 0;
    }

    /**
     * Visits a PcreVerbNode and calculates its complexity score.
     *
     * Purpose: PCRE control verbs (e.g., `(*FAIL)`, `(*COMMIT)`) directly influence
     * the regex engine's behavior, often affecting backtracking or match termination.
     * These are advanced constructs that significantly increase the complexity of
     * understanding and debugging a regex. They are assigned a higher complexity score.
     *
     * @param Node\PcreVerbNode $node the `PcreVerbNode` representing a PCRE verb
     *
     * @return int the complexity score for a PCRE verb
     *
     * @example
     * ```php
     * // For a PCRE verb `(*FAIL)`
     * $pcreVerbNode->accept($visitor); // Score will be COMPLEX_CONSTRUCT_SCORE
     * ```
     */
    #[\Override]
    public function visitPcreVerb(Node\PcreVerbNode $node): int
    {
        return self::COMPLEX_CONSTRUCT_SCORE;
    }

    /**
     * Visits a DefineNode and calculates its complexity score.
     *
     * Purpose: The `(?(DEFINE)...)` block is used to define named sub-patterns.
     * While the block itself doesn't match text, its presence and the complexity
     * of the patterns it defines contribute to the overall regex complexity.
     * This method assigns a higher base score for the DEFINE block and sums the
     * scores of its content.
     *
     * @param Node\DefineNode $node The `DefineNode` representing a `(?(DEFINE)...)` block.
     *
     * @return int the complexity score of the DEFINE block, including its content
     *
     * @example
     * ```php
     * // For a DEFINE block `(?(DEFINE)(?<digit>\d+))`
     * $defineNode->accept($visitor); // Score will be COMPLEX_CONSTRUCT_SCORE + score(\d+)
     * ```
     */
    #[\Override]
    public function visitDefine(Node\DefineNode $node): int
    {
        // DEFINE blocks add complexity from their content
        return self::COMPLEX_CONSTRUCT_SCORE + $node->content->accept($this);
    }

    #[\Override]
    public function visitLimitMatch(Node\LimitMatchNode $node): int
    {
        return self::COMPLEX_CONSTRUCT_SCORE;
    }

    #[\Override]
    public function visitCallout(Node\CalloutNode $node): int
    {
        // Callouts introduce external logic and break regex flow, making them complex.
        return self::COMPLEX_CONSTRUCT_SCORE;
    }
}
