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
 * Purpose: This visitor is the counterpart to the `Parser`. It traverses a (potentially
 * modified) AST and reconstructs the original PCRE string. This is crucial for features
 * like `Regex::optimize()`, where the AST is simplified and then needs to be converted
 * back into a string. For contributors, this class demonstrates how to correctly
 * serialize each node back to its string representation, including handling context-sensitive
 * escaping (e.g., inside and outside of character classes).
 *
 * @implements NodeVisitorInterface<string>
 */
class CompilerNodeVisitor implements NodeVisitorInterface
{
    // PCRE meta-characters that must be escaped *outside* a character class.
    private const array META_CHARACTERS = [
        '\\' => true, '.' => true, '^' => true, '$' => true,
        '[' => true, ']' => true, '(' => true, ')' => true,
        '*' => true, '+' => true, '?' => true, '{' => true, '}' => true,
    ];

    // Meta-characters that must be escaped *inside* a character class.
    private const array CHAR_CLASS_META = [
        '\\' => true, ']' => true, '-' => true, '^' => true,
    ];

    private bool $inCharClass = false;

    private string $delimiter = '/';

    /**
     * Compiles the root `RegexNode`.
     *
     * Purpose: This is the entry point for the compilation. It reconstructs the full
     * PCRE string by wrapping the compiled pattern with its original delimiters and flags.
     *
     * @param Node\RegexNode $node the root node of the AST
     *
     * @return string the complete, recompiled PCRE regex string
     */
    public function visitRegex(Node\RegexNode $node): string
    {
        $this->delimiter = $node->delimiter;
        $map = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'];
        $closingDelimiter = $map[$node->delimiter] ?? $node->delimiter;

        return $node->delimiter.$node->pattern->accept($this).$closingDelimiter.$node->flags;
    }

    /**
     * Compiles an `AlternationNode`.
     *
     * Purpose: This method reconstructs an alternation by compiling each of its
     * branches and joining them with the `|` character.
     *
     * @param Node\AlternationNode $node the alternation node to compile
     *
     * @return string the recompiled alternation string
     */
    public function visitAlternation(Node\AlternationNode $node): string
    {
        return implode('|', array_map(fn ($alt) => $alt->accept($this), $node->alternatives));
    }

    /**
     * Compiles a `SequenceNode`.
     *
     * Purpose: This method reconstructs a sequence by simply concatenating the
     * compiled output of each of its child nodes in order.
     *
     * @param Node\SequenceNode $node the sequence node to compile
     *
     * @return string the recompiled sequence string
     */
    public function visitSequence(Node\SequenceNode $node): string
    {
        return implode('', array_map(fn ($child) => $child->accept($this), $node->children));
    }

    /**
     * Compiles a `GroupNode`.
     *
     * Purpose: This method reconstructs the syntax for all group types (e.g., `(...)`,
     * `(?:...)`, `(?<name>...)`) by wrapping the compiled child expression with the
     * correct opening and closing syntax.
     *
     * @param Node\GroupNode $node the group node to compile
     *
     * @return string the recompiled group string
     */
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

    /**
     * Compiles a `QuantifierNode`.
     *
     * Purpose: This method reconstructs a quantifier by appending the quantifier
     * token (e.g., `*`, `+?`, `{2,5}+`) to the compiled child node. It also correctly
     * wraps the child in a non-capturing group if necessary to avoid ambiguity.
     *
     * @param Node\QuantifierNode $node the quantifier node to compile
     *
     * @return string the recompiled quantified expression
     */
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

    /**
     * Compiles a `LiteralNode`.
     *
     * Purpose: This method reconstructs a literal string, carefully escaping any
     * characters that have a special meaning in the current context (e.g., escaping `[`
     * outside a character class, but escaping `-` inside one).
     *
     * @param Node\LiteralNode $node the literal node to compile
     *
     * @return string the escaped literal string
     */
    public function visitLiteral(Node\LiteralNode $node): string
    {
        $meta = $this->inCharClass ? self::CHAR_CLASS_META : self::META_CHARACTERS;

        if (!$this->inCharClass && ']' === $node->value) {
            return $node->value;
        }

        $result = '';
        $length = mb_strlen($node->value);
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

    /**
     * Compiles a `CharTypeNode`.
     *
     * Purpose: This method reconstructs a character type escape sequence like `\d` or `\s`.
     *
     * @param Node\CharTypeNode $node the character type node to compile
     *
     * @return string the recompiled character type
     */
    public function visitCharType(Node\CharTypeNode $node): string
    {
        return '\\'.$node->value;
    }

    /**
     * Compiles a `DotNode`.
     *
     * Purpose: This method reconstructs the `.` wildcard character.
     *
     * @param Node\DotNode $node the dot node to compile
     *
     * @return string The `.` character.
     */
    public function visitDot(Node\DotNode $node): string
    {
        return '.';
    }

    /**
     * Compiles an `AnchorNode`.
     *
     * Purpose: This method reconstructs an anchor like `^` or `$`.
     *
     * @param Node\AnchorNode $node the anchor node to compile
     *
     * @return string the anchor character
     */
    public function visitAnchor(Node\AnchorNode $node): string
    {
        return $node->value;
    }

    /**
     * Compiles an `AssertionNode`.
     *
     * Purpose: This method reconstructs a zero-width assertion like `\b` or `\A`.
     *
     * @param Node\AssertionNode $node the assertion node to compile
     *
     * @return string the recompiled assertion
     */
    public function visitAssertion(Node\AssertionNode $node): string
    {
        return '\\'.$node->value;
    }

    /**
     * Compiles a `KeepNode`.
     *
     * Purpose: This method reconstructs the `\K` escape sequence.
     *
     * @param Node\KeepNode $node the keep node to compile
     *
     * @return string the `\K` sequence
     */
    public function visitKeep(Node\KeepNode $node): string
    {
        return '\K';
    }

    /**
     * Compiles a `CharClassNode`.
     *
     * Purpose: This method reconstructs a character class `[...]`. It sets a flag to
     * ensure child nodes are escaped correctly for this context, adds the negation
     * character `^` if needed, and wraps the compiled parts in brackets.
     *
     * @param Node\CharClassNode $node the character class node to compile
     *
     * @return string the recompiled character class
     */
    public function visitCharClass(Node\CharClassNode $node): string
    {
        $this->inCharClass = true;
        $parts = implode('', array_map(fn ($part) => $part->accept($this), $node->parts));
        $result = '['.($node->isNegated ? '^' : '').$parts.']';
        $this->inCharClass = false;

        return $result;
    }

    /**
     * Compiles a `RangeNode`.
     *
     * Purpose: This method reconstructs a range within a character class by compiling
     * the start and end nodes and joining them with a hyphen.
     *
     * @param Node\RangeNode $node the range node to compile
     *
     * @return string the recompiled range string
     */
    public function visitRange(Node\RangeNode $node): string
    {
        return $node->start->accept($this).'-'.$node->end->accept($this);
    }

    /**
     * Compiles a `BackrefNode`.
     *
     * Purpose: This method reconstructs a backreference like `\1` or `\k<name>`.
     *
     * @param Node\BackrefNode $node the backreference node to compile
     *
     * @return string the recompiled backreference
     */
    public function visitBackref(Node\BackrefNode $node): string
    {
        if (ctype_digit($node->ref)) {
            return '\\'.$node->ref;
        }

        return $node->ref;
    }

    /**
     * Compiles a `UnicodeNode`.
     *
     * Purpose: This method reconstructs a Unicode character escape like `\xHH`.
     *
     * @param Node\UnicodeNode $node the Unicode node to compile
     *
     * @return string the recompiled Unicode escape
     */
    public function visitUnicode(Node\UnicodeNode $node): string
    {
        return $node->code;
    }

    /**
     * Compiles a `UnicodePropNode`.
     *
     * Purpose: This method reconstructs a Unicode property escape, correctly choosing
     * between the short form `\pL` and the full form `\p{Letter}`.
     *
     * @param Node\UnicodePropNode $node the Unicode property node to compile
     *
     * @return string the recompiled Unicode property escape
     */
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

    /**
     * Compiles an `OctalNode`.
     *
     * Purpose: This method reconstructs a modern octal escape `\o{...}`.
     *
     * @param Node\OctalNode $node the octal node to compile
     *
     * @return string the recompiled octal escape
     */
    public function visitOctal(Node\OctalNode $node): string
    {
        return $node->code;
    }

    /**
     * Compiles an `OctalLegacyNode`.
     *
     * Purpose: This method reconstructs a legacy octal escape like `\077`.
     *
     * @param Node\OctalLegacyNode $node the legacy octal node to compile
     *
     * @return string the recompiled legacy octal escape
     */
    public function visitOctalLegacy(Node\OctalLegacyNode $node): string
    {
        return '\\'.$node->code;
    }

    /**
     * Compiles a `PosixClassNode`.
     *
     * Purpose: This method reconstructs a POSIX character class like `[[:alpha:]]`.
     *
     * @param Node\PosixClassNode $node the POSIX class node to compile
     *
     * @return string the recompiled POSIX class
     */
    public function visitPosixClass(Node\PosixClassNode $node): string
    {
        return '[[:'.$node->class.':]]';
    }

    /**
     * Compiles a `CommentNode`.
     *
     * Purpose: This method reconstructs an inline comment `(?#...)`.
     *
     * @param Node\CommentNode $node the comment node to compile
     *
     * @return string the recompiled comment
     */
    public function visitComment(Node\CommentNode $node): string
    {
        return '(?#'.$node->comment.')';
    }

    /**
     * Compiles a `ConditionalNode`.
     *
     * Purpose: This method reconstructs a conditional subpattern `(?(cond)yes|no)`,
     * correctly handling the syntax for the condition and the two branches.
     *
     * @param Node\ConditionalNode $node the conditional node to compile
     *
     * @return string the recompiled conditional subpattern
     */
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

    /**
     * Compiles a `SubroutineNode`.
     *
     * Purpose: This method reconstructs a subroutine call, choosing the correct
     * syntax (e.g., `(?R)`, `(?&name)`, `(?P>name)`) based on the node's properties.
     *
     * @param Node\SubroutineNode $node the subroutine node to compile
     *
     * @return string the recompiled subroutine call
     */
    public function visitSubroutine(Node\SubroutineNode $node): string
    {
        return match ($node->syntax) {
            '&' => '(?&'.$node->reference.')',
            'P>' => '(?P>'.$node->reference.')',
            'g' => '\g<'.$node->reference.'>',
            default => '(?'.$node->reference.')',
        };
    }

    /**
     * Compiles a `PcreVerbNode`.
     *
     * Purpose: This method reconstructs a PCRE verb like `(*FAIL)`.
     *
     * @param Node\PcreVerbNode $node the PCRE verb node to compile
     *
     * @return string the recompiled PCRE verb
     */
    public function visitPcreVerb(Node\PcreVerbNode $node): string
    {
        return '(*'.$node->verb.')';
    }

    /**
     * Compiles a `DefineNode`.
     *
     * Purpose: This method reconstructs a `(?(DEFINE)...)` block.
     *
     * @param Node\DefineNode $node the define node to compile
     *
     * @return string the recompiled DEFINE block
     */
    public function visitDefine(Node\DefineNode $node): string
    {
        return '(?(DEFINE)'.$node->content->accept($this).')';
    }

    public function visitLimitMatch(Node\LimitMatchNode $node): string
    {
        return '(*LIMIT_MATCH='.$node->limit.')';
    }

    public function visitCallout(Node\CalloutNode $node): string
    {
        if (\is_int($node->identifier)) {
            return '(?C'.$node->identifier.')';
        }

        if (!$node->isStringIdentifier && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $node->identifier)) {
            return '(?C'.$node->identifier.')';
        }

        return '(?C"'.$node->identifier.'")';
    }
}
