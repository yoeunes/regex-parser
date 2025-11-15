<?php

namespace RegexParser\Lexer;

enum TokenType: string
{
    // Un seul caractère qui n'est pas un méta-caractère
    case T_LITERAL = 'literal';

    // Groupes
    case T_GROUP_OPEN = 'group_open';  // (
    case T_GROUP_CLOSE = 'group_close';  // )

    // Classes de caractères
    case T_CHAR_CLASS_OPEN = 'char_class_open'; // [
    case T_CHAR_CLASS_CLOSE = 'char_class_close'; // ]

    // Quantifieurs
    case T_QUANTIFIER = 'quantifier';  // *, +, ?, {n,m}

    // Logique
    case T_ALTERNATION = 'alternation';  // |
    case T_BACKSLASH = 'backslash';    // \

    // Structure
    case T_FLAG = 'flag';      // i, m, s après /
    case T_DELIMITER = 'delimiter';    // / au début/fin
    case T_EOF = 'eof';        // Fin de la chaîne
}
