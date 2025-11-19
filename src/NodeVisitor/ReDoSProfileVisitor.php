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
use RegexParser\ReDoSSeverity;

/**
 * Analyzes the AST to detect ReDoS vulnerabilities.
 * Returns the maximum detected severity level for the visited node tree.
 *
 * @implements NodeVisitorInterface<ReDoSSeverity>
 */
final class ReDoSProfileVisitor implements NodeVisitorInterface
{
    private int $unboundedQuantifierDepth = 0;
    private array $vulnerabilities = [];
    // Track if we are inside an atomic group (which mitigates ReDoS)
    private bool $inAtomicGroup = false;

    /**
     * @return array{severity: ReDoSSeverity, recommendations: string[], vulnerablePattern: ?string}
     */
    public function getResult(): array
    {
        // Calculate max severity found
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
            'severity'          => $maxSeverity,
            'recommendations'   => array_unique($recommendations),
            'vulnerablePattern' => $pattern,
        ];
    }

    public function visitRegex(RegexNode $node): ReDoSSeverity
    {
        $this->unboundedQuantifierDepth = 0;
        $this->vulnerabilities = [];
        $this->inAtomicGroup = false;

        return $node->pattern->accept($this);
    }

    public function visitQuantifier(QuantifierNode $node): ReDoSSeverity
    {
        if ($this->inAtomicGroup || $node->type === QuantifierType::T_POSSESSIVE) {
            // Atomic grouping prevents backtracking, so ReDoS is mitigated here
            return $node->node->accept($this);
        }

        $isUnbounded = $this->isUnbounded($node->quantifier);
        $severity = ReDoSSeverity::SAFE;

        if ($isUnbounded) {
            $this->unboundedQuantifierDepth++;

            if ($this->unboundedQuantifierDepth > 1) {
                // Nested unbounded quantifier detected: (a+)+
                $severity = ReDoSSeverity::HIGH;
                $this->addVulnerability(
                    ReDoSSeverity::HIGH,
                    'Nested unbounded quantifiers detected. This allows exponential backtracking.',
                    $node->quantifier
                );
            } elseif ($this->unboundedQuantifierDepth === 1) {
                $severity = ReDoSSeverity::MEDIUM;
            }
        } else {
            // Bounded but potentially large
            if ($this->isLargeBounded($node->quantifier)) {
                $severity = ReDoSSeverity::LOW;
            }
        }

        // Check child
        $childSeverity = $node->node->accept($this);

        // Check for "Star Height > 1" logic (simplified)
        if ($isUnbounded && $childSeverity === ReDoSSeverity::HIGH) {
            $severity = ReDoSSeverity::CRITICAL;
            $this->addVulnerability(
                ReDoSSeverity::CRITICAL,
                'Critical nesting of quantifiers detected (Star Height > 1).',
                $node->quantifier
            );
        }

        if ($isUnbounded) {
            $this->unboundedQuantifierDepth--;
        }

        return $this->maxSeverity($severity, $childSeverity);
    }

    public function visitAlternation(AlternationNode $node): ReDoSSeverity
    {
        $max = ReDoSSeverity::SAFE;

        // Check for overlapping alternatives if we are inside a quantifier
        // e.g. (a|a)*
        if ($this->unboundedQuantifierDepth > 0 && $this->hasOverlappingAlternatives($node)) {
            $this->addVulnerability(
                ReDoSSeverity::CRITICAL,
                'Overlapping alternation branches inside a quantifier. e.g. (a|a)*',
                '|'
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

        if ($node->type === GroupType::T_GROUP_ATOMIC) {
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

    // --- Leaf nodes usually return SAFE unless they contain logic ---

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
            $node->no->accept($this)
        );
    }

    public function visitSubroutine(SubroutineNode $node): ReDoSSeverity
    {
        // Recursion is technically unbounded but usually depth-limited by PCRE engine settings.
        // However, infinite recursion is possible.
        return ReDoSSeverity::MEDIUM;
    }

    // --- Helpers ---

    private function isUnbounded(string $quantifier): bool
    {
        return str_contains($quantifier, '*')
            || str_contains($quantifier, '+')
            || (str_contains($quantifier, ',') && str_ends_with($quantifier, '}')); // {n,}
    }

    private function isLargeBounded(string $quantifier): bool
    {
        if (preg_match('/\{(\d+)(?:,(\d+))?\}/', $quantifier, $m)) {
            $max = isset($m[2]) ? (int)$m[2] : (int)$m[1];

            return $max > 1000; // Arbitrary threshold for "Large"
        }

        return false;
    }

    private function hasOverlappingAlternatives(AlternationNode $node): bool
    {
        // Naive overlap check: if alternatives start with the same literal/type
        // This is a heuristic. A real check would require a DFA intersection.
        $prefixes = [];
        foreach ($node->alternatives as $alt) {
            $prefix = $this->getPrefixSignature($alt);
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
        if ($node instanceof SequenceNode && !empty($node->children)) {
            return $this->getPrefixSignature($node->children[0]);
        }
        if ($node instanceof GroupNode) {
            return $this->getPrefixSignature($node->child);
        }

        return uniqid(); // Unknown/Complex, assume unique
    }

    private function addVulnerability(ReDoSSeverity $severity, string $message, string $pattern): void
    {
        $this->vulnerabilities[] = [
            'severity' => $severity,
            'message'  => $message,
            'pattern'  => $pattern,
        ];
    }

    private function severityGreaterThan(ReDoSSeverity $a, ReDoSSeverity $b): bool
    {
        $levels = [
            ReDoSSeverity::SAFE->value     => 0,
            ReDoSSeverity::LOW->value      => 1,
            ReDoSSeverity::MEDIUM->value   => 2,
            ReDoSSeverity::HIGH->value     => 3,
            ReDoSSeverity::CRITICAL->value => 4,
        ];

        return $levels[$a->value] > $levels[$b->value];
    }

    private function maxSeverity(ReDoSSeverity $a, ReDoSSeverity $b): ReDoSSeverity
    {
        return $this->severityGreaterThan($a, $b) ? $a : $b;
    }
}
