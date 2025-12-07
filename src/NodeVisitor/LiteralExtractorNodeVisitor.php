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

use RegexParser\LiteralSet;
use RegexParser\Node;

/**
 * Extracts literal strings that must appear in any match.
 *
 * Purpose: This visitor analyzes the regex AST to identify fixed strings
 * that are guaranteed to appear in every possible match. These
 * literals can be used for fast-path optimizations (e.g. strpos check).
 * For instance, if a regex is `/foo.*bar/`, this visitor would identify
 * "foo" as a prefix and "bar" as a suffix that must exist in any matching string.
 * This can significantly speed up initial matching attempts by using simple string
 * search functions before engaging the full regex engine.
 *
 * @extends AbstractNodeVisitor<LiteralSet>
 */
final class LiteralExtractorNodeVisitor extends AbstractNodeVisitor
{
    /**
     * Maximum number of literals generated to prevent explosion (e.g. [a-z]{10}).
     */
    private const int MAX_LITERALS_COUNT = 128;

    private bool $caseInsensitive = false;

    /**
     * Visits a RegexNode and initiates the literal extraction process.
     *
     * Purpose: This is the entry point for extracting literal strings from an entire
     * regular expression. It first checks for the 'i' flag to determine case-insensitivity,
     * which affects how literals are extracted. It then delegates the actual extraction
     * to the main pattern node.
     *
     * @param Node\RegexNode $node the `RegexNode` representing the entire regular expression
     *
     * @return LiteralSet a `LiteralSet` containing all guaranteed literal prefixes and suffixes
     *                    that must be present in any string matching the regex
     *
     * @example
     * ```php
     * // Assuming $regexNode is the root of your parsed AST for '/hello.*world/i'
     * $visitor = new LiteralExtractorNodeVisitor();
     * $literalSet = $regexNode->accept($visitor);
     * // $literalSet->prefixes might contain ['hello', 'Hello', 'HEllo', ...]
     * // $literalSet->suffixes might contain ['world', 'World', 'WORld', ...]
     * ```
     */
    public function visitRegex(Node\RegexNode $node): LiteralSet
    {
        $this->caseInsensitive = str_contains($node->flags, 'i');

        return $node->pattern->accept($this);
    }

    /**
     * Visits an AlternationNode and extracts common literals from its alternatives.
     *
     * Purpose: When a regex contains an "OR" condition (e.g., `cat|dog`), this method
     * identifies literals that are common to *all* alternatives. If no common literal
     * can be found, or if the alternatives are too complex, it returns an empty `LiteralSet`.
     * This ensures that only truly guaranteed literals are extracted.
     *
     * @param Node\AlternationNode $node the `AlternationNode` representing a choice between patterns
     *
     * @return LiteralSet a `LiteralSet` representing the common literals across all alternatives
     *
     * @example
     * ```php
     * // For an alternation like `(foo|bar)`
     * $alternationNode->accept($visitor); // Returns an empty LiteralSet as 'foo' and 'bar' are not common.
     *
     * // For an alternation like `(prefix_foo|prefix_bar)`
     * $alternationNode->accept($visitor); // Might return a LiteralSet with 'prefix_' as a common prefix.
     * ```
     */
    public function visitAlternation(Node\AlternationNode $node): LiteralSet
    {
        $result = null;

        foreach ($node->alternatives as $alt) {
            /** @var LiteralSet $altSet */
            $altSet = $alt->accept($this);

            if (null === $result) {
                $result = $altSet;
            } else {
                $result = $result->unite($altSet);
            }

            // Safety valve for memory
            if (\count($result->prefixes) > self::MAX_LITERALS_COUNT) {
                return LiteralSet::empty();
            }
        }

        return $result ?? LiteralSet::empty();
    }

    /**
     * Visits a SequenceNode and concatenates literals from its child nodes.
     *
     * Purpose: This method processes a linear sequence of regex elements (e.g., `abc`).
     * It recursively extracts literals from each child node and then concatenates them
     * to form a longer literal sequence. This is crucial for building up longer guaranteed
     * literal strings from simpler components.
     *
     * @param Node\SequenceNode $node the `SequenceNode` representing a series of regex components
     *
     * @return LiteralSet a `LiteralSet` representing the concatenated literals from its children
     *
     * @example
     * ```php
     * // For a sequence `foo.*bar`
     * $sequenceNode->accept($visitor); // Might return a LiteralSet with 'foo' as prefix and 'bar' as suffix.
     * ```
     */
    public function visitSequence(Node\SequenceNode $node): LiteralSet
    {
        $result = LiteralSet::fromString(''); // Start with empty complete string

        foreach ($node->children as $child) {
            /** @var LiteralSet $childSet */
            $childSet = $child->accept($this);
            $result = $result->concat($childSet);

            // Safety valve
            if (\count($result->prefixes) > self::MAX_LITERALS_COUNT) {
                return LiteralSet::empty();
            }
        }

        return $result;
    }

    /**
     * Visits a GroupNode and extracts literals from its child, handling inline flags.
     *
     * Purpose: This method is responsible for extracting literals from within groups.
     * It's particularly important for handling inline flag modifiers (e.g., `(?i:...)` or `(?-i:...)`),
     * which can change the case-insensitivity setting for the group's content. The original
     * case-insensitivity state is restored after visiting the group's child.
     *
     * @param Node\GroupNode $node the `GroupNode` representing a specific grouping construct
     *
     * @return LiteralSet a `LiteralSet` containing literals extracted from the group's child
     *
     * @example
     * ```php
     * // For a group `(?i:hello)`
     * $groupNode->accept($visitor); // Will extract 'hello', 'Hello', 'HEllo', etc., due to inline 'i' flag.
     * ```
     */
    public function visitGroup(Node\GroupNode $node): LiteralSet
    {
        // Handle inline flags if present
        $previousState = $this->caseInsensitive;
        if ($node->flags) {
            if (str_contains($node->flags, '-i')) {
                $this->caseInsensitive = false;
            } elseif (str_contains($node->flags, 'i')) {
                $this->caseInsensitive = true;
            }
        }

        /** @var LiteralSet $result */
        $result = $node->child->accept($this);

        // Restore state
        $this->caseInsensitive = $previousState;

        return $result;
    }

    /**
     * Visits a QuantifierNode and extracts literals based on its repetition rules.
     *
     * Purpose: This method intelligently extracts literals from quantified elements.
     * For exact quantifiers (`{n}`), it repeats the child's literals `n` times.
     * For quantifiers guaranteeing at least one repetition (`+`, `{n,}`), it extracts
     * the child's literals but marks them as potentially incomplete (as more repetitions
     * might follow). For optional quantifiers (`*`, `?`), it cannot guarantee presence,
     * so it returns an empty `LiteralSet`.
     *
     * @param Node\QuantifierNode $node the `QuantifierNode` representing a repetition operator
     *
     * @return LiteralSet a `LiteralSet` containing literals extracted based on the quantifier's rules
     *
     * @example
     * ```php
     * // For a quantifier `a{3}`
     * $quantifierNode->accept($visitor); // Returns a LiteralSet with 'aaa'.
     *
     * // For a quantifier `a+`
     * $quantifierNode->accept($visitor); // Returns a LiteralSet with 'a' as a prefix, but not complete.
     *
     * // For a quantifier `a*`
     * $quantifierNode->accept($visitor); // Returns an empty LiteralSet.
     * ```
     */
    public function visitQuantifier(Node\QuantifierNode $node): LiteralSet
    {
        // Case 1: Exact quantifier {n} -> repeat literals n times
        if (preg_match('/^\{(\d+)\}$/', $node->quantifier, $m)) {
            $count = (int) $m[1];
            if (0 === $count) {
                return LiteralSet::fromString(''); // Matches empty string
            }

            /** @var LiteralSet $childSet */
            $childSet = $node->node->accept($this);

            // Repeat concatenation
            $result = $childSet;
            for ($i = 1; $i < $count; $i++) {
                $result = $result->concat($childSet);
                if (\count($result->prefixes) > self::MAX_LITERALS_COUNT) {
                    return LiteralSet::empty();
                }
            }

            return $result;
        }

        // Case 2: + or {n,} (At least 1)
        // We can extract the literal from the node, but it's not complete anymore because of the tail
        if ('+' === $node->quantifier || preg_match('/^\{(\d+),/', $node->quantifier)) {
            /** @var LiteralSet $childSet */
            $childSet = $node->node->accept($this);

            // The literal is present at least once, but followed by unknown quantity.
            // So suffixes are lost, completeness is lost.
            return new LiteralSet($childSet->prefixes, [], false);
        }

        // Case 3: * or ? (Optional)
        // Cannot guarantee presence.
        return LiteralSet::empty();
    }

    /**
     * Visits a LiteralNode and extracts its value as a literal.
     *
     * Purpose: This is the most direct literal extraction. When a literal character
     * or string (e.g., `a`, `hello`) is encountered, its exact value is returned
     * as a `LiteralSet`. If case-insensitivity is active, it expands the literal
     * to include all possible case variations.
     *
     * @param Node\LiteralNode $node the `LiteralNode` representing a literal character or string
     *
     * @return LiteralSet a `LiteralSet` containing the literal value(s)
     *
     * @example
     * ```php
     * // For a literal `a` (case-sensitive)
     * $literalNode->accept($visitor); // Returns a LiteralSet with ['a'].
     *
     * // For a literal `a` (case-insensitive)
     * $literalNode->accept($visitor); // Returns a LiteralSet with ['a', 'A'].
     * ```
     */
    public function visitLiteral(Node\LiteralNode $node): LiteralSet
    {
        if ($this->caseInsensitive) {
            return $this->expandCaseInsensitive($node->value);
        }

        return LiteralSet::fromString($node->value);
    }

    /**
     * Visits a CharClassNode and extracts literals if it represents a simple,
     * non-negated set of literals.
     *
     * Purpose: This method attempts to extract literals from character classes like `[abc]`.
     * If the character class is simple (non-negated and contains only literal parts),
     * it treats it as an alternation of those literals. More complex character classes
     * (e.g., `[^0-9]`, `[a-z]`, or those containing character types) are considered
     * non-literal for simplicity and return an empty `LiteralSet`.
     *
     * @param Node\CharClassNode $node the `CharClassNode` representing a character class
     *
     * @return LiteralSet a `LiteralSet` containing literals from the character class, or empty if complex
     *
     * @example
     * ```php
     * // For a character class `[abc]`
     * $charClassNode->accept($visitor); // Returns a LiteralSet with ['a', 'b', 'c'].
     *
     * // For a character class `[^0-9]`
     * $charClassNode->accept($visitor); // Returns an empty LiteralSet.
     * ```
     */
    public function visitCharClass(Node\CharClassNode $node): LiteralSet
    {
        // Optimization: Single character class [a] is literal 'a'
        if (!$node->isNegated && 1 === \count($node->parts) && $node->parts[0] instanceof Node\LiteralNode) {
            return $this->visitLiteral($node->parts[0]);
        }

        // [abc] is effectively an alternation a|b|c
        // We only handle simple literals inside char classes for now to avoid complexity
        if (!$node->isNegated) {
            $literals = [];
            foreach ($node->parts as $part) {
                if ($part instanceof Node\LiteralNode) {
                    if ($this->caseInsensitive) {
                        $expanded = $this->expandCaseInsensitive($part->value);
                        array_push($literals, ...$expanded->prefixes);
                    } else {
                        $literals[] = $part->value;
                    }
                } else {
                    // Range, char type, etc. -> considered non-literal for simplicity
                    return LiteralSet::empty();
                }
            }

            return new LiteralSet($literals, $literals, true); // Complete single char match
        }

        return LiteralSet::empty();
    }

    /**
     * Visits a CharTypeNode. Character types do not yield fixed literals.
     *
     * Purpose: Predefined character types (e.g., `\d`, `\s`, `\w`) match a *class*
     * of characters, not a single fixed literal. Therefore, they cannot contribute
     * to a guaranteed literal string, and this method returns an empty `LiteralSet`.
     *
     * @param Node\CharTypeNode $node the `CharTypeNode` representing a predefined character type
     *
     * @return LiteralSet an empty `LiteralSet`
     */
    public function visitCharType(Node\CharTypeNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    /**
     * Visits a DotNode. The wildcard dot does not yield a fixed literal.
     *
     * Purpose: The dot (`.`) matches any single character (except newline by default).
     * Since it can match various characters, it cannot contribute to a guaranteed
     * literal string, and this method returns an empty `LiteralSet`.
     *
     * @param Node\DotNode $node the `DotNode` representing the wildcard dot character
     *
     * @return LiteralSet an empty `LiteralSet`
     */
    public function visitDot(Node\DotNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    /**
     * Visits an AnchorNode. Anchors match empty strings and can be part of literal sequences.
     *
     * Purpose: Positional anchors (e.g., `^`, `$`, `\b`) assert a position but do not
     * consume characters. They effectively match an empty string. Returning a `LiteralSet`
     * representing an empty string allows them to be correctly integrated into literal
     * sequences (e.g., `/^abc/` can still yield 'abc' as a prefix).
     *
     * @param Node\AnchorNode $node the `AnchorNode` representing a positional anchor
     *
     * @return LiteralSet a `LiteralSet` representing an empty string
     */
    public function visitAnchor(Node\AnchorNode $node): LiteralSet
    {
        // Anchors match empty strings, so they are "complete" empty matches
        // This allows /^abc/ to return prefix 'abc'
        return LiteralSet::fromString('');
    }

    /**
     * Visits an AssertionNode. Assertions match empty strings and can be part of literal sequences.
     *
     * Purpose: Zero-width assertions (e.g., `\b`, `\A`) check for conditions without
     * consuming characters. Similar to anchors, they effectively match an empty string.
     * Returning a `LiteralSet` representing an empty string allows them to be correctly
     * integrated into literal sequences.
     *
     * @param Node\AssertionNode $node the `AssertionNode` representing a zero-width assertion
     *
     * @return LiteralSet a `LiteralSet` representing an empty string
     */
    public function visitAssertion(Node\AssertionNode $node): LiteralSet
    {
        return LiteralSet::fromString('');
    }

    /**
     * Visits a KeepNode. The `\K` assertion matches an empty string and can be part of literal sequences.
     *
     * Purpose: The `\K` assertion resets the starting point of the match but does not
     * consume characters. It effectively matches an empty string. Returning a `LiteralSet`
     * representing an empty string allows it to be correctly integrated into literal sequences.
     *
     * @param Node\KeepNode $node the `KeepNode` representing the `\K` assertion
     *
     * @return LiteralSet a `LiteralSet` representing an empty string
     */
    public function visitKeep(Node\KeepNode $node): LiteralSet
    {
        return LiteralSet::fromString('');
    }

    /**
     * Visits a RangeNode. Character ranges do not yield fixed literals.
     *
     * Purpose: Character ranges (e.g., `a-z`) within a character class match any character
     * within that range. Since they can match various characters, they cannot contribute
     * to a guaranteed literal string, and this method returns an empty `LiteralSet`.
     *
     * @param Node\RangeNode $node the `RangeNode` representing a character range
     *
     * @return LiteralSet an empty `LiteralSet`
     */
    public function visitRange(Node\RangeNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    /**
     * Visits a BackrefNode. Backreferences do not yield fixed literals.
     *
     * Purpose: Backreferences (e.g., `\1`, `\k<name>`) match previously captured text,
     * which is dynamic and not a fixed literal. Therefore, they cannot contribute to
     * a guaranteed literal string, and this method returns an empty `LiteralSet`.
     *
     * @param Node\BackrefNode $node the `BackrefNode` representing a backreference
     *
     * @return LiteralSet an empty `LiteralSet`
     */
    public function visitBackref(Node\BackrefNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    /**
     * Visits a UnicodeNode. Unicode character escapes do not yield fixed literals in this context.
     *
     * Purpose: Unicode character escapes (e.g., `\x{2603}`) represent a single, specific
     * character. While they are fixed, this visitor currently treats them as non-literal
     * for simplicity, as resolving their exact string value might require complex decoding
     * and handling of various Unicode representations. It returns an empty `LiteralSet`.
     *
     * @param Node\UnicodeNode $node the `UnicodeNode` representing a Unicode character escape
     *
     * @return LiteralSet an empty `LiteralSet`
     */
    public function visitUnicode(Node\UnicodeNode $node): LiteralSet
    {
        // Could resolve hex, but for now treat as empty set unless we decode it
        // Assuming RegexParser doesn't decode unicode in AST yet (it keeps raw \xHH)
        return LiteralSet::empty();
    }

    /**
     * Visits a UnicodePropNode. Unicode properties do not yield fixed literals.
     *
     * Purpose: Unicode character properties (e.g., `\p{L}`) match a class of characters
     * based on their property, not a single fixed literal. Therefore, they cannot
     * contribute to a guaranteed literal string, and this method returns an empty `LiteralSet`.
     *
     * @param Node\UnicodePropNode $node the `UnicodePropNode` representing a Unicode property
     *
     * @return LiteralSet an empty `LiteralSet`
     */
    public function visitUnicodeProp(Node\UnicodePropNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    /**
     * Visits an OctalNode. Octal character escapes do not yield fixed literals in this context.
     *
     * Purpose: Modern octal character escapes (e.g., `\o{101}`) represent a single, specific
     * character. Similar to Unicode nodes, this visitor currently treats them as non-literal
     * for simplicity, as resolving their exact string value might require decoding.
     * It returns an empty `LiteralSet`.
     *
     * @param Node\OctalNode $node the `OctalNode` representing a modern octal escape
     *
     * @return LiteralSet an empty `LiteralSet`
     */
    public function visitOctal(Node\OctalNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    /**
     * Visits an OctalLegacyNode. Legacy octal character escapes do not yield fixed literals in this context.
     *
     * Purpose: Legacy octal character escapes (e.g., `\012`) represent a single, specific
     * character. Similar to other character escape nodes, this visitor currently treats
     * them as non-literal for simplicity. It returns an empty `LiteralSet`.
     *
     * @param Node\OctalLegacyNode $node the `OctalLegacyNode` representing a legacy octal escape
     *
     * @return LiteralSet an empty `LiteralSet`
     */
    public function visitOctalLegacy(Node\OctalLegacyNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    /**
     * Visits a PosixClassNode. POSIX character classes do not yield fixed literals.
     *
     * Purpose: POSIX character classes (e.g., `[:alpha:]`) match a class of characters,
     * not a single fixed literal. Therefore, they cannot contribute to a guaranteed
     * literal string, and this method returns an empty `LiteralSet`.
     *
     * @param Node\PosixClassNode $node the `PosixClassNode` representing a POSIX character class
     *
     * @return LiteralSet an empty `LiteralSet`
     */
    public function visitPosixClass(Node\PosixClassNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    /**
     * Visits a CommentNode. Comments match empty strings and can be part of literal sequences.
     *
     * Purpose: Comments within a regex (e.g., `(?#comment)`) are ignored by the regex engine
     * and do not consume characters. They effectively match an empty string. Returning a
     * `LiteralSet` representing an empty string allows them to be correctly integrated
     * into literal sequences.
     *
     * @param Node\CommentNode $node the `CommentNode` representing an inline comment
     *
     * @return LiteralSet a `LiteralSet` representing an empty string
     */
    public function visitComment(Node\CommentNode $node): LiteralSet
    {
        return LiteralSet::fromString('');
    }

    /**
     * Visits a ConditionalNode. Conditional patterns do not yield fixed literals.
     *
     * Purpose: Conditional patterns (e.g., `(?(condition)yes|no)`) introduce branching
     * logic where different paths can be taken. Unless both branches yield the *exact*
     * same literal, no fixed literal can be guaranteed. For simplicity, this visitor
     * treats conditional nodes as non-literal and returns an empty `LiteralSet`.
     *
     * @param Node\ConditionalNode $node the `ConditionalNode` representing a conditional sub-pattern
     *
     * @return LiteralSet an empty `LiteralSet`
     */
    public function visitConditional(Node\ConditionalNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    /**
     * Visits a SubroutineNode. Subroutines do not yield fixed literals.
     *
     * Purpose: Subroutines (e.g., `(?&name)`) call other patterns, which can be dynamic
     * or recursive. Determining a fixed literal from a subroutine call is complex and
     * beyond the scope of this visitor's simple literal extraction. It returns an empty `LiteralSet`.
     *
     * @param Node\SubroutineNode $node the `SubroutineNode` representing a subroutine call
     *
     * @return LiteralSet an empty `LiteralSet`
     */
    public function visitSubroutine(Node\SubroutineNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    /**
     * Visits a PcreVerbNode. PCRE verbs match empty strings and can be part of literal sequences.
     *
     * Purpose: PCRE control verbs (e.g., `(*FAIL)`, `(*COMMIT)`) influence the regex
     * engine's behavior but do not consume characters. They effectively match an empty string.
     * Returning a `LiteralSet` representing an empty string allows them to be correctly
     * integrated into literal sequences.
     *
     * @param Node\PcreVerbNode $node the `PcreVerbNode` representing a PCRE verb
     *
     * @return LiteralSet a `LiteralSet` representing an empty string
     */
    public function visitPcreVerb(Node\PcreVerbNode $node): LiteralSet
    {
        return LiteralSet::fromString('');
    }

    /**
     * Visits a DefineNode. DEFINE blocks do not yield fixed literals.
     *
     * Purpose: The `(?(DEFINE)...)` block is used to define named sub-patterns that
     * are not matched directly but can be referenced by subroutines. Since this block
     * itself does not match any text, it cannot contribute to a guaranteed literal string.
     * It returns an empty `LiteralSet`.
     *
     * @param Node\DefineNode $node The `DefineNode` representing a `(?(DEFINE)...)` block.
     *
     * @return LiteralSet an empty `LiteralSet`
     */
    public function visitDefine(Node\DefineNode $node): LiteralSet
    {
        // DEFINE blocks don't produce any literal matches
        return LiteralSet::empty();
    }

    public function visitLimitMatch(Node\LimitMatchNode $node): LiteralSet
    {
        return LiteralSet::fromString('');
    }

    public function visitCallout(Node\CalloutNode $node): LiteralSet
    {
        // Callouts do not match characters, so they don't contribute to literal extraction.
        return LiteralSet::fromString('');
    }

    /**
     * Generates case variants for a given string if case-insensitivity is active.
     *
     * Purpose: This private helper method is used when the regex is case-insensitive.
     * It takes a literal string and generates all possible case permutations (e.g.,
     * 'foo' -> ['foo', 'Foo', 'fOo', 'foO', 'FOo', 'fOO', 'FoO', 'FOO']).
     * It includes a safety mechanism to limit the number of generated variants
     * to prevent excessive memory usage for long strings.
     *
     * @param string $value the literal string to expand for case variations
     *
     * @return LiteralSet a `LiteralSet` containing all case variations of the input string
     */
    private function expandCaseInsensitive(string $value): LiteralSet
    {
        // Limit expansion length
        if (\strlen($value) > 8) {
            return LiteralSet::empty(); // Too expensive to compute permutations
        }

        $results = [''];
        for ($i = 0; $i < \strlen($value); $i++) {
            $char = $value[$i];
            $lower = strtolower($char);
            $upper = strtoupper($char);

            $nextResults = [];
            foreach ($results as $prefix) {
                if ($lower === $upper) {
                    $nextResults[] = $prefix.$char;
                } else {
                    $nextResults[] = $prefix.$lower;
                    $nextResults[] = $prefix.$upper;
                }
            }
            $results = $nextResults;
        }

        if (\count($results) > self::MAX_LITERALS_COUNT) {
            return LiteralSet::empty();
        }

        return new LiteralSet($results, $results, true);
    }
}
