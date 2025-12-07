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

use RegexParser\Exception\ParserException;
use RegexParser\Node;
use RegexParser\Node\GroupType;
use RegexParser\Node\QuantifierType;
use RegexParser\ReDoS\CharSetAnalyzer;

/**
 * Validates the semantic rules of a parsed regex Abstract Syntax Tree (AST).
 *
 * Purpose: This visitor traverses the AST and checks for logical errors that are not
 * simple syntax errors, such as:
 * - Catastrophic backtracking (ReDoS) from nested quantifiers.
 * - Invalid quantifier ranges (e.g., {5,2}).
 * - Variable-length quantifiers inside lookbehinds.
 * - References to non-existent capturing groups (backreferences/subroutines).
 * - Duplicate capturing group names.
 * - Invalid character ranges (e.g., [z-a]).
 * - Invalid Unicode properties or POSIX classes.
 *
 * @extends AbstractNodeVisitor<void>
 */
final class ValidatorNodeVisitor extends AbstractNodeVisitor
{
    private const array VALID_ASSERTIONS = [
        'A' => true, 'z' => true, 'Z' => true,
        'G' => true, 'b' => true, 'B' => true,
    ];

    private const array VALID_PCRE_VERBS = [
        'FAIL' => true, 'ACCEPT' => true, 'COMMIT' => true,
        'PRUNE' => true, 'SKIP' => true, 'THEN' => true,
        'DEFINE' => true, 'MARK' => true,
        'UTF8' => true, 'UTF' => true, 'UCP' => true,
        'CR' => true, 'LF' => true, 'CRLF' => true,
        'BSR_ANYCRLF' => true, 'BSR_UNICODE' => true,
        'NO_AUTO_POSSESS' => true,
        'script_run' => true, 'atomic_script_run' => true,
    ];

    private const array VALID_POSIX_CLASSES = [
        'alnum' => true, 'alpha' => true, 'ascii' => true,
        'blank' => true, 'cntrl' => true, 'digit' => true,
        'graph' => true, 'lower' => true, 'print' => true,
        'punct' => true, 'space' => true, 'upper' => true,
        'word' => true, 'xdigit' => true,
    ];

    /**
     * Tracks the depth of nested unbounded quantifiers to detect ReDoS.
     */
    private int $quantifierDepth = 0;

    /**
     * Tracks if the visitor is currently inside a lookbehind.
     */
    private bool $inLookbehind = false;

    /**
     * Tracks the highest *defined* capturing group number.
     */
    private int $groupCount = 0;

    /**
     * Tracks all *defined* named groups to check for duplicates.
     *
     * @var array<string, true>
     */
    private array $namedGroups = [];

    private ?Node\NodeInterface $previousNode = null;

    private ?Node\NodeInterface $nextNode = null;

    /**
     * Whether the (?J) flag is active, allowing duplicate named groups.
     */
    private bool $J_modifier = false;

    /**
     * Caches the validation result for Unicode properties.
     *
     * @var array<string, bool>
     */
    private static array $unicodePropCache = [];

    public function __construct(
        private readonly CharSetAnalyzer $charSetAnalyzer = new CharSetAnalyzer(),
    ) {}

    /**
     * Validates the root `RegexNode`.
     *
     * Purpose: This is the entry point for the validation process. It initializes the
     * visitor's internal state (like `quantifierDepth` and `groupCount`) and then
     * recursively triggers validation for the main pattern. This ensures that the
     * entire regex structure adheres to semantic rules.
     *
     * @param Node\RegexNode $node the root node of the AST
     *
     * @throws ParserException if any semantic validation rule is violated within the regex
     */
    #[\Override]
    public function visitRegex(Node\RegexNode $node): void
    {
        // Reset state for this validation run. This ensures the visitor
        // instance is clean, even if it's reused (though cloning is safer).
        $this->quantifierDepth = 0;
        $this->inLookbehind = false;
        $this->groupCount = 0;
        $this->namedGroups = [];
        $this->J_modifier = str_contains($node->flags, 'J');
        $this->previousNode = null;
        $this->nextNode = null;

        $node->pattern->accept($this);
    }

    /**
     * Validates an `AlternationNode`.
     *
     * Purpose: This method recursively validates each alternative branch within an
     * alternation. It ensures that each possible path in the regex is semantically
     * valid.
     *
     * @param Node\AlternationNode $node the alternation node to validate
     *
     * @throws ParserException if any semantic validation rule is violated within an alternative
     */
    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): void
    {
        // Note: PHP 7.3+ (PCRE2) supports variable-length lookbehinds,
        // so we no longer enforce fixed-length or same-length alternation restrictions.

        $previous = $this->previousNode;
        $next = $this->nextNode;

        foreach ($node->alternatives as $alt) {
            $this->previousNode = null;
            $this->nextNode = null;
            $alt->accept($this);
        }

        $this->previousNode = $previous;
        $this->nextNode = $next;
    }

    /**
     * Validates a `SequenceNode`.
     *
     * Purpose: This method recursively validates each child node within a sequence.
     * It ensures that the ordered components of the regex are individually valid.
     *
     * @param Node\SequenceNode $node the sequence node to validate
     *
     * @throws ParserException if any semantic validation rule is violated within a child node
     */
    #[\Override]
    public function visitSequence(Node\SequenceNode $node): void
    {
        $previous = $this->previousNode;
        $next = $this->nextNode;
        $total = \count($node->children);
        $last = null;

        foreach ($node->children as $index => $child) {
            $this->previousNode = $last;
            $this->nextNode = $index + 1 < $total ? $node->children[$index + 1] : null;
            $child->accept($this);
            $last = $child;
        }

        $this->previousNode = $previous;
        $this->nextNode = $next;
    }

    /**
     * Validates a `GroupNode`.
     *
     * Purpose: This method validates various types of groups, including capturing,
     * non-capturing, and lookarounds. It tracks the `groupCount` and `namedGroups`
     * to ensure backreferences and subroutines refer to existing groups. It also
     * manages the `inLookbehind` state, which is crucial for validating elements
     * that have restrictions inside lookbehinds (e.g., `\K`).
     *
     * @param Node\GroupNode $node the group node to validate
     *
     * @throws ParserException if a duplicate named group is found, or if any
     *                         semantic rule is violated within the group's child
     */
    #[\Override]
    public function visitGroup(Node\GroupNode $node): void
    {
        $wasInLookbehind = $this->inLookbehind;
        $previous = $this->previousNode;
        $next = $this->nextNode;

        if (\in_array(
            $node->type,
            [GroupType::T_GROUP_LOOKBEHIND_POSITIVE, GroupType::T_GROUP_LOOKBEHIND_NEGATIVE],
            true,
        )) {
            $this->inLookbehind = true;
        }

        // Track defined capturing groups
        if (GroupType::T_GROUP_CAPTURING === $node->type) {
            $this->groupCount++;
        } elseif (GroupType::T_GROUP_NAMED === $node->type) {
            $this->groupCount++;
            if (null !== $node->name) {
                // Only throw error if (?J) is NOT active
                if (isset($this->namedGroups[$node->name]) && !$this->J_modifier) {
                    throw new ParserException(\sprintf('Duplicate group name "%s" at position %d.', $node->name, $node->startPosition));
                }
                $this->namedGroups[$node->name] = true;
            }
        } elseif (GroupType::T_GROUP_INLINE_FLAGS === $node->type && null !== $node->flags) {
            // Check for inline (?J) flag
            if (str_contains($node->flags, 'J')) {
                $this->J_modifier = true;
            }
        }

        $this->previousNode = null;
        $this->nextNode = null;
        $node->child->accept($this);
        $this->previousNode = $previous;
        $this->nextNode = $next;

        $this->inLookbehind = $wasInLookbehind; // Restore state
    }

    /**
     * Validates a `QuantifierNode`.
     *
     * Purpose: This method performs critical validations for quantifiers. It checks
     * for invalid ranges (e.g., `{5,2}`), and, importantly, detects potential
     * catastrophic backtracking (ReDoS) by tracking nested unbounded quantifiers.
     * It also ensures that quantifiers are not used in contexts where they are
     * disallowed (e.g., `\K` in lookbehinds).
     *
     * @param Node\QuantifierNode $node the quantifier node to validate
     *
     * @throws ParserException if an invalid quantifier range is found, or if a
     *                         potential ReDoS pattern (nested unbounded quantifier) is detected
     */
    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): void
    {
        // 1. Validate quantifier range (e.g., {5,2})
        [$min, $max] = $this->parseQuantifierBounds($node->quantifier);
        if (-1 !== $max && $min > $max) {
            throw new ParserException(\sprintf('Invalid quantifier range "%s": min > max at position %d.', $node->quantifier, $node->startPosition));
        }

        $isUnbounded = -1 === $max; // *, +, or {n,}

        // Possessive quantifiers (*+, ++, ?+, {n,}+) cannot backtrack,
        // so they are safe from catastrophic backtracking (ReDoS).
        $isPossessive = QuantifierType::T_POSSESSIVE === $node->type;

        // Note: PHP 7.3+ (PCRE2) supports variable-length lookbehinds,
        // so we no longer enforce fixed-length or same-length alternation restrictions.

        // 2. Check for Catastrophic Backtracking (ReDoS)
        // Only non-possessive unbounded quantifiers can cause ReDoS.
        $hasBoundarySeparator = false;

        if ($isUnbounded && !$isPossessive) {
            $hasBoundarySeparator = $this->hasMutuallyExclusiveBoundary($this->previousNode, $node->node);

            if ($this->quantifierDepth > 0 && !$hasBoundarySeparator) {
                throw new ParserException(\sprintf('Potential catastrophic backtracking (ReDoS): nested unbounded quantifier "%s" at position %d.', $node->quantifier, $node->startPosition));
            }
            if (!$hasBoundarySeparator) {
                $this->quantifierDepth++;
            }
        }

        $node->node->accept($this);

        if ($isUnbounded && !$isPossessive && !$hasBoundarySeparator) {
            $this->quantifierDepth--;
        }
    }

    /**
     * Validates a `LiteralNode`.
     *
     * Purpose: Literal nodes generally do not require semantic validation beyond what
     * the Lexer and Parser already handle (e.g., valid characters). This method serves
     * as a placeholder for future, more complex literal-specific validations if needed.
     *
     * @param Node\LiteralNode $node the literal node to validate
     */
    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): void
    {
        // No semantic validation needed for literals
    }

    /**
     * Validates a `CharTypeNode`.
     *
     * Purpose: Character type nodes (e.g., `\d`, `\s`) are typically validated at the
     * Lexer/Parser level for syntactic correctness. This method serves as a placeholder
     * for any future semantic checks specific to character types.
     *
     * @param Node\CharTypeNode $node the character type node to validate
     */
    #[\Override]
    public function visitCharType(Node\CharTypeNode $node): void
    {
        // No semantic validation needed for char types
    }

    /**
     * Validates a `DotNode`.
     *
     * Purpose: The dot wildcard (`.`) is a fundamental regex element and typically
     * does not require semantic validation beyond its basic existence. This method
     * serves as a placeholder.
     *
     * @param Node\DotNode $node the dot node to validate
     */
    #[\Override]
    public function visitDot(Node\DotNode $node): void
    {
        // No semantic validation needed for dot
    }

    /**
     * Validates an `AnchorNode`.
     *
     * Purpose: Anchor nodes (e.g., `^`, `$`) define positions and are generally
     * semantically valid if syntactically correct. This method serves as a placeholder.
     *
     * @param Node\AnchorNode $node the anchor node to validate
     */
    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): void
    {
        // No semantic validation needed for anchors
    }

    /**
     * Validates an `AssertionNode`.
     *
     * Purpose: This method checks if the assertion (e.g., `\b`, `\A`) is a known
     * and valid PCRE assertion. While the Lexer/Parser handles basic syntax, this
     * provides an additional layer of semantic correctness.
     *
     * @param Node\AssertionNode $node the assertion node to validate
     *
     * @throws ParserException if an invalid or unknown assertion is encountered
     */
    #[\Override]
    public function visitAssertion(Node\AssertionNode $node): void
    {
        if (!isset(self::VALID_ASSERTIONS[$node->value])) {
            // This should be caught by the Lexer/Parser, but validates as a safeguard.
            throw new ParserException(\sprintf('Invalid assertion: \%s at position %d.', $node->value, $node->startPosition));
        }
    }

    /**
     * Validates a `KeepNode`.
     *
     * Purpose: This method enforces the rule that the `\K` (keep) assertion is not
     * allowed inside lookbehind groups. This is a PCRE-specific semantic restriction.
     *
     * @param Node\KeepNode $node the keep node to validate
     *
     * @throws ParserException if `\K` is found within a lookbehind
     */
    #[\Override]
    public function visitKeep(Node\KeepNode $node): void
    {
        if ($this->inLookbehind) {
            throw new ParserException(\sprintf('\K (keep) is not allowed in lookbehinds at position %d.', $node->startPosition));
        }
    }

    /**
     * Validates a `CharClassNode`.
     *
     * Purpose: This method recursively validates each part within a character class.
     * This ensures that all components (literals, ranges, POSIX classes, etc.)
     * inside the `[...]` are individually valid.
     *
     * @param Node\CharClassNode $node the character class node to validate
     *
     * @throws ParserException if any semantic validation rule is violated within a part of the character class
     */
    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): void
    {
        foreach ($node->parts as $part) {
            $part->accept($this);
        }
    }

    /**
     * Validates a `RangeNode`.
     *
     * Purpose: This method performs crucial validations for character ranges (e.g., `a-z`)
     * within a character class. It ensures that:
     * 1. The start and end of the range are single-character nodes.
     * 2. For literal characters, the start character's ordinal value does not exceed the end character's.
     *
     * @param Node\RangeNode $node the range node to validate
     *
     * @throws ParserException If the range is invalid (e.g., `z-a`, multi-character range endpoints).
     */
    #[\Override]
    public function visitRange(Node\RangeNode $node): void
    {
        // 1. Validation: Ensure start and end nodes represent a single character.
        // We allow LiteralNode, but also UnicodeNode, OctalNode, etc.
        if (!$this->isSingleCharNode($node->start) || !$this->isSingleCharNode($node->end)) {
            throw new ParserException(\sprintf(
                'Invalid range at position %d: ranges must be between literal characters or single escape sequences. Found %s and %s.',
                $node->startPosition,
                $node->start::class,
                $node->end::class,
            ));
        }

        // 2. Validation: Ensure characters are single-byte or single codepoint (for LiteralNodes).
        if ($node->start instanceof Node\LiteralNode && \strlen($node->start->value) > 1) {
            throw new ParserException(\sprintf('Invalid range at position %d: start char must be a single character.', $node->startPosition));
        }
        if ($node->end instanceof Node\LiteralNode && \strlen($node->end->value) > 1) {
            throw new ParserException(\sprintf('Invalid range at position %d: end char must be a single character.', $node->startPosition));
        }

        // 3. Validation: ASCII/Unicode order check.
        // Note: We only strictly compare two LiteralNodes here to avoid complex cross-type decoding logic.
        if ($node->start instanceof Node\LiteralNode && $node->end instanceof Node\LiteralNode) {
            if (mb_ord($node->start->value) > mb_ord($node->end->value)) {
                throw new ParserException(\sprintf(
                    'Invalid range "%s-%s" at position %d: start character comes after end character.',
                    $node->start->value,
                    $node->end->value,
                    $node->startPosition,
                ));
            }
        }
    }

    /**
     * Validates a `BackrefNode`.
     *
     * Purpose: This method ensures that backreferences (e.g., `\1`, `\k<name>`) refer
     * to existing capturing groups. It checks both numeric and named backreferences
     * against the `groupCount` and `namedGroups` tracked by the visitor.
     *
     * @param Node\BackrefNode $node the backreference node to validate
     *
     * @throws ParserException if the backreference refers to a non-existent group or uses invalid syntax
     */
    #[\Override]
    public function visitBackref(Node\BackrefNode $node): void
    {
        $ref = $node->ref;

        if (preg_match('/^\\\\(\d+)$/', $ref, $matches)) {
            // Numeric backref: \1, \2, etc.
            $num = (int) $matches[1];
            if (0 === $num) {
                throw new ParserException('Backreference \0 is not valid');
            }
            if ($num > $this->groupCount) {
                throw new ParserException(\sprintf('Backreference to non-existent group: \%d at position %d.', $num, $node->startPosition));
            }

            return;
        }

        // Named backref: \k<name> or \k'name' or \k{name}
        if (preg_match('/^\\\\k[<{\'](?<name>\w+)[>}\']$/', $ref, $matches)) {
            $name = $matches['name'];
            if (!isset($this->namedGroups[$name])) {
                throw new ParserException(\sprintf('Backreference to non-existent named group: "%s"', $name));
            }

            return;
        }

        // Bare name (used in conditionals like (?(name)...))
        if (preg_match('/^\w+$/', $ref)) {
            if (!isset($this->namedGroups[$ref])) {
                throw new ParserException(\sprintf('Backreference to non-existent named group: "%s"', $ref));
            }

            return;
        }

        // \g backref: \g{N}, \gN, \g{-N}
        if (preg_match('/^\\\\g\{?(?<num>[0-9+-]+)\}?$/', $ref, $matches)) {
            $numStr = $matches['num'];
            if ('0' === $numStr || '+0' === $numStr || '-0' === $numStr) {
                return; // \g{0} is a valid reference to the entire pattern.
            }

            $num = (int) $numStr;
            if ($num > 0 && $num > $this->groupCount) {
                throw new ParserException(\sprintf('Backreference to non-existent group: \g{%d} at position %d.', $num, $node->startPosition));
            }
            if ($num < 0 && abs($num) > $this->groupCount) {
                throw new ParserException(\sprintf('Relative backreference \g{%d} at position %d exceeds total group count (%d).', $num, $node->startPosition, $this->groupCount));
            }

            return;
        }

        // Note: \g<name> is a subroutine, handled by visitSubroutine, not a backref
        throw new ParserException(\sprintf('Invalid backreference syntax: "%s" at position %d.', $ref, $node->startPosition));
    }

    /**
     * Validates a `UnicodeNode`.
     *
     * Purpose: This method checks the semantic validity of Unicode character escapes
     * (e.g., `\x{2603}`). Specifically, it ensures that the specified Unicode code point
     * is within the valid range (0 to 0x10FFFF).
     *
     * @param Node\UnicodeNode $node the Unicode node to validate
     *
     * @throws ParserException if the Unicode code point is out of range
     */
    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): void
    {
        // The Lexer/Parser combination already ensures these are
        // syntactically valid hex/octal. We validate the *value*.
        $code = -1;
        if (preg_match('/^\\\\x([0-9a-fA-F]{2})$/', $node->code, $m)) {
            $code = (int) hexdec($m[1]);
        } elseif (preg_match('/^\\\\u\{([0-9a-fA-F]+)\}$/', $node->code, $m)) {
            $code = (int) hexdec($m[1]);
        }

        if ($code > 0x10FFFF) {
            throw new ParserException(\sprintf('Invalid Unicode codepoint "%s" (out of range) at position %d.', $node->code, $node->startPosition));
        }
    }

    #[\Override]
    public function visitUnicodeNamed(Node\UnicodeNamedNode $node): void
    {
        // TODO: Validate that the Unicode name is valid. For now, assume it's correct.
    }

    /**
     * Validates a `UnicodePropNode`.
     *
     * Purpose: This method performs a runtime check to ensure that the specified
     * Unicode property (e.g., `\p{L}`, `\P{N}`) is recognized and supported by the
     * underlying PCRE engine. It uses `preg_match` with error suppression to
     * determine validity, caching results for performance.
     *
     * @param Node\UnicodePropNode $node the Unicode property node to validate
     *
     * @throws ParserException if the Unicode property is invalid or unsupported
     */
    #[\Override]
    public function visitUnicodeProp(Node\UnicodePropNode $node): void
    {
        // The only 100% "prod-ready" way to validate a Unicode property
        // is to check it against the PCRE engine being used.
        $prop = $node->prop;
        $key = (\strlen($prop) > 1 || str_starts_with($prop, '^')) ? "p{{$prop}}" : "p{$prop}";

        if (!isset(self::$unicodePropCache[$key])) {
            // We use error suppression as preg_match will warn on an invalid property.
            // We check the *return value* and preg_last_error() to confirm validity.
            // The 'u' flag is essential.
            $result = @preg_match("/^\\{$key}$/u", '');
            $error = preg_last_error();

            // PREG_NO_ERROR means it compiled successfully.
            self::$unicodePropCache[$key] = false !== $result && \PREG_NO_ERROR === $error;
        }

        if (false === self::$unicodePropCache[$key]) {
            throw new ParserException(\sprintf('Invalid or unsupported Unicode property: \%s at position %d.', $key, $node->startPosition));
        }
    }

    /**
     * Validates an `OctalNode`.
     *
     * Purpose: This method validates modern octal character escapes (e.g., `\o{101}`).
     * It ensures that the octal code contains only valid octal digits (0-7) and that
     * the resulting character value does not exceed the single-byte limit (0-255)
     * enforced by PCRE for this syntax.
     *
     * @param Node\OctalNode $node the octal node to validate
     *
     * @throws ParserException if the octal code is invalid or out of range
     */
    #[\Override]
    public function visitOctal(Node\OctalNode $node): void
    {
        // \o{...}
        if (preg_match('/^\\\\o\{([0-9]+)\}$/', $node->code, $m)) {
            $octalStr = $m[1];

            // Check if all digits are valid octal (0-7)
            if (!preg_match('/^[0-7]+$/', $octalStr)) {
                throw new ParserException(\sprintf('Invalid octal codepoint "%s" at position %d.', $node->code, $node->startPosition));
            }

            // PCRE limits \o{} to single-byte values (0-255)
            $code = (int) octdec($octalStr);
            if ($code > 0xFF) {
                throw new ParserException(\sprintf('Invalid octal codepoint "%s" at position %d.', $node->code, $node->startPosition));
            }
        }
    }

    /**
     * Validates an `OctalLegacyNode`.
     *
     * Purpose: This method validates legacy octal character escapes (e.g., `\077`).
     * It specifically flags `\0` as an invalid backreference (as it's ambiguous)
     * and checks if the octal code, if interpreted as a character, is within a
     * reasonable range.
     *
     * @param Node\OctalLegacyNode $node the legacy octal node to validate
     *
     * @throws ParserException if `\0` is used as a backreference or if the octal
     *                         codepoint is out of a valid range
     */
    #[\Override]
    public function visitOctalLegacy(Node\OctalLegacyNode $node): void
    {
        // \0 is treated as an invalid backreference
        if ('0' === $node->code) {
            throw new ParserException('Backreference \0 is not valid');
        }

        // Check if all digits are valid octal
        if (!preg_match('/^[0-7]+$/', $node->code)) {
            throw new ParserException(\sprintf('Invalid octal codepoint "\%s" at position %d.', $node->code, $node->startPosition));
        }

        // \0...
        $code = (int) octdec($node->code);
        if ($code > 0x10FFFF) {
            // This is unlikely as \077 is max, but good to check.
            throw new ParserException(\sprintf('Invalid legacy octal codepoint "\%s" (out of range) at position %d.', $node->code, $node->startPosition));
        }
    }

    /**
     * Validates a `PosixClassNode`.
     *
     * Purpose: This method checks if the specified POSIX character class (e.g., `[:alpha:]`)
     * is a recognized and valid class. It also enforces specific PCRE rules, such as
     * the non-negatability of the `word` class.
     *
     * @param Node\PosixClassNode $node the POSIX class node to validate
     *
     * @throws ParserException if an invalid POSIX class is encountered or if a class
     *                         is negated when it shouldn't be
     */
    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): void
    {
        $class = strtolower($node->class);
        $isNegated = false;

        if (str_starts_with($class, '^')) {
            $class = substr($class, 1);
            $isNegated = true;
        }

        if (!isset(self::VALID_POSIX_CLASSES[$class])) {
            throw new ParserException(\sprintf('Invalid POSIX class: "%s" at position %d.', $node->class, $node->startPosition));
        }

        if ($isNegated && 'word' === $class) {
            // [[:^word:]] is not a valid construct.
            throw new ParserException(\sprintf('Negation of POSIX class "word" is not supported at position %d.', $node->startPosition));
        }
    }

    /**
     * Validates a `CommentNode`.
     *
     * Purpose: Comments (`(?#...)`) are ignored by the regex engine and thus do not
     * require semantic validation. This method serves as a pass-through.
     *
     * @param Node\CommentNode $node the comment node to validate
     */
    #[\Override]
    public function visitComment(Node\CommentNode $node): void
    {
        // Comments are ignored in validation
    }

    /**
     * Validates a `ConditionalNode`.
     *
     * Purpose: This method ensures that the condition within a conditional subpattern
     * (e.g., `(?(condition)yes|no)`) is of a valid type (e.g., a backreference, a
     * subroutine call, or a lookaround assertion). It then recursively validates
     * the condition, the "yes" pattern, and the "no" pattern.
     *
     * @param Node\ConditionalNode $node the conditional node to validate
     *
     * @throws ParserException if the condition is not a valid type or if any
     *                         semantic rule is violated within the conditional branches
     */
    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): void
    {
        // Check if the condition is a valid *type* of condition first
        // (e.g., a backreference, a subroutine call, or a lookaround)
        if ($node->condition instanceof Node\BackrefNode) {
            // This is (?(1)...) or (?(<name>)...) or (?(name)...)
            // For bare names, check if the group exists before calling accept
            $ref = $node->condition->ref;
            if (preg_match('/^\w+$/', $ref) && !isset($this->namedGroups[$ref])) {
                // Bare name that doesn't exist - this is an invalid conditional
                throw new ParserException(\sprintf('Invalid conditional construct at position %d. Condition must be a group reference, lookaround, or (DEFINE).', $node->condition->getStartPosition()));
            }
            // Now validate the backreference itself
            $node->condition->accept($this);
        } elseif ($node->condition instanceof Node\SubroutineNode) {
            $ref = $node->condition->reference;
            if ('R' === $ref || '0' === $ref) {
                // Always valid recursion condition to entire pattern.
            } elseif (preg_match('/^R-?\d+$/', $ref)) {
                $num = (int) substr($ref, 1);
                if ($this->groupCount > 0) {
                    if ($num > 0 && $num > $this->groupCount) {
                        throw new ParserException(\sprintf('Recursion condition to non-existent group: %d at position %d.', $num, $node->condition->startPosition));
                    }
                    if ($num < 0 && abs($num) > $this->groupCount) {
                        throw new ParserException(\sprintf('Relative recursion condition (%d) at position %d exceeds total group count (%d).', $num, $node->condition->startPosition, $this->groupCount));
                    }
                }
            } else {
                $node->condition->accept($this);
            }
        } elseif ($node->condition instanceof Node\GroupNode && \in_array($node->condition->type, [
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
        ], true)) {
            // This is (?(?=...)...) etc. This is valid.
            $node->condition->accept($this);
        } elseif ($node->condition instanceof Node\AssertionNode && 'DEFINE' === $node->condition->value) {
            // (?(DEFINE)...) This is valid.
            $node->condition->accept($this);
        } else {
            // Any other atom is not a valid condition
            throw new ParserException(\sprintf('Invalid conditional construct at position %d. Condition must be a group reference, lookaround, or (DEFINE).', $node->condition->getStartPosition()));
        }

        $node->yes->accept($this);
        $node->no->accept($this);
    }

    /**
     * Validates a `SubroutineNode`.
     *
     * Purpose: This method ensures that subroutine calls (e.g., `(?R)`, `(?&name)`)
     * refer to existing capturing groups or are valid special references (`R`, `0`).
     * It checks both numeric and named subroutine references against the `groupCount`
     * and `namedGroups` tracked by the visitor.
     *
     * @param Node\SubroutineNode $node the subroutine node to validate
     *
     * @throws ParserException if the subroutine refers to a non-existent group
     */
    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): void
    {
        $ref = $node->reference;

        if ('R' === $ref || '0' === $ref) {
            return; // (?R) or (?0) is always valid.
        }

        if (str_starts_with($ref, 'R')) {
            $numPart = substr($ref, 1);
            if ('' === $numPart) {
                return;
            }

            if (ctype_digit($numPart)) {
                $num = (int) $numPart;
                if ($num > $this->groupCount) {
                    throw new ParserException(\sprintf('Subroutine call to non-existent group: %d at position %d.', $num, $node->startPosition));
                }

                return;
            }

            if (str_starts_with($numPart, '-') && ctype_digit(substr($numPart, 1))) {
                $num = (int) $numPart;
                if (abs($num) > $this->groupCount) {
                    throw new ParserException(\sprintf('Relative subroutine call (%d) at position %d exceeds total group count (%d).', $num, $node->startPosition, $this->groupCount));
                }

                return;
            }
        }

        // Numeric reference: (?1), (?-1)
        if (ctype_digit($ref) || (str_starts_with($ref, '-') && ctype_digit(substr($ref, 1)))) {
            $num = (int) $ref;
            if (0 === $num) {
                return; // (?0) is an alias for (?R)
            }
            if ($num > 0 && $num > $this->groupCount) {
                throw new ParserException(\sprintf('Subroutine call to non-existent group: %d at position %d.', $num, $node->startPosition));
            }
            if ($num < 0 && abs($num) > $this->groupCount) {
                throw new ParserException(\sprintf('Relative subroutine call (%d) at position %d exceeds total group count (%d).', $num, $node->startPosition, $this->groupCount));
            }

            return;
        }

        // Named reference: (?&name), (?P>name), \g<name>
        if (!isset($this->namedGroups[$ref])) {
            throw new ParserException(\sprintf('Subroutine call to non-existent named group: "%s" at position %d.', $ref, $node->startPosition));
        }
    }

    /**
     * Validates a `PcreVerbNode`.
     *
     * Purpose: This method checks if the specified PCRE verb (e.g., `(*FAIL)`, `(*MARK)`)
     * is a recognized and valid verb. This ensures that only supported PCRE directives
     * are used in the regex.
     *
     * @param Node\PcreVerbNode $node the PCRE verb node to validate
     *
     * @throws ParserException if an invalid or unsupported PCRE verb is encountered
     */
    #[\Override]
    public function visitPcreVerb(Node\PcreVerbNode $node): void
    {
        $verbName = explode(':', $node->verb, 2)[0];

        if (!isset(self::VALID_PCRE_VERBS[$verbName])) {
            throw new ParserException(\sprintf('Invalid or unsupported PCRE verb: "%s" at position %d.', $verbName, $node->startPosition));
        }
    }

    /**
     * Validates a `DefineNode`.
     *
     * Purpose: This method recursively validates the content within a `(?(DEFINE)...)`
     * block. This ensures that the patterns defined for later subroutine calls are
     * themselves semantically valid.
     *
     * @param Node\DefineNode $node the define node to validate
     *
     * @throws ParserException if any semantic validation rule is violated within the DEFINE block
     */
    #[\Override]
    public function visitDefine(Node\DefineNode $node): void
    {
        $node->content->accept($this);
    }

    #[\Override]
    public function visitLimitMatch(Node\LimitMatchNode $node): void
    {
        // No specific validation needed for this node.
    }

    #[\Override]
    public function visitCallout(Node\CalloutNode $node): void
    {
        $position = $node->startPosition + 4;

        if (\is_int($node->identifier)) {
            if ($node->identifier < 0 || $node->identifier > 255) {
                throw new ParserException(\sprintf('Callout identifier must be between 0 and 255, got %d at position %d.', $node->identifier, $position));
            }
        } elseif (\is_string($node->identifier)) {
            // PCRE2 allows any string as an argument, but it's good to ensure it's not empty.
            if ('' === $node->identifier) {
                throw new ParserException(\sprintf('Callout string identifier cannot be empty at position %d.', $position));
            }
        } else {
            // This case should ideally be caught by the Lexer/Parser, but as a safeguard.
            throw new ParserException(\sprintf('Invalid callout identifier type at position %d.', $position));
        }
    }

    /**
     * Detects if the trailing set of a previous atom and the leading set of the current atom do not overlap.
     */
    private function hasMutuallyExclusiveBoundary(?Node\NodeInterface $previous, Node\NodeInterface $current): bool
    {
        if (null === $previous) {
            return false;
        }

        $previousTail = $this->charSetAnalyzer->lastChars($previous);
        $currentHead = $this->charSetAnalyzer->firstChars($current);

        if ($previousTail->isUnknown() || $currentHead->isUnknown()) {
            return false;
        }

        return !$previousTail->intersects($currentHead);
    }

    /**
     * Helper to check if a node represents a valid single character for a range.
     *
     * Purpose: This private helper method is used by `visitRange` to determine if a
     * given node can serve as a valid start or end point for a character range.
     * It ensures that only single-character representations are used for ranges.
     *
     * @param Node\NodeInterface $node the node to check
     *
     * @return bool true if the node represents a single character, false otherwise
     */
    private function isSingleCharNode(Node\NodeInterface $node): bool
    {
        return $node instanceof Node\LiteralNode
            || $node instanceof Node\UnicodeNode
            || $node instanceof Node\OctalNode
            || $node instanceof Node\OctalLegacyNode;
        // CharTypeNode (e.g., \d) is technically invalid in a standard PCRE range start/end,
        // but we exclude it here to remain spec-compliant unless lenient mode is desired.
    }

    /**
     * Parses a quantifier string (e.g., "{2,5}") into min/max bounds.
     *
     * Purpose: This private helper method extracts the minimum and maximum repetition
     * counts from a quantifier string. It's used by `visitQuantifier` to validate
     * the range and identify unbounded quantifiers for ReDoS detection.
     *
     * @param string $q The quantifier string (e.g., "*", "+", "?", "{n,m}").
     *
     * @return array{0: int, 1: int} a tuple containing `[min, max]`, where `max = -1` means unbounded
     */
    private function parseQuantifierBounds(string $q): array
    {
        return match ($q) {
            '*' => [0, -1],
            '+' => [1, -1],
            '?' => [0, 1],
            default => preg_match('/^\{(\d+)(?:,(\d*))?\}$/', $q, $m) ?
                (isset($m[2]) ?
                    ('' === $m[2] ? [(int) $m[1], -1] : [(int) $m[1], (int) $m[2]]) : // {n,} or {n,m}
                    [(int) $m[1], (int) $m[1]] // {n}
                ) :
                [1, 1], // Should be impossible if Lexer is correct
        };
    }

    /**
     * Calculates the fixed length of a node.
     *
     * Purpose: This private helper method attempts to determine the fixed length
     * (in characters) that a given node will match. This is particularly useful
     * for validating lookbehinds, which traditionally require fixed-length patterns.
     * If a node can match a variable number of characters, it returns `null`.
     *
     * @param Node\NodeInterface $node the node for which to calculate the length
     *
     * @return int|null the fixed length of the node, or `null` if its length is variable
     */
    private function calculateFixedLength(Node\NodeInterface $node): ?int
    {
        return match (true) {
            $node instanceof Node\LiteralNode => mb_strlen($node->value),
            $node instanceof Node\CharTypeNode, $node instanceof Node\DotNode => 1,
            $node instanceof Node\AnchorNode, $node instanceof Node\AssertionNode => 0,
            $node instanceof Node\SequenceNode => $this->calculateSequenceLength($node),
            $node instanceof Node\GroupNode => $this->calculateFixedLength($node->child),
            $node instanceof Node\QuantifierNode => $this->calculateQuantifierLength($node),
            $node instanceof Node\CharClassNode => 1,
            $node instanceof Node\AlternationNode => null, // Handled separately
            default => null, // Unknown or variable
        };
    }

    /**
     * Calculates the fixed length of a sequence node.
     *
     * Purpose: This private helper method is used by `calculateFixedLength` to
     * sum the fixed lengths of all children within a `SequenceNode`. If any child
     * has a variable length, the entire sequence is considered variable length.
     *
     * @param Node\SequenceNode $node the sequence node for which to calculate the length
     *
     * @return int|null the fixed length of the sequence, or `null` if its length is variable
     */
    private function calculateSequenceLength(Node\SequenceNode $node): ?int
    {
        $total = 0;
        foreach ($node->children as $child) {
            $length = $this->calculateFixedLength($child);
            if (null === $length) {
                return null; // Variable length
            }
            $total += $length;
        }

        return $total;
    }

    /**
     * Calculates the fixed length of a quantifier node.
     *
     * Purpose: This private helper method is used by `calculateFixedLength` to
     * determine the length of a quantified node. A quantified node only has a
     * fixed length if its quantifier specifies an exact number of repetitions
     * (e.g., `{3}`) and its child also has a fixed length.
     *
     * @param Node\QuantifierNode $node the quantifier node for which to calculate the length
     *
     * @return int|null the fixed length of the quantified node, or `null` if its length is variable
     */
    private function calculateQuantifierLength(Node\QuantifierNode $node): ?int
    {
        [$min, $max] = $this->parseQuantifierBounds($node->quantifier);

        // Only fixed if min == max (and both are not -1)
        if ($min !== $max || -1 === $max) {
            return null; // Variable length
        }

        $childLength = $this->calculateFixedLength($node->node);
        if (null === $childLength) {
            return null;
        }

        return $min * $childLength;
    }
}
