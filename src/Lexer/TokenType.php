<?php

namespace RegexParser\Lexer;

enum TokenType: string
{
    case T_LITERAL = 'literal';  // Chars normaux
    case T_GROUP_OPEN = 'group_open';  // (
    case T_GROUP_CLOSE = 'group_close';  // )
    case T_QUANTIFIER = 'quantifier';  // *, +, ?, {n,m}
    case T_ALTERNATION = 'alternation';  // |
    case T_FLAG = 'flag';  // i, m, s après /
    case T_DELIMITER = 'delimiter';  // / au début/fin
    case T_EOF = 'eof';
}
