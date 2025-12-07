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
use RegexParser\Node\QuantifierType;
use RegexParser\ReDoS\CharSetAnalyzer;
use RegexParser\ReDoS\ReDoSSeverity;

/**
 * Analyzes the AST to detect ReDoS vulnerabilities.
 * Returns the maximum detected severity level for the visited node tree.
 *
 * Purpose: This visitor is designed to identify potential Regular Expression Denial of Service (ReDoS)
 * vulnerabilities within a given regex pattern. It traverses the Abstract Syntax Tree (AST)
 * and applies a set of heuristics to detect patterns that could lead to exponential or
 * polynomial backtracking, which can be exploited to cause a denial of service.
 * It categorizes risks into different severity levels (SAFE, LOW, UNKNOWN, MEDIUM, HIGH, CRITICAL)
 * and provides recommendations for mitigation.
 *
 * @extends AbstractNodeVisitor<ReDoSSeverity>
 */
final class ReDoSProfileNodeVisitor extends AbstractNodeVisitor
{
    private int $unboundedQuantifierDepth = 0;

    /**
     * Tracks total nesting of quantifiers (bounded or not) to detect LOW risks.
     */
    private int $totalQuantifierDepth = 0;

    /**
     * Stores all detected ReDoS vulnerabilities during the AST traversal.
     *
     * @var array<array{severity: ReDoSSeverity, message: string, pattern: string}>
     */
    private array $vulnerabilities = [];

    private bool $inAtomicGroup = false;

    private ?Node\NodeInterface $previousNode = null;

    private ?Node\NodeInterface $nextNode = null;

    private bool $backrefLoopDetected = false;

    public function __construct(
        private readonly CharSetAnalyzer $charSetAnalyzer = new CharSetAnalyzer(),
    ) {}

    /**
     * Retrieves the aggregated ReDoS analysis result after visiting the AST.
     *
     * Purpose: After the visitor has traversed the entire regex AST, this method compiles
     * all detected vulnerabilities into a single, comprehensive report. It determines
     * the highest severity found and collects all unique recommendations, providing
     * a clear summary of the ReDoS risk and how to address it.
     *
     * @return array{severity: ReDoSSeverity, recommendations: array<string>, vulnerablePattern: ?string}
     *                                                                                                    An associative array containing:
     *                                                                                                    - 'severity': The highest `ReDoSSeverity` level detected.
     *                                                                                                    - 'recommendations': An array of unique strings describing the detected issues and
     *                                                                                                    potential mitigations.
     *                                                                                                    - 'vulnerablePattern': The specific regex pattern fragment that triggered the highest
     *                                                                                                    severity, if any.
     *
     * @example
     * ```php
     * $visitor = new ReDoSProfileNodeVisitor();
     * $regexNode->accept($visitor); // Assuming $regexNode is the root of your parsed AST
     * $result = $visitor->getResult();
     * // $result might look like:
     * // [
     * //   'severity' => ReDoSSeverity::HIGH,
     * //   'recommendations' => [
     * //     'Nested unbounded quantifiers detected. This allows exponential backtracking. Consider using atomic groups (?>...) or possessive quantifiers (*+, ++).',
     * //     'Overlapping alternation branches inside a quantifier. e.g. (a|a)* or (ab|a)*. This can lead to catastrophic backtracking.'
     * //   ],
     * //   'vulnerablePattern' => 'a*a*'
     * // ]
     * ```
     */
    public function getResult(): array
    {
        $maxSeverity = ReDoSSeverity::SAFE;
        $recommendations = [];
        $pattern = null;

        foreach ($this->vulnerabilities as $vuln) {
            if ($this->severityGreaterThan($vuln['severity'], $maxSeverity)) {
                $maxSeverity = $vuln['severity'];
                $pattern = $vuln['pattern'];
            }
            $recommendations[] = $vuln['message'];
        }

        if ($this->backrefLoopDetected) {
            $maxSeverity = $this->maxSeverity($maxSeverity, ReDoSSeverity::CRITICAL);
        }

        return [
            'severity' => $maxSeverity,
            'recommendations' => array_unique($recommendations),
            'vulnerablePattern' => $pattern,
        ];
    }

    /**
     * Visits a RegexNode, initializing the ReDoS analysis.
     *
     * Purpose: This method serves as the entry point for analyzing an entire regular expression.
     * It resets the internal state of the visitor (e.g., quantifier depths, vulnerability list)
     * to ensure a fresh analysis for each new regex. It then delegates the actual analysis
     * to the main pattern node.
     *
     * @param Node\RegexNode $node the `RegexNode` representing the entire regular expression
     *
     * @return ReDoSSeverity the highest ReDoS severity found within the regex pattern
     *
     * @example
     * ```php
     * // Assuming $regexNode is the root of your parsed AST for '/(a+)+/'
     * $visitor = new ReDoSProfileNodeVisitor();
     * $severity = $regexNode->accept($visitor); // $severity would be ReDoSSeverity::CRITICAL
     * ```
     */
    #[\Override]
    public function visitRegex(Node\RegexNode $node): ReDoSSeverity
    {
        $this->unboundedQuantifierDepth = 0;
        $this->totalQuantifierDepth = 0;
        $this->vulnerabilities = [];
        $this->inAtomicGroup = false;
        $this->previousNode = null;
        $this->nextNode = null;
        $this->backrefLoopDetected = false;

        return $node->pattern->accept($this);
    }

    /**
     * Visits a QuantifierNode to detect ReDoS vulnerabilities related to repetition.
     *
     * Purpose: This is a critical method for ReDoS detection. It analyzes quantifiers
     * (`*`, `+`, `?`, `{n,m}`) for potential backtracking issues. It tracks nested
     * unbounded quantifiers (which can lead to exponential time complexity), large
     * bounded quantifiers, and the overall nesting depth. It also accounts for
     * possessive quantifiers and atomic groups, which mitigate ReDoS by disabling backtracking.
     *
     * @param Node\QuantifierNode $node the `QuantifierNode` representing a repetition operator
     *
     * @return ReDoSSeverity the highest ReDoS severity detected within this quantifier
     *                       and its child node
     *
     * @example
     * ```php
     * // For a pattern like `(a*)*` (critical ReDoS)
     * $quantifierNode->accept($visitor); // Will add a CRITICAL vulnerability and return ReDoSSeverity::CRITICAL
     *
     * // For a pattern like `a{10000}` (low ReDoS)
     * $quantifierNode->accept($visitor); // Will add a LOW vulnerability and return ReDoSSeverity::LOW
     * ```
     */
    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): ReDoSSeverity
    {
        // Save the current atomic state to restore it later
        $wasAtomic = $this->inAtomicGroup;
        $boundarySeparatedPrev = $this->hasMutuallyExclusiveBoundary($this->previousNode, $node->node);
        $boundarySeparatedNext = $this->hasForwardMutuallyExclusiveBoundary($node->node, $this->nextNode);
        $boundarySeparated = $boundarySeparatedPrev || $boundarySeparatedNext;

        $controlVerbShield = $this->hasTrailingBacktrackingControl($node->node);

        // If the quantifier is possessive (*+, ++), its content is implicitly atomic.
        // This means it does not backtrack, preventing ReDoS in nested structures.
        if (QuantifierType::T_POSSESSIVE === $node->type || $controlVerbShield) {
            $this->inAtomicGroup = true;
        }

        // If we are inside an atomic group (explicit or via possessive quantifier),
        // we visit the child without ReDoS checks (as backtracking is disabled),
        // then restore the state and return immediately.
        if ($this->inAtomicGroup) {
            $result = $node->node->accept($this);
            $this->inAtomicGroup = $wasAtomic; // Restore state is crucial here!

            return $controlVerbShield ? $this->reduceSeverity($result, ReDoSSeverity::LOW) : $result;
        }

        // --- Standard ReDoS logic for non-atomic quantifiers ---

        $this->totalQuantifierDepth++;
        $isUnbounded = $this->isUnbounded($node->quantifier);

        // Check if the immediate target is an atomic group (e.g., (? >...)+)
        $isTargetAtomic = $node->node instanceof Node\GroupNode && GroupType::T_GROUP_ATOMIC === $node->node->type;

        $severity = ReDoSSeverity::SAFE;
        $entersUnbounded = $isUnbounded && !$isTargetAtomic;
        $isNestedUnbounded = $entersUnbounded && $this->unboundedQuantifierDepth > 0;

        if ($entersUnbounded) {
            $this->unboundedQuantifierDepth++;

            if ($this->hasBackrefLoop($node->node)) {
                $this->backrefLoopDetected = true;
                $severity = ReDoSSeverity::CRITICAL;
                $this->addVulnerability(
                    ReDoSSeverity::CRITICAL,
                    'Unbounded quantifier combined with backreferences to variable-length captures can cause catastrophic backtracking.',
                    $node->quantifier,
                );
            }

            if ($isNestedUnbounded) {
                $severity = $boundarySeparated ? ReDoSSeverity::LOW : ReDoSSeverity::CRITICAL;
                if (!$boundarySeparated) {
                    $this->addVulnerability(
                        ReDoSSeverity::CRITICAL,
                        'Nested unbounded quantifiers detected. This allows exponential backtracking. Consider using atomic groups (?>...) or possessive quantifiers (*+, ++).',
                        $node->quantifier,
                    );
                }
            } else {
                $severity = $boundarySeparated ? ReDoSSeverity::LOW : ReDoSSeverity::MEDIUM;
                if (!$boundarySeparated) {
                    $this->addVulnerability(
                        ReDoSSeverity::MEDIUM,
                        'Unbounded quantifier detected. May cause backtracking on non-matching input. Consider making it possessive (*+) or using atomic groups (?>...).',
                        $node->quantifier,
                    );
                }
            }
        } else {
            if ($this->isLargeBounded($node->quantifier)) {
                $severity = ReDoSSeverity::LOW;
                $this->addVulnerability(
                    ReDoSSeverity::LOW,
                    'Large bounded quantifier detected (>1000). May cause slow matching. Consider reducing the upper bound.',
                    $node->quantifier,
                );
            } elseif ($this->totalQuantifierDepth > 1) {
                $severity = ReDoSSeverity::LOW;
                $this->addVulnerability(
                    ReDoSSeverity::LOW,
                    'Nested bounded quantifiers detected. May cause polynomial backtracking. Consider simplifying the pattern or using atomic groups (?>...).',
                    $node->quantifier,
                );
            }
        }

        $childPrevious = $this->previousNode;
        $childNext = $this->nextNode;
        $this->previousNode = null;
        $this->nextNode = null;
        $childSeverity = $node->node->accept($this);
        $this->previousNode = $childPrevious;
        $this->nextNode = $childNext;

        if ($entersUnbounded && !$boundarySeparated && ReDoSSeverity::HIGH === $childSeverity) {
            $severity = ReDoSSeverity::CRITICAL;
            $this->addVulnerability(
                ReDoSSeverity::CRITICAL,
                'Critical nesting of quantifiers detected (Star Height > 1). This is a classic ReDoS vulnerability. Refactor the pattern to avoid nested unbounded quantifiers over the same subpattern.',
                $node->quantifier,
            );
        }

        if ($entersUnbounded) {
            $this->unboundedQuantifierDepth--;
        }
        $this->totalQuantifierDepth--;

        // Restore state (just in case, though the early return handles the true case)
        $this->inAtomicGroup = $wasAtomic;

        return $this->maxSeverity($severity, $childSeverity);
    }

    /**
     * Visits an AlternationNode to detect ReDoS vulnerabilities in branching patterns.
     *
     * Purpose: This method checks for overlapping alternatives within an alternation
     * (e.g., `(a|a)*` or `(ab|a)*`) when nested inside an unbounded quantifier. Such patterns
     * can lead to catastrophic backtracking, resulting in a critical ReDoS vulnerability.
     * It also recursively analyzes each alternative.
     *
     * @param Node\AlternationNode $node the `AlternationNode` representing a choice between patterns
     *
     * @return ReDoSSeverity the highest ReDoS severity detected within this alternation
     *                       and its alternatives
     *
     * @example
     * ```php
     * // For a pattern like `(a|a)*` (critical ReDoS)
     * $alternationNode->accept($visitor); // Will add a CRITICAL vulnerability and return ReDoSSeverity::CRITICAL
     *
     * // For a pattern like `(ab|a)*` (critical ReDoS)
     * $alternationNode->accept($visitor); // Will add a CRITICAL vulnerability and return ReDoSSeverity::CRITICAL
     * ```
     */
    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): ReDoSSeverity
    {
        $max = ReDoSSeverity::SAFE;
        $previous = $this->previousNode;
        $next = $this->nextNode;

        if ($this->unboundedQuantifierDepth > 0 && $this->hasOverlappingAlternatives($node)) {
            $this->addVulnerability(
                ReDoSSeverity::CRITICAL,
                'Overlapping alternation branches inside a quantifier. e.g. (a|a)* or (ab|a)*. This can lead to catastrophic backtracking.',
                '|',
            );
            $max = ReDoSSeverity::CRITICAL;
        }

        foreach ($node->alternatives as $alt) {
            $this->previousNode = null;
            $this->nextNode = null;
            $max = $this->maxSeverity($max, $alt->accept($this));
        }

        $this->previousNode = $previous;
        $this->nextNode = $next;

        return $max;
    }

    /**
     * Visits a GroupNode, handling atomic groups for ReDoS analysis.
     *
     * Purpose: This method is crucial for correctly assessing ReDoS risks within groups.
     * It specifically identifies atomic groups (`(?>...)`), which disable backtracking
     * for their content. By setting `inAtomicGroup` to true, it ensures that any nested
     * quantifiers within an atomic group are not flagged as ReDoS vulnerabilities,
     * as their backtracking behavior is suppressed.
     *
     * @param Node\GroupNode $node the `GroupNode` representing a specific grouping construct
     *
     * @return ReDoSSeverity the highest ReDoS severity detected within the group's child node
     *
     * @example
     * ```php
     * // For an atomic group `(?>a*)`
     * $groupNode->accept($visitor); // Will set `inAtomicGroup` to true, preventing ReDoS detection inside.
     * ```
     */
    #[\Override]
    public function visitGroup(Node\GroupNode $node): ReDoSSeverity
    {
        $wasAtomic = $this->inAtomicGroup;
        $previous = $this->previousNode;
        $next = $this->nextNode;

        if (GroupType::T_GROUP_ATOMIC === $node->type) {
            $this->inAtomicGroup = true;
        }

        $this->previousNode = null;
        $this->nextNode = null;
        $severity = $node->child->accept($this);
        $this->previousNode = $previous;
        $this->nextNode = $next;

        $this->inAtomicGroup = $wasAtomic;

        return $severity;
    }

    /**
     * Visits a SequenceNode, propagating the maximum ReDoS severity from its children.
     *
     * Purpose: A sequence of regex elements (e.g., `abc`) itself does not introduce
     * new ReDoS vulnerabilities. This method simply ensures that any vulnerabilities
     * found in its constituent parts are carried forward, contributing to the overall
     * severity of the regex.
     *
     * @param Node\SequenceNode $node the `SequenceNode` representing a series of regex components
     *
     * @return ReDoSSeverity the highest ReDoS severity found among all child nodes in the sequence
     *
     * @example
     * ```php
     * // For a sequence `a(b+)*c`
     * $sequenceNode->accept($visitor); // Will return the max severity found in 'a', '(b+)*', and 'c'.
     * ```
     */
    #[\Override]
    public function visitSequence(Node\SequenceNode $node): ReDoSSeverity
    {
        $max = ReDoSSeverity::SAFE;
        $previous = $this->previousNode;
        $next = $this->nextNode;
        $last = null;
        $total = \count($node->children);
        foreach ($node->children as $index => $child) {
            $this->previousNode = $last;
            $this->nextNode = $index + 1 < $total ? $node->children[$index + 1] : null;
            $max = $this->maxSeverity($max, $child->accept($this));
            $last = $child;
        }

        $this->previousNode = $previous;
        $this->nextNode = $next;

        return $max;
    }

    /**
     * Visits a LiteralNode. Literal characters are inherently safe from ReDoS.
     *
     * Purpose: Literal characters (e.g., 'a', 'hello') match themselves directly
     * and do not involve any backtracking or repetition that could lead to ReDoS.
     * Therefore, this method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\LiteralNode $node the `LiteralNode` representing a literal character or string
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits a CharTypeNode. Character types are inherently safe from ReDoS.
     *
     * Purpose: Predefined character types (e.g., `\d`, `\s`, `\w`) match a single
     * character from a defined set and do not introduce backtracking issues on their own.
     * Therefore, this method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\CharTypeNode $node the `CharTypeNode` representing a predefined character type
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitCharType(Node\CharTypeNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits a DotNode. The wildcard dot is inherently safe from ReDoS.
     *
     * Purpose: The dot (`.`) matches any single character (except newline by default)
     * and does not introduce backtracking issues on its own. Therefore, this method
     * always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\DotNode $node the `DotNode` representing the wildcard dot character
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitDot(Node\DotNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits an AnchorNode. Anchors are inherently safe from ReDoS.
     *
     * Purpose: Positional anchors (e.g., `^`, `$`, `\b`) assert a position in the string
     * but do not consume characters or involve repetition. They are therefore safe from ReDoS.
     * This method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\AnchorNode $node the `AnchorNode` representing a positional anchor
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits an AssertionNode. Assertions are inherently safe from ReDoS.
     *
     * Purpose: Zero-width assertions (e.g., `\b`, `\A`) check for conditions without
     * consuming characters or involving repetition. They are therefore safe from ReDoS.
     * This method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\AssertionNode $node the `AssertionNode` representing a zero-width assertion
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitAssertion(Node\AssertionNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits a KeepNode. The `\K` assertion is inherently safe from ReDoS.
     *
     * Purpose: The `\K` assertion resets the starting point of the match but does not
     * consume characters or involve repetition in a way that leads to ReDoS.
     * This method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\KeepNode $node the `KeepNode` representing the `\K` assertion
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitKeep(Node\KeepNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits a CharClassNode. Character classes are inherently safe from ReDoS.
     *
     * Purpose: Character classes (e.g., `[a-z]`, `[^0-9]`) match a single character
     * from a defined set and do not introduce backtracking issues on their own.
     * Therefore, this method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\CharClassNode $node the `CharClassNode` representing a character class
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits a RangeNode. Character ranges are inherently safe from ReDoS.
     *
     * Purpose: Character ranges (e.g., `a-z` within a character class) match a single
     * character from a defined range and do not introduce backtracking issues on their own.
     * Therefore, this method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\RangeNode $node the `RangeNode` representing a character range
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitRange(Node\RangeNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits a BackrefNode. Backreferences are generally safe from ReDoS on their own.
     *
     * Purpose: Backreferences (e.g., `\1`, `\k<name>`) match previously captured text.
     * While they can be part of complex patterns that lead to ReDoS, the backreference
     * itself does not introduce the vulnerability. This method returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\BackrefNode $node the `BackrefNode` representing a backreference
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitBackref(Node\BackrefNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits a UnicodeNode. Unicode character escapes are inherently safe from ReDoS.
     *
     * Purpose: Unicode character escapes (e.g., `\x{2603}`) represent a single, specific
     * character and do not introduce backtracking or repetition issues.
     * This method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\UnicodeNode $node the `UnicodeNode` representing a Unicode character escape
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    #[\Override]
    public function visitUnicodeNamed(Node\UnicodeNamedNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits a UnicodePropNode. Unicode properties are inherently safe from ReDoS.
     *
     * Purpose: Unicode character properties (e.g., `\p{L}`) match a single character
     * based on its property and do not introduce backtracking or repetition issues on their own.
     * This method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\UnicodePropNode $node the `UnicodePropNode` representing a Unicode property
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitUnicodeProp(Node\UnicodePropNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits an OctalNode. Octal character escapes are inherently safe from ReDoS.
     *
     * Purpose: Octal character escapes (e.g., `\o{101}`) represent a single, specific
     * character and do not introduce backtracking or repetition issues.
     * This method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\OctalNode $node the `OctalNode` representing a modern octal escape
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitOctal(Node\OctalNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits an OctalLegacyNode. Legacy octal character escapes are inherently safe from ReDoS.
     *
     * Purpose: Legacy octal character escapes (e.g., `\012`) represent a single, specific
     * character and do not introduce backtracking or repetition issues.
     * This method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\OctalLegacyNode $node the `OctalLegacyNode` representing a legacy octal escape
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitOctalLegacy(Node\OctalLegacyNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits a PosixClassNode. POSIX character classes are inherently safe from ReDoS.
     *
     * Purpose: POSIX character classes (e.g., `[:alpha:]`) match a single character
     * from a defined set and do not introduce backtracking or repetition issues on their own.
     * This method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\PosixClassNode $node the `PosixClassNode` representing a POSIX character class
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits a CommentNode. Comments are ignored by the regex engine and are safe from ReDoS.
     *
     * Purpose: Comments within a regex (e.g., `(?#comment)`) do not affect the matching
     * behavior and thus cannot introduce ReDoS vulnerabilities.
     * This method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\CommentNode $node the `CommentNode` representing an inline comment
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitComment(Node\CommentNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits a PcreVerbNode. PCRE verbs are generally safe from ReDoS on their own.
     *
     * Purpose: PCRE control verbs (e.g., `(*FAIL)`, `(*COMMIT)`) influence the regex
     * engine's behavior but do not typically introduce ReDoS vulnerabilities directly.
     * This method always returns `ReDoSSeverity::SAFE`.
     *
     * @param Node\PcreVerbNode $node the `PcreVerbNode` representing a PCRE verb
     *
     * @return ReDoSSeverity always `ReDoSSeverity::SAFE`
     */
    #[\Override]
    public function visitPcreVerb(Node\PcreVerbNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits a ConditionalNode, propagating the maximum ReDoS severity from its branches.
     *
     * Purpose: Conditional patterns (e.g., `(?(condition)yes|no)`) introduce branching.
     * This method analyzes both the "if true" and "if false" branches recursively
     * and returns the highest ReDoS severity found in either path, as both are
     * potential execution paths for the regex engine.
     *
     * @param Node\ConditionalNode $node the `ConditionalNode` representing a conditional sub-pattern
     *
     * @return ReDoSSeverity the highest ReDoS severity found in either the 'yes' or 'no' branch
     *
     * @example
     * ```php
     * // For a conditional `(?(1)a*|b*)`
     * $conditionalNode->accept($visitor); // Will return the max severity of 'a*' and 'b*'.
     * ```
     */
    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): ReDoSSeverity
    {
        return $this->maxSeverity(
            $node->yes->accept($this),
            $node->no->accept($this),
        );
    }

    /**
     * Visits a SubroutineNode, flagging it as a potential medium ReDoS risk.
     *
     * Purpose: Subroutines (e.g., `(?&name)`) allow for recursive or repeated pattern calls.
     * While not inherently a critical ReDoS vulnerability on their own, they can
     * contribute to complex backtracking scenarios, especially when combined with quantifiers.
     * Therefore, they are flagged as a medium risk to encourage careful review.
     *
     * @param Node\SubroutineNode $node the `SubroutineNode` representing a subroutine call
     *
     * @return ReDoSSeverity always `ReDoSSeverity::MEDIUM`
     *
     * @example
     * ```php
     * // For a subroutine call `(?&my_pattern)`
     * $subroutineNode->accept($visitor); // Will return ReDoSSeverity::MEDIUM
     * ```
     */
    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): ReDoSSeverity
    {
        $this->addVulnerability(
            ReDoSSeverity::MEDIUM,
            'Subroutines can lead to complex backtracking and potential ReDoS if not used carefully, especially with recursion. Review the referenced pattern.',
            '(?&'.$node->reference.')',
        );

        return ReDoSSeverity::MEDIUM;
    }

    /**
     * Visits a DefineNode, analyzing its content for ReDoS vulnerabilities.
     *
     * Purpose: The `(?(DEFINE)...)` block is used to define named sub-patterns.
     * While the block itself doesn't match text, its content can contain patterns
     * that are later referenced by subroutines. Therefore, this method recursively
     * analyzes the content of the DEFINE block to detect any potential ReDoS risks
     * within the defined patterns.
     *
     * @param Node\DefineNode $node The `DefineNode` representing a `(?(DEFINE)...)` block.
     *
     * @return ReDoSSeverity the highest ReDoS severity found within the DEFINE block's content
     *
     * @example
     * ```php
     * // For a DEFINE block `(?(DEFINE)(?<digit>\d+))`
     * $defineNode->accept($visitor); // Will analyze `\d+` for ReDoS.
     * ```
     */
    #[\Override]
    public function visitDefine(Node\DefineNode $node): ReDoSSeverity
    {
        // Analyze the content of the DEFINE block for ReDoS vulnerabilities
        return $node->content->accept($this);
    }

    #[\Override]
    public function visitLimitMatch(Node\LimitMatchNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Visits a CalloutNode and treats it as neutral for ReDoS purposes.
     *
     * Callouts delegate to user code without changing the regex's matching language,
     * so they are considered safe in this static analysis.
     */
    #[\Override]
    public function visitCallout(Node\CalloutNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    /**
     * Checks if a given quantifier is unbounded (e.g., `*`, `+`, `{n,}`).
     *
     * Purpose: This helper method determines if a quantifier allows for an infinite
     * number of repetitions. This is a key factor in identifying potential ReDoS
     * vulnerabilities, as unbounded quantifiers are often involved in catastrophic backtracking.
     *
     * @param string $quantifier The quantifier string (e.g., `*`, `+`, `{1,5}`).
     *
     * @return bool true if the quantifier is unbounded, false otherwise
     */
    private function isUnbounded(string $quantifier): bool
    {
        if (str_contains($quantifier, '*') || str_contains($quantifier, '+')) {
            return true;
        }

        if (str_contains($quantifier, ',')) {
            // Check for {n,} (unbounded upper limit)
            return !preg_match('/,\d++\}$/', $quantifier);
        }

        return false;
    }

    /**
     * Checks if a given quantifier is bounded but allows for a very large number of
     * repetitions.
     *
     * Purpose: While not as dangerous as unbounded quantifiers, very large bounded quantifiers
     * (e.g., `{1,10000}`) can still lead to performance issues and potential denial of service
     * if the regex engine has to backtrack extensively. This method helps flag such cases.
     *
     * @param string $quantifier The quantifier string (e.g., `{1,5}`, `{1000}`).
     *
     * @return bool true if the quantifier is bounded and large, false otherwise
     */
    private function isLargeBounded(string $quantifier): bool
    {
        if (preg_match('/\{(\d++)(?:,(\d++))?\}/', $quantifier, $m)) {
            $max = isset($m[2]) ? (int) $m[2] : (int) $m[1];

            return $max > 1000;
        }

        return false;
    }

    /**
     * Determines if an AlternationNode contains overlapping alternatives.
     *
     * Purpose: This complex helper method detects a common ReDoS pattern where different
     * branches of an alternation can match the same prefix (e.g., `(ab|a)`). When such
     * an alternation is quantified, it can lead to exponential backtracking. It analyzes
     * the initial characters of each alternative using `CharSetAnalyzer` to find overlaps.
     *
     * @param Node\AlternationNode $node the `AlternationNode` to check for overlaps
     *
     * @return bool true if overlapping alternatives are found, false otherwise
     */
    private function hasOverlappingAlternatives(Node\AlternationNode $node): bool
    {
        $sets = [];

        foreach ($node->alternatives as $alt) {
            $set = $this->charSetAnalyzer->firstChars($alt);

            if ($set->isUnknown() || $this->startsWithDot($alt)) {
                if (!empty($sets)) {
                    return true;
                }
                $sets[] = $set;

                continue;
            }

            foreach ($sets as $existing) {
                if ($set->intersects($existing)) {
                    return true;
                }
            }

            $sets[] = $set;
        }

        return false;
    }

    /**
     * Generates a "signature" for the starting element of a node, used for overlap detection.
     *
     * Purpose: This helper method is used by `hasOverlappingAlternatives` to quickly
     * determine if two different regex branches start with a potentially overlapping pattern.
     * It currently distinguishes only the "match anything" prefix (dot) and delegates detailed character overlap
     * checks to `CharSetAnalyzer`.
     *
     * @param Node\NodeInterface $node the AST node to get the prefix signature for
     *
     * @return string A string representing the prefix signature (e.g., 'DOT' or empty if not applicable).
     */
    private function getPrefixSignature(Node\NodeInterface $node): string
    {
        if ($node instanceof Node\DotNode) {
            return 'DOT';
        }
        if ($node instanceof Node\SequenceNode && !empty($node->children)) {
            return $this->getPrefixSignature($node->children[0]);
        }
        if ($node instanceof Node\GroupNode) {
            return $this->getPrefixSignature($node->child);
        }
        if ($node instanceof Node\QuantifierNode) {
            return $this->getPrefixSignature($node->node);
        }

        return '';
    }

    private function startsWithDot(Node\NodeInterface $node): bool
    {
        return 'DOT' === $this->getPrefixSignature($node);
    }

    private function hasTrailingBacktrackingControl(Node\NodeInterface $node): bool
    {
        $verbNode = $this->extractTrailingVerb($node);
        if (null === $verbNode) {
            return false;
        }

        $verbName = strtoupper(explode(':', $verbNode->verb, 2)[0]);

        return \in_array($verbName, ['COMMIT', 'PRUNE', 'SKIP'], true);
    }

    private function extractTrailingVerb(Node\NodeInterface $node): ?Node\PcreVerbNode
    {
        if ($node instanceof Node\PcreVerbNode) {
            return $node;
        }

        if ($node instanceof Node\SequenceNode && !empty($node->children)) {
            $last = $node->children[\count($node->children) - 1];

            return $this->extractTrailingVerb($last);
        }

        if ($node instanceof Node\GroupNode) {
            return $this->extractTrailingVerb($node->child);
        }

        return null;
    }

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

    private function hasForwardMutuallyExclusiveBoundary(Node\NodeInterface $current, ?Node\NodeInterface $next): bool
    {
        if (null === $next) {
            return false;
        }

        $currentTail = $this->charSetAnalyzer->lastChars($current);
        $nextHead = $this->charSetAnalyzer->firstChars($next);

        if ($currentTail->isUnknown() || $nextHead->isUnknown()) {
            return false;
        }

        return !$currentTail->intersects($nextHead);
    }

    private function reduceSeverity(ReDoSSeverity $severity, ReDoSSeverity $cap): ReDoSSeverity
    {
        return $this->severityGreaterThan($severity, $cap) ? $cap : $severity;
    }

    /**
     * Adds a detected ReDoS vulnerability to the internal list.
     *
     * Purpose: This private helper method standardizes the way vulnerabilities are recorded.
     * It stores the severity, a descriptive message, and the specific pattern fragment
     * that triggered the detection, which is then used by `getResult()` to compile the final report.
     *
     * @param ReDoSSeverity $severity the severity level of the detected vulnerability
     * @param string        $message  a descriptive message explaining the vulnerability
     * @param string        $pattern  the regex pattern fragment that caused the vulnerability
     */
    private function addVulnerability(ReDoSSeverity $severity, string $message, string $pattern): void
    {
        $this->vulnerabilities[] = [
            'severity' => $severity,
            'message' => $message,
            'pattern' => $pattern,
        ];
    }

    /**
     * Compares two ReDoSSeverity values to check if the first is greater than
     * the second.
     *
     * Purpose: This helper method provides a consistent way to compare severity levels,
     * which are represented by an enum. It's used to determine the highest severity
     * encountered during the AST traversal.
     *
     * @param ReDoSSeverity $a the first severity level to compare
     * @param ReDoSSeverity $b the second severity level to compare
     *
     * @return bool true if severity `$a` is greater than severity `$b`, false otherwise
     */
    private function severityGreaterThan(ReDoSSeverity $a, ReDoSSeverity $b): bool
    {
        $levels = [
            ReDoSSeverity::SAFE->value => 0,
            ReDoSSeverity::LOW->value => 1,
            ReDoSSeverity::UNKNOWN->value => 2,
            ReDoSSeverity::MEDIUM->value => 3,
            ReDoSSeverity::HIGH->value => 4,
            ReDoSSeverity::CRITICAL->value => 5,
        ];

        return $levels[$a->value] > $levels[$b->value];
    }

    /**
     * Returns the higher of two ReDoSSeverity values.
     *
     * Purpose: This helper method simplifies finding the maximum severity level
     * when combining results from different parts of the AST.
     *
     * @param ReDoSSeverity $a the first severity level
     * @param ReDoSSeverity $b the second severity level
     *
     * @return ReDoSSeverity the higher of the two severity levels
     */
    private function maxSeverity(ReDoSSeverity $a, ReDoSSeverity $b): ReDoSSeverity
    {
        return $this->severityGreaterThan($a, $b) ? $a : $b;
    }

    /**
     * Detects if a subtree contains a backreference and a variable-length capturing group,
     * which can lead to catastrophic backtracking when repeated.
     */
    private function hasBackrefLoop(Node\NodeInterface $node): bool
    {
        $state = $this->analyzeBackrefLoop($node);

        return $state['hasBackref'] && $state['hasVariableCapture'];
    }

    /**
     * @return array{hasBackref: bool, hasVariableCapture: bool}
     */
    private function analyzeBackrefLoop(Node\NodeInterface $node): array
    {
        $hasBackref = $node instanceof Node\BackrefNode;
        $hasVariableCapture = false;

        if ($node instanceof Node\GroupNode && $this->isCapturingGroup($node)) {
            [$min, $max] = $this->lengthRange($node->child);
            if (null === $max || $min !== $max) {
                $hasVariableCapture = true;
            }
        }

        $children = match (true) {
            $node instanceof Node\SequenceNode => $node->children,
            $node instanceof Node\AlternationNode => $node->alternatives,
            $node instanceof Node\QuantifierNode => [$node->node],
            $node instanceof Node\GroupNode => [$node->child],
            $node instanceof Node\ConditionalNode => [$node->condition, $node->yes, $node->no],
            default => [],
        };

        foreach ($children as $child) {
            $childState = $this->analyzeBackrefLoop($child);
            $hasBackref = $hasBackref || $childState['hasBackref'];
            $hasVariableCapture = $hasVariableCapture || $childState['hasVariableCapture'];
        }

        return [
            'hasBackref' => $hasBackref,
            'hasVariableCapture' => $hasVariableCapture,
        ];
    }

    /**
     * @return array{0:int, 1:int|null}
     */
    private function lengthRange(Node\NodeInterface $node): array
    {
        if ($node instanceof Node\LiteralNode) {
            $len = \strlen($node->value);

            return [$len, $len];
        }

        if ($node instanceof Node\CharTypeNode
            || $node instanceof Node\DotNode
            || $node instanceof Node\CharClassNode
            || $node instanceof Node\RangeNode
            || $node instanceof Node\UnicodeNode
            || $node instanceof Node\UnicodePropNode
            || $node instanceof Node\OctalNode
            || $node instanceof Node\OctalLegacyNode
            || $node instanceof Node\PosixClassNode
        ) {
            return [1, 1];
        }

        if ($node instanceof Node\AnchorNode
            || $node instanceof Node\AssertionNode
            || $node instanceof Node\KeepNode
            || $node instanceof Node\PcreVerbNode
            || $node instanceof Node\CommentNode
            || $node instanceof Node\CalloutNode
        ) {
            return [0, 0];
        }

        if ($node instanceof Node\SequenceNode) {
            $min = 0;
            $max = 0;
            foreach ($node->children as $child) {
                [$cMin, $cMax] = $this->lengthRange($child);
                $min += $cMin;
                $max = null === $max || null === $cMax ? null : $max + $cMax;
            }

            return [$min, $max];
        }

        if ($node instanceof Node\AlternationNode) {
            $min = null;
            $max = 0;
            foreach ($node->alternatives as $child) {
                [$cMin, $cMax] = $this->lengthRange($child);
                $min = null === $min ? $cMin : min($min, $cMin);
                $max = null === $max || null === $cMax ? null : max($max, $cMax);
            }

            return [$min ?? 0, $max];
        }

        if ($node instanceof Node\GroupNode) {
            return $this->lengthRange($node->child);
        }

        if ($node instanceof Node\QuantifierNode) {
            [$cMin, $cMax] = $this->lengthRange($node->node);
            [$qMin, $qMax] = $this->quantifierBounds($node->quantifier);

            $min = $cMin * $qMin;
            $max = null === $cMax || null === $qMax ? null : $cMax * $qMax;

            return [$min, $max];
        }

        if ($node instanceof Node\BackrefNode || $node instanceof Node\SubroutineNode) {
            return [0, null];
        }

        return [0, null];
    }

    /**
     * @return array{0:int, 1:int|null}
     */
    private function quantifierBounds(string $quantifier): array
    {
        if ('*' === $quantifier) {
            return [0, null];
        }
        if ('+' === $quantifier) {
            return [1, null];
        }
        if ('?' === $quantifier) {
            return [0, 1];
        }

        if (preg_match('/^\\{(\\d++),(\\d++)\\}$/', $quantifier, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        if (preg_match('/^\\{(\\d++),\\}$/', $quantifier, $m)) {
            return [(int) $m[1], null];
        }

        if (preg_match('/^\\{(\\d++)\\}$/', $quantifier, $m)) {
            return [(int) $m[1], (int) $m[1]];
        }

        return [0, null];
    }

    private function isCapturingGroup(Node\GroupNode $group): bool
    {
        return \in_array($group->type, [
            GroupType::T_GROUP_CAPTURING,
            GroupType::T_GROUP_NAMED,
            GroupType::T_GROUP_BRANCH_RESET,
        ], true);
    }
}
