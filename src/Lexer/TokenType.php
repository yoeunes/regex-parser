<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Lexer;

enum TokenType: string
{
    /** A single literal character (e.g., "a", "1"). */
    case T_LITERAL = 'literal';

    /** A special character class type (e.g., \d, \s, \w). */
    case T_CHAR_TYPE = 'char_type';

    /** A group opening parenthesis "(". */
    case T_GROUP_OPEN = 'group_open';

    /** A group closing parenthesis ")". */
    case T_GROUP_CLOSE = 'group_close';

    /** A special group opening sequence (e.g., "(?:", "(?<name>", "(?="). */
    case T_GROUP_MODIFIER_OPEN = 'group_modifier_open';

    /** A character class opening bracket "[". */
    case T_CHAR_CLASS_OPEN = 'char_class_open';

    /** A character class closing bracket "]". */
    case T_CHAR_CLASS_CLOSE = 'char_class_close';

    /** A quantifier (e.g., "*", "+", "?", "{n,m}", "*?", "++", "{n,m}+"). */
    case T_QUANTIFIER = 'quantifier';

    /** The alternation pipe "|". */
    case T_ALTERNATION = 'alternation';

    /** The wildcard dot ".". */
    case T_DOT = 'dot';

    /** An anchor (e.g., "^", "$"). */
    case T_ANCHOR = 'anchor';

    /** The end-of-file marker. */
    case T_EOF = 'eof';

    /** A range operator "-" inside a character class. */
    case T_RANGE = 'range';

    /** A negation operator "^" at the start of a character class. */
    case T_NEGATION = 'negation';

    /** A colon ":" (used in "(?:"). */
    case T_COLON = 'colon';

    /** An equals sign "=" (used in "(?="). */
    case T_EQUALS = 'equals';

    /** An exclamation mark "!" (used in "(?!"). */
    case T_EXCLAMATION = 'exclamation';

    /** A less-than sign "<" (used in "(?<" and "(?<="). */
    case T_LT = 'lt';

    /** A greater-than sign ">" (used in "(?<name>"). */
    case T_GT = 'gt';

    /** A "P" character (used in "(?P<name>"). */
    case T_P = 'P';

    /** A name inside a named group (e.g., "name" in "(?<name>"). */
    case T_NAME = 'name';
}
