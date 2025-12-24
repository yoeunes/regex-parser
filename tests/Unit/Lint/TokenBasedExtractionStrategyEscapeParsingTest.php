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
        $this->assertSame('/A', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_hex_escape_braced(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\x{41}/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/A', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_unicode_escape(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\u{41}/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/A', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_octal_escape(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\101/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/A', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_newline_escape(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\n/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/\n', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_carriage_return_escape(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\r/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/\r', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_tab_escape(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\t/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/\t', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_vertical_tab_escape(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\v/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/\v', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_escape_escape(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\\\\/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/\\', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_dollar_sign_escape(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\$/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/\$', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_form_feed_escape(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\f/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/\f', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_double_quote_escape(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match("/\"/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/"', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_extracts_pattern_with_escaped_quote_in_single_quoted_string(): void
    {
        $strategy = new TokenBasedExtractionStrategy();

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php preg_match(\'/\\\'/\', $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/\'/', $result[0]->pattern);

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
