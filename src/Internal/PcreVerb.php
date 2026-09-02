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

namespace RegexParser\Internal;

use RegexParser\Node\GroupType;

/**
 * What "(*...)" holds.
 *
 * Most of these are backtracking verbs — (*FAIL), (*SKIP), (*MARK:name) —
 * but PCRE also spells three other things this way: the alphabetic form of a
 * lookaround, a script run, and the match limit. Telling them apart is
 * string work on the text between the parentheses.
 *
 * @internal
 */
final readonly class PcreVerb
{
    /**
     * PCRE2 10.32+ alphabetic assertion verbs and their group equivalents.
     */
    private const ASSERTIONS = [
        'positive_lookahead' => GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
        'pla' => GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
        'negative_lookahead' => GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
        'nla' => GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
        'positive_lookbehind' => GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
        'plb' => GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
        'negative_lookbehind' => GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
        'nlb' => GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
        'atomic' => GroupType::T_GROUP_ATOMIC,
    ];

    /**
     * The two spellings of a script run.
     */
    private const SCRIPT_RUN_PREFIXES = ['script_run:', 'sr:'];

    private function __construct(
        /**
         * The verb as it should be recorded, which is not what was written
         * when the pattern used the "(*:name)" shorthand for a mark.
         */
        public string $name,
        /**
         * The group an alphabetic assertion stands for, or null.
         */
        public ?GroupType $assertion = null,
        /**
         * The sub-pattern an assertion or a script run wraps, or null.
         */
        public ?string $payload = null,
        /**
         * The limit "(*LIMIT_MATCH=n)" sets, or null.
         */
        public ?int $matchLimit = null,
        /**
         * Where the payload starts, relative to the verb text.
         */
        public int $payloadOffset = 0,
    ) {}

    public static function read(string $verb): self
    {
        // "(*:name)" and "(*=name)" are shorthands for a mark.
        if ('' !== $verb && (str_starts_with($verb, ':') || str_starts_with($verb, '='))) {
            $verb = 'MARK'.$verb;
        }

        $colon = strpos($verb, ':');
        if (false !== $colon) {
            $assertion = self::ASSERTIONS[strtolower(substr($verb, 0, $colon))] ?? null;
            if (null !== $assertion) {
                return new self($verb, $assertion, substr($verb, $colon + 1), null, $colon + 1);
            }
        }

        $matches = [];
        if (preg_match('/^LIMIT_MATCH=(\d++)$/i', $verb, $matches)) {
            return new self($verb, null, null, (int) $matches[1]);
        }

        $lower = strtolower($verb);
        foreach (self::SCRIPT_RUN_PREFIXES as $prefix) {
            if (!str_starts_with($lower, $prefix)) {
                continue;
            }

            $payload = substr($verb, \strlen($prefix));
            if ('' !== $payload) {
                return new self($verb, null, $payload, null, \strlen($prefix));
            }
        }

        return new self($verb);
    }

    public function isScriptRun(): bool
    {
        return null === $this->assertion && null !== $this->payload;
    }
}
