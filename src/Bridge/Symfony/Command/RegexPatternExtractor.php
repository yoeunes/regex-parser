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

namespace RegexParser\Bridge\Symfony\Command;

/**
 * Extracts constant regex patterns from PHP source files.
 *
 * This scanner is intentionally conservative: it only reports patterns that are
 * PHP constant strings passed directly to `preg_*` calls.
 *
 * @internal
 */
final class RegexPatternExtractor
{
    private const IGNORABLE_TOKENS = [
        \T_WHITESPACE => true,
        \T_COMMENT => true,
        \T_DOC_COMMENT => true,
    ];

    private const PREG_FUNCTIONS = [
        'preg_match' => true,
        'preg_match_all' => true,
        'preg_replace' => true,
        'preg_replace_callback' => true,
        'preg_split' => true,
        'preg_grep' => true,
        'preg_filter' => true,
        'preg_replace_callback_array' => true,
    ];

    /**
     * @param list<string> $paths
     *
     * @return list<RegexPatternOccurrence>
     */
    public function extract(array $paths): array
    {
        $occurrences = [];

        foreach ($this->iteratePhpFiles($paths) as $file) {
            $occurrences = [...$occurrences, ...$this->extractFromFile($file)];
        }

        return $occurrences;
    }

    /**
     * @param list<string> $paths
     *
     * @return \Generator<string>
     */
    private function iteratePhpFiles(array $paths): \Generator
    {
        foreach ($paths as $path) {
            if ('' === $path) {
                continue;
            }

            if (is_file($path)) {
                if (str_ends_with($path, '.php')) {
                    yield $path;
                }

                continue;
            }

            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                if ('php' !== $file->getExtension()) {
                    continue;
                }

                yield $file->getPathname();
            }
        }
    }

    /**
     * @return list<RegexPatternOccurrence>
     */
    private function extractFromFile(string $file): array
    {
        $code = @file_get_contents($file);
        if (false === $code || '' === $code) {
            return [];
        }

        $tokens = token_get_all($code);
        $count = \count($tokens);

        $occurrences = [];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i] ?? null;
            if (!\is_array($token) || \T_STRING !== $token[0]) {
                continue;
            }

            $functionName = strtolower($token[1]);
            if (!isset(self::PREG_FUNCTIONS[$functionName])) {
                continue;
            }

            if ($this->isMethodOrStaticCall($tokens, $i)) {
                continue;
            }

            $openParen = $this->nextMeaningfulTokenIndex($tokens, $i + 1);
            if (null === $openParen || '(' !== ($tokens[$openParen] ?? null)) {
                continue;
            }

            $argStart = $this->nextMeaningfulTokenIndex($tokens, $openParen + 1);
            if (null === $argStart) {
                continue;
            }

            $source = $functionName.'()';

            if ('preg_replace_callback_array' === $functionName) {
                [$found, $endIndex] = $this->extractCallbackArrayPatterns($tokens, $argStart, $file, $source);
                $occurrences = [...$occurrences, ...$found];
                if (null !== $endIndex) {
                    $i = $endIndex;
                }

                continue;
            }

            $argToken = $tokens[$argStart];
            if (!\is_array($argToken) || \T_CONSTANT_ENCAPSED_STRING !== $argToken[0]) {
                continue;
            }

            $pattern = $this->decodeConstantString($argToken[1]);
            if (null === $pattern || '' === $pattern) {
                continue;
            }

            $occurrences[] = new RegexPatternOccurrence($pattern, $file, (int) $argToken[2], $source);
        }

        return $occurrences;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array{0: list<RegexPatternOccurrence>, 1: int|null}
     */
    private function extractCallbackArrayPatterns(array $tokens, int $startIndex, string $file, string $source): array
    {
        $token = $tokens[$startIndex] ?? null;
        if (null === $token) {
            return [[], null];
        }

        if ('[' === $token) {
            [$patterns, $endIndex] = $this->extractArrayStringKeys($tokens, $startIndex, '[', ']');

            return [$this->buildOccurrences($patterns, $file, $source), $endIndex];
        }

        if (\is_array($token) && \T_ARRAY === $token[0]) {
            $openParen = $this->nextMeaningfulTokenIndex($tokens, $startIndex + 1);
            if (null === $openParen || '(' !== ($tokens[$openParen] ?? null)) {
                return [[], null];
            }

            [$patterns, $endIndex] = $this->extractArrayStringKeys($tokens, $openParen, '(', ')');

            return [$this->buildOccurrences($patterns, $file, $source), $endIndex];
        }

        return [[], null];
    }

    /**
     * @param list<array{pattern: string, line: int}> $patterns
     *
     * @return list<RegexPatternOccurrence>
     */
    private function buildOccurrences(array $patterns, string $file, string $source): array
    {
        $occurrences = [];
        foreach ($patterns as $pattern) {
            if ('' === $pattern['pattern']) {
                continue;
            }

            $occurrences[] = new RegexPatternOccurrence($pattern['pattern'], $file, $pattern['line'], $source);
        }

        return $occurrences;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array{0: list<array{pattern: string, line: int}>, 1: int|null}
     */
    private function extractArrayStringKeys(array $tokens, int $openIndex, string $open, string $close): array
    {
        $patterns = [];
        $depth = 0;
        $count = \count($tokens);

        for ($i = $openIndex; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($open === $token) {
                $depth++;

                continue;
            }

            if ($close === $token) {
                $depth--;

                if (0 === $depth) {
                    return [$patterns, $i];
                }

                continue;
            }

            if (1 !== $depth) {
                continue;
            }

            if (!\is_array($token) || \T_CONSTANT_ENCAPSED_STRING !== $token[0]) {
                continue;
            }

            $next = $this->nextMeaningfulTokenIndex($tokens, $i + 1);
            if (null === $next) {
                continue;
            }

            $nextToken = $tokens[$next];
            if (!\is_array($nextToken) || \T_DOUBLE_ARROW !== $nextToken[0]) {
                continue;
            }

            $pattern = $this->decodeConstantString($token[1]);
            if (null === $pattern) {
                continue;
            }

            $patterns[] = ['pattern' => $pattern, 'line' => (int) $token[2]];
        }

        return [$patterns, null];
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function isMethodOrStaticCall(array $tokens, int $index): bool
    {
        $previous = $this->previousMeaningfulTokenIndex($tokens, $index - 1);
        if (null === $previous) {
            return false;
        }

        $token = $tokens[$previous];
        if (!\is_array($token)) {
            return false;
        }

        return \in_array($token[0], [\T_OBJECT_OPERATOR, \T_DOUBLE_COLON, \T_FUNCTION], true);
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function nextMeaningfulTokenIndex(array $tokens, int $start): ?int
    {
        $count = \count($tokens);
        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!\is_array($token)) {
                return $i;
            }

            if (!isset(self::IGNORABLE_TOKENS[$token[0]])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function previousMeaningfulTokenIndex(array $tokens, int $start): ?int
    {
        for ($i = $start; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (!\is_array($token)) {
                return $i;
            }

            if (!isset(self::IGNORABLE_TOKENS[$token[0]])) {
                return $i;
            }
        }

        return null;
    }

    private function decodeConstantString(string $literal): ?string
    {
        $len = \strlen($literal);
        if ($len < 2) {
            return null;
        }

        $quote = $literal[0];
        if (("'" !== $quote && '"' !== $quote) || $quote !== $literal[$len - 1]) {
            return null;
        }

        $content = substr($literal, 1, -1);

        if ("'" === $quote) {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $content);
        }

        return stripcslashes($content);
    }
}
