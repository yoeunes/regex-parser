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

use RegexParser\LiteralSet;
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
 * Extracts literal strings that must appear in any match.
 *
 * This visitor analyzes the regex AST to identify fixed strings
 * that are guaranteed to appear in every possible match. These
 * literals can be used for fast-path optimizations (e.g. strpos check).
 *
 * @implements NodeVisitorInterface<LiteralSet>
 */
class LiteralExtractorNodeVisitor implements NodeVisitorInterface
{
    /**
     * Maximum number of literals generated to prevent explosion (e.g. [a-z]{10}).
     */
    private const int MAX_LITERALS_COUNT = 128;

    private bool $caseInsensitive = false;

    public function visitRegex(RegexNode $node): LiteralSet
    {
        $this->caseInsensitive = str_contains($node->flags, 'i');

        return $node->pattern->accept($this);
    }

    public function visitAlternation(AlternationNode $node): LiteralSet
    {
        $result = null;

        foreach ($node->alternatives as $alt) {
            /** @var LiteralSet $altSet */
            $altSet = $alt->accept($this);

            if (null === $result) {
                $result = $altSet;
            } else {
                $result = $result->unite($altSet);
            }

            // Safety valve for memory
            if (\count($result->prefixes) > self::MAX_LITERALS_COUNT) {
                return LiteralSet::empty();
            }
        }

        return $result ?? LiteralSet::empty();
    }

    public function visitSequence(SequenceNode $node): LiteralSet
    {
        $result = LiteralSet::fromString(''); // Start with empty complete string

        foreach ($node->children as $child) {
            /** @var LiteralSet $childSet */
            $childSet = $child->accept($this);
            $result = $result->concat($childSet);

            // Safety valve
            if (\count($result->prefixes) > self::MAX_LITERALS_COUNT) {
                return LiteralSet::empty();
            }
        }

        return $result;
    }

    public function visitGroup(GroupNode $node): LiteralSet
    {
        // Handle inline flags if present
        $previousState = $this->caseInsensitive;
        if ($node->flags) {
            if (str_contains($node->flags, '-i')) {
                $this->caseInsensitive = false;
            } elseif (str_contains($node->flags, 'i')) {
                $this->caseInsensitive = true;
            }
        }

        /** @var LiteralSet $result */
        $result = $node->child->accept($this);

        // Restore state
        $this->caseInsensitive = $previousState;

        return $result;
    }

    public function visitQuantifier(QuantifierNode $node): LiteralSet
    {
        // Case 1: Exact quantifier {n} -> repeat literals n times
        if (preg_match('/^\{(\d+)\}$/', $node->quantifier, $m)) {
            $count = (int) $m[1];
            if (0 === $count) {
                return LiteralSet::fromString(''); // Matches empty string
            }

            /** @var LiteralSet $childSet */
            $childSet = $node->node->accept($this);

            // Repeat concatenation
            $result = $childSet;
            for ($i = 1; $i < $count; $i++) {
                $result = $result->concat($childSet);
                if (\count($result->prefixes) > self::MAX_LITERALS_COUNT) {
                    return LiteralSet::empty();
                }
            }

            return $result;
        }

        // Case 2: + or {n,} (At least 1)
        // We can extract the literal from the node, but it's not complete anymore because of the tail
        if ('+' === $node->quantifier || preg_match('/^\{(\d+),/', $node->quantifier)) {
            /** @var LiteralSet $childSet */
            $childSet = $node->node->accept($this);

            // The literal is present at least once, but followed by unknown quantity.
            // So suffixes are lost, completeness is lost.
            return new LiteralSet($childSet->prefixes, [], false);
        }

        // Case 3: * or ? (Optional)
        // Cannot guarantee presence.
        return LiteralSet::empty();
    }

    public function visitLiteral(LiteralNode $node): LiteralSet
    {
        if ($this->caseInsensitive) {
            return $this->expandCaseInsensitive($node->value);
        }

        return LiteralSet::fromString($node->value);
    }

    public function visitCharClass(CharClassNode $node): LiteralSet
    {
        // Optimization: Single character class [a] is literal 'a'
        if (!$node->isNegated && 1 === \count($node->parts) && $node->parts[0] instanceof LiteralNode) {
            return $this->visitLiteral($node->parts[0]);
        }

        // [abc] is effectively an alternation a|b|c
        // We only handle simple literals inside char classes for now to avoid complexity
        if (!$node->isNegated) {
            $literals = [];
            foreach ($node->parts as $part) {
                if ($part instanceof LiteralNode) {
                    if ($this->caseInsensitive) {
                        $expanded = $this->expandCaseInsensitive($part->value);
                        array_push($literals, ...$expanded->prefixes);
                    } else {
                        $literals[] = $part->value;
                    }
                } else {
                    // Range, char type, etc. -> considered non-literal for simplicity
                    return LiteralSet::empty();
                }
            }

            return new LiteralSet($literals, $literals, true); // Complete single char match
        }

        return LiteralSet::empty();
    }

    // --- The following nodes interrupt literal sequences ---

    public function visitCharType(CharTypeNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    public function visitDot(DotNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    public function visitAnchor(AnchorNode $node): LiteralSet
    {
        // Anchors match empty strings, so they are "complete" empty matches
        // This allows /^abc/ to return prefix 'abc'
        return LiteralSet::fromString('');
    }

    public function visitAssertion(AssertionNode $node): LiteralSet
    {
        return LiteralSet::fromString('');
    }

    public function visitKeep(KeepNode $node): LiteralSet
    {
        return LiteralSet::fromString('');
    }

    public function visitRange(RangeNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    public function visitBackref(BackrefNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    public function visitUnicode(UnicodeNode $node): LiteralSet
    {
        // Could resolve hex, but for now treat as empty set unless we decode it
        // Assuming RegexParser doesn't decode unicode in AST yet (it keeps raw \xHH)
        return LiteralSet::empty();
    }

    public function visitUnicodeProp(UnicodePropNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    public function visitOctal(OctalNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    public function visitOctalLegacy(OctalLegacyNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    public function visitPosixClass(PosixClassNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    public function visitComment(CommentNode $node): LiteralSet
    {
        return LiteralSet::fromString('');
    }

    public function visitConditional(ConditionalNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    public function visitSubroutine(SubroutineNode $node): LiteralSet
    {
        return LiteralSet::empty();
    }

    public function visitPcreVerb(PcreVerbNode $node): LiteralSet
    {
        return LiteralSet::fromString('');
    }

    public function visitDefine(DefineNode $node): LiteralSet
    {
        // DEFINE blocks don't produce any literal matches
        return LiteralSet::empty();
    }

    /**
     * Generates case variants. Currently limited to basic ASCII for performance/simplicity.
     * Only expands if resulting set size is small.
     */
    private function expandCaseInsensitive(string $value): LiteralSet
    {
        // Limit expansion length
        if (\strlen($value) > 8) {
            return LiteralSet::empty(); // Too expensive to compute permutations
        }

        $results = [''];
        for ($i = 0; $i < \strlen($value); $i++) {
            $char = $value[$i];
            $lower = strtolower($char);
            $upper = strtoupper($char);

            $nextResults = [];
            foreach ($results as $prefix) {
                if ($lower === $upper) {
                    $nextResults[] = $prefix.$char;
                } else {
                    $nextResults[] = $prefix.$lower;
                    $nextResults[] = $prefix.$upper;
                }
            }
            $results = $nextResults;
        }

        if (\count($results) > self::MAX_LITERALS_COUNT) {
            return LiteralSet::empty();
        }

        return new LiteralSet($results, $results, true);
    }
}
