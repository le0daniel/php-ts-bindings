<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser;

enum TokenType: string
{
    case PIPE = "|";
    case LT = "<";
    case GT = ">";
    case COMMA = ",";
    case LBRACE = "{";
    case RBRACE = "}";
    case SINGLE_QUOTE = "'";
    case DOUBLE_QUOTE = '"';
    case LBRACKET = "[";
    case RBRACKET = "]";
    case QUESTION_MARK = '?';
    case CLASS_CONST = "name::CONST";
    case COLON = ":";
    case DOUBLE_COLON = '::';

    case CLOSED_BRACKETS = '[]';

    case INT = "int";
    case FLOAT = "float";
    case BOOL = "bool";
    case STRING = "string";

    // Buffered tokens
    case IDENTIFIER = "identifier";

    // Special tokens
    case EOF = "eof";
    case WHITESPACE = "whitespace";
}
