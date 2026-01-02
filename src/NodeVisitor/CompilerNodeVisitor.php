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
use RegexParser\Node\CalloutNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\ClassOperationNode;
use RegexParser\Node\ClassOperationType;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\ControlCharNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LimitMatchNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\ScriptRunNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\Node\VersionConditionNode;

/**
 * High-performance compiler that recompiles regex AST back into optimized strings.
 *
 * This optimized visitor provides intelligent compilation with caching and
 * streamlined string building for maximum performance while maintaining
 * full PCRE compatibility.
 *
 * @extends AbstractNodeVisitor<string>
 */
final class CompilerNodeVisitor extends AbstractNodeVisitor
{
    // Optimized meta-character sets for fast lookups
    private const META_CHARACTERS = [
        '\\' => true, '.' => true, '^' => true, '$' => true,
        '[' => true, ']' => true, '(' => true, ')' => true,
        '|' => true, '*' => true, '+' => true, '?' => true, '{' => true, '}' => true,
    ];

    private const CHAR_CLASS_META = [
        '\\' => true, ']' => true, '-' => true, '^' => true, '[' => true,
    ];

    // Intelligent delimiter mapping cache
    /**
     * @var array<string, string>
     */
    private static array $delimiterCache = [];

    // Minimal state tracking
    private bool $inCharClass = false;

    private string $delimiter = '/';

    private string $closingDelimiter = '/';

    private string $flags = '';

    private int $indentLevel;

    public function __construct(private readonly bool $pretty = false, /**
     * When true, comments in extended (/x) mode are collapsed to a generic
     * "(?#...)" placeholder. This is useful for generating a normalized
     * representation of verbose regexes without leaking full comment text.
     */
        private readonly bool $collapseExtendedComments = false)
    {
        $this->indentLevel = 0;
    }

    public function resetState(): void
    {
        $this->inCharClass = false;
        $this->delimiter = '/';
        $this->closingDelimiter = '/';
        $this->flags = '';
        $this->indentLevel = 0;
    }

    #[\Override]
    public function visitRegex(RegexNode $node): string
    {
        $this->delimiter = $node->delimiter;
        $this->flags = $node->flags;
        $this->closingDelimiter = $this->getClosingDelimiter($node->delimiter);

        return $node->delimiter.$node->pattern->accept($this).$this->closingDelimiter.$node->flags;
    }

    #[\Override]
    public function visitAlternation(AlternationNode $node): string
    {
        // Optimized: direct compilation without array_map overhead
        $alternatives = $node->alternatives;
        if ([] === $alternatives) {
            return '';
        }

        if ($this->inCharClass) {
            $result = $this->compileCharClassNode($alternatives[0], $alternatives[1] ?? null);
            for ($i = 1, $count = \count($alternatives); $i < $count; $i++) {
                $result .= $this->compileCharClassNode($alternatives[$i], $alternatives[$i + 1] ?? null);
            }

            return $result;
        }

        if ($this->pretty) {
            $result = $alternatives[0]->accept($this);
            for ($i = 1, $count = \count($alternatives); $i < $count; $i++) {
                $this->indentLevel++;
                $alt = $alternatives[$i]->accept($this);
                $this->indentLevel--;
                $result .= "\n".str_repeat(' ', $this->indentLevel * 4).'| '.$alt;
            }

            return $result;
        }

        $separator = '|';
        $result = $alternatives[0]->accept($this);

        for ($i = 1, $count = \count($alternatives); $i < $count; $i++) {
            $result .= $separator.$alternatives[$i]->accept($this);
        }

        return $result;
    }

    #[\Override]
    public function visitSequence(SequenceNode $node): string
    {
        // Optimized: direct compilation without array_map overhead
        $children = $node->children;
        if ([] === $children) {
            return '';
        }

        if ($this->inCharClass) {
            $result = $this->compileCharClassNode($children[0], $children[1] ?? null);
            for ($i = 1, $count = \count($children); $i < $count; $i++) {
                $result .= $this->compileCharClassNode($children[$i], $children[$i + 1] ?? null);
            }

            return $result;
        }

        $result = $children[0]->accept($this);

        for ($i = 1, $count = \count($children); $i < $count; $i++) {
            $result .= $children[$i]->accept($this);
        }

        return $result;
    }

    #[\Override]
    public function visitGroup(GroupNode $node): string
    {
        $flags = $node->flags ?? '';

        if ($this->pretty) {
            $opening = match ($node->type) {
                GroupType::T_GROUP_CAPTURING => '(',
                GroupType::T_GROUP_NON_CAPTURING => '(?:',
                GroupType::T_GROUP_NAMED => $node->usePythonSyntax
                    ? '(?P<'.$node->name.'>'
                    : '(?<'.$node->name.'>',
                GroupType::T_GROUP_LOOKAHEAD_POSITIVE => '(?=',
                GroupType::T_GROUP_LOOKAHEAD_NEGATIVE => '(?!',
                GroupType::T_GROUP_LOOKBEHIND_POSITIVE => '(?<=',
                GroupType::T_GROUP_LOOKBEHIND_NEGATIVE => '(?<!',
                GroupType::T_GROUP_ATOMIC => '(?>',
                GroupType::T_GROUP_BRANCH_RESET => '(?|',
                GroupType::T_GROUP_INLINE_FLAGS => '(?'.$flags.':',
            };
            $closing = ')';
            $this->indentLevel++;
            $child = $node->child->accept($this);
            $this->indentLevel--;
            $indent = str_repeat(' ', $this->indentLevel * 4);

            return $indent.$opening."\n".$child."\n".$indent.$closing;
        }

        $child = $node->child->accept($this);

        return match ($node->type) {
            GroupType::T_GROUP_CAPTURING => '('.$child.')',
            GroupType::T_GROUP_NON_CAPTURING => '(?:'.$child.')',
            GroupType::T_GROUP_NAMED => $node->usePythonSyntax
                ? '(?P<'.$node->name.'>'.$child.')'
                : '(?<'.$node->name.'>'.$child.')',
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE => '(?='.$child.')',
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE => '(?!'.$child.')',
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE => '(?<='.$child.')',
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE => '(?<!'.$child.')',
            GroupType::T_GROUP_ATOMIC => '(?>'.$child.')',
            GroupType::T_GROUP_BRANCH_RESET => '(?|'.$child.')',
            GroupType::T_GROUP_INLINE_FLAGS => '' === $child ? '(?'.$flags.')' : '(?'.$flags.':'.$child.')',
        };
    }

    #[\Override]
    public function visitQuantifier(QuantifierNode $node): string
    {
        $nodeCompiled = $node->node->accept($this);

        if ($node->node instanceof SequenceNode || $node->node instanceof AlternationNode) {
            $nodeCompiled = '(?:'.$nodeCompiled.')';
        }

        $suffix = match ($node->type) {
            QuantifierType::T_LAZY => '?',
            QuantifierType::T_POSSESSIVE => '+',
            default => '',
        };

        $quantifier = $this->normalizeQuantifier($node->quantifier);

        return $nodeCompiled.$quantifier.$suffix;
    }

    #[\Override]
    public function visitLiteral(LiteralNode $node): string
    {
        $value = $node->value;

        // Fast path for empty strings
        if ('' === $value) {
            return '';
        }

        // Raw literals should not be escaped (used for regex syntax characters)
        if ($node->isRaw) {
            return $value;
        }

        // Special case for closing bracket outside char class
        if (!$this->inCharClass && ']' === $value && ']' !== $this->closingDelimiter) {
            return $value;
        }

        // Intelligent escaping with optimized character processing
        return $this->escapeString($value);
    }

    #[\Override]
    public function visitDot(DotNode $node): string
    {
        return '.';
    }

    #[\Override]
    public function visitAnchor(AnchorNode $node): string
    {
        return $node->value;
    }

    #[\Override]
    public function visitAssertion(AssertionNode $node): string
    {
        return '\\'.$node->value;
    }

    #[\Override]
    public function visitCharType(CharTypeNode $node): string
    {
        return '\\'.$node->value;
    }

    #[\Override]
    public function visitKeep(KeepNode $node): string
    {
        return '\K';
    }

    #[\Override]
    public function visitCharClass(CharClassNode $node): string
    {
        $wasInCharClass = $this->inCharClass;
        $this->inCharClass = true;

        try {
            $negation = $node->isNegated ? '^' : '';

            return '['.$negation.$node->expression->accept($this).']';
        } finally {
            $this->inCharClass = $wasInCharClass;
        }
    }

    #[\Override]
    public function visitRange(RangeNode $node): string
    {
        return $node->start->accept($this).'-'.$node->end->accept($this);
    }

    #[\Override]
    public function visitBackref(BackrefNode $node): string
    {
        if (ctype_digit($node->ref)) {
            return '\\'.$node->ref;
        }

        return $node->ref;
    }

    #[\Override]
    public function visitCharLiteral(CharLiteralNode $node): string
    {
        $rep = $node->originalRepresentation;

        // If it's already an escape sequence, return as is
        if (str_starts_with($rep, '\\')) {
            return $rep;
        }

        // If it's a single character, check if it needs escaping
        if (1 === \strlen($rep)) {
            $ord = \ord($rep);
            if ($ord < 32 || 127 === $ord || $ord >= 128) {
                // Escape control characters and extended ASCII
                return match ($ord) {
                    9 => '\\t',
                    10 => '\\n',
                    13 => '\\r',
                    12 => '\\f',
                    27 => '\\e',
                    default => '\\x'.strtoupper(str_pad(dechex($ord), 2, '0', \STR_PAD_LEFT)),
                };
            }

            return $this->escapeString($rep);
        }

        return $rep;
    }

    #[\Override]
    public function visitClassOperation(ClassOperationNode $node): string
    {
        return $node->left->accept($this).(ClassOperationType::INTERSECTION === $node->type ? '&&' : '--').$node->right->accept($this);
    }

    #[\Override]
    public function visitControlChar(ControlCharNode $node): string
    {
        return '\\c'.$node->char;
    }

    #[\Override]
    public function visitScriptRun(ScriptRunNode $node): string
    {
        return '(*script_run:'.$node->script.')';
    }

    #[\Override]
    public function visitVersionCondition(VersionConditionNode $node): string
    {
        return '(?(VERSION'.$node->operator.$node->version.')';
    }

    #[\Override]
    public function visitUnicodeProp(UnicodePropNode $node): string
    {
        $prop = $node->hasBraces ? trim($node->prop, '{}') : $node->prop;

        if ($node->hasBraces || \strlen($prop) > 1 || str_starts_with($prop, '^')) {
            return '\p{'.$prop.'}';
        }

        return '\p'.$prop;

    }

    #[\Override]
    public function visitPosixClass(PosixClassNode $node): string
    {
        return '[[:'.$node->class.':]]';
    }

    #[\Override]
    public function visitComment(CommentNode $node): string
    {
        $isExtended = str_contains($this->flags, 'x');

        // In normalized mode, collapse all extended (/x) comments to a
        // lightweight inline placeholder so that we preserve structure
        // without leaking (or reflowing) the original comment text.
        if ($this->collapseExtendedComments && $isExtended) {
            return '(?#...)';
        }

        // Extended (/x) mode line comments (starting with '#') should be
        // preserved as real /x comments, not rewritten into (?#...) blocks.
        // We still indent them when pretty-printing so they line up with
        // surrounding constructs, but we keep the original "# ..." text and
        // trailing newline intact.
        if ($isExtended && str_starts_with($node->comment, '#')) {
            if ($this->pretty) {
                $indent = str_repeat(' ', $this->indentLevel * 4);
                $lines = explode("\n", rtrim($node->comment, "\n"));
                $formatted = [];
                foreach ($lines as $line) {
                    $formatted[] = $indent.$line;
                }

                return implode("\n", $formatted)."\n";
            }

            return $node->comment;
        }

        // Multi-line inline comments from (?# ... ) are rendered as a block of
        // "# "-prefixed lines for readability when pretty-printing. This is
        // only used outside of extended mode so we don't change semantics.
        if ($this->pretty && str_contains($node->comment, "\n")) {
            $indent = str_repeat(' ', $this->indentLevel * 4);
            $lines = explode("\n", rtrim($node->comment, "\n"));
            $formatted = [];
            foreach ($lines as $line) {
                $formatted[] = $indent.'# '.$line;
            }

            return implode("\n", $formatted)."\n";
        }

        // Single-line inline comments that already start with '#' can be
        // indented in pretty mode for nicer alignment.
        if ($this->pretty && str_starts_with($node->comment, '#')) {
            $indent = str_repeat(' ', $this->indentLevel * 4);

            return $indent.$node->comment;
        }

        // Inline comments (?#...) keep their original content without the
        // delimiters and are reconstructed using standard PCRE syntax.
        return '(?#'.$node->comment.')';
    }

    #[\Override]
    public function visitConditional(ConditionalNode $node): string
    {
        if ($node->condition instanceof BackrefNode) {
            $cond = $node->condition->ref;
        } else {
            $cond = $node->condition->accept($this);
        }

        $yes = $node->yes->accept($this);
        $no = $node->no->accept($this);

        if ($this->pretty) {
            $indent = str_repeat(' ', $this->indentLevel * 4);
            if ('' === $no) {
                return $indent.'(?('.$cond.")\n".$yes."\n".$indent.')';
            }

            return $indent.'(?('.$cond.")\n".$yes."\n".$indent.'|'.$no."\n".$indent.')';
        }

        if ('' === $no) {
            return '(?('.$cond.')'.$yes.')';
        }

        return '(?('.$cond.')'.$yes.'|'.$no.')';
    }

    #[\Override]
    public function visitSubroutine(SubroutineNode $node): string
    {
        return match ($node->syntax) {
            '&' => '(?&'.$node->reference.')',
            'P>' => '(?P>'.$node->reference.')',
            'g' => '\g<'.$node->reference.'>',
            default => '(?'.$node->reference.')',
        };
    }

    #[\Override]
    public function visitPcreVerb(PcreVerbNode $node): string
    {
        return '(*'.$node->verb.')';
    }

    #[\Override]
    public function visitDefine(DefineNode $node): string
    {
        if ($this->pretty) {
            $this->indentLevel++;
            $content = $node->content->accept($this);
            $this->indentLevel--;
            $indent = str_repeat(' ', $this->indentLevel * 4);

            return $indent."(?(DEFINE)\n".$content."\n".$indent.')';
        }

        return '(?(DEFINE)'.$node->content->accept($this).')';
    }

    #[\Override]
    public function visitLimitMatch(LimitMatchNode $node): string
    {
        return '(*LIMIT_MATCH='.$node->limit.')';
    }

    #[\Override]
    public function visitCallout(CalloutNode $node): string
    {
        if (null === $node->identifier) {
            return '(?C)';
        }

        if (\is_int($node->identifier)) {
            return '(?C'.$node->identifier.')';
        }

        if (
            !$node->isStringIdentifier
            && \is_string($node->identifier)
            && preg_match('/^[A-Z_a-z]\w*+$/', $node->identifier)
        ) {
            return '(?C'.$node->identifier.')';
        }

        return '(?C"'.$node->identifier.'")';
    }

    private function normalizeQuantifier(string $quantifier): string
    {
        return preg_replace('/\\s+/', '', $quantifier) ?? $quantifier;
    }

    /**
     * Intelligent delimiter mapping with caching.
     */
    private function getClosingDelimiter(string $delimiter): string
    {
        if (!isset(self::$delimiterCache[$delimiter])) {
            self::$delimiterCache[$delimiter] = match ($delimiter) {
                '(' => ')',
                '[' => ']',
                '{' => '}',
                '<' => '>',
                default => $delimiter,
            };
        }

        return self::$delimiterCache[$delimiter];
    }

    /**
     * High-performance string escaping with minimal allocations.
     */
    private function escapeString(string $value): string
    {
        // Check if the entire value is a valid quantifier pattern - don't escape it
        if (!$this->inCharClass && preg_match('/^\{\d+(?:,\d*)?\}$/', $value)) {
            return $value;
        }

        $meta = $this->inCharClass ? self::CHAR_CLASS_META : self::META_CHARACTERS;
        $escapeExtended = str_contains($this->flags, 'x') && !$this->inCharClass;
        $needsEscape = false;

        // Fast pre-scan to check if escaping is needed
        $len = \strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $char = $value[$i];
            $ord = \ord($char);
            if (
                $char === $this->delimiter
                || $char === $this->closingDelimiter
                || isset($meta[$char])
                || ($escapeExtended && (' ' === $char || '#' === $char))
                || $ord < 32
                || 127 === $ord
                || $ord >= 128
            ) {
                $needsEscape = true;

                break;
            }
        }

        // Fast path: no escaping needed
        if (!$needsEscape) {
            return $value;
        }

        // Optimized escaping with single pass
        $result = '';
        for ($i = 0; $i < $len; $i++) {
            $char = $value[$i];
            if (
                $char === $this->delimiter
                || $char === $this->closingDelimiter
                || isset($meta[$char])
                || ($escapeExtended && (' ' === $char || '#' === $char))
            ) {
                $result .= '\\'.$char;
            } elseif (\ord($char) < 32 || 127 === \ord($char) || \ord($char) >= 128) {
                // Escape control characters and extended ASCII
                $result .= match (\ord($char)) {
                    8 => $this->inCharClass ? '\\b' : '\\x08', // Backspace: \b only valid inside char class
                    9 => '\\t',
                    10 => '\\n',
                    13 => '\\r',
                    12 => '\\f',
                    27 => '\\e',
                    default => '\\x'.strtoupper(str_pad(dechex(\ord($char)), 2, '0', \STR_PAD_LEFT)),
                };
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    private function compileCharClassNode(NodeInterface $node, ?NodeInterface $next): string
    {
        if ($node instanceof LiteralNode && '[' === $node->value) {
            return $this->shouldEscapeCharClassOpen($next) ? '\\[' : '[';
        }

        if ($node instanceof RangeNode) {
            $start = $node->start;
            $startCompiled = $start instanceof LiteralNode && '[' === $start->value
                ? '['
                : $start->accept($this);

            return $startCompiled.'-'.$node->end->accept($this);
        }

        return $node->accept($this);
    }

    private function shouldEscapeCharClassOpen(?NodeInterface $next): bool
    {
        if (!$next instanceof LiteralNode) {
            return false;
        }

        return \in_array($next->value, [':', '.', '='], true);
    }
}
