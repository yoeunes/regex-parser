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

use RegexParser\Internal\PatternParser;
use RegexParser\LintIssue;
use RegexParser\Node;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CalloutNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharLiteralType;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\ClassOperationNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\ControlCharNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\ScriptRunNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\ReDoS\CharSet;
use RegexParser\ReDoS\CharSetAnalyzer;

/**
 * Lints regex patterns for semantic issues like useless flags.
 *
 * @extends AbstractNodeVisitor<Node\NodeInterface>
 */
final class LinterNodeVisitor extends AbstractNodeVisitor
{
    /**
     * @var array<LintIssue>
     */
    private array $issues = [];

    private string $flags = '';

    private string $delimiter = '';

    private bool $hasCaseSensitiveChars = false;

    private bool $hasDots = false;

    private bool $hasAnchors = false;

    private bool $hasBackreferences = false;

    private ?string $patternValue = null;

    private int $maxCapturingGroup = 0;

    /**
     * @var array<string, bool>
     */
    private array $definedNamedGroups = [];

    private CharSetAnalyzer $charSetAnalyzer;

    private bool $trackCaseSensitivity = false;

    private bool $unicodeMode = false;

    private bool $intlAvailable = false;

    /**
     * Stack of parent nodes to track context.
     * Used to determine if an alternation is inside a quantifier.
     *
     * @var array<NodeInterface>
     */
    private array $parentStack = [];

    public function __construct()
    {
        $this->charSetAnalyzer = new CharSetAnalyzer();
        $this->intlAvailable = class_exists(\IntlChar::class);
    }

    /**
     * Get the full regex pattern including delimiters and flags
     */
    public function getFullPattern(): string
    {
        $closingDelimiter = PatternParser::closingDelimiter($this->delimiter);

        return $this->delimiter.$this->patternValue.$closingDelimiter.$this->flags;
    }

    /**
     * @return array<string>
     */
    public function getWarnings(): array
    {
        return array_map(
            static fn (LintIssue $issue): string => $issue->message,
            $this->issues,
        );
    }

    /**
     * @return array<LintIssue>
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    #[\Override]
    public function visitRegex(RegexNode $node): NodeInterface
    {
        $this->flags = $node->flags;
        $this->delimiter = $node->delimiter;
        $this->unicodeMode = str_contains($this->flags, 'u');
        $this->trackCaseSensitivity = str_contains($this->flags, 'i');
        $this->intlAvailable = class_exists(\IntlChar::class);
        $this->charSetAnalyzer = new CharSetAnalyzer($this->flags);
        $this->issues = [];
        $this->hasCaseSensitiveChars = false;
        $this->hasDots = false;
        $this->hasAnchors = false;
        $this->hasBackreferences = false;
        $this->maxCapturingGroup = 0;
        $this->definedNamedGroups = [];
        $this->parentStack = [];

        // Use a simple visitor to compile the pattern string for diagnostics
        $compiler = new CompilerNodeVisitor();
        $this->patternValue = $node->pattern->accept($compiler);

        // First pass: count capturing groups
        $this->countCapturingGroups($node->pattern);

        // Second pass: traverse and lint
        $node->pattern->accept($this);

        // Finally, compute useless-flag diagnostics based on the collected
        // state and the fully compiled pattern.
        $this->checkUselessFlags();

        return $node;
    }

    #[\Override]
    public function visitLiteral(LiteralNode $node): NodeInterface
    {
        if ($this->trackCaseSensitivity
            && !$this->hasCaseSensitiveChars
            && $this->stringHasCaseSensitiveLetters($node->value)
        ) {
            $this->hasCaseSensitiveChars = true;
        }

        return $node;
    }

    #[\Override]
    public function visitCharClass(CharClassNode $node): NodeInterface
    {
        if ($this->trackCaseSensitivity && !$this->hasCaseSensitiveChars) {
            // Check if char class contains case-sensitive letters
            $expression = $node->expression;
            if ($expression instanceof AlternationNode) {
                foreach ($expression->alternatives as $alt) {
                    if ($this->charClassPartHasLetters($alt)) {
                        $this->hasCaseSensitiveChars = true;

                        break;
                    }
                }
            } elseif ($this->charClassPartHasLetters($expression)) {
                $this->hasCaseSensitiveChars = true;
            }
        }

        $this->lintRedundantCharClass($node);
        $this->lintSuspiciousCharClassRange($node);
        $this->lintSuspiciousCharClassPipe($node);

        return $node;
    }

    #[\Override]
    public function visitDot(DotNode $node): NodeInterface
    {
        $this->hasDots = true;

        return $node;
    }

    #[\Override]
    public function visitAnchor(AnchorNode $node): NodeInterface
    {
        if ('^' === $node->value || '$' === $node->value) {
            $this->hasAnchors = true;
        }

        return $node;
    }

    // Implement other visit methods as no-op
    #[\Override]
    public function visitAlternation(AlternationNode $node): NodeInterface
    {
        $this->lintAlternation($node);

        $this->parentStack[] = $node;
        foreach ($node->alternatives as $alt) {
            $alt->accept($this);
        }
        array_pop($this->parentStack);

        return $node;
    }

    #[\Override]
    public function visitSequence(SequenceNode $node): NodeInterface
    {
        // Check for anchor conflicts
        $this->checkAnchorConflicts($node);

        $this->parentStack[] = $node;
        foreach ($node->children as $child) {
            $child->accept($this);
        }
        array_pop($this->parentStack);

        return $node;
    }

    #[\Override]
    public function visitGroup(GroupNode $node): NodeInterface
    {
        if (GroupType::T_GROUP_INLINE_FLAGS === $node->type && null !== $node->flags) {
            $this->lintInlineFlags($node);
        }

        if (GroupType::T_GROUP_NON_CAPTURING === $node->type && $this->isRedundantGroup($node->child)) {
            $this->addIssue(
                'regex.lint.group.redundant',
                'Redundant non-capturing group; it can be removed without changing behavior.',
                $node->startPosition,
            );
        }

        $this->parentStack[] = $node;
        $node->child->accept($this);
        array_pop($this->parentStack);

        return $node;
    }

    #[\Override]
    public function visitBackref(BackrefNode $node): NodeInterface
    {
        $this->hasBackreferences = true;
        $ref = $node->ref;

        // Check numeric backreferences
        if (preg_match('/^\\\\g\{?[+-]\d+\\}?$/', $ref) > 0) {
            return $node;
        }

        if (preg_match('/^\\\\(\d+)$/', $ref, $matches) || preg_match('/^\\\\g\{?(\d+)\\}?$/', $ref, $matches)) {
            $num = (int) $matches[1];
            if (0 === $num) {
                return $node;
            }

            if ($num > $this->maxCapturingGroup) {
                $this->addIssue(
                    'regex.lint.backref.undefined',
                    "Backreference \\{$num} refers to a non-existent capturing group.",
                    $node->startPosition,
                );
            }
        }
        // Check named backreferences
        elseif (preg_match('/^\\\\k[<{\'](?<name>\w+)[>}\']$/', $ref, $matches)) {
            $name = $matches['name'];
            if (!isset($this->definedNamedGroups[$name])) {
                $this->addIssue(
                    'regex.lint.backref.undefined',
                    "Backreference \\k<{$name}> refers to a non-existent named group.",
                    $node->startPosition,
                );
            }
        }

        return $node;
    }

    #[\Override]
    public function visitQuantifier(QuantifierNode $node): NodeInterface
    {
        $isAtomicQuantifier = QuantifierType::T_POSSESSIVE === $node->type
            || ($node->node instanceof GroupNode && GroupType::T_GROUP_ATOMIC === $node->node->type);

        if ($this->isVariableQuantifier($node->quantifier)) {
            if (!$isAtomicQuantifier && $this->isRepeatableQuantifier($node->quantifier)) {
                $nested = $this->findNestedQuantifier($node->node);
                if (null !== $nested && $this->isVariableQuantifier($nested->quantifier)) {
                    if (!$this->isSafelySeparatedNestedQuantifier($node, $nested)) {
                        $this->addIssue(
                            'regex.lint.quantifier.nested',
                            'Nested quantifiers can cause catastrophic backtracking.',
                            $node->startPosition,
                            'Consider using atomic groups (?>...) or possessive quantifiers.',
                        );
                    }
                }
            }

            if (
                !$isAtomicQuantifier
                && $this->isUnboundedQuantifier($node->quantifier)
                && $this->containsDotStar($node->node)
            ) {
                $this->addIssue(
                    'regex.lint.dotstar.nested',
                    'An unbounded quantifier wraps a dot-star, which can cause severe backtracking.',
                    $node->startPosition,
                    'Refactor with atomic groups or a more specific character class.',
                );
            }
        }

        $this->parentStack[] = $node;
        $node->node->accept($this);
        array_pop($this->parentStack);

        return $node;
    }

    #[\Override]
    public function visitUnicode(UnicodeNode $node): NodeInterface
    {
        $code = $this->parseUnicodeEscapeCodePoint($node->code);

        if (null !== $code && $code > 0x10FFFF) {
            $this->addIssue(
                'regex.lint.escape.suspicious',
                \sprintf('Suspicious Unicode escape "%s" (out of range).', $node->code),
                $node->startPosition,
            );
        }

        if ($this->trackCaseSensitivity
            && !$this->hasCaseSensitiveChars
            && null !== $code
            && $this->codePointHasCase($code)
        ) {
            $this->hasCaseSensitiveChars = true;
        }

        return $node;
    }

    #[\Override]
    public function visitCharLiteral(CharLiteralNode $node): NodeInterface
    {
        if (CharLiteralType::UNICODE === $node->type && $node->codePoint > 0x10FFFF) {
            $this->addIssue(
                'regex.lint.escape.suspicious',
                \sprintf('Suspicious Unicode escape "%s" (out of range).', $node->originalRepresentation),
                $node->startPosition,
            );
        }

        if (\in_array($node->type, [CharLiteralType::OCTAL, CharLiteralType::OCTAL_LEGACY], true) && $node->codePoint > 0xFF) {
            $this->addIssue(
                'regex.lint.escape.suspicious',
                \sprintf('Suspicious octal escape "%s" (out of range).', $node->originalRepresentation),
                $node->startPosition,
            );
        }

        if (CharLiteralType::UNICODE_NAMED === $node->type && class_exists(\IntlChar::class)) {
            $name = $node->originalRepresentation;
            if (preg_match('/^\\\\N\\{(.+)}$/', $name, $matches)) {
                $char = \IntlChar::charFromName($matches[1]);
                if (null === $char) {
                    $this->addIssue(
                        'regex.lint.escape.suspicious',
                        \sprintf('Unknown Unicode character name "%s".', $matches[1]),
                        $node->startPosition,
                    );
                }
            }
        }

        if ($this->trackCaseSensitivity
            && !$this->hasCaseSensitiveChars
            && $this->charLiteralHasCaseSensitiveLetter($node)
        ) {
            $this->hasCaseSensitiveChars = true;
        }

        return $node;
    }

    #[\Override]
    public function visitUnicodeProp(UnicodePropNode $node): NodeInterface
    {
        if ($this->trackCaseSensitivity
            && !$this->hasCaseSensitiveChars
            && $this->unicodePropIsCaseSensitive($node->prop)
        ) {
            $this->hasCaseSensitiveChars = true;
        }

        return $node;
    }

    private function countCapturingGroups(NodeInterface $node): void
    {
        if ($node instanceof GroupNode && (GroupType::T_GROUP_CAPTURING === $node->type || GroupType::T_GROUP_NAMED === $node->type)) {
            $this->maxCapturingGroup++;
            if (null !== $node->name) {
                $this->definedNamedGroups[$node->name] = true;
            }
        }

        // Recursively count in children
        if ($node instanceof GroupNode) {
            $this->countCapturingGroups($node->child);
        } elseif ($node instanceof AlternationNode) {
            foreach ($node->alternatives as $alt) {
                $this->countCapturingGroups($alt);
            }
        } elseif ($node instanceof SequenceNode) {
            foreach ($node->children as $child) {
                $this->countCapturingGroups($child);
            }
        } elseif ($node instanceof QuantifierNode) {
            $this->countCapturingGroups($node->node);
        } elseif ($node instanceof ConditionalNode) {
            $this->countCapturingGroups($node->condition);
            $this->countCapturingGroups($node->yes);
            $this->countCapturingGroups($node->no);
        } elseif ($node instanceof CharClassNode) {
            $this->countCapturingGroups($node->expression);
        }
        // Other node types don't contain groups
    }

    private function checkUselessFlags(): void
    {
        if (str_contains($this->flags, 'i') && !$this->hasCaseSensitiveChars && !$this->hasBackreferences) {
            $this->addIssue(
                'regex.lint.flag.useless.i',
                "Flag 'i' is useless: the pattern contains no case-sensitive characters.",
            );
        }

        if (str_contains($this->flags, 's') && !$this->hasDots) {
            $this->addIssue(
                'regex.lint.flag.useless.s',
                "Flag 's' is useless: the pattern contains no dots.",
            );
        }

        if (str_contains($this->flags, 'm') && !$this->hasAnchors) {
            $this->addIssue(
                'regex.lint.flag.useless.m',
                "Flag 'm' is useless: pattern '{$this->getFullPattern()}' contains no anchors.",
            );
        }
    }

    private function charClassPartHasLetters(NodeInterface $node): bool
    {
        if ($node instanceof LiteralNode) {
            return $this->stringHasCaseSensitiveLetters($node->value);
        }

        if ($node instanceof CharLiteralNode) {
            return $this->charLiteralHasCaseSensitiveLetter($node);
        }

        if ($node instanceof UnicodeNode) {
            $codePoint = $this->parseUnicodeEscapeCodePoint($node->code);

            return null !== $codePoint && $this->codePointHasCase($codePoint);
        }

        if ($node instanceof UnicodePropNode) {
            return $this->unicodePropIsCaseSensitive($node->prop);
        }

        if ($node instanceof PosixClassNode) {
            return $this->posixClassHasCaseSensitiveLetters($node->class);
        }

        if ($node instanceof RangeNode) {
            return $this->rangeHasLetters($node);
        }

        // Other types like CharTypeNode are case-insensitive by design.
        return false;
    }

    private function rangeHasLetters(RangeNode $node): bool
    {
        $start = $this->codePointFromNode($node->start);
        $end = $this->codePointFromNode($node->end);

        if (null === $start || null === $end) {
            return false;
        }

        $min = min($start, $end);
        $max = max($start, $end);

        if ($this->rangeHasAsciiLetters($min, $max)) {
            return true;
        }

        if (!$this->unicodeMode || !$this->intlAvailable) {
            return false;
        }

        return $this->codePointHasCase($start) || $this->codePointHasCase($end);
    }

    private function rangeHasAsciiLetters(int $min, int $max): bool
    {
        return ($min <= \ord('Z') && $max >= \ord('A'))
            || ($min <= \ord('z') && $max >= \ord('a'));
    }

    private function codePointFromNode(NodeInterface $node): ?int
    {
        if ($node instanceof LiteralNode) {
            return $this->codePointFromLiteral($node->value);
        }

        if ($node instanceof CharLiteralNode) {
            return $node->codePoint >= 0 ? $node->codePoint : null;
        }

        if ($node instanceof UnicodeNode) {
            return $this->parseUnicodeEscapeCodePoint($node->code);
        }

        return null;
    }

    private function codePointFromLiteral(string $value): ?int
    {
        if ('' === $value) {
            return null;
        }

        if ($this->unicodeMode && $this->intlAvailable) {
            $chars = preg_split('//u', $value, -1, \PREG_SPLIT_NO_EMPTY);
            if (false === $chars || 1 !== \count($chars)) {
                return null;
            }

            return \IntlChar::ord($chars[0]);
        }

        if (1 !== \strlen($value)) {
            return null;
        }

        return \ord($value[0]);
    }

    private function parseUnicodeEscapeCodePoint(string $escape): ?int
    {
        if (preg_match('/^\\\\x([0-9a-fA-F]{2})$/', $escape, $matches)) {
            return (int) hexdec($matches[1]);
        }

        if (preg_match('/^\\\\u([0-9a-fA-F]{4})$/', $escape, $matches)) {
            return (int) hexdec($matches[1]);
        }

        if (preg_match('/^\\\\[xu]\\{([0-9a-fA-F]++)\\}$/', $escape, $matches)) {
            return (int) hexdec($matches[1]);
        }

        return null;
    }

    private function stringHasCaseSensitiveLetters(string $value): bool
    {
        if ('' === $value) {
            return false;
        }

        if (preg_match('/[A-Za-z]/', $value) > 0) {
            return true;
        }

        if (!$this->unicodeMode || !$this->intlAvailable) {
            return false;
        }

        $chars = preg_split('//u', $value, -1, \PREG_SPLIT_NO_EMPTY);
        if (false === $chars) {
            return false;
        }

        foreach ($chars as $char) {
            $codePoint = \IntlChar::ord($char);
            if ($this->codePointHasCase($codePoint)) {
                return true;
            }
        }

        return false;
    }

    private function charLiteralHasCaseSensitiveLetter(CharLiteralNode $node): bool
    {
        $codePoint = $node->codePoint;
        if ($codePoint < 0) {
            $codePoint = $this->parseUnicodeEscapeCodePoint($node->originalRepresentation) ?? $codePoint;
        }

        if ($codePoint >= 0) {
            return $this->codePointHasCase($codePoint);
        }

        if (1 === \strlen($node->originalRepresentation)) {
            return $this->stringHasCaseSensitiveLetters($node->originalRepresentation);
        }

        return false;
    }

    private function codePointHasCase(int $codePoint): bool
    {
        if ($codePoint < 0 || $codePoint > 0x10FFFF) {
            return false;
        }

        if (!$this->intlAvailable) {
            return ($codePoint >= \ord('A') && $codePoint <= \ord('Z'))
                || ($codePoint >= \ord('a') && $codePoint <= \ord('z'));
        }

        if (!\IntlChar::isalpha($codePoint)) {
            return false;
        }

        return \IntlChar::toupper($codePoint) !== $codePoint
            || \IntlChar::tolower($codePoint) !== $codePoint;
    }

    private function unicodePropIsCaseSensitive(string $prop): bool
    {
        if ('' === $prop) {
            return false;
        }

        $normalized = $this->normalizeUnicodePropName($prop);

        return \in_array($normalized, [
            'lu',
            'll',
            'lt',
            'lc',
            'l&',
            'upper',
            'lower',
            'title',
            'uppercase_letter',
            'lowercase_letter',
            'titlecase_letter',
            'cased_letter',
        ], true);
    }

    private function normalizeUnicodePropName(string $prop): string
    {
        $normalized = ltrim($prop, '^');
        $normalized = trim($normalized, '{}');
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return strtolower($normalized);
    }

    private function posixClassHasCaseSensitiveLetters(string $class): bool
    {
        $normalized = strtolower(ltrim($class, '^'));

        return \in_array($normalized, ['upper', 'lower'], true);
    }

    private function isStartAnchorNode(NodeInterface $node): bool
    {
        if ($node instanceof AnchorNode) {
            return '^' === $node->value;
        }

        if ($node instanceof AssertionNode) {
            return \in_array($node->value, ['A', 'G'], true);
        }

        return false;
    }

    private function isEndAnchorNode(NodeInterface $node): bool
    {
        if ($node instanceof AnchorNode) {
            return '$' === $node->value;
        }

        if ($node instanceof AssertionNode) {
            return \in_array($node->value, ['z', 'Z'], true);
        }

        return false;
    }

    private function anchorDisplay(NodeInterface $node): string
    {
        if ($node instanceof AnchorNode) {
            return $node->value;
        }

        if ($node instanceof AssertionNode) {
            return '\\'.$node->value;
        }

        return '';
    }

    private function checkAnchorConflicts(SequenceNode $node): void
    {
        $children = $node->children;
        $count = \count($children);

        for ($i = 0; $i < $count; $i++) {
            $child = $children[$i];

            if ($this->isStartAnchorNode($child)) {
                $skipForMultiline = $child instanceof AnchorNode
                    && '^' === $child->value
                    && str_contains($this->flags, 'm');

                if (!$skipForMultiline) {
                    $anchorLabel = $this->anchorDisplay($child);
                    $prefix = array_values(array_slice($children, 0, $i));
                    if ([] !== $prefix && !$this->sequenceCanBeEmpty($prefix)) {
                        $this->addIssue(
                            'regex.lint.anchor.impossible.start',
                            \sprintf(
                                "Start anchor '%s' appears after consuming characters, making it impossible to match.",
                                $anchorLabel,
                            ),
                            $child->getStartPosition(),
                        );
                    }
                }
            }

            if ($this->isEndAnchorNode($child)) {
                $anchorLabel = $this->anchorDisplay($child);
                $tail = array_values(array_slice($children, $i + 1));
                if ([] !== $tail && !$this->sequenceCanBeEmpty($tail)) {
                    $this->addIssue(
                        'regex.lint.anchor.impossible.end',
                        \sprintf(
                            "End anchor '%s' appears before consuming characters, making it impossible to match.",
                            $anchorLabel,
                        ),
                        $child->getStartPosition(),
                    );
                }
            }
        }
    }

    private function isConsuming(NodeInterface $node): bool
    {
        if ($node instanceof LiteralNode) {
            return true;
        }
        if ($node instanceof CharClassNode) {
            return true;
        }
        if ($node instanceof CharTypeNode) {
            return true;
        }
        if ($node instanceof DotNode) {
            return true;
        }
        if ($node instanceof CharLiteralNode) {
            return true;
        }
        if ($node instanceof UnicodePropNode) {
            return true;
        }
        if ($node instanceof PosixClassNode) {
            return true;
        }
        if ($node instanceof QuantifierNode) {
            return $this->isConsuming($node->node);
        }
        if ($node instanceof GroupNode) {
            // Lookarounds don't consume
            return !(GroupType::T_GROUP_LOOKAHEAD_POSITIVE === $node->type
                || GroupType::T_GROUP_LOOKAHEAD_NEGATIVE === $node->type
                || GroupType::T_GROUP_LOOKBEHIND_POSITIVE === $node->type
                || GroupType::T_GROUP_LOOKBEHIND_NEGATIVE === $node->type);
        }
        if ($node instanceof AlternationNode) {
            // If any alternative consumes, consider it consuming
            foreach ($node->alternatives as $alt) {
                if ($this->isConsuming($alt)) {
                    return true;
                }
            }

            return false;
        }
        if ($node instanceof SequenceNode) {
            // If any child consumes, consider it consuming
            foreach ($node->children as $child) {
                if ($this->isConsuming($child)) {
                    return true;
                }
            }

            return false;
        }

        // Anchors, assertions, etc. don't consume
        return false;
    }

    /**
     * Determine if the given sequence can match an empty string.
     *
     * @param array<int, NodeInterface> $nodes
     */
    private function sequenceCanBeEmpty(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!$this->canBeEmpty($node)) {
                return false;
            }
        }

        return true;
    }

    private function canBeEmpty(NodeInterface $node): bool
    {
        if ($node instanceof AnchorNode
            || $node instanceof AssertionNode
            || $node instanceof KeepNode
            || $node instanceof CommentNode
            || $node instanceof CalloutNode
            || $node instanceof ScriptRunNode
            || $node instanceof DefineNode
        ) {
            return true;
        }

        if ($node instanceof LiteralNode) {
            return '' === $node->value;
        }

        if ($node instanceof QuantifierNode) {
            [$min] = $this->parseQuantifierRange($node->quantifier);

            return 0 === $min || $this->canBeEmpty($node->node);
        }

        if ($node instanceof SequenceNode) {
            return $this->sequenceCanBeEmpty(array_values($node->children));
        }

        if ($node instanceof AlternationNode) {
            foreach ($node->alternatives as $alt) {
                if ($this->canBeEmpty($alt)) {
                    return true;
                }
            }

            return false;
        }

        if ($node instanceof GroupNode) {
            if (\in_array($node->type, [
                GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
                GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
                GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
                GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
            ], true)) {
                return true;
            }

            return $this->canBeEmpty($node->child);
        }

        if ($node instanceof ConditionalNode) {
            return $this->canBeEmpty($node->yes) || $this->canBeEmpty($node->no);
        }

        return false;
    }

    private function addIssue(string $id, string $message, ?int $offset = null, ?string $hint = null): void
    {
        $this->issues[] = new LintIssue($id, $message, $offset, $hint);
    }

    /**
     * Check if we're currently inside an unbounded quantifier (*, +, {n,}).
     * This is used to determine if overlapping alternations pose a ReDoS risk.
     */
    private function isInsideUnboundedQuantifier(): bool
    {
        foreach ($this->parentStack as $parent) {
            if ($parent instanceof QuantifierNode) {
                // Skip possessive quantifiers - they don't backtrack
                if (QuantifierType::T_POSSESSIVE === $parent->type) {
                    continue;
                }

                // Check if the quantifier's child is an atomic group - atomic groups don't backtrack
                if ($parent->node instanceof GroupNode && GroupType::T_GROUP_ATOMIC === $parent->node->type) {
                    continue;
                }

                if ($this->isUnboundedQuantifier($parent->quantifier)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if we're currently inside any quantifier (bounded or unbounded).
     * This is a more permissive check than isInsideUnboundedQuantifier.
     */
    private function isInsideQuantifier(): bool
    {
        foreach ($this->parentStack as $parent) {
            if ($parent instanceof QuantifierNode) {
                // Skip possessive quantifiers - they don't backtrack
                if (QuantifierType::T_POSSESSIVE === $parent->type) {
                    continue;
                }

                // Check if the quantifier's child is an atomic group - atomic groups don't backtrack
                if ($parent->node instanceof GroupNode && GroupType::T_GROUP_ATOMIC === $parent->node->type) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    private function lintAlternation(AlternationNode $node): void
    {
        $literals = [];
        foreach ($node->alternatives as $alt) {
            $literal = $this->extractLiteralSequence($alt);
            if (null === $literal) {
                continue;
            }

            $literals[] = $literal;
        }

        // Check for literal-based issues
        if ([] !== $literals) {
            $counts = array_count_values($literals);
            foreach ($counts as $literal => $count) {
                if ($count > 1) {
                    $this->addIssue(
                        'regex.lint.alternation.duplicate',
                        \sprintf('Duplicate alternation branch "%s".', addcslashes((string) $literal, "\0..\37\177..\377")),
                        $node->startPosition,
                    );

                    break;
                }
            }

            // Only flag overlapping literal branches when they're inside an unbounded quantifier.
            // Overlapping alternations without a quantifier (e.g., /\r\n|\r|\n/ or /^(978|979)/)
            // do not pose a ReDoS risk because there's no exponential backtracking.
            if ($this->isInsideUnboundedQuantifier()) {
                $unique = array_values(array_unique($literals));
                $total = \count($unique);
                for ($i = 0; $i < $total; $i++) {
                    for ($j = $i + 1; $j < $total; $j++) {
                        $a = $unique[$i];
                        $b = $unique[$j];
                        if ('' === $a || '' === $b) {
                            continue;
                        }

                        if (str_starts_with($a, $b) || str_starts_with($b, $a)) {
                            $this->addIssue(
                                'regex.lint.alternation.overlap',
                                \sprintf('Alternation branches "%s" and "%s" overlap.', addcslashes($a, "\0..\37\177..\377"), addcslashes($b, "\0..\37\177..\377")),
                                $node->startPosition,
                                'Consider using atomic groups (?>...) to prevent backtracking. Do not reorder overlapping alternatives as it changes match semantics.',
                            );

                            return;
                        }
                    }
                }
            }
        }

        // Check for semantic overlaps using character set analysis
        $this->checkSemanticOverlaps($node);
    }

    private function checkSemanticOverlaps(AlternationNode $node): void
    {
        // Only flag overlapping alternations when they're inside an unbounded quantifier.
        // Overlapping alternations without a quantifier (e.g., /\r\n|\r|\n/ or /^(978|979)/)
        // do not pose a ReDoS risk because there's no exponential backtracking.
        if (!$this->isInsideUnboundedQuantifier()) {
            return;
        }

        $charSets = [];
        foreach ($node->alternatives as $alt) {
            $charSet = $this->charSetAnalyzer->firstChars($alt);
            if ($charSet->isUnknown()) {
                // If we can't analyze any charset, skip semantic overlap detection
                return;
            }
            $charSets[] = $charSet;
        }

        $total = \count($charSets);
        for ($i = 0; $i < $total; $i++) {
            for ($j = $i + 1; $j < $total; $j++) {
                if (!$charSets[$i]->isEmpty() && !$charSets[$j]->isEmpty() && $charSets[$i]->intersects($charSets[$j])) {
                    $this->addIssue(
                        'regex.lint.overlap.charset',
                        'Alternation branches have overlapping character sets, which may cause unnecessary backtracking.',
                        $node->startPosition,
                        'Consider using atomic groups (?>...) to prevent backtracking. Do not reorder overlapping alternatives as it changes match semantics.',
                    );

                    return;
                }
            }
        }
    }

    private function extractLiteralSequence(NodeInterface $node): ?string
    {
        if ($node instanceof LiteralNode) {
            return $node->value;
        }

        if ($node instanceof GroupNode) {
            return $this->extractLiteralSequence($node->child);
        }

        if ($node instanceof SequenceNode) {
            $value = '';
            foreach ($node->children as $child) {
                $literal = $this->extractLiteralSequence($child);
                if (null === $literal) {
                    return null;
                }
                $value .= $literal;
            }

            return $value;
        }

        return null;
    }

    private function lintRedundantCharClass(CharClassNode $node): void
    {
        $parts = $this->collectCharClassParts($node->expression);
        if (null === $parts) {
            return;
        }

        $ranges = [];
        $literals = [];
        $redundant = false;

        foreach ($parts as $part) {
            if ($part instanceof LiteralNode && 1 === \strlen($part->value)) {
                $ord = \ord($part->value);
                if (isset($literals[$ord]) || $this->isOrdCoveredByRanges($ord, $ranges)) {
                    $redundant = true;
                }
                $literals[$ord] = true;

                continue;
            }

            if ($part instanceof RangeNode && $part->start instanceof LiteralNode && $part->end instanceof LiteralNode) {
                if (1 !== \strlen($part->start->value) || 1 !== \strlen($part->end->value)) {
                    continue;
                }

                $start = \ord($part->start->value);
                $end = \ord($part->end->value);
                if ($start > $end) {
                    continue;
                }

                if ($this->rangeOverlaps($start, $end, $ranges)) {
                    $redundant = true;
                }

                foreach ($literals as $ord => $seen) {
                    if ($ord >= $start && $ord <= $end) {
                        $redundant = true;
                        unset($literals[$ord]);
                    }
                }

                $ranges[] = [$start, $end];
            }
        }

        if ($redundant) {
            $this->addIssue(
                'regex.lint.charclass.redundant',
                'Redundant elements detected in character class.',
                $node->startPosition,
            );
        }
    }

    private function lintSuspiciousCharClassRange(CharClassNode $node): void
    {
        $parts = $this->collectCharClassParts($node->expression);
        if (null === $parts) {
            return;
        }

        foreach ($parts as $part) {
            if (!$part instanceof RangeNode) {
                continue;
            }

            if (!$part->start instanceof LiteralNode || !$part->end instanceof LiteralNode) {
                continue;
            }

            if (1 !== \strlen($part->start->value) || 1 !== \strlen($part->end->value)) {
                continue;
            }

            $startOrd = \ord($part->start->value);
            $endOrd = \ord($part->end->value);
            if ($startOrd > 127 || $endOrd > 127) {
                continue;
            }

            $minOrd = min($startOrd, $endOrd);
            $maxOrd = max($startOrd, $endOrd);

            if ($this->isAsciiLetter($startOrd) && $this->isAsciiLetter($endOrd) && $minOrd <= 90 && $maxOrd >= 97) {
                $rangeLabel = $part->start->value.'-'.$part->end->value;
                $minChar = \chr($minOrd);
                $maxChar = \chr($maxOrd);
                $this->addIssue(
                    'regex.lint.charclass.suspicious_range',
                    \sprintf(
                        'Suspicious ASCII range "%s" includes non-letters between "%s" and "%s" in ASCII order.',
                        $rangeLabel,
                        $minChar,
                        $maxChar,
                    ),
                    $part->startPosition,
                    'Use separate ranges like [A-Z] and [a-z] (or combine as [A-Za-z]).',
                );

                return;
            }
        }
    }

    private function lintSuspiciousCharClassPipe(CharClassNode $node): void
    {
        $parts = $this->collectCharClassParts($node->expression);
        if (null === $parts) {
            return;
        }

        $letters = 0;
        $pipes = 0;

        foreach ($parts as $part) {
            if (!$part instanceof LiteralNode || 1 !== \strlen($part->value)) {
                return;
            }

            $value = $part->value;
            if ('|' === $value) {
                $pipes++;

                continue;
            }

            $ord = \ord($value);
            if ($ord > 127) {
                return;
            }

            if ($this->isAsciiLetter($ord)) {
                $letters++;

                continue;
            }

            return;
        }

        if ($pipes > 0 && $letters >= 4) {
            $this->addIssue(
                'regex.lint.charclass.suspicious_pipe',
                'Character class contains "|" which is literal inside []. It looks like an alternation typo.',
                $node->startPosition,
                'Did you mean an alternation like "(error|failure)" instead of a character class?',
            );
        }
    }

    /**
     * @return array<Node\NodeInterface>|null
     */
    private function collectCharClassParts(NodeInterface $node): ?array
    {
        if ($node instanceof ClassOperationNode) {
            return null;
        }

        if ($node instanceof AlternationNode) {
            return array_values($node->alternatives);
        }

        if ($node instanceof SequenceNode) {
            return array_values($node->children);
        }

        return [$node];
    }

    private function isAsciiLetter(int $ord): bool
    {
        return ($ord >= 65 && $ord <= 90) || ($ord >= 97 && $ord <= 122);
    }

    /**
     * @param array<array{0: int, 1: int}> $ranges
     */
    private function rangeOverlaps(int $start, int $end, array $ranges): bool
    {
        foreach ($ranges as [$rStart, $rEnd]) {
            if ($start <= $rEnd && $end >= $rStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array{0: int, 1: int}> $ranges
     */
    private function isOrdCoveredByRanges(int $ord, array $ranges): bool
    {
        foreach ($ranges as [$rStart, $rEnd]) {
            if ($ord >= $rStart && $ord <= $rEnd) {
                return true;
            }
        }

        return false;
    }

    private function lintInlineFlags(GroupNode $node): void
    {
        $flags = (string) $node->flags;
        if ('' === $flags) {
            return;
        }

        $resetAll = str_starts_with($flags, '^');
        if ($resetAll) {
            $flags = substr($flags, 1);
        }

        [$set, $unset] = str_contains($flags, '-')
            ? explode('-', $flags, 2)
            : [$flags, ''];

        $baseFlags = $resetAll ? '' : $this->flags;

        foreach (str_split($set) as $flag) {
            if ('' === $flag) {
                continue;
            }
            if (str_contains($baseFlags, $flag)) {
                $this->addIssue(
                    'regex.lint.flag.redundant',
                    \sprintf("Inline flag '%s' is redundant; it is already set globally.", $flag),
                    $node->startPosition,
                );
            }
        }

        foreach (str_split($unset) as $flag) {
            if ('' === $flag) {
                continue;
            }

            if (!str_contains($baseFlags, $flag)) {
                $this->addIssue(
                    'regex.lint.flag.redundant',
                    \sprintf("Inline flag '-%s' is redundant; the flag is not set globally.", $flag),
                    $node->startPosition,
                );
            } else {
                $this->addIssue(
                    'regex.lint.flag.override',
                    \sprintf("Inline flag '-%s' overrides a global modifier.", $flag),
                    $node->startPosition,
                    'Consider removing the global flag or limiting it to specific groups.',
                );
            }
        }
    }

    private function isRedundantGroup(NodeInterface $node): bool
    {
        if ($node instanceof SequenceNode) {
            if (1 !== \count($node->children)) {
                return false;
            }

            return $this->isRedundantGroup($node->children[0]);
        }

        if ($node instanceof AlternationNode || $node instanceof QuantifierNode) {
            return false;
        }

        return $node instanceof LiteralNode
            || $node instanceof CharTypeNode
            || $node instanceof CharClassNode
            || $node instanceof CharLiteralNode
            || $node instanceof UnicodeNode
            || $node instanceof DotNode
            || $node instanceof AnchorNode
            || $node instanceof AssertionNode
            || $node instanceof KeepNode
            || $node instanceof UnicodePropNode
            || $node instanceof PosixClassNode
            || $node instanceof ControlCharNode
            || $node instanceof CommentNode
            || $node instanceof CalloutNode
            || $node instanceof ScriptRunNode;
    }

    private function isVariableQuantifier(string $quantifier): bool
    {
        [$min, $max] = $this->parseQuantifierRange($quantifier);

        return null === $max || $min !== $max;
    }

    private function isRepeatableQuantifier(string $quantifier): bool
    {
        [, $max] = $this->parseQuantifierRange($quantifier);

        return null === $max || $max > 1;
    }

    private function isUnboundedQuantifier(string $quantifier): bool
    {
        [, $max] = $this->parseQuantifierRange($quantifier);

        return null === $max;
    }

    private function findNestedQuantifier(NodeInterface $node): ?QuantifierNode
    {
        if ($node instanceof QuantifierNode) {
            if (QuantifierType::T_POSSESSIVE === $node->type) {
                return null;
            }

            return $node;
        }

        if ($node instanceof GroupNode) {
            if (GroupType::T_GROUP_ATOMIC === $node->type) {
                return null;
            }

            return $this->findNestedQuantifier($node->child);
        }

        if ($node instanceof SequenceNode) {
            foreach ($node->children as $child) {
                $nested = $this->findNestedQuantifier($child);
                if (null !== $nested) {
                    return $nested;
                }
            }
        }

        if ($node instanceof AlternationNode) {
            foreach ($node->alternatives as $alt) {
                $nested = $this->findNestedQuantifier($alt);
                if (null !== $nested) {
                    return $nested;
                }
            }
        }

        if ($node instanceof ConditionalNode) {
            return $this->findNestedQuantifier($node->yes) ?? $this->findNestedQuantifier($node->no);
        }

        if ($node instanceof DefineNode) {
            return $this->findNestedQuantifier($node->content);
        }

        return null;
    }

    private function isSafelySeparatedNestedQuantifier(QuantifierNode $outer, QuantifierNode $nested): bool
    {
        $sequenceInfo = $this->findSequenceForNestedQuantifier($outer->node, $nested);
        if (null === $sequenceInfo) {
            return false;
        }

        $innerBoundary = $this->boundaryCharSet($nested->node);
        if ($innerBoundary->isUnknown() || $innerBoundary->isEmpty()) {
            return false;
        }

        $sequence = $sequenceInfo['sequence'];
        $index = $sequenceInfo['index'];
        $neighbors = [];
        if ($index > 0) {
            $neighbors[] = $sequence->children[$index - 1];
        }
        if ($index + 1 < \count($sequence->children)) {
            $neighbors[] = $sequence->children[$index + 1];
        }

        foreach ($neighbors as $neighbor) {
            if ($this->isExclusiveSeparator($neighbor, $innerBoundary)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{sequence: Node\SequenceNode, index: int}|null
     */
    private function findSequenceForNestedQuantifier(NodeInterface $node, QuantifierNode $nested): ?array
    {
        if ($node instanceof GroupNode) {
            return $this->findSequenceForNestedQuantifier($node->child, $nested);
        }

        if (!($node instanceof SequenceNode)) {
            return null;
        }

        foreach ($node->children as $index => $child) {
            $unwrapped = $this->unwrapTransparentNode($child);
            if ($unwrapped === $nested) {
                return ['sequence' => $node, 'index' => $index];
            }
        }

        return null;
    }

    private function unwrapTransparentNode(NodeInterface $node): NodeInterface
    {
        if ($node instanceof GroupNode && $this->isTransparentGroup($node->type)) {
            return $this->unwrapTransparentNode($node->child);
        }

        if ($node instanceof SequenceNode && 1 === \count($node->children)) {
            return $this->unwrapTransparentNode($node->children[0]);
        }

        return $node;
    }

    private function isTransparentGroup(GroupType $type): bool
    {
        return !\in_array($type, [
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
        ], true);
    }

    private function boundaryCharSet(NodeInterface $node): CharSet
    {
        $first = $this->charSetAnalyzer->firstChars($node);
        $last = $this->charSetAnalyzer->lastChars($node);

        return $first->union($last);
    }

    private function isExclusiveSeparator(NodeInterface $separator, CharSet $innerBoundary): bool
    {
        if ($this->isOptionalNode($separator) || !$this->isConsuming($separator)) {
            return false;
        }

        $separatorSet = $this->boundaryCharSet($separator);
        if ($separatorSet->isUnknown() || $separatorSet->isEmpty()) {
            return false;
        }

        return !$separatorSet->intersects($innerBoundary);
    }

    private function isOptionalNode(NodeInterface $node): bool
    {
        if ($node instanceof LiteralNode) {
            return '' === $node->value;
        }

        if ($node instanceof QuantifierNode) {
            [$min] = $this->parseQuantifierRange($node->quantifier);

            return 0 === $min;
        }

        if ($node instanceof GroupNode) {
            if ($this->isTransparentGroup($node->type)) {
                return $this->isOptionalNode($node->child);
            }

            return true;
        }

        if ($node instanceof SequenceNode) {
            foreach ($node->children as $child) {
                if (!$this->isOptionalNode($child)) {
                    return false;
                }
            }

            return true;
        }

        if ($node instanceof AlternationNode) {
            foreach ($node->alternatives as $alt) {
                if ($this->isOptionalNode($alt)) {
                    return true;
                }
            }

            return false;
        }

        if ($node instanceof ConditionalNode) {
            return $this->isOptionalNode($node->yes) || $this->isOptionalNode($node->no);
        }

        return !$this->isConsuming($node);
    }

    private function containsDotStar(NodeInterface $node): bool
    {
        if ($node instanceof QuantifierNode && $node->node instanceof DotNode) {
            return $this->isUnboundedQuantifier($node->quantifier);
        }

        if ($node instanceof GroupNode) {
            return $this->containsDotStar($node->child);
        }

        if ($node instanceof SequenceNode) {
            foreach ($node->children as $child) {
                if ($this->containsDotStar($child)) {
                    return true;
                }
            }
        }

        if ($node instanceof AlternationNode) {
            foreach ($node->alternatives as $alt) {
                if ($this->containsDotStar($alt)) {
                    return true;
                }
            }
        }

        if ($node instanceof ConditionalNode) {
            return $this->containsDotStar($node->yes) || $this->containsDotStar($node->no);
        }

        if ($node instanceof DefineNode) {
            return $this->containsDotStar($node->content);
        }

        return false;
    }

    /**
     * @return array{0: int, 1: int|null}
     */
    private function parseQuantifierRange(string $quantifier): array
    {
        return match ($quantifier) {
            '*' => [0, null],
            '+' => [1, null],
            '?' => [0, 1],
            default => preg_match('/^\{(\d++)(?:,(\d*+))?\}$/', $quantifier, $m) ?
                (isset($m[2]) ?
                    ('' === $m[2] ? [(int) $m[1], null] : [(int) $m[1], (int) $m[2]]) :
                    [(int) $m[1], (int) $m[1]]
                ) :
                [1, 1],
        };
    }

    // Add other visit methods as needed, default to no-op
}
