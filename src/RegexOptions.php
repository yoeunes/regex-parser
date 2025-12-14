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

use RegexParser\Cache\CacheInterface;
use RegexParser\Cache\FilesystemCache;
use RegexParser\Cache\NullCache;
use RegexParser\Exception\InvalidRegexOptionException;

/**
 * High-performance immutable configuration for Regex::create().
 *
 * This optimized options class provides intelligent validation, caching,
 * and efficient option processing for maximum performance.
 */
final readonly class RegexOptions
{
    private const ALLOWED_KEYS = ['max_pattern_length', 'cache', 'redos_ignored_patterns'];

    /**
     * @param array<string> $redosIgnoredPatterns
     */
    public function __construct(
        public int $maxPatternLength,
        public CacheInterface $cache,
        public array $redosIgnoredPatterns = [],
    ) {}

    /**
     * Optimized array-based configuration with intelligent validation.
     *
     * @param array<string, mixed> $options
     */
    public static function fromArray(array $options): self
    {
        // Fast path for empty options
        if ([] === $options) {
            return new self(
                Regex::DEFAULT_MAX_PATTERN_LENGTH,
                new NullCache(),
                [],
            );
        }

        // Validate unknown keys with optimized check
        self::validateKeys($options);

        // Extract and validate options with early returns
        $maxPatternLength = self::validateMaxPatternLength($options);
        $cache = self::validateAndNormalizeCache($options);
        $redosIgnoredPatterns = self::validateAndNormalizeRedosPatterns($options);

        return new self($maxPatternLength, $cache, $redosIgnoredPatterns);
    }

    /**
     * High-performance key validation with clear error messages.
     *
     * @param array<string, mixed> $options
     */
    private static function validateKeys(array $options): void
    {
        $unknownKeys = [];
        foreach (array_keys($options) as $key) {
            if (!\in_array($key, self::ALLOWED_KEYS, true)) {
                $unknownKeys[] = $key;
            }
        }

        if ([] !== $unknownKeys) {
            throw new InvalidRegexOptionException(\sprintf(
                'Unknown option(s): %s. Allowed options are: %s.',
                implode(', ', $unknownKeys),
                implode(', ', self::ALLOWED_KEYS),
            ));
        }
    }

    /**
     * Optimized max pattern length validation.
     *
     * @param array<string, mixed> $options
     */
    private static function validateMaxPatternLength(array $options): int
    {
        $value = $options['max_pattern_length'] ?? Regex::DEFAULT_MAX_PATTERN_LENGTH;

        if (!\is_int($value) || $value <= 0) {
            throw new InvalidRegexOptionException('"max_pattern_length" must be a positive integer.');
        }

        return $value;
    }

    /**
     * Intelligent cache validation and normalization.
     *
     * @param array<string, mixed> $options
     */
    private static function validateAndNormalizeCache(array $options): CacheInterface
    {
        $cache = $options['cache'] ?? null;

        if (null === $cache) {
            return new NullCache();
        }

        if (\is_string($cache)) {
            if ('' === trim($cache)) {
                throw new InvalidRegexOptionException('The "cache" option cannot be an empty string.');
            }

            return new FilesystemCache($cache);
        }

        if ($cache instanceof CacheInterface) {
            return $cache;
        }

        throw new InvalidRegexOptionException(
            'The "cache" option must be null, a cache path, or a CacheInterface implementation.',
        );
    }

    /**
     * High-performance ReDoS patterns validation and normalization.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string>
     */
    private static function validateAndNormalizeRedosPatterns(array $options): array
    {
        $patterns = $options['redos_ignored_patterns'] ?? [];

        if (!\is_array($patterns)) {
            throw new InvalidRegexOptionException('"redos_ignored_patterns" must be a list of strings.');
        }

        if ([] === $patterns) {
            return [];
        }

        // Validate all patterns are strings
        foreach ($patterns as $pattern) {
            if (!\is_string($pattern)) {
                throw new InvalidRegexOptionException('"redos_ignored_patterns" must contain only strings.');
            }
        }

        // Efficient deduplication and normalization
        /** @var array<string> $result */
        $result = array_values(array_unique($patterns));
        return $result;
    }
}
