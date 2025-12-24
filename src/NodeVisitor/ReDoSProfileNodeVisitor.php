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
use RegexParser\ReDoS\ReDoSConfidence;
use RegexParser\ReDoS\ReDoSFinding;
use RegexParser\ReDoS\ReDoSSeverity;

/**
 * Analyzes the AST to detect ReDoS vulnerabilities.
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
     * @var list<ReDoSFinding>
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
     * @return array{
     *     severity: ReDoSSeverity,
     *     recommendations: array<string>,
     *     vulnerablePattern: ?string,
     *     trigger: ?string,
     *     confidence: ?ReDoSConfidence,
     *     falsePositiveRisk: ?string,
     *     findings: array<ReDoSFinding>
     * }
     */
    public function getResult(): array
    {
        $maxSeverity = ReDoSSeverity::SAFE;
        $recommendations = [];
        $pattern = null;
        $trigger = null;
        $confidence = null;
        $falsePositiveRisk = null;

        foreach ($this->vulnerabilities as $vuln) {
            if ($this->severityGreaterThan($vuln->severity, $maxSeverity)) {
                $maxSeverity = $vuln->severity;
                $pattern = $vuln->pattern;
                $trigger = $vuln->trigger;
                $confidence = $vuln->confidence;
                $falsePositiveRisk = $vuln->falsePositiveRisk;
            }
            $recommendations[] = null !== $vuln->suggestedRewrite
                ? $vuln->message.' Suggested: '.$vuln->suggestedRewrite
                : $vuln->message;
        }

        if ($this->backrefLoopDetected) {
            $maxSeverity = $this->maxSeverity($maxSeverity, ReDoSSeverity::CRITICAL);
        }

        return [
            'severity' => $maxSeverity,
            'recommendations' => array_unique($recommendations),
            'vulnerablePattern' => $pattern,
            'trigger' => $trigger,
            'confidence' => $confidence,
            'falsePositiveRisk' => $falsePositiveRisk,
            'findings' => $this->vulnerabilities,
        ];
    }

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

    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): ReDoSSeverity
    {
        // Save the current atomic state to restore it later
        $wasAtomic = $this->inAtomicGroup;
        $boundarySeparatedPrev = $this->hasMutuallyExclusiveBoundary($this->previousNode, $node->node);
        $boundarySeparatedNext = $this->hasForwardMutuallyExclusiveBoundary($node->node, $this->nextNode);
        $boundarySeparated = $boundarySeparatedPrev || $boundarySeparatedNext;

        $controlVerbShield = $this->hasTrailingBacktrackingControl($node->node);
        $isPossessive = QuantifierType::T_POSSESSIVE === $node->type;

        // If the quantifier is possessive (*+, ++), its content is implicitly atomic.
        // This means it does not backtrack, preventing ReDoS in nested structures.
        if ($isPossessive || $controlVerbShield) {
            $this->inAtomicGroup = true;
        }

        // If we are inside an atomic group (explicit or via possessive quantifier),
        // we visit the child without ReDoS checks (as backtracking is disabled),
        // then restore the state and return immediately.
        if ($this->inAtomicGroup) {
            $result = $node->node->accept($this);
            $this->inAtomicGroup = $wasAtomic; // Restore state is crucial here!

            return $this->reduceSeverity($result, ReDoSSeverity::LOW);
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
                    $node,
                    'Use atomic groups (?>...) or possessive quantifiers around the quantified token.',
                    ReDoSConfidence::HIGH,
                    'Low false-positive risk; nested backtracking with backreferences is a known hotspot.',
                );
            }

            if ($isNestedUnbounded) {
                $severity = $boundarySeparated ? ReDoSSeverity::LOW : ReDoSSeverity::CRITICAL;
                if (!$boundarySeparated) {
                    $this->addVulnerability(
                        ReDoSSeverity::CRITICAL,
                        'Nested unbounded quantifiers detected. This allows exponential backtracking. Consider using atomic groups (?>...) or possessive quantifiers (*+, ++).',
                        $node,
                        'Replace inner quantifiers with possessive variants or wrap them in (?>...).',
                        ReDoSConfidence::HIGH,
                        'Low false-positive risk; nested unbounded quantifiers are a classic ReDoS pattern.',
                    );
                }
            } else {
                $severity = $boundarySeparated ? ReDoSSeverity::LOW : ReDoSSeverity::MEDIUM;
                if (!$boundarySeparated) {
                    $this->addVulnerability(
                        ReDoSSeverity::MEDIUM,
                        'Unbounded quantifier detected. May cause backtracking on non-matching input. Consider making it possessive (*+) or using atomic groups (?>...).',
                        $node,
                        'Consider using possessive quantifiers or atomic groups to limit backtracking.',
                        ReDoSConfidence::MEDIUM,
                        'Medium false-positive risk; depends on input distribution and surrounding tokens.',
                    );
                }
            }
        } else {
            if ($this->isLargeBounded($node->quantifier)) {
                $severity = ReDoSSeverity::LOW;
                $this->addVulnerability(
                    ReDoSSeverity::LOW,
                    'Large bounded quantifier detected (>1000). May cause slow matching. Consider reducing the upper bound.',
                    $node,
                    'Reduce the upper bound or pre-validate input length.',
                    ReDoSConfidence::LOW,
                    'High false-positive risk; bounded quantifiers may still be safe in context.',
                );
            } elseif ($this->totalQuantifierDepth > 1 && $this->unboundedQuantifierDepth == 0) {
                $severity = ReDoSSeverity::LOW;
                $this->addVulnerability(
                    ReDoSSeverity::LOW,
                    'Nested bounded quantifiers detected. May cause polynomial backtracking. Consider simplifying the pattern or using atomic groups (?>...).',
                    $node,
                    'Flatten nested quantifiers or introduce atomic groups.',
                    ReDoSConfidence::LOW,
                    'Medium false-positive risk; bounded quantifiers are often acceptable.',
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
                $node,
                'Use atomic groups or restructure the repetition to be deterministic.',
                ReDoSConfidence::HIGH,
                'Low false-positive risk; star-height > 1 patterns are highly suspect.',
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
                $node,
                'Make alternatives mutually exclusive or order longer alternatives first.',
                ReDoSConfidence::HIGH,
                'Low false-positive risk; overlapping alternations are a known backtracking trigger.',
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

    #[\Override]
    public function visitGroup(Node\GroupNode $node): ReDoSSeverity
    {
        $wasAtomic = $this->inAtomicGroup;
        $previous = $this->previousNode;
        $next = $this->nextNode;
        $isAtomicGroup = GroupType::T_GROUP_ATOMIC === $node->type;
        if ($isAtomicGroup) {
            $this->inAtomicGroup = true;
        }

        $this->previousNode = null;
        $this->nextNode = null;
        $severity = $node->child->accept($this);
        $this->previousNode = $previous;
        $this->nextNode = $next;

        $this->inAtomicGroup = $wasAtomic;

        return $isAtomicGroup ? $this->reduceSeverity($severity, ReDoSSeverity::LOW) : $severity;
    }

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

    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): ReDoSSeverity
    {
        return $this->maxSeverity(
            $node->yes->accept($this),
            $node->no->accept($this),
        );
    }

    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): ReDoSSeverity
    {
        $this->addVulnerability(
            ReDoSSeverity::MEDIUM,
            'Subroutines can lead to complex backtracking and potential ReDoS if not used carefully, especially with recursion. Review the referenced pattern.',
            $node,
            'Avoid excessive recursion or add atomic groups around recursive parts.',
            ReDoSConfidence::MEDIUM,
            'Medium false-positive risk; recursion depth and input shape matter.',
        );

        return ReDoSSeverity::MEDIUM;
    }

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
     */
    private function addVulnerability(
        ReDoSSeverity $severity,
        string $message,
        Node\NodeInterface $triggerNode,
        ?string $suggestedRewrite = null,
        ReDoSConfidence $confidence = ReDoSConfidence::MEDIUM,
        ?string $falsePositiveRisk = null,
    ): void {
        $pattern = $this->compileNode($triggerNode);
        $trigger = $this->describeTrigger($triggerNode);

        $this->vulnerabilities[] = new ReDoSFinding(
            $severity,
            $message,
            $pattern,
            $trigger,
            $suggestedRewrite,
            $confidence,
            $falsePositiveRisk,
        );
    }

    private function compileNode(Node\NodeInterface $node): string
    {
        return $node->accept(new CompilerNodeVisitor());
    }

    private function describeTrigger(Node\NodeInterface $node): string
    {
        return match (true) {
            $node instanceof Node\QuantifierNode => 'quantifier '.$node->quantifier,
            $node instanceof Node\AlternationNode => 'alternation',
            $node instanceof Node\GroupNode => 'group',
            $node instanceof Node\SubroutineNode => 'subroutine',
            default => $node::class,
        };
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

    private function hasUnboundedQuantifiers(Node\NodeInterface $node): bool
    {
        if ($node instanceof Node\QuantifierNode && $this->isUnbounded($node->quantifier)) {
            return true;
        }
        $children = match (true) {
            $node instanceof Node\SequenceNode => $node->children,
            $node instanceof Node\AlternationNode => $node->alternatives,
            $node instanceof Node\QuantifierNode => [$node->node],
            $node instanceof Node\GroupNode => [$node->child],
            default => [],
        };
        foreach ($children as $child) {
            if ($this->hasUnboundedQuantifiers($child)) {
                return true;
            }
        }
        return false;
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
            || $node instanceof Node\CharLiteralNode
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
