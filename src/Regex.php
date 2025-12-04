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
use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Node\RegexNode;
use RegexParser\ReDoS\ReDoSAnalysis;
use RegexParser\ReDoS\ReDoSAnalyzer;

/**
 * Main service for parsing, validating, and manipulating regex patterns.
 *
 * This class provides a high-level API for common regex operations.
 * It orchestrates the Lexer and Parser to provide convenient string-based
 * parsing while keeping those components decoupled from each other.
 */
readonly class Regex
{
    /**
     * Default hard limit on the regex string length to prevent excessive processing/memory usage.
     */
    public const int DEFAULT_MAX_PATTERN_LENGTH = 100_000;

    private const array DEFAULT_IGNORED_PATTERNS = [
        '[a-z0-9]+(?:-[a-z0-9]+)*',
        '^[a-z0-9]+(?:-[a-z0-9]+)*$',
        '[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*',
        '^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$',
        '[a-z0-9_]+',
        '^[a-z0-9_]+$',
        '[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}',
        '^\d+$',
        '^\d{4}-\d{2}-\d{2}$',
        '[0-9a-fA-F]{24}',
        '[1-9]\d*',
        '[1-9]\d{3,}',
        '[A-Za-z0-9]{26}',
        '[1-9A-HJ-NP-Za-km-z]{21,22}',
        '[0-9A-F]{8}-[0-9A-F]{4}-[1-5][0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}',
        '^[0-9A-F]{8}-[0-9A-F]{4}-[1-5][0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$',
    ];

    /**
     * @param list<string> $ignoredPatterns
     */
    private function __construct(
        private int $maxPatternLength,
        private CacheInterface $cache,
        /**
         * @var list<string>
         */
        private array $ignoredPatterns,
    ) {}

    /**
     * Initializes the main Regex service object.
     *
     * Purpose: This static factory method provides a clean, dependency-free way to
     * instantiate the `Regex` service. It's the standard entry point for users of
     * the library. As a contributor, you can add new options here to configure
     * the behavior of the parsing and analysis process.
     *
     * @param array{
     *     max_pattern_length?: int,
     *     cache?: CacheInterface|string|null,
     *     ignored_patterns?: list<string>,
     * } $options An associative array of configuration options.
     *  - `max_pattern_length` (int):
     *         Sets a safeguard limit on the length of the regex string to prevent performance
     *         issues with overly long patterns. Defaults to `self::DEFAULT_MAX_PATTERN_LENGTH`.
     *  - `cache` (string|CacheInterface|null):
     *         Provide a directory path or cache implementation to enable AST caching.
     *  - `ignored_patterns` (list<string>):
     *         Patterns that should be treated as trusted/safe by the ReDoS analyzer.
     *
     * @return self a new, configured instance of the `Regex` service, ready to be used
     *
     * @example
     * ```php
     * // Create a service with default settings
     * $regexService = Regex::create();
     *
     * // Create a service with a custom pattern length limit
     * $regexService = Regex::create(['max_pattern_length' => 5000]);
     * ```
     */
    public static function create(array $options = []): self
    {
        $maxPatternLength = $options['max_pattern_length'] ?? self::DEFAULT_MAX_PATTERN_LENGTH;
        $cache = self::normalizeCache($options['cache'] ?? null);
        $ignoredPatterns = array_values(array_unique([
            ...self::DEFAULT_IGNORED_PATTERNS,
            ...($options['ignored_patterns'] ?? []),
        ]));

        return new self($maxPatternLength, $cache, $ignoredPatterns);
    }

    /**
     * Parses a full PCRE regex string into an Abstract Syntax Tree (AST).
     *
     * Purpose: This is the core parsing function. It orchestrates the entire process:
     * 1. It first separates the pattern, delimiters, and flags (e.g., `/pattern/i`).
     * 2. It then tokenizes the raw pattern using the `Lexer`.
     * 3. Finally, it feeds the resulting `TokenStream` to the `Parser` to build the AST.
     * This method is the foundation for all other analysis methods in this class (`validate`,
     * `explain`, etc.), as they all operate on the AST produced here.
     *
     * @param string $regex the full PCRE regex string, including delimiters and flags
     *
     * @throws LexerException  if the lexer encounters an invalid sequence of characters
     * @throws ParserException if the parser encounters a syntax error or if the pattern
     *                         exceeds the configured `max_pattern_length`
     *
     * @return RegexNode The root node of the generated Abstract Syntax Tree. This object
     *                   and its children represent the complete structure of the regex.
     *
     * @example
     * ```php
     * $regexService = Regex::create();
     * $ast = $regexService->parse('/(a|b)+/i');
     * // $ast is now a RegexNode object.
     * ```
     */
    public function parse(string $regex): RegexNode
    {
        if (\strlen($regex) > $this->maxPatternLength) {
            throw new ParserException(\sprintf('Regex pattern exceeds maximum length of %d characters.', $this->maxPatternLength));
        }

        [$cached, $cacheKey] = $this->loadFromCache($regex);
        if (null !== $cached) {
            return $cached;
        }

        [$pattern, $flags, $delimiter] = $this->extractPatternAndFlags($regex);

        $stream = $this->getLexer()->tokenize($pattern);
        $parser = $this->getParser();

        $ast = $parser->parse($stream, $flags, $delimiter, \strlen($pattern));

        $this->storeInCache($cacheKey, $ast);

        return $ast;
    }

    /**
     * Checks a regex for syntax errors and other potential issues.
     *
     * Purpose: This method provides a simple way to verify if a regex is well-formed
     * and safe to use. It first attempts to parse the regex, which catches any
     * syntax errors. If parsing succeeds, it then runs a `ValidatorNodeVisitor` over
     * the AST to check for semantic issues, like invalid backreference calls. It's a
     * convenient "all-in-one" validation tool.
     *
     * @param string $regex the full PCRE regex string to validate
     *
     * @return ValidationResult An object containing the result. `isValid()` will be `true`
     *                          if the regex is valid, or `false` otherwise. If invalid,
     *                          `getErrorMessage()` will provide details.
     *
     * @example
     * ```php
     * $regexService = Regex::create();
     * $result = $regexService->validate('/[a-z/i'); // Invalid due to unclosed class
     *
     * if (!$result->isValid()) {
     *     echo "Validation failed: " . $result->getErrorMessage();
     * }
     * ```
     */
    public function validate(string $regex): ValidationResult
    {
        try {
            $ast = $this->parse($regex);
            $ast->accept(new NodeVisitor\ValidatorNodeVisitor());
            $score = $ast->accept(new NodeVisitor\ComplexityScoreNodeVisitor());

            return new ValidationResult(true, null, $score);
        } catch (LexerException|ParserException $e) {
            return new ValidationResult(false, $e->getMessage());
        }
    }

    /**
     * Generates a human-readable explanation of what a regex does.
     *
     * Purpose: This method translates the complex syntax of a regex into a more
     * understandable, step-by-step description. It works by parsing the regex into an
     * AST and then walking it with the `ExplainNodeVisitor`. This is incredibly useful
     * for developers trying to understand a complex pattern or for generating documentation.
     *
     * @param string $regex the full PCRE regex string to explain
     *
     * @throws LexerException  if the regex has a lexical error
     * @throws ParserException if the regex has a syntax error
     *
     * @return string a natural language description of the regex pattern's logic
     *
     * @example
     * ```php
     * $regexService = Regex::create();
     * $explanation = $regexService->explain('/^(\d{4})-\d{2}-\d{2}$/');
     * echo $explanation;
     * // Outputs something like:
     * // "Asserts position at the start of the string.
     * // Capturing Group #1: Matches exactly 4 digits.
     * // Matches the literal "-".
     * // ...and so on."
     * ```
     */
    public function explain(string $regex): string
    {
        return $this->parse($regex)->accept(new NodeVisitor\ExplainNodeVisitor());
    }

    /**
     * Generates a random sample string that is guaranteed to match the given regex.
     *
     * Purpose: This method is a powerful tool for testing and demonstration. It traverses
     * the regex AST using the `SampleGeneratorNodeVisitor` to construct a concrete example
     * string that satisfies the pattern. This can be used to generate test cases for
     * systems that use regex validation, or to show users an example of valid input.
     *
     * @param string $regex the full PCRE regex string for which to generate a sample
     *
     * @throws LexerException  if the regex has a lexical error
     * @throws ParserException if the regex has a syntax error
     *
     * @return string a randomly generated string that matches the regex
     *
     * @example
     * ```php
     * $regexService = Regex::create();
     * $sample = $regexService->generate('/[a-zA-Z0-9]{8}/');
     * // Could return "aB3x9PqZ", "Kk29LpW1", etc.
     * echo $sample;
     * ```
     */
    public function generate(string $regex): string
    {
        return $this->parse($regex)->accept(new NodeVisitor\SampleGeneratorNodeVisitor());
    }

    /**
     * Analyzes and simplifies a regex pattern, returning a more efficient version.
     *
     * Purpose: This method attempts to reduce the complexity of a regex without changing
     * its matching behavior. It first parses the regex, then applies the `OptimizerNodeVisitor`
     * to perform simplifications (e.g., merging literals, simplifying character classes).
     * Finally, it uses the `CompilerNodeVisitor` to convert the optimized AST back into a
     * string. This is useful for cleaning up user-provided regexes or improving performance.
     *
     * @param string $regex the full PCRE regex string to optimize
     *
     * @throws LexerException  if the original regex has a lexical error
     * @throws ParserException if the original regex has a syntax error
     *
     * @return string the optimized and recompiled PCRE regex string
     *
     * @example
     * ```php
     * $regexService = Regex::create();
     * // The optimizer can merge consecutive literals.
     * $optimized = $regexService->optimize('/a-b-c/i');
     * echo $optimized; // Outputs '/abc/i'
     * ```
     */
    public function optimize(string $regex): string
    {
        return $this->parse($regex)->accept(new NodeVisitor\OptimizerNodeVisitor())->accept(new NodeVisitor\CompilerNodeVisitor());
    }

    /**
     * Generates a Mermaid.js flowchart to visualize the regex structure.
     *
     * Purpose: This method provides a powerful debugging and documentation tool by
     * converting the regex's AST into a visual flowchart using the Mermaid.js syntax.
     * This is extremely helpful for understanding the control flow of complex patterns,
     * especially those with many alternations and groups. Contributors can use this to
     * verify the structure of the AST created by the parser.
     *
     * @param string $regex the full PCRE regex string to visualize
     *
     * @throws LexerException  if the regex has a lexical error
     * @throws ParserException if the regex has a syntax error
     *
     * @return string A string containing the Mermaid.js graph definition. This can be
     *                rendered in any environment that supports Mermaid.js.
     *
     * @example
     * ```php
     * $regexService = Regex::create();
     * $mermaidGraph = $regexService->visualize('/(a|b)+/');
     * // $mermaidGraph now contains the text for a flowchart.
     * // You can render it in a Markdown file or using a JS library.
     * ```
     */
    public function visualize(string $regex): string
    {
        return $this->parse($regex)->accept(new NodeVisitor\MermaidNodeVisitor());
    }

    /**
     * Generates a string representation of the AST for debugging purposes.
     *
     * Purpose: This method is a core debugging tool for contributors. It walks the AST
     * using the `DumperNodeVisitor` and creates a hierarchical, indented string that
     * shows the exact structure of the parsed nodes. This allows you to verify that the
     * `Parser` is correctly interpreting a regex and building the corresponding tree.
     *
     * @param string $regex the full PCRE regex string to dump
     *
     * @throws LexerException  if the regex has a lexical error
     * @throws ParserException if the regex has a syntax error
     *
     * @return string an indented string showing the AST structure
     *
     * @example
     * ```php
     * $regexService = Regex::create();
     * $dump = $regexService->dump('/a+/');
     * echo $dump;
     * // Outputs a tree-like structure:
     * // RegexNode
     * // └── Sequence
     * //     └── Quantifier
     * //         └── Literal "a"
     * ```
     */
    public function dump(string $regex): string
    {
        return $this->parse($regex)->accept(new NodeVisitor\DumperNodeVisitor());
    }

    /**
     * Finds all non-optional, non-alternating literal strings within a regex.
     *
     * Purpose: This method identifies sequences of characters that *must* exist in any
     * string that the regex matches. This is highly useful for pre-filtering or indexing.
     * For example, before running a complex regex over a large text, you could first quickly
     * search for one of the extracted literals. If it's not found, the full regex match
     * is guaranteed to fail.
     *
     * @param string $regex the full PCRE regex string to analyze
     *
     * @throws LexerException  if the regex has a lexical error
     * @throws ParserException if the regex has a syntax error
     *
     * @return LiteralSet a set containing all the mandatory literal substrings
     *
     * @example
     * ```php
     * $regexService = Regex::create();
     * $literals = $regexService->extractLiterals('/^start_(middle|center)_end$/');
     * // Returns a LiteralSet containing "start_", "_end"
     * print_r($literals->getAll());
     * ```
     */
    public function extractLiterals(string $regex): LiteralSet
    {
        return $this->parse($regex)->accept(new NodeVisitor\LiteralExtractorNodeVisitor());
    }

    /**
     * Performs a static analysis to detect potential ReDoS vulnerabilities.
     *
     * Purpose: "Regular Expression Denial of Service" (ReDoS) is a vulnerability where a
     * poorly-written regex can lead to catastrophic backtracking, causing extreme CPU usage.
     * This method analyzes the regex for common ReDoS patterns, such as nested quantifiers
     * with ambiguity. It provides a report indicating the vulnerability's severity and a
     * list of detected issues. This is a critical tool for ensuring the security and
     * stability of applications that accept user-defined regexes.
     *
     * @param string $regex the full PCRE regex string to analyze
     *
     * @return ReDoSAnalysis a report object containing the analysis results, including
     *                       severity, a complexity score, and detailed findings
     *
     * @example
     * ```php
     * $regexService = Regex::create();
     * // This pattern is vulnerable to ReDoS
     * $analysis = $regexService->analyzeReDoS('/(a+)+/');
     *
     * if ($analysis->isVulnerable()) {
     *     echo "ReDoS vulnerability detected! Severity: " . $analysis->getSeverity();
     * }
     * ```
     */
    public function analyzeReDoS(string $regex): ReDoSAnalysis
    {
        return new ReDoSAnalyzer($this, $this->ignoredPatterns)->analyze($regex);
    }

    /**
     * @return list<string>
     */
    public function getIgnoredPatterns(): array
    {
        return array_values($this->ignoredPatterns);
    }

    /**
     * Provides direct access to the `Parser` component.
     *
     * Purpose: While the `Regex` class provides a convenient high-level API, some advanced
     * use cases might require interacting directly with the `Parser`. This method exposes
     * the `Parser` instance, allowing contributors to, for example, parse a `TokenStream`
     * that has been manually created or modified.
     *
     * @return Parser a new instance of the `Parser`
     *
     * @example
     * ```php
     * $regexService = Regex::create();
     * $parser = $regexService->getParser();
     * $tokenStream = $regexService->getLexer()->tokenize('a|b');
     * // Now you can work with the parser and token stream directly.
     * $ast = $parser->parse($tokenStream, '', '/', 3);
     * ```
     */
    public function getParser(): Parser
    {
        return new Parser();
    }

    /**
     * Provides direct access to the `Lexer` component.
     *
     * Purpose: This method exposes the `Lexer` instance, which is responsible for the
     * first phase of parsing: converting the raw regex string into a stream of tokens.
     * Advanced users or contributors might use this to inspect the tokenization process
     * or to build custom tools that operate on the token level.
     *
     * @return Lexer a new instance of the `Lexer`
     *
     * @example
     * ```php
     * $regexService = Regex::create();
     * $lexer = $regexService->getLexer();
     * $tokenStream = $lexer->tokenize('(?<name>\w+)');
     *
     * foreach ($tokenStream as $token) {
     *     echo $token->type->name . "\n";
     * }
     * ```
     */
    public function getLexer(): Lexer
    {
        return new Lexer();
    }

    /**
     * A convenience method to tokenize a regex pattern without its delimiters.
     *
     * Purpose: This is a shortcut for `(new Lexer())->tokenize($pattern)`. It is useful
     * when you have a raw regex pattern (the part between the delimiters) and want to
     * quickly get a `TokenStream` without needing to instantiate the `Lexer` yourself.
     *
     * @param string $pattern the regex pattern content, without delimiters or flags
     *
     * @throws LexerException if the pattern contains invalid characters
     *
     * @return TokenStream the resulting stream of tokens
     *
     * @example
     * ```php
     * $regexService = Regex::create();
     * $stream = $regexService->createTokenStream('a|b|c');
     * // The stream can now be used by the Parser.
     * ```
     */
    public function createTokenStream(string $pattern): TokenStream
    {
        return $this->getLexer()->tokenize($pattern);
    }

    /**
     * Deconstructs a full PCRE string into its core components.
     *
     * Purpose: This internal utility is responsible for the first step of parsing:
     * separating the user-provided regex string (e.g., `"/pattern/ims"`) into the
     * pattern body (`pattern`), the flags (`ims`), and the delimiter (`/`). It correctly
     * handles escaped delimiters within the pattern and supports paired delimiters
     * like `(...)` or `[...]`.
     *
     * @param string $regex the full PCRE regex string
     *
     * @throws ParserException if the delimiters are missing or mismatched, or if an
     *                         invalid flag is provided
     *
     * @return array{0: string, 1: string, 2: string} a tuple containing `[pattern, flags, delimiter]`
     *
     * @example
     * ```php
     * $parts = $this->extractPatternAndFlags('{hello\/world}i');
     * // $parts is now ['hello\/world', 'i', '{']
     * ```
     */
    public function extractPatternAndFlags(string $regex): array
    {
        $len = \strlen($regex);
        if ($len < 2) {
            throw new ParserException('Regex is too short. It must include delimiters.');
        }

        $delimiter = $regex[0];
        // Handle bracket delimiters style: (pattern), [pattern], {pattern}, <pattern>
        $closingDelimiter = match ($delimiter) {
            '(' => ')',
            '[' => ']',
            '{' => '}',
            '<' => '>',
            default => $delimiter,
        };

        // Find the last occurrence of the closing delimiter that is NOT escaped
        // We scan from the end to optimize for flags
        for ($i = $len - 1; $i > 0; $i--) {
            if ($regex[$i] === $closingDelimiter) {
                // Check if escaped (count odd number of backslashes before it)
                $escapes = 0;
                for ($j = $i - 1; $j > 0 && '\\' === $regex[$j]; $j--) {
                    $escapes++;
                }

                if (0 === $escapes % 2) {
                    // Found the end delimiter
                    $pattern = substr($regex, 1, $i - 1);
                    $flags = substr($regex, $i + 1);

                    // Validate flags (only allow standard PCRE flags)
                    // n = NO_AUTO_CAPTURE, r = PCRE2_EXTRA_CASELESS_RESTRICT (unicode restricted)
                    if (!preg_match('/^[imsxADSUXJunr]*$/', $flags)) {
                        // Find the invalid flag for a better error message
                        $invalid = preg_replace('/[imsxADSUXJunr]/', '', $flags);

                        throw new ParserException(\sprintf('Unknown regex flag(s) found: "%s"', $invalid ?? $flags));
                    }

                    return [$pattern, $flags, $delimiter];
                }
            }
        }

        throw new ParserException(\sprintf('No closing delimiter "%s" found.', $closingDelimiter));
    }

    private static function normalizeCache(mixed $cache): CacheInterface
    {
        if (null === $cache) {
            return new NullCache();
        }

        if (\is_string($cache)) {
            if ('' === trim($cache)) {
                throw new \InvalidArgumentException('The "cache" option cannot be an empty string.');
            }

            return new FilesystemCache($cache);
        }

        if ($cache instanceof CacheInterface) {
            return $cache;
        }

        throw new \InvalidArgumentException('The "cache" option must be null, a cache path, or a CacheInterface implementation.');
    }

    /**
     * @return array{0: RegexNode|null, 1: string|null}
     */
    private function loadFromCache(string $regex): array
    {
        if ($this->cache instanceof NullCache) {
            return [null, null];
        }

        $cacheKey = $this->cache->generateKey($regex);
        $cached = $this->cache->load($cacheKey);

        return [$cached instanceof RegexNode ? $cached : null, $cacheKey];
    }

    private function storeInCache(?string $cacheKey, RegexNode $ast): void
    {
        if (null === $cacheKey) {
            return;
        }

        try {
            $this->cache->write($cacheKey, self::compileCachePayload($ast));
        } catch (\Throwable) {
        }
    }

    private static function compileCachePayload(RegexNode $ast): string
    {
        $serialized = serialize($ast);
        $exported = var_export($serialized, true);

        return <<<PHP
            <?php

            declare(strict_types=1);

            return unserialize($exported, ['allowed_classes' => true]);

            PHP;
    }
}
