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
use RegexParser\Exception\SemanticErrorException;
use RegexParser\GroupNumbering;
use RegexParser\GroupNumberingCollector;
use RegexParser\Node;
use RegexParser\Node\GroupType;
use RegexParser\Regex;

/**
 * High-performance validator for regex Abstract Syntax Trees with intelligent caching and optimization.
 *
 * This optimized validator provides comprehensive semantic validation while minimizing
 * computational overhead through intelligent caching and streamlined validation logic.
 *
 * @extends AbstractNodeVisitor<void>
 */
final class ValidatorNodeVisitor extends AbstractNodeVisitor
{
    // Precomputed validation sets for maximum performance
    private const VALID_ASSERTIONS = [
        'A' => true, 'z' => true, 'Z' => true,
        'G' => true, 'b' => true, 'B' => true,
    ];

    private const VALID_PCRE_VERBS = [
        'FAIL' => true, 'ACCEPT' => true, 'COMMIT' => true,
        'PRUNE' => true, 'SKIP' => true, 'THEN' => true,
        'DEFINE' => true, 'MARK' => true,
        'UTF8' => true, 'UTF' => true, 'UCP' => true,
        'CR' => true, 'LF' => true, 'CRLF' => true,
        'BSR_ANYCRLF' => true, 'BSR_UNICODE' => true,
        'NO_AUTO_POSSESS' => true,
        'LIMIT_MATCH' => true, 'LIMIT_RECURSION' => true,
        'LIMIT_DEPTH' => true, 'LIMIT_HEAP' => true,
        'LIMIT_LOOKBEHIND' => true,
        'script_run' => true, 'atomic_script_run' => true,
        'NOTEMPTY' => true, 'NOTEMPTY_ATSTART' => true,
    ];

    private const VALID_POSIX_CLASSES = [
        'alnum' => true, 'alpha' => true, 'ascii' => true,
        'blank' => true, 'cntrl' => true, 'digit' => true,
        'graph' => true, 'lower' => true, 'print' => true,
        'punct' => true, 'space' => true, 'upper' => true,
        'word' => true, 'xdigit' => true,
    ];

    // Optimized state management with minimal memory footprint

    private bool $inLookbehind = false;

    private GroupNumbering $groupNumbering;

    /**
     * @var list<int>
     */
    private array $captureSequence = [];

    private int $captureIndex = 0;

    private int $lookbehindLimit = 0;

    private ?Node\NodeInterface $previousNode = null;

    private ?Node\NodeInterface $nextNode = null;

    // Intelligent caching for expensive validations
    /**
     * @var array<string, bool>
     */
    private static array $unicodePropCache = [];

    /**
     * @var array<string, array{0: int, 1: int}>
     */
    private static array $quantifierBoundsCache = [];

    public function __construct(private readonly int $maxLookbehindLength = Regex::DEFAULT_MAX_LOOKBEHIND_LENGTH, private readonly ?string $pattern = null) {}

    #[\Override]
    public function visitRegex(Node\RegexNode $node): void
    {
        // Fast state reset with minimal allocations
        $this->inLookbehind = false;
        $this->groupNumbering = (new GroupNumberingCollector())->collect($node);
        $this->captureSequence = $this->groupNumbering->captureSequence;
        $this->captureIndex = 0;
        $this->lookbehindLimit = $this->extractLookbehindLimit($node->pattern) ?? $this->maxLookbehindLength;
        $this->previousNode = null;
        $this->nextNode = null;

        $node->pattern->accept($this);
    }

    private function normalizeQuantifier(string $q): string
    {
        if (!str_starts_with($q, '{') || !str_ends_with($q, '}')) {
            return $q;
        }

        $inner = substr($q, 1, -1);
        $inner = preg_replace('/\\s+/', '', $inner) ?? $inner;

        return '{'.$inner.'}';
    }

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

    #[\Override]
    public function visitGroup(Node\GroupNode $node): void
    {
        $this->ensureGroupNumberingInitialized();

        $wasInLookbehind = $this->inLookbehind;
        $previous = $this->previousNode;
        $next = $this->nextNode;

        if (\in_array(
            $node->type,
            [GroupType::T_GROUP_LOOKBEHIND_POSITIVE, GroupType::T_GROUP_LOOKBEHIND_NEGATIVE],
            true,
        )) {
            $this->inLookbehind = true;
            $this->validateLookbehindLength($node);
        }

        if (GroupType::T_GROUP_CAPTURING === $node->type || GroupType::T_GROUP_NAMED === $node->type) {
            $this->captureIndex++;
        }

        $this->previousNode = null;
        $this->nextNode = null;
        $node->child->accept($this);
        $this->previousNode = $previous;
        $this->nextNode = $next;

        $this->inLookbehind = $wasInLookbehind; // Restore state
    }

    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): void
    {
        // Fast cached quantifier bounds parsing
        [$min, $max] = $this->getQuantifierBounds($node->quantifier);

        // Early validation with clear error messages
        if (-1 !== $max && $min > $max) {
            $this->raiseSemanticError(
                \sprintf('Invalid quantifier range "%s": min > max.', $node->quantifier),
                $node->startPosition,
                'regex.quantifier.invalid_range',
            );
        }

        $node->node->accept($this);
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
     * @throws SemanticErrorException if an invalid or unknown assertion is encountered
     */
    #[\Override]
    public function visitAssertion(Node\AssertionNode $node): void
    {
        // Fast array lookup with early return
        if (!isset(self::VALID_ASSERTIONS[$node->value])) {
            $this->raiseSemanticError(
                \sprintf('Invalid assertion: \\%s.', $node->value),
                $node->startPosition,
                'regex.assertion.invalid',
            );
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
     * @throws SemanticErrorException if `\K` is found within a lookbehind
     */
    #[\Override]
    public function visitKeep(Node\KeepNode $node): void
    {
        if ($this->inLookbehind) {
            $this->raiseSemanticError(
                '\K (keep) is not allowed in lookbehinds.',
                $node->startPosition,
                'regex.lookbehind.keep_not_allowed',
            );
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
     * @throws SemanticErrorException if any semantic validation rule is violated within a part of the character class
     */
    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): void
    {
        $parts = $node->expression instanceof Node\AlternationNode ? $node->expression->alternatives : [$node->expression];
        foreach ($parts as $part) {
            $part->accept($this);
        }
    }

    #[\Override]
    public function visitRange(Node\RangeNode $node): void
    {
        // 1. Validation: Ensure start and end nodes represent a single character.
        // We allow LiteralNode, but also CharLiteralNode, UnicodeNode, etc.
        if (!$this->isSingleCharNode($node->start) || !$this->isSingleCharNode($node->end)) {
            $this->raiseSemanticError(
                \sprintf(
                    'Invalid range: ranges must be between literal characters or single escape sequences. Found %s and %s.',
                    $node->start::class,
                    $node->end::class,
                ),
                $node->startPosition,
                'regex.range.invalid_bounds',
            );
        }

        // 2. Validation: Ensure characters are single-byte or single codepoint (for LiteralNodes).
        if ($node->start instanceof Node\LiteralNode && \strlen($node->start->value) > 1) {
            $this->raiseSemanticError(
                'Invalid range: start char must be a single character.',
                $node->startPosition,
                'regex.range.invalid_start',
            );
        }
        if ($node->end instanceof Node\LiteralNode && \strlen($node->end->value) > 1) {
            $this->raiseSemanticError(
                'Invalid range: end char must be a single character.',
                $node->startPosition,
                'regex.range.invalid_end',
            );
        }

        // 3. Validation: ASCII/Unicode order check.
        // Note: We only strictly compare two LiteralNodes here to avoid complex cross-type decoding logic.
        if ($node->start instanceof Node\LiteralNode && $node->end instanceof Node\LiteralNode) {
            if (mb_ord($node->start->value) > mb_ord($node->end->value)) {
                $this->raiseSemanticError(
                    \sprintf('Invalid range "%s-%s": start character comes after end character.', $node->start->value, $node->end->value),
                    $node->startPosition,
                    'regex.range.reversed',
                );
            }
        }
    }

    #[\Override]
    public function visitBackref(Node\BackrefNode $node): void
    {
        $this->ensureGroupNumberingInitialized();

        $ref = $node->ref;

        $suggestions = $this->getNameSuggestions($ref);

        // Fast path for numeric backreferences
        if (preg_match('/^\\\\(\d++)$/', $ref, $matches)) {
            $num = (int) $matches[1];
            if (0 === $num) {
                $this->raiseSemanticError(
                    'Backreference \\0 is not valid.',
                    $node->startPosition,
                    'regex.backref.zero',
                    'Use \\g<0> for recursion to the whole pattern, or remove the reference.',
                );
            }
            if ($num > $this->groupNumbering->maxGroupNumber) {
                $this->raiseSemanticError(
                    \sprintf('Backreference to non-existent group: \\%d.', $num),
                    $node->startPosition,
                    'regex.backref.missing_group',
                );
            }

            return;
        }

        // Optimized named backreference validation
        if (preg_match('/^\\\\k[<{\'](?<name>\w++)[>}\']$/', $ref, $matches)) {
            $name = $matches['name'];
            if (!$this->groupNumbering->hasNamedGroup($name)) {
                $suggestions = $this->getNameSuggestions($name);
                $this->raiseSemanticError(
                    \sprintf('Backreference to non-existent named group: "%s".', $name).$suggestions,
                    $node->startPosition,
                    'regex.backref.missing_named_group',
                );
            }

            return;
        }

        // Bare name validation (conditionals)
        if (preg_match('/^\w++$/', $ref)) {
            if (!$this->groupNumbering->hasNamedGroup($ref)) {
                $suggestions = $this->getNameSuggestions($ref);
                $this->raiseSemanticError(
                    \sprintf('Backreference to non-existent named group: "%s".', $ref).$suggestions,
                    $node->startPosition,
                    'regex.backref.missing_named_group',
                );
            }

            return;
        }

        // \g backreference with optimized validation
        if (preg_match('/^\\\\g\{?(?<num>[0-9+-]++)\}?$/', $ref, $matches)) {
            $numStr = $matches['num'];
            if ('0' === $numStr || '+0' === $numStr || '-0' === $numStr) {
                $this->raiseSemanticError(
                    'Backreference \\g{0} is not valid.',
                    $node->startPosition,
                    'regex.backref.zero',
                    'Use \\g<0> for recursion to the whole pattern.',
                );
            }

            if (str_starts_with($numStr, '+') || str_starts_with($numStr, '-')) {
                $offset = (int) $numStr;
                $this->assertRelativeReferenceExists($offset, $node->startPosition, 'regex.backref.relative', 'Backreference');

                return;
            }

            $num = (int) $numStr;
            if ($num > $this->groupNumbering->maxGroupNumber) {
                $this->raiseSemanticError(
                    \sprintf('Backreference to non-existent group: \\g{%d}.', $num),
                    $node->startPosition,
                    'regex.backref.missing_group',
                );
            }

            return;
        }

        $this->raiseSemanticError(
            \sprintf('Invalid backreference syntax: "%s".', $ref),
            $node->startPosition,
            'regex.backref.invalid_syntax',
        );
    }

    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): void
    {
        // The Lexer/Parser combination already ensures these are
        // syntactically valid hex/octal. We validate the *value*.
        $code = -1;
        if (preg_match('/^\\\\x([0-9a-fA-F]{2})$/', $node->code, $m)) {
            $code = (int) hexdec($m[1]);
        } elseif (preg_match('/^\\\\u\{([0-9a-fA-F]++)\}$/', $node->code, $m)) {
            $code = (int) hexdec($m[1]);
        }

        if ($code > 0x10FFFF) {
            $this->raiseSemanticError(
                \sprintf('Invalid Unicode codepoint "%s" (out of range).', $node->code),
                $node->startPosition,
                'regex.unicode.out_of_range',
            );
        }
    }

    #[\Override]
    public function visitCharLiteral(Node\CharLiteralNode $node): void
    {
        // The Lexer/Parser combination already ensures these are
        // syntactically valid. We validate the *value*.
        match ($node->type) {
            Node\CharLiteralType::UNICODE => $this->validateUnicode($node),
            Node\CharLiteralType::OCTAL => $this->validateOctal($node),
            Node\CharLiteralType::OCTAL_LEGACY => $this->validateOctalLegacy($node),
            Node\CharLiteralType::UNICODE_NAMED => $this->validateUnicodeNamed($node),
        };
    }

    #[\Override]
    public function visitUnicodeProp(Node\UnicodePropNode $node): void
    {
        $prop = $node->prop;
        $key = 'p'.$prop;

        // Intelligent caching with lazy validation
        if (!isset(self::$unicodePropCache[$key])) {
            self::$unicodePropCache[$key] = $this->validateUnicodeProperty($key);
        }

        if (false === self::$unicodePropCache[$key]) {
            $this->raiseSemanticError(
                \sprintf('Invalid or unsupported Unicode property: \\%s.', $key),
                $node->startPosition,
                'regex.unicode.property_invalid',
            );
        }
    }

    #[\Override]
    public function visitControlChar(Node\ControlCharNode $node): void
    {
        if ($node->codePoint < 0 || $node->codePoint > 0xFF) {
            $this->raiseSemanticError(
                \sprintf('Invalid control character "\\c%s".', $node->char),
                $node->startPosition,
                'regex.control_char.invalid',
            );
        }
    }

    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): void
    {
        $class = strtolower($node->class);
        $isNegated = str_starts_with($class, '^');

        if ($isNegated) {
            $class = substr($class, 1);
        }

        // Fast validation with clear error messages
        if (!isset(self::VALID_POSIX_CLASSES[$class])) {
            $this->raiseSemanticError(
                \sprintf('Invalid POSIX class: "%s".', $node->class),
                $node->startPosition,
                'regex.posix.invalid',
            );
        }

        if ($isNegated && 'word' === $class) {
            $this->raiseSemanticError(
                'Negation of POSIX class "word" is not supported.',
                $node->startPosition,
                'regex.posix.word_negation',
            );
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

    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): void
    {
        $this->ensureGroupNumberingInitialized();

        // Check if the condition is a valid *type* of condition first
        // (e.g., a backreference, a subroutine call, or a lookaround)
        if ($node->condition instanceof Node\BackrefNode) {
            // This is (?(1)...) or (?(<name>)...) or (?(name)...)
            // For bare names, check if the group exists before calling accept
            $ref = $node->condition->ref;
            if (preg_match('/^\w++$/', $ref) && !$this->groupNumbering->hasNamedGroup($ref)) {
                // Bare name that doesn't exist - this is an invalid conditional
                $this->raiseSemanticError(
                    'Invalid conditional construct. Condition must be a group reference, lookaround, or (DEFINE).',
                    $node->condition->getStartPosition(),
                    'regex.conditional.invalid',
                );
            }
            // Now validate the backreference itself
            $node->condition->accept($this);
        } elseif ($node->condition instanceof Node\SubroutineNode) {
            $ref = $node->condition->reference;
            if ('R' === $ref || '0' === $ref) {
                // Always valid recursion condition to entire pattern.
            } elseif (preg_match('/^R-?\d++$/', $ref)) {
                $num = (int) substr($ref, 1);
                $this->assertSubroutineReferenceExists($num, $node->condition->startPosition, 'regex.subroutine.recursion', 'Recursion condition');
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
            $this->raiseSemanticError(
                'Invalid conditional construct. Condition must be a group reference, lookaround, or (DEFINE).',
                $node->condition->getStartPosition(),
                'regex.conditional.invalid',
            );
        }

        $node->yes->accept($this);
        $node->no->accept($this);
    }

    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): void
    {
        $this->ensureGroupNumberingInitialized();

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
                $this->assertAbsoluteReferenceExists($num, $node->startPosition, 'regex.subroutine.recursion', 'Recursion condition');

                return;
            }

            if (str_starts_with($numPart, '-') && ctype_digit(substr($numPart, 1))) {
                $num = (int) $numPart;
                $this->assertRelativeReferenceExists($num, $node->startPosition, 'regex.subroutine.recursion', 'Recursion condition');

                return;
            }
        }

        // Numeric reference: (?1), (?-1)
        if (ctype_digit($ref) || (str_starts_with($ref, '-') && ctype_digit(substr($ref, 1)))) {
            $num = (int) $ref;
            if (0 === $num) {
                return; // (?0) is an alias for (?R)
            }
            if ($num > 0) {
                $this->assertAbsoluteReferenceExists($num, $node->startPosition, 'regex.subroutine.missing_group', 'Subroutine call');
            } else {
                $this->assertRelativeReferenceExists($num, $node->startPosition, 'regex.subroutine.relative_missing', 'Subroutine call');
            }

            return;
        }

        // Named reference: (?&name), (?P>name), \g<name>
        if (!$this->groupNumbering->hasNamedGroup($ref)) {
            $this->raiseSemanticError(
                \sprintf('Subroutine call to non-existent named group: "%s".', $ref),
                $node->startPosition,
                'regex.subroutine.missing_named_group',
            );
        }
    }

    #[\Override]
    public function visitPcreVerb(Node\PcreVerbNode $node): void
    {
        $verbName = preg_split('/[:=]/', $node->verb, 2)[0] ?? $node->verb;
        if ('LIMIT_LOOKBEHIND' === $verbName && preg_match('/^LIMIT_LOOKBEHIND=(\d++)$/', $node->verb, $matches)) {
            $this->lookbehindLimit = (int) $matches[1];
        }

        if (!isset(self::VALID_PCRE_VERBS[$verbName])) {
            $this->raiseSemanticError(
                \sprintf('Invalid or unsupported PCRE verb: "%s".', $verbName),
                $node->startPosition,
                'regex.verb.invalid',
            );
        }
    }

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
                $this->raiseSemanticError(
                    \sprintf('Callout identifier must be between 0 and 255, got %d.', $node->identifier),
                    $position,
                    'regex.callout.out_of_range',
                );
            }
        } elseif (\is_string($node->identifier)) {
            // PCRE2 allows any string as an argument, but it's good to ensure it's not empty.
            if ('' === $node->identifier) {
                $this->raiseSemanticError(
                    'Callout string identifier cannot be empty.',
                    $position,
                    'regex.callout.empty_identifier',
                );
            }
        } else {
            // This case should ideally be caught by the Lexer/Parser, but as a safeguard.
            $this->raiseSemanticError(
                'Invalid callout identifier type.',
                $position,
                'regex.callout.invalid_type',
            );
        }
    }

    private function getNameSuggestions(string $name): string
    {
        $available = array_keys($this->groupNumbering->namedGroups);
        $suggestions = [];
        foreach ($available as $avail) {
            if (levenshtein($name, $avail) <= 2) {
                $suggestions[] = $avail;
            }
        }
        if (!empty($suggestions)) {
            return ' Did you mean: '.implode(', ', $suggestions).'?';
        }

        return '';
    }

    private function validateUnicode(Node\CharLiteralNode $node): void
    {
        // Parse codePoint from the escape string
        $rep = $node->originalRepresentation;
        if (preg_match('/^\\\\x([0-9a-fA-F]{1,2})$/', $rep, $m)) {
            $codePoint = (int) hexdec($m[1]);
        } elseif (preg_match('/^\\\\(x|u)\\{([0-9a-fA-F]+)\\}$/', $rep, $m)) {
            $codePoint = (int) hexdec($m[2]);
        } else {
            return; // Invalid format, skip
        }

        if ($codePoint > 0x10FFFF) {
            $this->raiseSemanticError(
                \sprintf('Invalid Unicode codepoint "%s" (out of range).', $node->originalRepresentation),
                $node->startPosition,
                'regex.unicode.out_of_range',
            );
        }
    }

    private function validateOctal(Node\CharLiteralNode $node): void
    {
        // PCRE limits \o{} to single-byte values (0-255)
        if ($node->codePoint > 0xFF) {
            $this->raiseSemanticError(
                \sprintf('Invalid octal codepoint "%s".', $node->originalRepresentation),
                $node->startPosition,
                'regex.octal.out_of_range',
            );
        }
    }

    private function validateOctalLegacy(Node\CharLiteralNode $node): void
    {
        // Legacy octal is limited to 0-255 in practice (including \0 for null byte)
        if ($node->codePoint > 0xFF) {
            $this->raiseSemanticError(
                \sprintf('Invalid legacy octal codepoint "%s" (out of range).', $node->originalRepresentation),
                $node->startPosition,
                'regex.octal.out_of_range',
            );
        }
    }

    private function validateUnicodeNamed(Node\CharLiteralNode $node): void
    {
        // Extract the Unicode name from the representation
        if (!preg_match('/^\\\\N\\{(.+)}$/', $node->originalRepresentation, $matches)) {
            throw new ParserException("Invalid Unicode named character format: {$node->originalRepresentation}", $node->getStartPosition());
        }

        $name = $matches[1];

        // If the codePoint is -1, the name could not be resolved
        if (-1 === $node->codePoint) {
            throw new ParserException("Invalid Unicode character name: {$name}", $node->getStartPosition());
        }
    }

    /**
     * Optimized Unicode property validation with error suppression.
     */
    private function validateUnicodeProperty(string $key): bool
    {
        // Use error suppression as preg_match warns on invalid properties
        $result = @preg_match("/^\\{$key}$/u", '');
        $error = preg_last_error();

        // PREG_NO_ERROR means it compiled successfully
        return false !== $result && \PREG_NO_ERROR === $error;
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
            || $node instanceof Node\CharLiteralNode
            || $node instanceof Node\UnicodeNode
            || $node instanceof Node\ControlCharNode;
        // CharTypeNode (e.g., \d) is technically invalid in a standard PCRE range start/end,
        // but we exclude it here to remain spec-compliant unless lenient mode is desired.
    }

    /**
     * High-performance cached quantifier bounds parsing.
     *
     * @return array{0: int, 1: int}
     */
    private function getQuantifierBounds(string $q): array
    {
        $normalized = $this->normalizeQuantifier($q);
        // Return cached result if available
        if (isset(self::$quantifierBoundsCache[$normalized])) {
            return self::$quantifierBoundsCache[$normalized];
        }

        // Compute and cache the result
        $bounds = $this->parseQuantifierBounds($normalized);
        self::$quantifierBoundsCache[$normalized] = $bounds;

        return $bounds;
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
            default => preg_match('/^\\{(\\d*?)(?:,(\\d*?))?\\}$/', $q, $m) ?
                (
                    ('' === $m[1] && (!isset($m[2]) || '' === $m[2]))
                        ? [1, 1] // entirely empty braces remain invalid/fallback
                        : (isset($m[2])
                            ? ('' === $m[2] ? [(int) $m[1] ?: 0, -1] : [(int) $m[1] ?: 0, (int) $m[2]]) // {n,} or {n,m} or {,m}
                            : [(int) $m[1] ?: 0, (int) $m[1] ?: 0]) // {n}
                )
                : [1, 1], // Should be impossible if Lexer is correct
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

    private function extractLookbehindLimit(Node\NodeInterface $node): ?int
    {
        if ($node instanceof Node\PcreVerbNode && preg_match('/^LIMIT_LOOKBEHIND=(\d++)$/', $node->verb, $matches)) {
            return (int) $matches[1];
        }

        if ($node instanceof Node\GroupNode) {
            return $this->extractLookbehindLimit($node->child);
        }

        if ($node instanceof Node\AlternationNode) {
            foreach ($node->alternatives as $alt) {
                $limit = $this->extractLookbehindLimit($alt);
                if (null !== $limit) {
                    return $limit;
                }
            }
        }

        if ($node instanceof Node\SequenceNode) {
            foreach ($node->children as $child) {
                $limit = $this->extractLookbehindLimit($child);
                if (null !== $limit) {
                    return $limit;
                }
            }
        }

        if ($node instanceof Node\QuantifierNode) {
            return $this->extractLookbehindLimit($node->node);
        }

        if ($node instanceof Node\ConditionalNode) {
            return $this->extractLookbehindLimit($node->condition)
                ?? $this->extractLookbehindLimit($node->yes)
                ?? $this->extractLookbehindLimit($node->no);
        }

        if ($node instanceof Node\DefineNode) {
            return $this->extractLookbehindLimit($node->content);
        }

        if ($node instanceof Node\CharClassNode) {
            return $this->extractLookbehindLimit($node->expression);
        }

        if ($node instanceof Node\ClassOperationNode) {
            return $this->extractLookbehindLimit($node->left) ?? $this->extractLookbehindLimit($node->right);
        }

        if ($node instanceof Node\RangeNode) {
            return $this->extractLookbehindLimit($node->start) ?? $this->extractLookbehindLimit($node->end);
        }

        return null;
    }

    private function validateLookbehindLength(Node\GroupNode $node): void
    {
        $lengthRange = $node->child->accept(new LengthRangeNodeVisitor());
        [$min, $max] = $lengthRange;

        if (null === $max) {
            $culprit = $this->findUnboundedLookbehindNode($node->child);
            $position = $culprit?->getStartPosition() ?? $node->startPosition;
            $detail = $culprit instanceof Node\QuantifierNode ? $culprit->quantifier : null;
            $hint = null !== $detail
                ? \sprintf('Use a bounded quantifier instead of "%s".', $detail)
                : 'Ensure the lookbehind has a bounded maximum length.';

            $this->raiseSemanticError(
                'Lookbehind is unbounded. PCRE requires a bounded maximum length.',
                $position,
                'regex.lookbehind.unbounded',
                $hint,
            );
        }

        if ($max > $this->lookbehindLimit) {
            $this->raiseSemanticError(
                \sprintf('Lookbehind exceeds the maximum length of %d (max=%d).', $this->lookbehindLimit, $max),
                $node->startPosition,
                'regex.lookbehind.too_long',
                \sprintf('Reduce lookbehind length or use (*LIMIT_LOOKBEHIND=%d).', $max),
            );
        }
    }

    private function findUnboundedLookbehindNode(Node\NodeInterface $node): ?Node\NodeInterface
    {
        if ($node instanceof Node\BackrefNode || $node instanceof Node\SubroutineNode) {
            return $node;
        }

        if ($node instanceof Node\QuantifierNode) {
            [, $max] = $this->getQuantifierBounds($node->quantifier);
            if (-1 === $max) {
                return $node;
            }

            return $this->findUnboundedLookbehindNode($node->node);
        }

        if ($node instanceof Node\GroupNode) {
            return $this->findUnboundedLookbehindNode($node->child);
        }

        if ($node instanceof Node\AlternationNode) {
            foreach ($node->alternatives as $alt) {
                $culprit = $this->findUnboundedLookbehindNode($alt);
                if (null !== $culprit) {
                    return $culprit;
                }
            }
        }

        if ($node instanceof Node\SequenceNode) {
            foreach ($node->children as $child) {
                $culprit = $this->findUnboundedLookbehindNode($child);
                if (null !== $culprit) {
                    return $culprit;
                }
            }
        }

        if ($node instanceof Node\ConditionalNode) {
            return $this->findUnboundedLookbehindNode($node->condition)
                ?? $this->findUnboundedLookbehindNode($node->yes)
                ?? $this->findUnboundedLookbehindNode($node->no);
        }

        if ($node instanceof Node\DefineNode) {
            return $this->findUnboundedLookbehindNode($node->content);
        }

        if ($node instanceof Node\CharClassNode) {
            return $this->findUnboundedLookbehindNode($node->expression);
        }

        if ($node instanceof Node\ClassOperationNode) {
            return $this->findUnboundedLookbehindNode($node->left) ?? $this->findUnboundedLookbehindNode($node->right);
        }

        if ($node instanceof Node\RangeNode) {
            return $this->findUnboundedLookbehindNode($node->start) ?? $this->findUnboundedLookbehindNode($node->end);
        }

        return null;
    }

    private function assertAbsoluteReferenceExists(int $num, int $position, string $code, string $context): void
    {
        if ($num <= 0 || $num > $this->groupNumbering->maxGroupNumber) {
            $this->raiseSemanticError(
                \sprintf('%s to non-existent group: %d.', $context, $num),
                $position,
                $code,
            );
        }
    }

    private function assertRelativeReferenceExists(int $offset, int $position, string $code, string $context): void
    {
        if (0 === $offset) {
            $this->raiseSemanticError(
                \sprintf('%s relative reference cannot be zero.', $context),
                $position,
                $code,
            );
        }

        $index = $offset > 0 ? $this->captureIndex + $offset - 1 : $this->captureIndex + $offset;
        if ($index < 0 || $index >= \count($this->captureSequence)) {
            $this->raiseSemanticError(
                \sprintf('%s relative reference %d is outside the range of available capture groups.', $context, $offset),
                $position,
                $code,
                'Check group numbering or remove the relative reference.',
            );
        }
    }

    private function assertSubroutineReferenceExists(int $num, int $position, string $code, string $context): void
    {
        if ($num > 0) {
            $this->assertAbsoluteReferenceExists($num, $position, $code, $context);

            return;
        }

        $this->assertRelativeReferenceExists($num, $position, $code, $context);
    }

    private function raiseSemanticError(string $message, int $position, string $code, ?string $hint = null): never
    {
        throw new SemanticErrorException(
            $message,
            $position,
            $this->pattern,
            null,
            $code,
            $hint,
        );
    }

    private function ensureGroupNumberingInitialized(): void
    {
        if (!isset($this->groupNumbering)) {
            $this->groupNumbering = new GroupNumbering(0, [], []);
            $this->captureSequence = [];
            $this->captureIndex = 0;
            $this->lookbehindLimit = $this->maxLookbehindLength;
        }
    }
}
