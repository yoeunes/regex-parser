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

namespace RegexParser\Bridge\Symfony\DataCollector;

/**
 * A Data Transfer Object (DTO) for holding regex information before analysis.
 *
 * Purpose: This simple, immutable class acts as a temporary container for regex data
 * collected during a request. The `RegexCollector` creates instances of this class
 * in its `collectRegex` method, which is called in the hot path of the request.
 * The expensive analysis is deferred to the `lateCollect` method, which processes
 * these DTOs after the response has been sent. This ensures that profiling does
 * not slow down the application's response time.
 */
readonly class CollectedRegex
{
    /**
     * Creates a new, immutable instance of a collected regex.
     *
     * Purpose: This constructor initializes the DTO with all the contextual information
     * gathered at the point of collection. This data is then used later during the
     * analysis phase to provide a rich report in the Symfony Web Profiler.
     *
     * @param string      $pattern     The full PCRE regex pattern string that was used.
     * @param string      $source      The origin of the regex (e.g., "Router", "Validator", "Custom Check").
     *                                 This helps developers identify where the regex is being used.
     * @param string|null $subject     The subject string that the regex was tested against, if available.
     *                                 This is useful for debugging and understanding the context of the match.
     * @param bool|null   $matchResult The result of the `preg_match` operation (`true` for a match, `false` for
     *                                 no match), if available. This can help diagnose unexpected behavior.
     */
    public function __construct(
        public string $pattern,
        public string $source,
        public ?string $subject,
        public ?bool $matchResult,
    ) {}
}
