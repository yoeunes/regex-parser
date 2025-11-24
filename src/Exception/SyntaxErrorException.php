<?php

declare(strict_types=1);

namespace RegexParser\Exception;

/**
 * Thrown when a syntax error is encountered in the PCRE pattern.
 */
class SyntaxErrorException extends ParserException implements RegexParserExceptionInterface {}
