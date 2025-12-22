<?php

declare(strict_types=1);

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

    public function testExtractRegexWithFlags(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'regex_test');
        file_put_contents($testFile, "<?php\npreg_replace('/QUICK_CHECK = .*;/m', \"QUICK_CHECK = {\$quickCheck};\", \$fs->readFile(\$file)));\n");
        
        $results = $this->strategy->extract([$testFile]);
        
        $this->assertCount(1, $results, 'Should extract exactly one pattern');
        
        // Verify pattern includes flags - our improved extraction should detect when flags are actually part of pattern
        $this->assertStringContainsString('/m', $results[0]->pattern);
        
        unlink($testFile);
    }

    public function testExtractSimpleRegex(): void
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