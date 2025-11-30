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
 * Configuration options for the Parser.
 * Provides resource limits and constraints to prevent DoS attacks.
 *
 * Immutable Data Transfer Object following the Rust `regex-syntax` standard
 * for resource management and security.
 */
class ParserOptions
{
    /**
     * Create parser options with default values.
     *
     * @param int $maxPatternLength  Maximum pattern length (default: 10,000)
     * @param int $maxNodes          Maximum number of AST nodes (default: 10,000)
     * @param int $maxRecursionDepth Maximum recursion depth (default: 250)
     */
    public function __construct(
        public readonly int $maxPatternLength = 10_000,
        public readonly int $maxNodes = 10_000,
        public readonly int $maxRecursionDepth = 250
    ) {}

    /**
     * Create options from an array configuration.
     *
     * @param array{
     *     max_pattern_length?: int,
     *     max_nodes?: int,
     *     max_recursion_depth?: int,
     * } $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            maxPatternLength: (int) ($config['max_pattern_length'] ?? 10_000),
            maxNodes: (int) ($config['max_nodes'] ?? 10_000),
            maxRecursionDepth: (int) ($config['max_recursion_depth'] ?? 250),
        );
    }
}
