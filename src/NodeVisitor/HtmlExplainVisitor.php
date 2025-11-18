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

/**
 * A visitor that explains the AST in an HTML format for rich display.
 *
 * @implements NodeVisitorInterface<string>
 */
class HtmlExplainVisitor implements NodeVisitorInterface
{
    private const array CHAR_TYPE_MAP = [
        'd' => 'any digit (0-9)',
        'D' => 'any non-digit',
        's' => 'any whitespace character',
        'S' => 'any non-whitespace character',
        'w' => 'any "word" character (alphanumeric or _)',
        'W' => 'any "non-word" character',
        'h' => 'any horizontal whitespace',
        'H' => 'any non-horizontal whitespace',
        'v' => 'any vertical whitespace',
        'V' => 'any non-vertical whitespace',
        'R' => 'a generic newline (\\r\\n, \\r, or \\n)',
    ];

    private const array ANCHOR_MAP = [
        '^' => 'the start of the string (or line, with /m flag)',
        '$' => 'the end of the string (or line, with /m flag)',
    ];

    private const array ASSERTION_MAP = [
        'A' => 'the absolute start of the string',
        'z' => 'the absolute end of the string',
        'Z' => 'the end of the string (before final newline)',
        'G' => 'the position of the last successful match',
        'b' => 'a word boundary',
        'B' => 'a non-word boundary',
    ];

    public function visitRegex(RegexNode $node): string
    {
        $patternExplain = $node->pattern->accept($this);
        $flags = $node->flags ? $this->e(' (with flags: '.$node->flags.')') : '';

        return \sprintf(
            "<div class=\"regex-explain\">\n<strong>Regex matches%s:</strong>\n<ul>%s</ul>\n</div>",
            $flags,
            $patternExplain,
        );
    }

    public function visitAlternation(AlternationNode $node): string
    {
        $alts = array_map(
            fn (NodeInterface $alt) => $alt->accept($this),
            $node->alternatives,
        );

        return \sprintf(
            "<li><strong>EITHER:</strong>\n<ul>%s</ul>\n</li>",
            implode("\n<li><strong>OR:</strong>\n<ul>", $alts),
        );
    }

    public function visitSequence(SequenceNode $node): string
    {
        $parts = array_map(fn ($child) => $child->accept($this), $node->children);
        $parts = array_filter($parts, fn ($part) => '' !== $part);

        return implode("\n", $parts);
    }

    public function visitGroup(GroupNode $node): string
    {
        $childExplain = $node->child->accept($this);
        $type = match ($node->type) {
            GroupType::T_GROUP_CAPTURING => 'Start Capturing Group',
            GroupType::T_GROUP_NON_CAPTURING => 'Start Non-Capturing Group',
            GroupType::T_GROUP_NAMED => \sprintf("Start Capturing Group (named: '%s')", $this->e($node->name)),
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE => 'Start Positive Lookahead',
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE => 'Start Negative Lookahead',
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE => 'Start Positive Lookbehind',
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE => 'Start Negative Lookbehind',
            GroupType::T_GROUP_ATOMIC => 'Start Atomic Group',
            GroupType::T_GROUP_INLINE_FLAGS => \sprintf("Start Group (with flags: '%s')", $this->e($node->flags)),
        };

        return \sprintf(
            "<li><span title=\"%s\"><strong>%s:</strong></span>\n<ul>%s</ul>\n</li>",
            $this->e($type),
            $this->e($type),
            $childExplain,
        );
    }

    public function visitQuantifier(QuantifierNode $node): string
    {
        $childExplain = $node->node->accept($this);
        $quantExplain = $this->explainQuantifierValue($node->quantifier, $node->type);

        // If the child is simple (one line <li>), put it on one line.
        if (str_starts_with($childExplain, '<li>') && !str_contains(substr($childExplain, 4), '<li>')) {
            // Inject the quantifier explanation into the child's <li>
            return str_replace('<li>', \sprintf('<li>(%s) ', $this->e($quantExplain)), $childExplain);
        }

        // If the child is complex, wrap it
        return \sprintf(
            "<li><strong>Quantifier (%s):</strong>\n<ul>%s</ul>\n</li>",
            $this->e($quantExplain),
            $childExplain,
        );
    }

    public function visitLiteral(LiteralNode $node): string
    {
        $explanation = $this->explainLiteral($node->value);

        return \sprintf(
            '<li><span title="Literal: %s">Literal: <strong>%s</strong></span></li>',
            $this->e($explanation),
            $this->e($explanation),
        );
    }

    public function visitCharType(CharTypeNode $node): string
    {
        $explanation = self::CHAR_TYPE_MAP[$node->value] ?? 'unknown (\\'.$node->value.')';

        return \sprintf(
            '<li><span title="Character Type: %s">Character Type: <strong>\%s</strong> (%s)</span></li>',
            $this->e($explanation),
            $this->e($node->value),
            $this->e($explanation),
        );
    }

    public function visitDot(DotNode $node): string
    {
        $explanation = 'any character (except newline, unless /s flag is used)';

        return \sprintf(
            '<li><span title="%s">Wildcard: <strong>.</strong> (%s)</span></li>',
            $this->e($explanation),
            $this->e($explanation),
        );
    }

    public function visitAnchor(AnchorNode $node): string
    {
        $explanation = self::ANCHOR_MAP[$node->value] ?? $node->value;

        return \sprintf(
            '<li><span title="%s">Anchor: <strong>%s</strong> (%s)</span></li>',
            $this->e($explanation),
            $this->e($node->value),
            $this->e($explanation),
        );
    }

    public function visitAssertion(AssertionNode $node): string
    {
        $explanation = self::ASSERTION_MAP[$node->value] ?? '\\'.$node->value;

        return \sprintf(
            '<li><span title="%s">Assertion: <strong>\%s</strong> (%s)</span></li>',
            $this->e($explanation),
            $this->e($node->value),
            $this->e($explanation),
        );
    }

    public function visitKeep(KeepNode $node): string
    {
        $explanation = '\K (reset match start)';

        return \sprintf(
            '<li><span title="%s">Assertion: <strong>\K</strong> (%s)</span></li>',
            $this->e($explanation),
            $this->e($explanation),
        );
    }

    public function visitCharClass(CharClassNode $node): string
    {
        $neg = $node->isNegated ? '<strong>NOT</strong> ' : '';
        $parts = array_map(fn (NodeInterface $part) => $part->accept($this), $node->parts);

        // Char class parts are just strings, not <li>
        $parts = array_map(strip_tags(...), $parts);

        $explanation = \sprintf('any character %sin [ %s ]', $neg, implode(', ', $parts));

        return \sprintf(
            '<li><span title="%s">Character Class: [ %s%s ]</span></li>',
            $this->e(strip_tags($explanation)),
            $neg,
            $this->e(implode(', ', $parts)),
        );
    }

    public function visitRange(RangeNode $node): string
    {
        $start = ($node->start instanceof LiteralNode)
            ? $this->explainLiteral($node->start->value)
            : $node->start->accept($this);

        $end = ($node->end instanceof LiteralNode)
            ? $this->explainLiteral($node->end->value)
            : $node->end->accept($this);

        return \sprintf('Range: from %s to %s', $this->e($start), $this->e($end));
    }

    public function visitBackref(BackrefNode $node): string
    {
        $explanation = \sprintf('matches text from group "%s"', $node->ref);

        return \sprintf(
            '<li><span title="%s">Backreference: <strong>\%s</strong></span></li>',
            $this->e($explanation),
            $this->e($node->ref),
        );
    }

    public function visitUnicode(UnicodeNode $node): string
    {
        return \sprintf(
            '<li><span title="Unicode: %s">Unicode: <strong>%s</strong></span></li>',
            $this->e($node->code),
            $this->e($node->code),
        );
    }

    public function visitUnicodeProp(UnicodePropNode $node): string
    {
        $type = str_starts_with($node->prop, '^') ? 'non-matching' : 'matching';
        $prop = ltrim($node->prop, '^');
        $explanation = \sprintf('any character %s "%s"', $type, $prop);
        $prefix = str_starts_with($node->prop, '^') ? 'P' : 'p';

        return \sprintf(
            '<li><span title="%s">Unicode Property: <strong>\%s{%s}</strong></span></li>',
            $this->e($explanation),
            $prefix,
            $this->e($prop),
        );
    }

    public function visitOctal(OctalNode $node): string
    {
        return \sprintf(
            '<li><span title="Octal: %s">Octal: <strong>%s</strong></span></li>',
            $this->e($node->code),
            $this->e($node->code),
        );
    }

    public function visitOctalLegacy(OctalLegacyNode $node): string
    {
        return \sprintf(
            '<li><span title="Legacy Octal: %s">Legacy Octal: <strong>\%s</strong></span></li>',
            $this->e($node->code),
            $this->e($node->code),
        );
    }

    public function visitPosixClass(PosixClassNode $node): string
    {
        return \sprintf('POSIX Class: [[:%s:]]', $this->e($node->class));
    }

    public function visitComment(CommentNode $node): string
    {
        return \sprintf(
            '<li><span title="Comment" style="color: #888; font-style: italic;">Comment: %s</span></li>',
            $this->e($node->comment),
        );
    }

    public function visitConditional(ConditionalNode $node): string
    {
        $cond = $node->condition->accept($this);
        $yes = $node->yes->accept($this);
        
        // Check if the 'no' branch is an empty literal node
        $hasElseBranch = !($node->no instanceof LiteralNode && '' === $node->no->value);
        $no = $hasElseBranch ? $node->no->accept($this) : '';

        // Condition node will be a <li>, just need its text
        $condText = trim(strip_tags($cond));

        if ('' === $no || '<li></li>' === $no) {
            return \sprintf(
                "<li><strong>Conditional: IF</strong> (%s) <strong>THEN:</strong>\n<ul>%s</ul>\n</li>",
                $this->e($condText),
                $yes,
            );
        }

        return \sprintf(
            "<li><strong>Conditional: IF</strong> (%s) <strong>THEN:</strong>\n<ul>%s</ul>\n<strong>ELSE:</strong>\n<ul>%s</ul>\n</li>",
            $this->e($condText),
            $yes,
            $no,
        );
    }

    public function visitSubroutine(SubroutineNode $node): string
    {
        $ref = match ($node->reference) {
            'R' => 'the entire pattern',
            '0' => 'the entire pattern',
            default => 'group '.$this->e($node->reference),
        };
        $explanation = \sprintf('recurses to %s', $ref);

        return \sprintf(
            '<li><span title="%s">Subroutine Call: <strong>(%s%s)</strong></span></li>',
            $this->e($explanation),
            $this->e($node->syntax),
            $this->e($node->reference),
        );
    }

    public function visitPcreVerb(PcreVerbNode $node): string
    {
        return \sprintf(
            '<li><span title="PCRE Verb">PCRE Verb: <strong>(*%s)</strong></span></li>',
            $this->e($node->verb),
        );
    }

    private function explainQuantifierValue(string $q, QuantifierType $type): string
    {
        $desc = match ($q) {
            '*' => 'zero or more times',
            '+' => 'one or more times',
            '?' => 'zero or one time',
            default => preg_match('/^\{(\d+)(?:,(\d*))?\}$/', $q, $m) ?
                (isset($m[2]) ? ('' === $m[2] ?
                    \sprintf('at least %d times', $m[1]) :
                    \sprintf('between %d and %d times', $m[1], $m[2])
                ) :
                    \sprintf('exactly %d times', $m[1])
                ) :
                'with quantifier '.$q, // Fallback
        };

        $desc .= match ($type) {
            QuantifierType::T_LAZY => ' (as few as possible)',
            QuantifierType::T_POSSESSIVE => ' (and do not backtrack)',
            default => '',
        };

        return $desc;
    }

    private function explainLiteral(string $value): string
    {
        return match ($value) {
            ' ' => "' ' (space)",
            "\t" => "'\\t' (tab)",
            "\n" => "'\\n' (newline)",
            "\r" => "'\\r' (carriage return)",
            default => ctype_print($value) ? "'".$value."'" : '(non-printable char)',
        };
    }

    /**
     * Helper for HTML escaping.
     */
    private function e(?string $s): string
    {
        return htmlspecialchars((string) $s, \ENT_QUOTES, 'UTF-8');
    }
}
