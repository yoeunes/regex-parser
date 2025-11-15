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

    /** A character class opening bracket "[". */
    case T_CHAR_CLASS_OPEN = 'char_class_open';

    /** A character class closing bracket "]". */
    case T_CHAR_CLASS_CLOSE = 'char_class_close';

    /** A quantifier (e.g., "*", "+", "?", "{n,m}"). */
    case T_QUANTIFIER = 'quantifier';

    /** The alternation pipe "|". */
    case T_ALTERNATION = 'alternation';

    /** The wildcard dot ".". */
    case T_DOT = 'dot';

    /** An anchor (e.g., "^", "$"). */
    case T_ANCHOR = 'anchor';

    /** A regex delimiter (e.g., "/", "#", "~"). */
    case T_DELIMITER = 'delimiter';

    /** The end-of-file marker. */
    case T_EOF = 'eof';
}
