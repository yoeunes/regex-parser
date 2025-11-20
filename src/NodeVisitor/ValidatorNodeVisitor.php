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
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
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
 * Validates the semantic rules of a parsed regex Abstract Syntax Tree (AST).
 *
 * This visitor traverses the AST and checks for logical errors that are not
 * simple syntax errors, such as:
 * - Catastrophic backtracking (ReDoS) from nested quantifiers.
 * - Invalid quantifier ranges (e.g., {5,2}).
 * - Variable-length quantifiers inside lookbehinds.
 * - References to non-existent capturing groups (backreferences/subroutines).
 * - Duplicate capturing group names.
 * - Invalid character ranges (e.g., [z-a]).
 * - Invalid Unicode properties or POSIX classes.
 *
 * @implements NodeVisitorInterface<void>
 */
final class ValidatorNodeVisitor implements NodeVisitorInterface
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

    /**
     * Caches the validation result for Unicode properties.
     *
     * @var array<string, bool>
     */
    private static array $unicodePropCache = [];

    /**
     * @throws ParserException
     */
    public function visitRegex(RegexNode $node): void
    {
        // Reset state for this validation run. This ensures the visitor
        // instance is clean, even if it's reused (though cloning is safer).
        $this->quantifierDepth = 0;
        $this->inLookbehind = false;
        $this->groupCount = 0;
        $this->namedGroups = [];

        // Note: The Parser is responsible for validating the flags themselves
        // (e.g., no unknown flags). This visitor only cares about *how* flags
        // (like 'u') might affect semantic validation.

        $node->pattern->accept($this);
    }

    /**
     * @throws ParserException
     */
    public function visitAlternation(AlternationNode $node): void
    {
        foreach ($node->alternatives as $alt) {
            $alt->accept($this);
        }
    }

    /**
     * @throws ParserException
     */
    public function visitSequence(SequenceNode $node): void
    {
        foreach ($node->children as $child) {
            $child->accept($this);
        }
    }

    /**
     * @throws ParserException
     */
    public function visitGroup(GroupNode $node): void
    {
        $wasInLookbehind = $this->inLookbehind;

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
                if (isset($this->namedGroups[$node->name])) {
                    throw new ParserException(\sprintf('Duplicate group name "%s" at position %d.', $node->name, $node->startPos));
                }
                $this->namedGroups[$node->name] = true;
            }
        }

        $node->child->accept($this);

        $this->inLookbehind = $wasInLookbehind; // Restore state
    }

    /**
     * @throws ParserException
     */
    public function visitQuantifier(QuantifierNode $node): void
    {
        // 1. Validate quantifier range (e.g., {5,2})
        [$min, $max] = $this->parseQuantifierBounds($node->quantifier);
        if (-1 !== $max && $min > $max) {
            throw new ParserException(\sprintf('Invalid quantifier range "%s": min > max at position %d.', $node->quantifier, $node->startPos));
        }

        $isUnbounded = -1 === $max; // *, +, or {n,}

        // 2. Validate quantifiers inside lookbehinds
        if ($this->inLookbehind) {
            // Strict check: Lookbehinds must be fixed length.
            // Any quantifier that allows variable length (min != max) is invalid.
            if ($min !== $max) {
                throw new ParserException(\sprintf('Variable-length quantifiers (%s) are not allowed in lookbehinds at position %d.', $node->quantifier, $node->startPos));
            }
        }

        // 3. Check for Catastrophic Backtracking (ReDoS)
        if ($isUnbounded) {
            if ($this->quantifierDepth > 0) {
                throw new ParserException(\sprintf('Potential catastrophic backtracking (ReDoS): nested unbounded quantifier "%s" at position %d.', $node->quantifier, $node->startPos));
            }
            $this->quantifierDepth++;
        }

        $node->node->accept($this);

        if ($isUnbounded) {
            $this->quantifierDepth--;
        }
    }

    public function visitLiteral(LiteralNode $node): void
    {
        // No semantic validation needed for literals
    }

    public function visitCharType(CharTypeNode $node): void
    {
        // No semantic validation needed for char types
    }

    public function visitDot(DotNode $node): void
    {
        // No semantic validation needed for dot
    }

    public function visitAnchor(AnchorNode $node): void
    {
        // No semantic validation needed for anchors
    }

    /**
     * @throws ParserException
     */
    public function visitAssertion(AssertionNode $node): void
    {
        if (!isset(self::VALID_ASSERTIONS[$node->value])) {
            // This should be caught by the Lexer/Parser, but validates as a safeguard.
            throw new ParserException(\sprintf('Invalid assertion: \%s at position %d.', $node->value, $node->startPos));
        }
    }

    /**
     * @throws ParserException
     */
    public function visitKeep(KeepNode $node): void
    {
        if ($this->inLookbehind) {
            throw new ParserException('\K (keep) is not allowed in lookbehinds at position %d.', $node->startPos);
        }
    }

    /**
     * @throws ParserException
     */
    public function visitCharClass(CharClassNode $node): void
    {
        foreach ($node->parts as $part) {
            $part->accept($this);
        }
    }

    /**
     * @throws ParserException
     */
    public function visitRange(RangeNode $node): void
    {
        // A range must be between two single-character literals
        if (!$node->start instanceof LiteralNode || !$node->end instanceof LiteralNode) {
            throw new ParserException(\sprintf('Invalid range at position %d: ranges must be between literal characters (e.g., "a-z"). Found non-literal.', $node->startPos));
        }

        if (mb_strlen($node->start->value) > 1 || mb_strlen($node->end->value) > 1) {
            throw new ParserException(\sprintf('Invalid range at position %d: range parts must be single characters.', $node->startPos));
        }

        // Check ASCII/Unicode code point values
        if (mb_ord($node->start->value) > mb_ord($node->end->value)) {
            throw new ParserException(\sprintf('Invalid range "%s-%s" at position %d: start character comes after end character.', $node->start->value, $node->end->value, $node->startPos));
        }
    }

    /**
     * @throws ParserException
     */
    public function visitBackref(BackrefNode $node): void
    {
        $ref = $node->ref;

        if (ctype_digit($ref)) {
            // Numeric backref: \1, \2, etc.
            $num = (int) $ref;
            if (0 === $num) {
                throw new ParserException('Backreference \0 is not valid');
            }
            if ($num > $this->groupCount) {
                throw new ParserException(\sprintf('Backreference to non-existent group: \%d at position %d.', $num, $node->startPos));
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
                throw new ParserException(\sprintf('Backreference to non-existent group: \g{%d} at position %d.', $num, $node->startPos));
            }
            if ($num < 0 && abs($num) > $this->groupCount) {
                throw new ParserException(\sprintf('Relative backreference \g{%d} at position %d exceeds total group count (%d).', $num, $node->startPos, $this->groupCount));
            }

            return;
        }

        // Note: \g<name> is a subroutine, handled by visitSubroutine, not a backref
        throw new ParserException(\sprintf('Invalid backreference syntax: "%s" at position %d.', $ref, $node->startPos));
    }

    /**
     * @throws ParserException
     */
    public function visitUnicode(UnicodeNode $node): void
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
            throw new ParserException(\sprintf('Invalid Unicode codepoint "%s" (out of range) at position %d.', $node->code, $node->startPos));
        }
    }

    /**
     * @throws ParserException
     */
    public function visitUnicodeProp(UnicodePropNode $node): void
    {
        // The only 100% "prod-ready" way to validate a Unicode property
        // is to check it against the PCRE engine being used.
        $prop = $node->prop;
        $key = (mb_strlen($prop) > 1 || str_starts_with($prop, '^')) ? "p{{$prop}}" : "p{$prop}";

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
            throw new ParserException(\sprintf('Invalid or unsupported Unicode property: \%s at position %d.', $key, $node->startPos));
        }
    }

    /**
     * @throws ParserException
     */
    public function visitOctal(OctalNode $node): void
    {
        // \o{...}
        if (preg_match('/^\\\\o\{([0-9]+)\}$/', $node->code, $m)) {
            $octalStr = $m[1];

            // Check if all digits are valid octal (0-7)
            if (!preg_match('/^[0-7]+$/', $octalStr)) {
                throw new ParserException(\sprintf('Invalid octal codepoint "%s" at position %d.', $node->code, $node->startPos));
            }

            // PCRE limits \o{} to single-byte values (0-255)
            $code = (int) octdec($octalStr);
            if ($code > 0xFF) {
                throw new ParserException(\sprintf('Invalid octal codepoint "%s" at position %d.', $node->code, $node->startPos));
            }
        }
    }

    /**
     * @throws ParserException
     */
    public function visitOctalLegacy(OctalLegacyNode $node): void
    {
        // \0 is treated as an invalid backreference
        if ('0' === $node->code) {
            throw new ParserException('Backreference \0 is not valid');
        }

        // \0...
        $code = (int) octdec($node->code);
        if ($code > 0x10FFFF) {
            // This is unlikely as \077 is max, but good to check.
            throw new ParserException(\sprintf('Invalid legacy octal codepoint "\%s" (out of range) at position %d.', $node->code, $node->startPos));
        }
    }

    /**
     * @throws ParserException
     */
    public function visitPosixClass(PosixClassNode $node): void
    {
        $class = strtolower($node->class);
        $isNegated = false;

        if (str_starts_with($class, '^')) {
            $class = substr($class, 1);
            $isNegated = true;
        }

        if (!isset(self::VALID_POSIX_CLASSES[$class])) {
            throw new ParserException(\sprintf('Invalid POSIX class: "%s" at position %d.', $node->class, $node->startPos));
        }

        if ($isNegated && 'word' === $class) {
            // [[:^word:]] is not a valid construct.
            throw new ParserException(\sprintf('Negation of POSIX class "word" is not supported at position %d.', $node->startPos));
        }
    }

    public function visitComment(CommentNode $node): void
    {
        // Comments are ignored in validation
    }

    /**
     * @throws ParserException
     */
    public function visitConditional(ConditionalNode $node): void
    {
        // Check if the condition is a valid *type* of condition first
        // (e.g., a backreference, a subroutine call, or a lookaround)
        if ($node->condition instanceof BackrefNode) {
            // This is (?(1)...) or (?(<name>)...) or (?(name)...)
            // For bare names, check if the group exists before calling accept
            $ref = $node->condition->ref;
            if (preg_match('/^\w+$/', $ref) && !isset($this->namedGroups[$ref])) {
                // Bare name that doesn't exist - this is an invalid conditional
                throw new ParserException(\sprintf('Invalid conditional construct at position %d. Condition must be a group reference, lookaround, or (DEFINE).', $node->condition->getStartPosition()));
            }
            // Now validate the backreference itself
            $node->condition->accept($this);
        } elseif ($node->condition instanceof SubroutineNode) {
            // This is (?(R)...) or (?(R1)...)
            $node->condition->accept($this);
        } elseif ($node->condition instanceof GroupNode && \in_array($node->condition->type, [
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
        ], true)) {
            // This is (?(?=...)...) etc. This is valid.
            $node->condition->accept($this);
        } elseif ($node->condition instanceof AssertionNode && 'DEFINE' === $node->condition->value) {
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
     * @throws ParserException
     */
    public function visitSubroutine(SubroutineNode $node): void
    {
        $ref = $node->reference;

        if ('R' === $ref || '0' === $ref) {
            return; // (?R) or (?0) is always valid.
        }

        // Numeric reference: (?1), (?-1)
        if (ctype_digit($ref) || (str_starts_with($ref, '-') && ctype_digit(substr($ref, 1)))) {
            $num = (int) $ref;
            if (0 === $num) {
                return; // (?0) is an alias for (?R)
            }
            if ($num > 0 && $num > $this->groupCount) {
                throw new ParserException(\sprintf('Subroutine call to non-existent group: %d at position %d.', $num, $node->startPos));
            }
            if ($num < 0 && abs($num) > $this->groupCount) {
                throw new ParserException(\sprintf('Relative subroutine call (%d) at position %d exceeds total group count (%d).', $num, $node->startPos, $this->groupCount));
            }

            return;
        }

        // Named reference: (?&name), (?P>name), \g<name>
        if (!isset($this->namedGroups[$ref])) {
            throw new ParserException(\sprintf('Subroutine call to non-existent named group: "%s" at position %d.', $ref, $node->startPos));
        }
    }

    /**
     * @throws ParserException
     */
    public function visitPcreVerb(PcreVerbNode $node): void
    {
        $verbName = explode(':', $node->verb, 2)[0];

        if (!isset(self::VALID_PCRE_VERBS[$verbName])) {
            throw new ParserException(\sprintf('Invalid or unsupported PCRE verb: "%s" at position %d.', $verbName, $node->startPos));
        }
    }

    /**
     * Parses a quantifier string (e.g., "{2,5}") into min/max bounds.
     *
     * @return array{0: int, 1: int} [min, max] where max = -1 means unbounded
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
}
