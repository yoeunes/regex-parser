<?php

declare(strict_types=1);

namespace RegexParser\Exception;

/**
 * Thrown when recursion depth exceeds the maximum allowed limit.
 * Prevents stack overflow attacks on deeply nested patterns.
 */
class RecursionLimitException extends ParserException implements RegexParserExceptionInterface {}
