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
 * Simple test for regex pattern extraction with flags.
 */
final class SimpleRegexExtractionTest extends TestCase
{
    private TokenBasedExtractionStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new TokenBasedExtractionStrategy();
    }

    public function test_extract_regex_with_flags(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'regex_test');
        file_put_contents($testFile, "<?php\npreg_replace('/QUICK_CHECK = .*;/m', \"QUICK_CHECK = {\$quickCheck};\", \$fs->readFile(\$file)));\n");

        $results = $this->strategy->extract([$testFile]);

        $this->assertCount(1, $results, 'Should extract exactly one pattern');

        // Verify pattern includes flags - our improved extraction should detect when flags are actually part of pattern
        $this->assertStringContainsString('/m', $results[0]->pattern);

        unlink($testFile);
    }

    public function test_extract_simple_regex(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'simple_test');
        file_put_contents($testFile, "<?php\npreg_match('/pattern/', \$subject);\n");

        $results = $this->strategy->extract([$testFile]);

        $this->assertCount(1, $results, 'Should extract exactly one pattern');

        // Verify simple pattern without flags
        $this->assertSame('/pattern/', $results[0]->pattern);

        unlink($testFile);
    }
}
