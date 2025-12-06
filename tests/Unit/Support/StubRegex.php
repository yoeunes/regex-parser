<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Support;

use RegexParser\ValidationResult;

final class StubRegex
{
    /**
     * @param array<string, array{0: bool, 1: ?string, 2: int}> $results
     * @param list<string>                                      $ignored
     */
    public function __construct(
        private array $results,
        private array $ignored = [],
    ) {}

    public function validate(string $pattern): ValidationResult
    {
        $result = $this->results[$pattern] ?? [true, null, 0];

        return new ValidationResult($result[0], $result[1], $result[2]);
    }

    /**
     * @return list<string>
     */
    public function getRedosIgnoredPatterns(): array
    {
        return $this->ignored;
    }
}
