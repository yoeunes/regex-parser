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

use RegexParser\LintIssue;
use RegexParser\Node;
use RegexParser\Node\GroupType;
use RegexParser\ReDoS\CharSetAnalyzer;

/**
 * Lints regex patterns for semantic issues like useless flags.
 *
 * @extends AbstractNodeVisitor<Node\NodeInterface>
 */
final class LinterNodeVisitor extends AbstractNodeVisitor
{
    /**
     * @var list<LintIssue>
     */
    private array $issues = [];

    private string $flags = '';

    private string $delimiter = '';

    private bool $hasCaseSensitiveChars = false;

    private bool $hasDots = false;

    private bool $hasAnchors = false;

    private ?string $patternValue = null;

    private int $maxCapturingGroup = 0;

    /**
     * @var array<string, bool>
     */
    private array $definedNamedGroups = [];

    private CharSetAnalyzer $charSetAnalyzer;

    public function __construct()
    {
        $this->charSetAnalyzer = new CharSetAnalyzer();
    }

    /**
     * Get the full regex pattern including delimiters and flags
     */
    public function getFullPattern(): string
    {
        return $this->delimiter.$this->patternValue.$this->delimiter.$this->flags;
    }

    /**
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return array_map(
            static fn (LintIssue $issue): string => $issue->message,
            $this->issues,
        );
    }

    /**
     * @return list<LintIssue>
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    #[\Override]
    public function visitRegex(Node\RegexNode $node): Node\NodeInterface
    {
        $this->flags = $node->flags;
        $this->delimiter = $node->delimiter;
        $this->charSetAnalyzer = new CharSetAnalyzer($this->flags);
        $this->issues = [];
        $this->hasCaseSensitiveChars = false;
        $this->hasDots = false;
        $this->hasAnchors = false;
        $this->maxCapturingGroup = 0;
        $this->definedNamedGroups = [];

        // Use a simple visitor to compile the pattern string for diagnostics
        $compiler = new \RegexParser\NodeVisitor\CompilerNodeVisitor();
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
    public function visitLiteral(Node\LiteralNode $node): Node\NodeInterface
    {
        if (preg_match('/[a-zA-Z]/', $node->value) > 0) {
            $this->hasCaseSensitiveChars = true;
        }

        return $node;
    }

    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): Node\NodeInterface
    {
        // Check if char class contains letters
        $expression = $node->expression;
        if ($expression instanceof Node\AlternationNode) {
            foreach ($expression->alternatives as $alt) {
                if ($this->charClassPartHasLetters($alt)) {
                    $this->hasCaseSensitiveChars = true;
                }
            }
        } elseif ($this->charClassPartHasLetters($expression)) {
            $this->hasCaseSensitiveChars = true;
        }

        $this->lintRedundantCharClass($node);

        return $node;
    }

    #[\Override]
    public function visitDot(Node\DotNode $node): Node\NodeInterface
    {
        $this->hasDots = true;

        return $node;
    }

    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): Node\NodeInterface
    {
        if ('^' === $node->value || '$' === $node->value) {
            $this->hasAnchors = true;
        }

        return $node;
    }

    // Implement other visit methods as no-op
    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): Node\NodeInterface
    {
        $this->lintAlternation($node);

        foreach ($node->alternatives as $alt) {
            $alt->accept($this);
        }

        return $node;
    }

    #[\Override]
    public function visitSequence(Node\SequenceNode $node): Node\NodeInterface
    {
        // Check for anchor conflicts
        $this->checkAnchorConflicts($node);

        foreach ($node->children as $child) {
            $child->accept($this);
        }

        return $node;
    }

    #[\Override]
    public function visitGroup(Node\GroupNode $node): Node\NodeInterface
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

        $node->child->accept($this);

        return $node;
    }

    #[\Override]
    public function visitBackref(Node\BackrefNode $node): Node\NodeInterface
    {
        $ref = $node->ref;

        // Check numeric backreferences
        if (preg_match('/^\\\\(\d+)$/', $ref, $matches)) {
            $num = (int) $matches[1];
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
    public function visitQuantifier(Node\QuantifierNode $node): Node\NodeInterface
    {
        if ($this->isVariableQuantifier($node->quantifier)) {
            if ($this->isRepeatableQuantifier($node->quantifier)) {
                $nested = $this->findNestedQuantifier($node->node);
                if (null !== $nested && $this->isVariableQuantifier($nested->quantifier)) {
                    $this->addIssue(
                        'regex.lint.quantifier.nested',
                        'Nested quantifiers can cause catastrophic backtracking.',
                        $node->startPosition,
                        'Consider using atomic groups (?>...) or possessive quantifiers.',
                    );
                }
            }

            if ($this->isUnboundedQuantifier($node->quantifier) && $this->containsDotStar($node->node)) {
                $this->addIssue(
                    'regex.lint.dotstar.nested',
                    'An unbounded quantifier wraps a dot-star, which can cause severe backtracking.',
                    $node->startPosition,
                    'Refactor with atomic groups or a more specific character class.',
                );
            }
        }

        $node->node->accept($this);

        return $node;
    }

    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): Node\NodeInterface
    {
        $code = null;
        if (preg_match('/^\\\\x([0-9a-fA-F]{2})$/', $node->code, $m)) {
            $code = (int) hexdec($m[1]);
        } elseif (preg_match('/^\\\\u\{([0-9a-fA-F]++)\}$/', $node->code, $m)) {
            $code = (int) hexdec($m[1]);
        }

        if (null !== $code && $code > 0x10FFFF) {
            $this->addIssue(
                'regex.lint.escape.suspicious',
                \sprintf('Suspicious Unicode escape "%s" (out of range).', $node->code),
                $node->startPosition,
            );
        }

        return $node;
    }

    #[\Override]
    public function visitCharLiteral(Node\CharLiteralNode $node): Node\NodeInterface
    {
        if (Node\CharLiteralType::UNICODE === $node->type && $node->codePoint > 0x10FFFF) {
            $this->addIssue(
                'regex.lint.escape.suspicious',
                \sprintf('Suspicious Unicode escape "%s" (out of range).', $node->originalRepresentation),
                $node->startPosition,
            );
        }

        if (\in_array($node->type, [Node\CharLiteralType::OCTAL, Node\CharLiteralType::OCTAL_LEGACY], true) && $node->codePoint > 0xFF) {
            $this->addIssue(
                'regex.lint.escape.suspicious',
                \sprintf('Suspicious octal escape "%s" (out of range).', $node->originalRepresentation),
                $node->startPosition,
            );
        }

        if (Node\CharLiteralType::UNICODE_NAMED === $node->type && class_exists(\IntlChar::class)) {
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

        return $node;
    }

    private function countCapturingGroups(Node\NodeInterface $node): void
    {
        if ($node instanceof Node\GroupNode && (GroupType::T_GROUP_CAPTURING === $node->type || GroupType::T_GROUP_NAMED === $node->type)) {
            $this->maxCapturingGroup++;
            if (null !== $node->name) {
                $this->definedNamedGroups[$node->name] = true;
            }
        }

        // Recursively count in children
        if ($node instanceof Node\GroupNode) {
            $this->countCapturingGroups($node->child);
        } elseif ($node instanceof Node\AlternationNode) {
            foreach ($node->alternatives as $alt) {
                $this->countCapturingGroups($alt);
            }
        } elseif ($node instanceof Node\SequenceNode) {
            foreach ($node->children as $child) {
                $this->countCapturingGroups($child);
            }
        } elseif ($node instanceof Node\QuantifierNode) {
            $this->countCapturingGroups($node->node);
        } elseif ($node instanceof Node\ConditionalNode) {
            $this->countCapturingGroups($node->condition);
            $this->countCapturingGroups($node->yes);
            $this->countCapturingGroups($node->no);
        } elseif ($node instanceof Node\CharClassNode) {
            $this->countCapturingGroups($node->expression);
        }
        // Other node types don't contain groups
    }

    private function checkUselessFlags(): void
    {
        if (str_contains($this->flags, 'i') && !$this->hasCaseSensitiveChars) {
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

    private function charClassPartHasLetters(Node\NodeInterface $node): bool
    {
        if ($node instanceof Node\LiteralNode && preg_match('/[a-zA-Z]/', $node->value) > 0) {
            return true;
        }
        if ($node instanceof Node\RangeNode) {
            return $this->rangeHasLetters($node);
        }

        // Other types like CharTypeNode might have letters, but for simplicity, assume not
        return false;
    }

    private function rangeHasLetters(Node\RangeNode $node): bool
    {
        $start = $node->start instanceof Node\LiteralNode ? $node->start->value : '';
        $end = $node->end instanceof Node\LiteralNode ? $node->end->value : '';

        return preg_match('/[a-zA-Z]/', $start.$end) > 0;
    }

    private function checkAnchorConflicts(Node\SequenceNode $node): void
    {
        $children = $node->children;
        $count = \count($children);

        for ($i = 0; $i < $count; $i++) {
            $child = $children[$i];

            if ($child instanceof Node\AnchorNode && '^' === $child->value) {
                // Check if there are consuming nodes before ^
                for ($j = 0; $j < $i; $j++) {
                    if ($this->isConsuming($children[$j])) {
                        if (!str_contains($this->flags, 'm')) {
                            $this->addIssue(
                                'regex.lint.anchor.impossible.start',
                                "Start anchor '^' appears after consuming characters, making it impossible to match.",
                                $child->startPosition,
                            );
                        }

                        break;
                    }
                }
            }

            if ($child instanceof Node\AnchorNode && '$' === $child->value) {
                // Check if there are consuming nodes after $
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($this->isConsuming($children[$j])) {
                        $this->addIssue(
                            'regex.lint.anchor.impossible.end',
                            "End anchor '$' appears before consuming characters, making it impossible to match.",
                            $child->startPosition,
                        );

                        break;
                    }
                }
            }
        }
    }

    private function isConsuming(Node\NodeInterface $node): bool
    {
        if ($node instanceof Node\LiteralNode) {
            return true;
        }
        if ($node instanceof Node\CharClassNode) {
            return true;
        }
        if ($node instanceof Node\CharTypeNode) {
            return true;
        }
        if ($node instanceof Node\DotNode) {
            return true;
        }
        if ($node instanceof Node\CharLiteralNode) {
            return true;
        }
        if ($node instanceof Node\UnicodePropNode) {
            return true;
        }
        if ($node instanceof Node\PosixClassNode) {
            return true;
        }
        if ($node instanceof Node\QuantifierNode) {
            return $this->isConsuming($node->node);
        }
        if ($node instanceof Node\GroupNode) {
            // Lookarounds don't consume
            return !(\RegexParser\Node\GroupType::T_GROUP_LOOKAHEAD_POSITIVE === $node->type
                || \RegexParser\Node\GroupType::T_GROUP_LOOKAHEAD_NEGATIVE === $node->type
                || \RegexParser\Node\GroupType::T_GROUP_LOOKBEHIND_POSITIVE === $node->type
                || \RegexParser\Node\GroupType::T_GROUP_LOOKBEHIND_NEGATIVE === $node->type);
        }
        if ($node instanceof Node\AlternationNode) {
            // If any alternative consumes, consider it consuming
            foreach ($node->alternatives as $alt) {
                if ($this->isConsuming($alt)) {
                    return true;
                }
            }

            return false;
        }
        if ($node instanceof Node\SequenceNode) {
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

    private function addIssue(string $id, string $message, ?int $offset = null, ?string $hint = null): void
    {
        $this->issues[] = new LintIssue($id, $message, $offset, $hint);
    }

    private function lintAlternation(Node\AlternationNode $node): void
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
                        \sprintf('Duplicate alternation branch "%s".', $literal),
                        $node->startPosition,
                    );

                    break;
                }
            }

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
                            \sprintf('Alternation branches "%s" and "%s" overlap.', $a, $b),
                            $node->startPosition,
                            'Consider ordering longer alternatives first or using atomic groups.',
                        );

                        return;
                    }
                }
            }
        }

        // Check for semantic overlaps using character set analysis
        $this->checkSemanticOverlaps($node);
    }

    private function checkSemanticOverlaps(Node\AlternationNode $node): void
    {
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
                        'Consider reordering alternatives or using atomic groups to improve performance.',
                    );

                    return;
                }
            }
        }
    }

    private function extractLiteralSequence(Node\NodeInterface $node): ?string
    {
        if ($node instanceof Node\LiteralNode) {
            return $node->value;
        }

        if ($node instanceof Node\GroupNode) {
            return $this->extractLiteralSequence($node->child);
        }

        if ($node instanceof Node\SequenceNode) {
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

    private function lintRedundantCharClass(Node\CharClassNode $node): void
    {
        $parts = $this->collectCharClassParts($node->expression);
        if (null === $parts) {
            return;
        }

        $ranges = [];
        $literals = [];
        $redundant = false;

        foreach ($parts as $part) {
            if ($part instanceof Node\LiteralNode && 1 === \strlen($part->value)) {
                $ord = \ord($part->value);
                if (isset($literals[$ord]) || $this->isOrdCoveredByRanges($ord, $ranges)) {
                    $redundant = true;
                }
                $literals[$ord] = true;

                continue;
            }

            if ($part instanceof Node\RangeNode && $part->start instanceof Node\LiteralNode && $part->end instanceof Node\LiteralNode) {
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

    /**
     * @return list<Node\NodeInterface>|null
     */
    private function collectCharClassParts(Node\NodeInterface $node): ?array
    {
        if ($node instanceof Node\ClassOperationNode) {
            return null;
        }

        if ($node instanceof Node\AlternationNode) {
            return array_values($node->alternatives);
        }

        if ($node instanceof Node\SequenceNode) {
            return array_values($node->children);
        }

        return [$node];
    }

    /**
     * @param list<array{0: int, 1: int}> $ranges
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
     * @param list<array{0: int, 1: int}> $ranges
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

    private function lintInlineFlags(Node\GroupNode $node): void
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

    private function isRedundantGroup(Node\NodeInterface $node): bool
    {
        if ($node instanceof Node\SequenceNode) {
            if (1 !== \count($node->children)) {
                return false;
            }

            return $this->isRedundantGroup($node->children[0]);
        }

        if ($node instanceof Node\AlternationNode || $node instanceof Node\QuantifierNode) {
            return false;
        }

        return $node instanceof Node\LiteralNode
            || $node instanceof Node\CharTypeNode
            || $node instanceof Node\CharClassNode
            || $node instanceof Node\CharLiteralNode
            || $node instanceof Node\UnicodeNode
            || $node instanceof Node\DotNode
            || $node instanceof Node\AnchorNode
            || $node instanceof Node\AssertionNode
            || $node instanceof Node\KeepNode
            || $node instanceof Node\UnicodePropNode
            || $node instanceof Node\PosixClassNode
            || $node instanceof Node\ControlCharNode
            || $node instanceof Node\CommentNode
            || $node instanceof Node\CalloutNode
            || $node instanceof Node\ScriptRunNode;
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

    private function findNestedQuantifier(Node\NodeInterface $node): ?Node\QuantifierNode
    {
        if ($node instanceof Node\QuantifierNode) {
            return $node;
        }

        if ($node instanceof Node\GroupNode) {
            return $this->findNestedQuantifier($node->child);
        }

        if ($node instanceof Node\SequenceNode) {
            foreach ($node->children as $child) {
                $nested = $this->findNestedQuantifier($child);
                if (null !== $nested) {
                    return $nested;
                }
            }
        }

        if ($node instanceof Node\AlternationNode) {
            foreach ($node->alternatives as $alt) {
                $nested = $this->findNestedQuantifier($alt);
                if (null !== $nested) {
                    return $nested;
                }
            }
        }

        if ($node instanceof Node\ConditionalNode) {
            return $this->findNestedQuantifier($node->yes) ?? $this->findNestedQuantifier($node->no);
        }

        if ($node instanceof Node\DefineNode) {
            return $this->findNestedQuantifier($node->content);
        }

        return null;
    }

    private function containsDotStar(Node\NodeInterface $node): bool
    {
        if ($node instanceof Node\QuantifierNode && $node->node instanceof Node\DotNode) {
            return $this->isUnboundedQuantifier($node->quantifier);
        }

        if ($node instanceof Node\GroupNode) {
            return $this->containsDotStar($node->child);
        }

        if ($node instanceof Node\SequenceNode) {
            foreach ($node->children as $child) {
                if ($this->containsDotStar($child)) {
                    return true;
                }
            }
        }

        if ($node instanceof Node\AlternationNode) {
            foreach ($node->alternatives as $alt) {
                if ($this->containsDotStar($alt)) {
                    return true;
                }
            }
        }

        if ($node instanceof Node\ConditionalNode) {
            return $this->containsDotStar($node->yes) || $this->containsDotStar($node->no);
        }

        if ($node instanceof Node\DefineNode) {
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
