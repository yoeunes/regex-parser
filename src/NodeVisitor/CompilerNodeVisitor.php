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
 * Recompiles the Abstract Syntax Tree (AST) back into a regular expression string.
 *
 * @extends AbstractNodeVisitor<string>
 */
final class CompilerNodeVisitor extends AbstractNodeVisitor
{
    // PCRE meta-characters that must be escaped *outside* a character class.
    private const META_CHARACTERS = [
        '\\' => true, '.' => true, '^' => true, '$' => true,
        '[' => true, ']' => true, '(' => true, ')' => true,
        '*' => true, '+' => true, '?' => true, '{' => true, '}' => true,
    ];

    // Meta-characters that must be escaped *inside* a character class.
    private const CHAR_CLASS_META = [
        '\\' => true, ']' => true, '-' => true, '^' => true,
    ];

    private bool $inCharClass = false;

    private string $delimiter = '/';

    #[\Override]
    public function visitRegex(Node\RegexNode $node): string
    {
        $this->delimiter = $node->delimiter;
        $map = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'];
        $closingDelimiter = $map[$node->delimiter] ?? $node->delimiter;

        return $node->delimiter.$node->pattern->accept($this).$closingDelimiter.$node->flags;
    }

    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): string
    {
        $separator = $this->inCharClass ? '' : '|';

        return implode($separator, array_map(fn ($alt) => $alt->accept($this), $node->alternatives));
    }

    #[\Override]
    public function visitSequence(Node\SequenceNode $node): string
    {
        return implode('', array_map(fn ($child) => $child->accept($this), $node->children));
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
        $meta = $this->inCharClass ? self::CHAR_CLASS_META : self::META_CHARACTERS;

        if (!$this->inCharClass && ']' === $node->value) {
            return $node->value;
        }

        $result = '';
        $length = \strlen($node->value);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($node->value, $i, 1);
            if ($char === $this->delimiter || isset($meta[$char])) {
                $result .= '\\'.$char;
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    #[\Override]
    public function visitCharType(Node\CharTypeNode $node): string
    {
        return '\\'.$node->value;
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
    public function visitKeep(Node\KeepNode $node): string
    {
        return '\K';
    }

    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): string
    {
        $this->inCharClass = true;
        $result = '['.($node->isNegated ? '^' : '').$node->expression->accept($this).']';
        $this->inCharClass = false;

        return $result;
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
    public function visitUnicode(Node\UnicodeNode $node): string
    {
        return $node->code;
    }

    #[\Override]
    public function visitUnicodeNamed(Node\UnicodeNamedNode $node): string
    {
        return '\\N{'.$node->name.'}';
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
    public function visitOctal(Node\OctalNode $node): string
    {
        return $node->code;
    }

    #[\Override]
    public function visitOctalLegacy(Node\OctalLegacyNode $node): string
    {
        return '\\'.$node->code;
    }

    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): string
    {
        return '[[:'.$node->class.':]]';
    }

    #[\Override]
    public function visitComment(Node\CommentNode $node): string
    {
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
}
