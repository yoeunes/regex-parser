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
        '\\' => true, ']' => true, '-' => true, '^' => true,
    ];

    // Intelligent delimiter mapping cache
    /**
     * @var array<string, string>
     */
    private static array $delimiterCache = [];

    // Minimal state tracking
    private bool $inCharClass = false;

    private string $delimiter = '/';

    private string $flags = '';

    #[\Override]
    public function visitRegex(Node\RegexNode $node): string
    {
        $this->delimiter = $node->delimiter;
        $this->flags = $node->flags;
        $closingDelimiter = $this->getClosingDelimiter($node->delimiter);

        return $node->delimiter.$node->pattern->accept($this).$closingDelimiter.$node->flags;
    }

    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): string
    {
        // Optimized: direct compilation without array_map overhead
        $alternatives = $node->alternatives;
        if ([] === $alternatives) {
            return '';
        }

        $separator = $this->inCharClass ? '' : '|';
        $result = $alternatives[0]->accept($this);

        for ($i = 1, $count = \count($alternatives); $i < $count; $i++) {
            $result .= $separator.$alternatives[$i]->accept($this);
        }

        return $result;
    }

    #[\Override]
    public function visitSequence(Node\SequenceNode $node): string
    {
        // Optimized: direct compilation without array_map overhead
        $children = $node->children;
        if ([] === $children) {
            return '';
        }

        $result = $children[0]->accept($this);

        for ($i = 1, $count = \count($children); $i < $count; $i++) {
            $result .= $children[$i]->accept($this);
        }

        return $result;
    }

    #[\Override]
    public function visitGroup(Node\GroupNode $node): string
    {
        $child = $node->child->accept($this);
        $flags = $node->flags ?? '';

        return match ($node->type) {
            GroupType::T_GROUP_CAPTURING => '('.$child.')',
            GroupType::T_GROUP_NON_CAPTURING => '(?:'.$child.')',
            GroupType::T_GROUP_NAMED => '(?<'.$node->name.'>'.$child.')',
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
    public function visitQuantifier(Node\QuantifierNode $node): string
    {
        $nodeCompiled = $node->node->accept($this);

        if ($node->node instanceof Node\SequenceNode || $node->node instanceof Node\AlternationNode) {
            $nodeCompiled = '(?:'.$nodeCompiled.')';
        }

        $suffix = match ($node->type) {
            Node\QuantifierType::T_LAZY => '?',
            Node\QuantifierType::T_POSSESSIVE => '+',
            default => '',
        };

        return $nodeCompiled.$node->quantifier.$suffix;
    }

    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): string
    {
        $value = $node->value;

        // Fast path for empty strings
        if ('' === $value) {
            return '';
        }

        // Special case for closing bracket outside char class
        if (!$this->inCharClass && ']' === $value) {
            return $value;
        }

        // Intelligent escaping with optimized character processing
        return $this->escapeString($value);
    }

    #[\Override]
    public function visitDot(Node\DotNode $node): string
    {
        return '.';
    }

    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): string
    {
        return $node->value;
    }

    #[\Override]
    public function visitAssertion(Node\AssertionNode $node): string
    {
        return '\\'.$node->value;
    }

    #[\Override]
    public function visitCharType(Node\CharTypeNode $node): string
    {
        return '\\'.$node->value;
    }

    #[\Override]
    public function visitKeep(Node\KeepNode $node): string
    {
        return '\K';
    }

    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): string
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
    public function visitRange(Node\RangeNode $node): string
    {
        return $node->start->accept($this).'-'.$node->end->accept($this);
    }

    #[\Override]
    public function visitBackref(Node\BackrefNode $node): string
    {
        if (ctype_digit($node->ref)) {
            return '\\'.$node->ref;
        }

        return $node->ref;
    }

    #[\Override]
    public function visitCharLiteral(Node\CharLiteralNode $node): string
    {
        $rep = $node->originalRepresentation;

        // If it's already an escape sequence, return as is
        if (str_starts_with($rep, '\\')) {
            return $rep;
        }

        // If it's a single character, check if it needs escaping
        if (1 === \strlen($rep)) {
            $ord = \ord($rep);
            if ($ord < 32 || 127 === $ord || ($ord >= 128 && $ord <= 255)) {
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
        }

        return $rep;
    }

    #[\Override]
    public function visitClassOperation(Node\ClassOperationNode $node): string
    {
        return $node->left->accept($this).(Node\ClassOperationType::INTERSECTION === $node->type ? '&&' : '--').$node->right->accept($this);
    }

    #[\Override]
    public function visitControlChar(Node\ControlCharNode $node): string
    {
        return '\\c'.$node->char;
    }

    #[\Override]
    public function visitScriptRun(Node\ScriptRunNode $node): string
    {
        return '(*script_run:'.$node->script.')';
    }

    #[\Override]
    public function visitVersionCondition(Node\VersionConditionNode $node): string
    {
        return '(?(VERSION'.$node->operator.$node->version.')';
    }

    #[\Override]
    public function visitUnicodeProp(Node\UnicodePropNode $node): string
    {
        if (str_starts_with($node->prop, '^')) {
            return '\p{'.$node->prop.'}';
        }

        if (\strlen($node->prop) > 1) {
            return '\p{'.$node->prop.'}';
        }

        return '\p'.$node->prop;
    }

    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): string
    {
        return '[[:'.$node->class.':]]';
    }

    #[\Override]
    public function visitComment(Node\CommentNode $node): string
    {
        // For extended (/x) patterns, we may have captured full line comments
        // starting with '#' and ending at a newline. In that case, emit the
        // comment verbatim so that formatting and positions are preserved.
        if (str_contains($this->flags, 'x') && str_starts_with($node->comment, '#')) {
            return $node->comment;
        }

        // Inline comments (?#...) keep their original content without the
        // delimiters and are reconstructed using standard PCRE syntax.
        return '(?#'.$node->comment.')';
    }

    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): string
    {
        if ($node->condition instanceof Node\BackrefNode) {
            $cond = $node->condition->ref;
        } else {
            $cond = $node->condition->accept($this);
        }

        $yes = $node->yes->accept($this);
        $no = $node->no->accept($this);
        if ('' === $no) {
            return '(?('.$cond.')'.$yes.')';
        }

        return '(?('.$cond.')'.$yes.'|'.$no.')';
    }

    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): string
    {
        return match ($node->syntax) {
            '&' => '(?&'.$node->reference.')',
            'P>' => '(?P>'.$node->reference.')',
            'g' => '\g<'.$node->reference.'>',
            default => '(?'.$node->reference.')',
        };
    }

    #[\Override]
    public function visitPcreVerb(Node\PcreVerbNode $node): string
    {
        return '(*'.$node->verb.')';
    }

    #[\Override]
    public function visitDefine(Node\DefineNode $node): string
    {
        return '(?(DEFINE)'.$node->content->accept($this).')';
    }

    #[\Override]
    public function visitLimitMatch(Node\LimitMatchNode $node): string
    {
        return '(*LIMIT_MATCH='.$node->limit.')';
    }

    #[\Override]
    public function visitCallout(Node\CalloutNode $node): string
    {
        if (\is_int($node->identifier)) {
            return '(?C'.$node->identifier.')';
        }

        if (!$node->isStringIdentifier && preg_match('/^[A-Za-z_][A-Za-z0-9_]*+$/', $node->identifier)) {
            return '(?C'.$node->identifier.')';
        }

        return '(?C"'.$node->identifier.'")';
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
        $meta = $this->inCharClass ? self::CHAR_CLASS_META : self::META_CHARACTERS;
        $needsEscape = false;

        // Fast pre-scan to check if escaping is needed
        $len = \strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $char = $value[$i];
            $ord = \ord($char);
            if ($char === $this->delimiter || isset($meta[$char]) || $ord < 32 || 127 === $ord || ($ord >= 128 && $ord <= 255)) {
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
            if ($char === $this->delimiter || isset($meta[$char])) {
                $result .= '\\'.$char;
            } elseif (\ord($char) < 32 || 127 === \ord($char) || (\ord($char) >= 128 && \ord($char) <= 255)) {
                // Escape control characters and extended ASCII
                $result .= match (\ord($char)) {
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
}
