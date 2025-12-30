<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Lint;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\RegexPatternOccurrence;
use RegexParser\Lint\TokenBasedExtractionStrategy;

final class TokenBasedExtractionStrategyTest extends TestCase
{

    public function test_extracts_preg_match_and_callback_array(): void
    {
        $file = __DIR__.'/../../Fixtures/Extractor/preg_match_and_callback_array.php';

        $strategy = new TokenBasedExtractionStrategy();
        $occurrences = $strategy->extract([$file]);

        $patterns = array_map(fn (RegexPatternOccurrence $o) => $o->pattern, $occurrences);
        $this->assertContains('/foo/i', $patterns);
        $this->assertContains('#bar#', $patterns);
    }

    public function test_extracts_custom_function_and_static_method(): void
    {
        $file = __DIR__.'/../../Fixtures/Extractor/custom_function_and_static_method.php';

        $strategy = new TokenBasedExtractionStrategy(['My\Util::check', 'myfunc']);
        $occurrences = $strategy->extract([$file]);

        $this->assertSame(['/baz/', '/qux/'], array_map(fn (RegexPatternOccurrence $o) => $o->pattern, $occurrences));
    }

    public function test_handles_unicode_escape_in_pattern(): void
    {
        $file = __DIR__.'/../../Fixtures/Extractor/unicode_escape.php';

        $strategy = new TokenBasedExtractionStrategy();
        $occurrences = $strategy->extract([$file]);

        $patterns = array_map(fn (RegexPatternOccurrence $o) => $o->pattern, $occurrences);
        $this->assertContains('/\x{41}/', $patterns);
    }

    public function test_handles_double_quoted_pattern(): void
    {
        $file = __DIR__.'/../../Fixtures/Extractor/double_quoted_pattern.php';

        $strategy = new TokenBasedExtractionStrategy();
        $occurrences = $strategy->extract([$file]);

        $patterns = array_map(fn (RegexPatternOccurrence $o) => $o->pattern, $occurrences);
        $this->assertContains('/pattern/i', $patterns);
    }

    public function test_handles_single_quoted_pattern(): void
    {
        $file = __DIR__.'/../../Fixtures/Extractor/single_quoted_pattern.php';

        $strategy = new TokenBasedExtractionStrategy();
        $occurrences = $strategy->extract([$file]);

        $patterns = array_map(fn (RegexPatternOccurrence $o) => $o->pattern, $occurrences);
        $this->assertContains('/pattern/', $patterns);
    }

    public function test_extracts_plain_string_pattern_when_not_regex_literal(): void
    {
        $file = __DIR__.'/../../Fixtures/Extractor/plain_string_pattern.php';

        $strategy = new TokenBasedExtractionStrategy();
        $occurrences = $strategy->extract([$file]);

        $this->assertSame('plainpattern', $occurrences[0]->pattern ?? null);
    }
}
