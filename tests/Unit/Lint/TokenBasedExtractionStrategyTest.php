<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Lint;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\RegexPatternOccurrence;
use RegexParser\Lint\TokenBasedExtractionStrategy;

final class TokenBasedExtractionStrategyTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir().'/regex-parser-token-test-'.bin2hex(random_bytes(4));
        @mkdir($this->tmp, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            foreach (glob($this->tmp.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->tmp);
        }
    }

    public function test_extracts_preg_match_and_callback_array(): void
    {
        $file = $this->tmp.'/sample.php';
        file_put_contents($file, <<<'PHP'
<?php
$subject = 'bar';
preg_match('/foo/i', $subject);
preg_replace_callback_array(['#bar#' => 'cb'], $subject);
PHP);

        $strategy = new TokenBasedExtractionStrategy();
        $occurrences = $strategy->extract([$file]);

        $patterns = array_map(fn (RegexPatternOccurrence $o) => $o->pattern, $occurrences);
        $this->assertContains('/foo/i', $patterns);
        $this->assertContains('#bar#', $patterns);
    }

    public function test_extracts_custom_function_and_static_method(): void
    {
        $file = $this->tmp.'/custom.php';
        file_put_contents($file, <<<'PHP'
<?php
My\Util::check("/baz/");
myfunc('/qux/');
PHP);

        $strategy = new TokenBasedExtractionStrategy(['My\Util::check', 'myfunc']);
        $occurrences = $strategy->extract([$file]);

        $this->assertSame(['/baz/', '/qux/'], array_map(fn (RegexPatternOccurrence $o) => $o->pattern, $occurrences));
    }

    public function test_handles_unicode_escape_in_pattern(): void
    {
        $file = $this->tmp.'/unicode.php';
        file_put_contents($file, <<<'PHP'
<?php
preg_match('/\x{41}/', $s);
PHP);

        $strategy = new TokenBasedExtractionStrategy();
        $occurrences = $strategy->extract([$file]);

        $patterns = array_map(fn (RegexPatternOccurrence $o) => $o->pattern, $occurrences);
        $this->assertContains('/\x{41}/', $patterns);
    }

    public function test_handles_double_quoted_pattern(): void
    {
        $file = $this->tmp.'/quoted.php';
        file_put_contents($file, <<<'PHP'
<?php
preg_match("/pattern/i", $s);
PHP);

        $strategy = new TokenBasedExtractionStrategy();
        $occurrences = $strategy->extract([$file]);

        $patterns = array_map(fn (RegexPatternOccurrence $o) => $o->pattern, $occurrences);
        $this->assertContains('/pattern/i', $patterns);
    }

    public function test_handles_single_quoted_pattern(): void
    {
        $file = $this->tmp.'/single-quoted.php';
        file_put_contents($file, <<<'PHP'
<?php
preg_match('/pattern/', $s);
PHP);

        $strategy = new TokenBasedExtractionStrategy();
        $occurrences = $strategy->extract([$file]);

        $patterns = array_map(fn (RegexPatternOccurrence $o) => $o->pattern, $occurrences);
        $this->assertContains('/pattern/', $patterns);
    }

    public function test_extracts_plain_string_pattern_when_not_regex_literal(): void
    {
        $file = $this->tmp.'/plain-string.php';
        file_put_contents($file, <<<'PHP'
<?php
preg_match('plainpattern', $subject);
PHP);

        $strategy = new TokenBasedExtractionStrategy();
        $occurrences = $strategy->extract([$file]);

        $this->assertSame('plainpattern', $occurrences[0]->pattern ?? null);
    }
}
