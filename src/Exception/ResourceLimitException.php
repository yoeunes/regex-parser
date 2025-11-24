<?php

declare(strict_types=1);

namespace RegexParser\Exception;

/**
 * Thrown when resource usage (e.g., node count) exceeds the maximum allowed limit.
 * Prevents Denial of Service attacks through resource exhaustion.
 */
class ResourceLimitException extends ParserException implements RegexParserExceptionInterface {}
