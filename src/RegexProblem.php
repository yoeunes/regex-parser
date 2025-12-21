<?php

declare(strict_types=1);

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser;

/**
 * Unified representation of syntax, semantic, lint, security, or optimization issues.
 */
final readonly class RegexProblem
{
    public function __construct(
        public ProblemType $type,
        public Severity $severity,
        public string $message,
        public ?string $code = null,
        public ?int $position = null,
        public ?string $snippet = null,
        public ?string $suggestion = null,
        public ?string $docsAnchor = null,
    ) {}

    /**
     * @return array{
     *     type: string,
     *     severity: string,
     *     message: string,
     *     code: ?string,
     *     position: ?int,
     *     snippet: ?string,
     *     suggestion: ?string,
     *     docsAnchor: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'code' => $this->code,
            'position' => $this->position,
            'snippet' => $this->snippet,
            'suggestion' => $this->suggestion,
            'docsAnchor' => $this->docsAnchor,
        ];
    }
}
