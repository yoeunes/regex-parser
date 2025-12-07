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
 * Immutable value object that encapsulates configuration for `Regex::create()`.
 */
final readonly class RegexOptions
{
    /**
     * @param list<string> $redosIgnoredPatterns
     */
    public function __construct(
        public int $maxPatternLength,
        public CacheInterface $cache,
        public array $redosIgnoredPatterns = [],
    ) {}

    /**
     * @param array{
     *     max_pattern_length?: int,
     *     cache?: CacheInterface|string|null,
     *     redos_ignored_patterns?: list<string>,
     * } $options
     */
    public static function fromArray(array $options): self
    {
        $allowedKeys = ['max_pattern_length', 'cache', 'redos_ignored_patterns'];
        $unknownKeys = array_diff(array_keys($options), $allowedKeys);

        if ([] !== $unknownKeys) {
            throw new InvalidRegexOptionException(\sprintf(
                'Unknown option(s): %s. Allowed options are: %s.',
                implode(', ', $unknownKeys),
                implode(', ', $allowedKeys),
            ));
        }

        $maxPatternLength = $options['max_pattern_length'] ?? Regex::DEFAULT_MAX_PATTERN_LENGTH;
        if (!\is_int($maxPatternLength) || $maxPatternLength <= 0) {
            throw new InvalidRegexOptionException('"max_pattern_length" must be a positive integer.');
        }

        $redosIgnoredPatterns = $options['redos_ignored_patterns'] ?? [];
        if (!\is_array($redosIgnoredPatterns)) {
            throw new InvalidRegexOptionException('"redos_ignored_patterns" must be a list of strings.');
        }

        foreach ($redosIgnoredPatterns as $pattern) {
            if (!\is_string($pattern)) {
                throw new InvalidRegexOptionException('"redos_ignored_patterns" must contain only strings.');
            }
        }

        return new self(
            $maxPatternLength,
            self::normalizeCache($options['cache'] ?? null),
            array_values(array_unique($redosIgnoredPatterns)),
        );
    }

    private static function normalizeCache(mixed $cache): CacheInterface
    {
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

        throw new InvalidRegexOptionException('The "cache" option must be null, a cache path, or a CacheInterface implementation.');
    }
}
