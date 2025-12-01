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
     * @var array<array{severity: ReDoSSeverity, message: string, pattern: string}>
     */
    private array $vulnerabilities = [];

    private bool $inAtomicGroup = false;

    /**
     * @return array{severity: ReDoSSeverity, recommendations: array<string>, vulnerablePattern: ?string}
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

    public function visitRegex(RegexNode $node): ReDoSSeverity
    {
        $this->unboundedQuantifierDepth = 0;
        $this->totalQuantifierDepth = 0;
        $this->vulnerabilities = [];
        $this->inAtomicGroup = false;

        return $node->pattern->accept($this);
    }

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

    public function visitSequence(SequenceNode $node): ReDoSSeverity
    {
        $max = ReDoSSeverity::SAFE;
        foreach ($node->children as $child) {
            $max = $this->maxSeverity($max, $child->accept($this));
        }

        return $max;
    }

    public function visitLiteral(LiteralNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitCharType(CharTypeNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitDot(DotNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitAnchor(AnchorNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitAssertion(AssertionNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitKeep(KeepNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitCharClass(CharClassNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitRange(RangeNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitBackref(BackrefNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitUnicode(UnicodeNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitUnicodeProp(UnicodePropNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitOctal(OctalNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitOctalLegacy(OctalLegacyNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitPosixClass(PosixClassNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitComment(CommentNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitPcreVerb(PcreVerbNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::SAFE;
    }

    public function visitConditional(ConditionalNode $node): ReDoSSeverity
    {
        return $this->maxSeverity(
            $node->yes->accept($this),
            $node->no->accept($this),
        );
    }

    public function visitSubroutine(SubroutineNode $node): ReDoSSeverity
    {
        return ReDoSSeverity::MEDIUM;
    }

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
