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
 * Represents a set of literal strings extracted from a regex pattern.
 *
 * Purpose: This immutable data structure holds the results of the `LiteralExtractorNodeVisitor`.
 * It captures constant parts of a regex, which can be used for powerful pre-match
 * optimizations. For example, if we know a regex must contain the literal "user_id=",
 * we can perform a fast `strpos` check before running the full, expensive regex engine.
 * It tracks prefixes, suffixes, and whether the set of literals represents the entire match.
 */
readonly class LiteralSet
{
    /**
     * Creates a new LiteralSet.
     *
     * @param array<string> $prefixes an array of possible literal strings that can appear at the beginning of a match
     * @param array<string> $suffixes an array of possible literal strings that can appear at the end of a match
     * @param bool          $complete If true, it means the prefixes and suffixes represent the *entire* possible match.
     *                                For example, the regex `/cat/` would have a complete set `{"cat"}`, while `/cat\d+/`
     *                                would have an incomplete set with prefix `{"cat"}`.
     */
    public function __construct(
        public array $prefixes = [],
        public array $suffixes = [],
        public bool $complete = false,
    ) {}

    /**
     * Creates a new, empty LiteralSet.
     *
     * Purpose: This factory method is the standard way to initialize a "void" or "null"
     * literal set. It represents a regex component that does not contribute any known
     * literal characters, such as `\d+` or `.*`.
     *
     * @return self an empty LiteralSet instance
     */
    public static function empty(): self
    {
        return new self([], [], false);
    }

    /**
     * Creates a new LiteralSet from a single, complete literal string.
     *
     * Purpose: This factory is used by the `LiteralExtractorNodeVisitor` when it encounters
     * a `LiteralNode`. It creates a "complete" set, indicating that this part of the
     * regex matches this exact string and nothing else.
     *
     * @param string $literal the literal string that makes up the entire match
     *
     * @return self a new, complete LiteralSet containing the provided string
     */
    public static function fromString(string $literal): self
    {
        return new self([$literal], [$literal], true);
    }

    /**
     * Combines two literal sets to represent a sequence (e.g., `A` followed by `B`).
     *
     * Purpose: When the `LiteralExtractorNodeVisitor` traverses a `SequenceNode`, it uses
     * this method to merge the literal sets of its children. The logic correctly
     * determines the new set of possible prefixes and suffixes. For example, concatenating
     * `{"a"}` and `{"b"}` results in `{"ab"}`. If the first set is "complete", its prefixes
     * are prepended to the second set's prefixes.
     *
     * @param self $other the LiteralSet to append to the current one
     *
     * @return self a new LiteralSet representing the combined sequence
     *
     * @example
     * ```php
     * $setA = LiteralSet::fromString("start_");
     * $setB = new LiteralSet(["middle", "center"]);
     * $sequence = $setA->concat($setB);
     * // $sequence->prefixes is now ["start_middle", "start_center"]
     * ```
     */
    public function concat(self $other): self
    {
        // If "this" matches nothing (void), result is void.
        // Note: We don't check if "other" is void immediately because "this" prefix might still be valid
        if ($this->isVoid() && empty($this->prefixes)) {
            return self::empty();
        }

        // 1. Calculate new Prefixes
        $newPrefixes = $this->prefixes;

        if ($this->complete) {
            if (!empty($other->prefixes)) {
                // Cross product: A is complete, B starts with something known
                $newPrefixes = $this->crossProduct($this->prefixes, $other->prefixes);
            }
            // If B has NO prefixes (unknown start), we KEEP A's prefixes as the start of the sequence
            // e.g. "user_" . "\d" -> prefix is "user_"
        }

        // 2. Calculate new Suffixes
        $newSuffixes = $other->suffixes;

        if ($other->complete) {
            if (!empty($this->suffixes)) {
                $newSuffixes = $this->crossProduct($this->suffixes, $other->suffixes);
            }
            // If A is unknown but B is complete, suffix is B (already set above)
        }

        // 3. Completeness
        // A sequence is complete only if both parts are complete.
        $newComplete = $this->complete && $other->complete;

        // If the resulting combined set has no valid prefixes or suffixes, it's empty
        if (empty($newPrefixes) && empty($newSuffixes)) {
            return self::empty();
        }

        return new self($this->deduplicate($newPrefixes), $this->deduplicate($newSuffixes), $newComplete);
    }

    /**
     * Combines two literal sets to represent an alternation (e.g., `A` or `B`).
     *
     * Purpose: When the `LiteralExtractorNodeVisitor` traverses an `AlternationNode`,
     * it uses this method to merge the literal sets of the different branches. The new
     * set's prefixes are the union of all branch prefixes, and the same applies to suffixes.
     * The resulting set is only "complete" if *all* branches were themselves complete.
     *
     * @param self $other the LiteralSet representing the alternate branch
     *
     * @return self a new LiteralSet representing the combined alternation
     *
     * @example
     * ```php
     * $setA = LiteralSet::fromString("cat");
     * $setB = LiteralSet::fromString("dog");
     * $alternation = $setA->unite($setB);
     * // $alternation->prefixes is now ["cat", "dog"]
     * ```
     */
    public function unite(self $other): self
    {
        // Union of prefixes and suffixes
        $newPrefixes = array_merge($this->prefixes, $other->prefixes);
        $newSuffixes = array_merge($this->suffixes, $other->suffixes);

        // An alternation is complete only if ALL branches are complete
        $newComplete = $this->complete && $other->complete;

        return new self($this->deduplicate($newPrefixes), $this->deduplicate($newSuffixes), $newComplete);
    }

    /**
     * Finds the longest string among all possible prefixes.
     *
     * Purpose: This accessor is useful for optimization. When multiple prefixes are
     * identified (e.g., from an alternation like `(cat|kitten)`), choosing the longest
     * one for a pre-match `strpos` check can sometimes be a more effective heuristic.
     *
     * @return string|null the longest prefix string, or null if no prefixes exist
     */
    public function getLongestPrefix(): ?string
    {
        return $this->getLongestString($this->prefixes);
    }

    /**
     * Finds the longest string among all possible suffixes.
     *
     * Purpose: Similar to `getLongestPrefix`, this accessor is useful for optimizations
     * that can be performed from the end of a string. It finds the longest guaranteed
     * suffix from all the possibilities identified by the extractor.
     *
     * @return string|null the longest suffix string, or null if no suffixes exist
     */
    public function getLongestSuffix(): ?string
    {
        return $this->getLongestString($this->suffixes);
    }

    /**
     * Checks if the set contains any literal information.
     *
     * Purpose: A "void" set is one that represents a part of a regex with no discernible
     * constant strings (e.g., `\w+` or `[a-z]*`). This check allows the extractor logic
     * to identify when a component offers no literal information to contribute.
     *
     * @return bool true if both prefixes and suffixes are empty, false otherwise
     */
    public function isVoid(): bool
    {
        return empty($this->prefixes) && empty($this->suffixes);
    }

    /**
     * @param array<string> $left
     * @param array<string> $right
     *
     * @return array<string>
     */
    private function crossProduct(array $left, array $right): array
    {
        $result = [];
        foreach ($left as $l) {
            foreach ($right as $r) {
                $result[] = $l.$r;
            }
        }

        return $result;
    }

    /**
     * @param array<string> $items
     *
     * @return array<string>
     */
    private function deduplicate(array $items): array
    {
        return array_values(array_unique($items));
    }

    /**
     * @param array<string> $candidates
     */
    private function getLongestString(array $candidates): ?string
    {
        if (empty($candidates)) {
            return null;
        }

        $longest = '';
        foreach ($candidates as $s) {
            if (\strlen($s) > \strlen($longest)) {
                $longest = $s;
            }
        }

        return '' === $longest && !\in_array('', $candidates, true) ? null : $longest;
    }
}
