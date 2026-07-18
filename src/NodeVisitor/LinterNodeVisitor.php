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
use RegexParser\Lint\Rule\GroupIndex;
use RegexParser\Lint\Rule\LintContext;
use RegexParser\Lint\Rule\LintRuleInterface;
use RegexParser\Lint\Rule\LintRuleRegistry;
use RegexParser\Lint\Rule\PatternInfo;
use RegexParser\LintIssue;
use RegexParser\Node;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharLiteralType;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\ClassOperationNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\ReDoS\CharSetAnalyzer;
use RegexParser\Severity;

/**
 * Lints regex patterns for semantic issues like useless flags.
 *
 * @extends AbstractNodeVisitor<Node\NodeInterface>
 */
final class LinterNodeVisitor extends AbstractNodeVisitor
{
    /**
     * Rules that are disabled by default (can be enabled via config).
     */
    private const DEFAULT_DISABLED_RULES = [
        'unicode.shorthandWithoutU' => false,
    ];

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

    /**
     * @var array<int, array{node: GroupNode, start: int, end: int, alternation: array<string, int>, alwaysEmpty: bool}>
     */
    private array $capturingGroups = [];

    /**
     * @var array<string, array<int, array{node: GroupNode, start: int, end: int, alternation: array<string, int>, alwaysEmpty: bool}>>
     */
    private array $capturingGroupsByName = [];

    private int $nextCapturingGroupNumber = 1;

    private bool $skipUselessBackref = false;

    private CharSetAnalyzer $charSetAnalyzer;

    private bool $trackCaseSensitivity = false;

    private bool $unicodeMode = false;

    private bool $intlAvailable = false;

    /**
     * Per-run context shared with the lint rules: immutable pattern facts
     * plus the mutable traversal cursor (parents, alternation branches,
     * active inline flags).
     */
    private LintContext $context;

    /**
     * @var list<LintRuleInterface>
     */
    private array $rules;

    /**
     * @var array<class-string<NodeInterface>, list<LintRuleInterface>>
     */
    private array $dispatchMap = [];

    /**
     * @param array<string, bool>   $enabledRules
     * @param LintRuleRegistry|null $registry     @internal custom registries are not yet a public extension point
     */
    public function __construct(/**
     * Configuration for which lint rules are enabled.
     */
        private array $enabledRules = [],
        ?LintRuleRegistry $registry = null)
    {
        $this->charSetAnalyzer = new CharSetAnalyzer();
        $this->intlAvailable = class_exists(\IntlChar::class);
        $this->rules = ($registry ?? new LintRuleRegistry())->all();
        foreach ($this->rules as $rule) {
            foreach ($rule->getNodeTypes() as $nodeType) {
                $this->dispatchMap[$nodeType][] = $rule;
            }
        }
        $this->context = $this->createContext();
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
        $this->capturingGroups = [];
        $this->capturingGroupsByName = [];
        $this->nextCapturingGroupNumber = 1;
        $this->skipUselessBackref = false;

        // Use a simple visitor to compile the pattern string for diagnostics
        $compiler = new CompilerNodeVisitor();
        $this->patternValue = $node->pattern->accept($compiler);

        $this->collectCapturingGroupInfo($node->pattern);

        // First pass: count capturing groups
        $this->countCapturingGroups($node->pattern);

        $this->context = $this->createContext();
        foreach ($this->rules as $rule) {
            $rule->begin($this->context);
        }

        // Second pass: traverse and lint
        $node->pattern->accept($this);

        // Finally, compute useless-flag diagnostics based on the collected
        // state and the fully compiled pattern.
        $this->checkUselessFlags();

        foreach ($this->rules as $rule) {
            $this->append($rule->finish($this->context));
        }

        return $node;
    }

    #[\Override]
    public function visitLiteral(LiteralNode $node): NodeInterface
    {
        $this->dispatch($node);
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

        $this->dispatch($node);

        return $node;
    }

    #[\Override]
    public function visitDot(DotNode $node): NodeInterface
    {
        $this->dispatch($node);
        $this->hasDots = true;

        return $node;
    }

    #[\Override]
    public function visitAnchor(AnchorNode $node): NodeInterface
    {
        $this->dispatch($node);
        if ('^' === $node->value || '$' === $node->value) {
            $this->hasAnchors = true;
        }

        return $node;
    }

    // Implement other visit methods as no-op
    #[\Override]
    public function visitAlternation(AlternationNode $node): NodeInterface
    {
        $this->dispatch($node);

        $this->context->pushParent($node);
        $altKey = $this->alternationKey($node);
        foreach ($node->alternatives as $index => $alt) {
            $this->context->pushAlternationBranch($altKey, $index);
            $alt->accept($this);
            $this->context->popAlternationBranch();
        }
        $this->context->popParent();

        return $node;
    }

    #[\Override]
    public function visitSequence(SequenceNode $node): NodeInterface
    {
        $this->dispatch($node);

        $this->context->pushParent($node);
        $sequenceFlags = $this->context->activeFlags();
        foreach ($node->children as $child) {
            $child->accept($this);

            if ($child instanceof GroupNode && $this->isStandaloneInlineFlagsGroup($child)) {
                $this->context->setActiveFlags($this->applyInlineFlags($this->context->activeFlags(), (string) $child->flags));
            }
        }
        $this->context->setActiveFlags($sequenceFlags);
        $this->context->popParent();

        return $node;
    }

    #[\Override]
    public function visitGroup(GroupNode $node): NodeInterface
    {
        $this->dispatch($node);
        $previousFlags = $this->context->activeFlags();

        if (GroupType::T_GROUP_INLINE_FLAGS === $node->type && null !== $node->flags) {
            if (!$this->isStandaloneInlineFlagsGroup($node)) {
                $this->context->setActiveFlags($this->applyInlineFlags($this->context->activeFlags(), (string) $node->flags));
            }
        }

        $this->context->pushParent($node);
        $node->child->accept($this);
        $this->context->popParent();
        $this->context->setActiveFlags($previousFlags);

        return $node;
    }

    #[\Override]
    public function visitBackref(BackrefNode $node): NodeInterface
    {
        $this->hasBackreferences = true;
        $this->dispatch($node);

        return $node;
    }

    #[\Override]
    public function visitQuantifier(QuantifierNode $node): NodeInterface
    {
        $this->dispatch($node);

        $this->context->pushParent($node);
        $node->node->accept($this);
        $this->context->popParent();

        return $node;
    }

    #[\Override]
    public function visitUnicode(UnicodeNode $node): NodeInterface
    {
        $this->dispatch($node);
        $code = $this->parseUnicodeEscapeCodePoint($node->code);

        if (null !== $code && $code > 0x10FFFF) {
            $this->addIssue(
                'regex.lint.escape.suspicious',
                \sprintf('Suspicious Unicode escape "%s" (out of range).', $node->code),
                $node->startPosition,
            );
        }

        // Braced Unicode escapes (e.g., \x{100}) require /u flag for code points > U+FF
        if ($this->isBracedUnicodeEscape($node->code) && !$this->unicodeMode && null !== $code && $code > 0xFF) {
            $this->addIssue(
                'regex.lint.unicode.bracedHexWithoutU',
                \sprintf('Unicode escape "%s" requires /u flag for code points > U+FF.', $node->code),
                $node->startPosition,
                'Add /u flag or use byte-level encoding.',
                Severity::Error,
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
        $this->dispatch($node);
        if (CharLiteralType::UNICODE === $node->type && $node->codePoint > 0x10FFFF) {
            $this->addIssue(
                'regex.lint.escape.suspicious',
                \sprintf('Suspicious Unicode escape "%s" (out of range).', $node->originalRepresentation),
                $node->startPosition,
            );
        }

        // Braced Unicode escapes (e.g., \x{100}) require /u flag for code points > U+FF
        if (CharLiteralType::UNICODE === $node->type
            && $this->isBracedUnicodeEscape($node->originalRepresentation)
            && !$this->unicodeMode
            && $node->codePoint > 0xFF
        ) {
            $this->addIssue(
                'regex.lint.unicode.bracedHexWithoutU',
                \sprintf('Unicode escape "%s" requires /u flag for code points > U+FF.', $node->originalRepresentation),
                $node->startPosition,
                'Add /u flag or use byte-level encoding.',
                Severity::Error,
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
        $this->dispatch($node);
        // Unicode properties require /u flag to work correctly
        if (!$this->unicodeMode) {
            $this->addIssue(
                'regex.lint.unicode.propertyWithoutU',
                \sprintf('Unicode property "\\p{%s}" requires /u flag.', trim($node->prop, '^{}')),
                $node->startPosition,
                'Add /u flag to enable Unicode property matching.',
                Severity::Error,
            );
        }

        if ($this->trackCaseSensitivity
            && !$this->hasCaseSensitiveChars
            && $this->unicodePropIsCaseSensitive($node->prop)
        ) {
            $this->hasCaseSensitiveChars = true;
        }

        return $node;
    }

    #[\Override]
    public function visitCharType(CharTypeNode $node): NodeInterface
    {
        $this->dispatch($node);
        // Shorthands \w, \d, \s match only ASCII without /u flag
        if (\in_array($node->value, ['w', 'd', 's', 'W', 'D', 'S'], true) && !$this->unicodeMode) {
            $this->addIssue(
                'regex.lint.unicode.shorthandWithoutU',
                \sprintf('Shorthand "\\%s" matches only ASCII without /u flag.', $node->value),
                $node->startPosition,
                'Add /u flag for Unicode support, or use \\p{L} for letters.',
                Severity::Style,
            );
        }

        return $node;
    }

    private function createContext(): LintContext
    {
        return new LintContext(
            new PatternInfo(
                $this->flags,
                $this->delimiter,
                $this->patternValue ?? '',
                $this->unicodeMode,
                $this->intlAvailable,
            ),
            new GroupIndex(
                $this->maxCapturingGroup,
                $this->definedNamedGroups,
                $this->capturingGroups,
                $this->capturingGroupsByName,
                $this->skipUselessBackref,
            ),
            $this->charSetAnalyzer,
        );
    }

    /**
     * Run every registered rule subscribed to this node's class.
     */
    private function dispatch(NodeInterface $node): void
    {
        foreach ($this->dispatchMap[$node::class] ?? [] as $rule) {
            $this->append($rule->check($node, $this->context));
        }
    }

    /**
     * Single append point: enablement filtering happens here, exactly as the
     * historical addIssue() did.
     *
     * @param list<LintIssue> $issues
     */
    private function append(array $issues): void
    {
        foreach ($issues as $issue) {
            if ($this->isRuleEnabled($issue->id)) {
                $this->issues[] = $issue;
            }
        }
    }

    /**
     * Check if a lint rule is enabled.
     */
    private function isRuleEnabled(string $ruleId): bool
    {
        // Remove the 'regex.lint.' prefix if present for lookup
        $shortId = str_starts_with($ruleId, 'regex.lint.')
            ? substr($ruleId, \strlen('regex.lint.'))
            : $ruleId;

        // Explicit config takes precedence
        if (\array_key_exists($shortId, $this->enabledRules)) {
            return $this->enabledRules[$shortId];
        }

        // Check default disabled rules
        if (\array_key_exists($shortId, self::DEFAULT_DISABLED_RULES)) {
            return self::DEFAULT_DISABLED_RULES[$shortId];
        }

        // All other rules enabled by default
        return true;
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

    /**
     * @param array<string, int> $alternation
     */
    private function collectCapturingGroupInfo(NodeInterface $node, array $alternation = []): void
    {
        if ($this->skipUselessBackref) {
            return;
        }

        if ($node instanceof GroupNode) {
            if (GroupType::T_GROUP_BRANCH_RESET === $node->type) {
                $this->skipUselessBackref = true;

                return;
            }

            if (GroupType::T_GROUP_CAPTURING === $node->type || GroupType::T_GROUP_NAMED === $node->type) {
                $number = $this->nextCapturingGroupNumber++;
                $info = [
                    'node' => $node,
                    'start' => $node->getStartPosition(),
                    'end' => $node->getEndPosition(),
                    'alternation' => $alternation,
                    'alwaysEmpty' => $this->nodeIsAlwaysEmpty($node->child),
                ];

                $this->capturingGroups[$number] = $info;

                if (GroupType::T_GROUP_NAMED === $node->type && null !== $node->name) {
                    $this->capturingGroupsByName[$node->name][] = $info;
                }
            }

            $this->collectCapturingGroupInfo($node->child, $alternation);

            return;
        }

        if ($node instanceof AlternationNode) {
            $key = $this->alternationKey($node);
            foreach ($node->alternatives as $index => $alt) {
                $nextAlternation = $alternation;
                $nextAlternation[$key] = $index;
                $this->collectCapturingGroupInfo($alt, $nextAlternation);
            }

            return;
        }

        if ($node instanceof SequenceNode) {
            foreach ($node->children as $child) {
                $this->collectCapturingGroupInfo($child, $alternation);
            }

            return;
        }

        if ($node instanceof QuantifierNode) {
            $this->collectCapturingGroupInfo($node->node, $alternation);

            return;
        }

        if ($node instanceof ConditionalNode) {
            $this->collectCapturingGroupInfo($node->condition, $alternation);
            $this->collectCapturingGroupInfo($node->yes, $alternation);
            $this->collectCapturingGroupInfo($node->no, $alternation);

            return;
        }

        if ($node instanceof DefineNode) {
            $this->collectCapturingGroupInfo($node->content, $alternation);

            return;
        }

        if ($node instanceof CharClassNode) {
            $this->collectCapturingGroupInfo($node->expression, $alternation);

            return;
        }

        if ($node instanceof ClassOperationNode) {
            $this->collectCapturingGroupInfo($node->left, $alternation);
            $this->collectCapturingGroupInfo($node->right, $alternation);

            return;
        }

        if ($node instanceof RangeNode) {
            $this->collectCapturingGroupInfo($node->start, $alternation);
            $this->collectCapturingGroupInfo($node->end, $alternation);
        }
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

    private function nodeIsAlwaysEmpty(NodeInterface $node): bool
    {
        [$min, $max] = $node->accept(new LengthRangeNodeVisitor());

        return 0 === $min && 0 === $max;
    }

    private function alternationKey(AlternationNode $node): string
    {
        return (string) spl_object_id($node);
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

    private function isBracedUnicodeEscape(string $escape): bool
    {
        return preg_match('/^\\\\[xu]\\{/', $escape) > 0;
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

    private function addIssue(string $id, string $message, ?int $offset = null, ?string $hint = null, Severity $severity = Severity::Warning): void
    {
        // Check if this rule is enabled before adding the issue
        if (!$this->isRuleEnabled($id)) {
            return;
        }

        $this->issues[] = new LintIssue($id, $message, $offset, $hint, $severity);
    }

    private function isStandaloneInlineFlagsGroup(GroupNode $node): bool
    {
        if (GroupType::T_GROUP_INLINE_FLAGS !== $node->type || null === $node->flags) {
            return false;
        }

        if ($node->child instanceof LiteralNode) {
            return '' === $node->child->value;
        }

        if ($node->child instanceof SequenceNode) {
            return 0 === \count($node->child->children);
        }

        return false;
    }

    private function applyInlineFlags(string $baseFlags, string $inlineFlags): string
    {
        $resetAll = str_starts_with($inlineFlags, '^');
        if ($resetAll) {
            $baseFlags = '';
            $inlineFlags = substr($inlineFlags, 1);
        }

        [$setFlags, $unsetFlags] = str_contains($inlineFlags, '-')
            ? explode('-', $inlineFlags, 2)
            : [$inlineFlags, ''];

        $flags = [];
        foreach (str_split($baseFlags) as $flag) {
            if ('' !== $flag) {
                $flags[$flag] = true;
            }
        }

        foreach (str_split($setFlags) as $flag) {
            if ('' !== $flag) {
                $flags[$flag] = true;
            }
        }

        foreach (str_split($unsetFlags) as $flag) {
            if ('' !== $flag) {
                unset($flags[$flag]);
            }
        }

        return implode('', array_keys($flags));
    }

    // Add other visit methods as needed, default to no-op
}
