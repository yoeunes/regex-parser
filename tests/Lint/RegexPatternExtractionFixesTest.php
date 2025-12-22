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

namespace RegexParser\Tests\Lint;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\TokenBasedExtractionStrategy;

/**
 * Focused tests for the regex pattern extraction fixes.
 */
final class RegexPatternExtractionFixesTest extends TestCase
{
    private TokenBasedExtractionStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new TokenBasedExtractionStrategy();
    }

    /**
     * Test that simple regex with flags is extracted correctly.
     */
    public function test_extract_simple_regex_with_m_flag(): void
    {
        $phpCode = "<?php\npreg_match('/pattern/m', \$subject);\n";
        $tempFile = tempnam(sys_get_temp_dir(), 'simple_test');
        file_put_contents($tempFile, $phpCode);

        $results = $this->strategy->extract([$tempFile]);

        $this->assertCount(1, $results, 'Should extract exactly one pattern');
        $this->assertSame('/pattern/m', $results[0]->pattern, 'Pattern with /m flag should be preserved');
    }

    /**
     * Test that regex with multiple flags is extracted correctly.
     */
    public function test_extract_regex_with_multiple_flags(): void
    {
        $phpCode = "<?php\npreg_match('/pattern/mx', \$subject);\n";
        $tempFile = tempnam(sys_get_temp_dir(), 'multi_test');
        file_put_contents($tempFile, $phpCode);

        $results = $this->strategy->extract([$tempFile]);

        $this->assertCount(1, $results, 'Should extract exactly one pattern');
        $this->assertSame('/pattern/mx', $results[0]->pattern, 'Pattern with /mx flags should be preserved');
    }

    /**
     * Test that regex without flags works normally.
     */
    public function test_extract_regex_without_flags(): void
    {
        $phpCode = "<?php\npreg_match('/pattern/', \$subject);\n";
        $tempFile = tempnam(sys_get_temp_dir(), 'no_flags_test');
        file_put_contents($tempFile, $phpCode);

        $results = $this->strategy->extract([$tempFile]);

        $this->assertCount(1, $results, 'Should extract exactly one pattern');
        $this->assertSame('/pattern/', $results[0]->pattern, 'Pattern without flags should work');
    }

    /**
     * Test case from the real-world issue.
     */
    public function test_real_world_case(): void
    {
        $phpCode = "<?php\npreg_replace('/QUICK_CHECK = .*;/m', \"QUICK_CHECK = {\$quickCheck};\", \$fs->readFile(\$file)));\n";
        $tempFile = tempnam(sys_get_temp_dir(), 'real_world_test');
        file_put_contents($tempFile, $phpCode);

        $results = $this->strategy->extract([$tempFile]);

        // The extractor now returns a single normalized pattern for this
        // real-world case; just assert that the QUICK_CHECK pattern with /m
        // flag is present.
        $this->assertCount(1, $results, 'Should extract 1 pattern from real world case');
        $this->assertStringContainsString('/QUICK_CHECK = .*;/m', $results[0]->pattern, 'Pattern should have /m flag');
    }
}
