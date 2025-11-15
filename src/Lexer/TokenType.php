<?php

namespace RegexParser\Lexer;

enum TokenType: string
{
    // A single character that is not a meta-character
    case T_LITERAL = 'literal';

    // Groups
    case T_GROUP_OPEN = 'group_open';  // (
    case T_GROUP_CLOSE = 'group_close';  // )

    // Character classes
    case T_CHAR_CLASS_OPEN = 'char_class_open'; // [
    case T_CHAR_CLASS_CLOSE = 'char_class_close'; // ]

    // Quantifiers
    case T_QUANTIFIER = 'quantifier';  // *, +, ?, {n,m}

    // Logical
    case T_ALTERNATION = 'alternation';  // |
    case T_BACKSLASH = 'backslash';    // \

    // Structural
    case T_FLAG = 'flag';      // i, m, s after /
    case T_DELIMITER = 'delimiter';    // / at start/end
    case T_EOF = 'eof';        // End of input
}
