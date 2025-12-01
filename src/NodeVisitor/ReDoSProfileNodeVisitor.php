<?php

declare(strict_types=1);

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with the source code.
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
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\OctalLegacyNode;
use RegexParser\Node\OctalNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\ReDoS\ReDoSSeverity;

/**
 * Analyzes the AST to detect ReDoS vulnerabilities.
 * Returns the maximum detected severity level for the visited node tree.
 *
 * Purpose: This visitor is designed to identify potential Regular Expression Denial of Service (ReDoS)
 * vulnerabilities within a given regex pattern. It traverses the Abstract Syntax Tree (AST)
 * and applies a set of heuristics to detect patterns that could lead to exponential or
 * polynomial backtracking, which can be exploited to cause a denial of service.
 * It categorizes risks into different severity levels (SAFE, LOW, MEDIUM, HIGH, CRITICAL)
 * and provides recommendations for mitigation.
 *
 * @implements NodeVisitorInterface<ReDoSSeverity>
 */
class ReDoSProfileNodeVisitor implements NodeVisitorInterface
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

    /**
     * Retrieves the aggregated ReDoS analysis result after visiting the AST.
     *
     * Purpose: After the visitor has traversed the entire regex AST, this method compiles
     * all detected vulnerabilities into a single, comprehensive report. It determines
     * the highest severity found and collects all unique recommendations, providing
     * a clear summary of the ReDoS risk and how to address it.
     *
     * @return array{severity: ReDoSSeverity, recommendations: array<string>, vulnerablePattern: ?string}
     *         An associative array containing:
     *         - 'severity': The highest `ReDoSSeverity` level detected.
     *         - 'recommendations': An array of unique strings describing the detected issues and
     *                              potential mitigations.
     *         - 'vulnerablePattern': The specific regex pattern fragment that triggered the highest
     *                                severity, if any.
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
     * @param RegexNode $node The `RegexNode` representing the entire regular expression.
     *
     * @return ReDoSSeverity The highest ReDoS severity found within the regex pattern.
     */
    public function visitRegex(RegexNode $node): ReDoSSeverity
    {
        $this->unboundedQuantifierDepth = 0;
        $this->totalQuantifierDepth = 0;
        $this->vulnerabilities = [];
        $this->inAtomicGroup = false;

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
     * @param QuantifierNode $node The `QuantifierNode` representing a repetition operator.
     *
     * @return ReDoSSeverity The highest ReDoS severity detected within this quantifier
     *                       and its child node.
     */
    public function visitQuantifier(QuantifierNode $node): ReDoSSeverity
    {
        // Save the current atomic state to restore it later
        $wasAtomic = $this->inAtomicGroup;

        // If the quantifier is possessive (*+, ++), its content is implicitly atomic.
        // This means it does not backtrack, preventing ReDoS in nested structures.
        if (QuantifierType::T_POSSESSIVE === $node->type) {
            $this->inAtomicGroup = true;
        }

        // If we are inside an atomic group (explicit or via possessive quantifier),
        // we visit the child without ReDoS checks (as backtracking is disabled),
        // then restore the state and return immediately.
        if ($this->inAtomicGroup) {
            $result = $node->node->accept($this);
            $this->inAtomicGroup = $wasAtomic; // Restore state is crucial here!

            return $result;
        }

        // --- Standard ReDoS logic for non-atomic quantifiers ---

        $this->totalQuantifierDepth++;
        $isUnbounded = $this->isUnbounded($node->quantifier);

        // Check if the immediate target is an atomic group (e.g., (? >...)+)
        $isTargetAtomic = $node->node instanceof GroupNode && GroupType::T_GROUP_ATOMIC === $node->node->type;

        $severity = ReDoSSeverity::SAFE;

        if ($isUnbounded && !$isTargetAtomic) {
            $this->unboundedQuantifierDepth++;

            if ($this->unboundedQuantifierDepth > 1) {
                $severity = ReDoSSeverity::HIGH;
                $this->addVulnerability(
                    ReDoSSeverity::HIGH,
                    'Nested unbounded quantifiers detected. This allows exponential backtracking.',
                    $node->quantifier,
                );
            } else {
                $severity = ReDoSSeverity::MEDIUM;
                $this->addVulnerability(
                    ReDoSSeverity::MEDIUM,
                    'Unbounded quantifier detected. May cause backtracking on non-matching input.',
                    $node->quantifier,
                );
            }
        } else {
            if ($this->isLargeBounded($node->quantifier)) {
                $severity = ReDoSSeverity::LOW;
                $this->addVulnerability(
                    ReDoSSeverity::LOW,
                    'Large bounded quantifier detected (>1000). May cause slow matching.',
                    $node->quantifier,
                );
            } elseif ($this->totalQuantifierDepth > 1) {
                $severity = ReDoSSeverity::LOW;
                $this->addVulnerability(
                    ReDoSSeverity::LOW,
                    'Nested bounded quantifiers detected. May cause polynomial backtracking.',
                    $node->quantifier,
                );
            }
        }

        $childSeverity = $node->node->accept($this);

        if ($isUnbounded && !$isTargetAtomic && ReDoSSeverity::HIGH === $childSeverity) {
            $severity = ReDoSSeverity::CRITICAL;
            $this->addVulnerability(
                ReDoSSeverity::CRITICAL,
                'Critical nesting of quantifiers detected (Star Height > 1).',
                $node->quantifier,
            );
        }

        if ($isUnbounded && !$isTargetAtomic) {
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
     * @param AlternationNode $node The `AlternationNode` representing a choice between patterns.
     *
     * @return ReDoSSeverity The highest ReDoS severity detected within this alternation
     *                       and its alternatives.
     */
    public function visitAlternation(AlternationNode $node): ReDoSSeverity
    {
        $max = ReDoSSeverity::SAFE;

        if ($this->unboundedQuantifierDepth > 0 && $this->hasOverlappingAlternatives($node)) {
            $this->addVulnerability(
                ReDoSSeverity::CRITICAL,
                'Overlapping alternation branches inside a quantifier. e.g. (a|a)*',
                '|',
            );
            $max = ReDoSSeverity::CRITICAL;
        }

        foreach ($node->alternatives as $alt) {
            $max = $this->maxSeverity($max, $alt->accept($this));
        }

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
     * @param GroupNode $node The `GroupNode` representing a specific grouping construct.
     *
     * @return ReDoSSeverity The highest ReDoS severity detected within the group's child node.
     */
    public function visitGroup(GroupNode $node): ReDoSSeverity
    {
        $wasAtomic = $this->inAtomicGroup;

        if (GroupType::T_GROUP_ATOMIC === $node->type) {
            $this->inAtomicGroup = true;
        }

        $severity = $node->child->accept($this);

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
     * @param SequenceNode $node The `SequenceNode` representing a series of regex components.
     *
     * @return ReDoSSeverity The highest ReDoS severity found among all child nodes in the sequence.
     */
    public function visitSequence(SequenceNode $node): ReDoSSeverity
    {
        $max = ReDoSSeverity::SAFE;
        foreach ($node->children as $child) {
            $max = $this->maxSeverity($max, $child->accept($this));
        }

        return $max;
    }

    /**
     * Visits a LiteralNode. Literal characters are inherently safe from ReDoS.
     *
     * Purpose: Literal characters (e.g., 'a', 'hello') match themselves directly
     * and do not involve any backtracking or repetition that could lead to ReDoS.
     * Therefore, this method always returns `ReDoSSeverity::SAFE`.
     *
     * @param LiteralNode $node The `LiteralNode` representing a literal character or string.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitLiteral(LiteralNode $node): ReDoSSeverity
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
     * @param CharTypeNode $node The `CharTypeNode` representing a predefined character type.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitCharType(CharTypeNode $node): ReDoSSeverity
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
     * @param DotNode $node The `DotNode` representing the wildcard dot character.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitDot(DotNode $node): ReDoSSeverity
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
     * @param AnchorNode $node The `AnchorNode` representing a positional anchor.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitAnchor(AnchorNode $node): ReDoSSeverity
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
     * @param AssertionNode $node The `AssertionNode` representing a zero-width assertion.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitAssertion(AssertionNode $node): ReDoSSeverity
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
     * @param KeepNode $node The `KeepNode` representing the `\K` assertion.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitKeep(KeepNode $node): ReDoSSeverity
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
     * @param CharClassNode $node The `CharClassNode` representing a character class.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitCharClass(CharClassNode $node): ReDoSSeverity
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
     * @param RangeNode $node The `RangeNode` representing a character range.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitRange(RangeNode $node): ReDoSSeverity
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
     * @param BackrefNode $node The `BackrefNode` representing a backreference.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitBackref(BackrefNode $node): ReDoSSeverity
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
     * @param UnicodeNode $node The `UnicodeNode` representing a Unicode character escape.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitUnicode(UnicodeNode $node): ReDoSSeverity
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
     * @param UnicodePropNode $node The `UnicodePropNode` representing a Unicode property.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitUnicodeProp(UnicodePropNode $node): ReDoSSeverity
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
     * @param OctalNode $node The `OctalNode` representing a modern octal escape.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitOctal(OctalNode $node): ReDoSSeverity
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
     * @param OctalLegacyNode $node The `OctalLegacyNode` representing a legacy octal escape.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitOctalLegacy(OctalLegacyNode $node): ReDoSSeverity
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
     * @param PosixClassNode $node The `PosixClassNode` representing a POSIX character class.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitPosixClass(PosixClassNode $node): ReDoSSeverity
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
     * @param CommentNode $node The `CommentNode` representing an inline comment.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitComment(CommentNode $node): ReDoSSeverity
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
     * @param PcreVerbNode $node The `PcreVerbNode` representing a PCRE verb.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::SAFE`.
     */
    public function visitPcreVerb(PcreVerbNode $node): ReDoSSeverity
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
     * @param ConditionalNode $node The `ConditionalNode` representing a conditional sub-pattern.
     *
     * @return ReDoSSeverity The highest ReDoS severity found in either the 'yes' or 'no' branch.
     */
    public function visitConditional(ConditionalNode $node): ReDoSSeverity
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
     * @param SubroutineNode $node The `SubroutineNode` representing a subroutine call.
     *
     * @return ReDoSSeverity Always `ReDoSSeverity::MEDIUM`.
     */
    public function visitSubroutine(SubroutineNode $node): ReDoSSeverity
    {
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
     * @param DefineNode $node The `DefineNode` representing a `(?(DEFINE)...)` block.
     *
     * @return ReDoSSeverity The highest ReDoS severity found within the DEFINE block's content.
     */
    public function visitDefine(DefineNode $node): ReDoSSeverity
    {
        // Analyze the content of the DEFINE block for ReDoS vulnerabilities
        return $node->content->accept($this);
    }

    private function isUnbounded(string $quantifier): bool
    {
        if (str_contains($quantifier, '*') || str_contains($quantifier, '+')) {
            return true;
        }

        if (str_contains($quantifier, ',')) {
            return !preg_match('/,\d+\}$/', $quantifier);
        }

        return false;
    }

    private function isLargeBounded(string $quantifier): bool
    {
        if (preg_match('/\{(\d+)(?:,(\d+))?\}/', $quantifier, $m)) {
            $max = isset($m[2]) ? (int) $m[2] : (int) $m[1];

            return $max > 1000;
        }

        return false;
    }

    private function hasOverlappingAlternatives(AlternationNode $node): bool
    {
        $prefixes = [];
        $hasDot = false;
        $hasCharClass = false;

        foreach ($node->alternatives as $alt) {
            $prefix = $this->getPrefixSignature($alt);

            if ('DOT' === $prefix) {
                if ($hasDot || !empty($prefixes) || $hasCharClass) {
                    return true;
                }
                $hasDot = true;

                continue;
            }

            if ('CLASS' === $prefix) {
                if ($hasDot || $hasCharClass || !empty($prefixes)) {
                    return true;
                }
                $hasCharClass = true;

                continue;
            }

            if ($hasDot || $hasCharClass) {
                return true;
            }

            if ($prefix && isset($prefixes[$prefix])) {
                return true;
            }
            $prefixes[$prefix] = true;
        }

        return false;
    }

    private function getPrefixSignature(NodeInterface $node): string
    {
        if ($node instanceof LiteralNode) {
            return 'L:'.$node->value;
        }
        if ($node instanceof CharTypeNode) {
            return 'T:'.$node->value;
        }
        if ($node instanceof DotNode) {
            return 'DOT';
        }
        if ($node instanceof CharClassNode) {
            return 'CLASS';
        }
        if ($node instanceof SequenceNode && !empty($node->children)) {
            return $this->getPrefixSignature($node->children[0]);
        }
        if ($node instanceof GroupNode) {
            return $this->getPrefixSignature($node->child);
        }

        return uniqid();
    }

    private function addVulnerability(ReDoSSeverity $severity, string $message, string $pattern): void
    {
        $this->vulnerabilities[] = [
            'severity' => $severity,
            'message' => $message,
            'pattern' => $pattern,
        ];
    }

    private function severityGreaterThan(ReDoSSeverity $a, ReDoSSeverity $b): bool
    {
        $levels = [
            ReDoSSeverity::SAFE->value => 0,
            ReDoSSeverity::LOW->value => 1,
            ReDoSSeverity::MEDIUM->value => 2,
            ReDoSSeverity::HIGH->value => 3,
            ReDoSSeverity::CRITICAL->value => 4,
        ];

        return $levels[$a->value] > $levels[$b->value];
    }

    private function maxSeverity(ReDoSSeverity $a, ReDoSSeverity $b): ReDoSSeverity
    {
        return $this->severityGreaterThan($a, $b) ? $a : $b;
    }
}
