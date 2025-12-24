<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Lint;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\TokenBasedExtractionStrategy;

final class TokenBasedExtractionStrategyEscapeParsingTest extends TestCase
{
    public function test_extracts_pattern_with_hex_escape(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\x41/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertNotEmpty($result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_hex_escape_braced(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\x{41}/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertNotEmpty($result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_unicode_escape(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\u{41}/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertNotEmpty($result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_octal_escape(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\101/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertNotEmpty($result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_brace_delimiter_and_flags(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("{test}iu", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('{test}iu', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_tilde_delimiter(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("~test~", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('~test~', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_hash_delimiter(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("#test#", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('#test#', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_percent_delimiter(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("%test%", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('%test%', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_handles_empty_file(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '');

        $result = $strategy->extract([$tempFile]);

        $this->assertEmpty($result);

        unlink($tempFile);
    }

    public function test_handles_file_with_only_whitespace(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '   ');

        $result = $strategy->extract([$tempFile]);

        $this->assertEmpty($result);

        unlink($tempFile);
    }

    public function test_handles_file_with_comments_only(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, "<?php\n// Comment\n/* Another comment */\n");

        $result = $strategy->extract([$tempFile]);

        $this->assertEmpty($result);

        unlink($tempFile);
    }
}
