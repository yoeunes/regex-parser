<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Lint;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\TokenBasedExtractionStrategy;

final class TokenBasedExtractionStrategyCustomFunctionTest extends TestCase
{
    public function test_extracts_pattern_from_custom_function(): void
    {
        $strategy = new TokenBasedExtractionStrategy(['myCustomRegex']);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php myCustomRegex("/test/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/test/', $result[0]->pattern);
        $this->assertSame('myCustomRegex()', $result[0]->source);

        unlink($tempFile);
    }

    public function test_extracts_pattern_from_case_insensitive_custom_function(): void
    {
        $strategy = new TokenBasedExtractionStrategy(['MyCustomRegex']);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php mycustomregex("/test/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/test/', $result[0]->pattern);
        $this->assertSame('mycustomregex()', $result[0]->source);

        unlink($tempFile);
    }

    public function test_extracts_pattern_from_fully_qualified_custom_function(): void
    {
        $strategy = new TokenBasedExtractionStrategy(['\MyNamespace\MyRegexFunction']);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php \MyNamespace\MyRegexFunction("/test/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/test/', $result[0]->pattern);
        $this->assertSame('MyRegexFunction()', $result[0]->source);

        unlink($tempFile);
    }

    public function test_extracts_pattern_from_static_method(): void
    {
        $strategy = new TokenBasedExtractionStrategy(['MyClass::validate']);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php MyClass::validate("/test/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/test/', $result[0]->pattern);
        $this->assertSame('MyClass::validate()', $result[0]->source);

        unlink($tempFile);
    }

    public function test_extracts_pattern_from_fully_qualified_static_method(): void
    {
        $strategy = new TokenBasedExtractionStrategy(['\MyNamespace\Validator::check']);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php \MyNamespace\Validator::check("/test/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/test/', $result[0]->pattern);
        $this->assertSame('Validator::check()', $result[0]->source);

        unlink($tempFile);
    }

    public function test_extracts_pattern_from_case_insensitive_static_method(): void
    {
        $strategy = new TokenBasedExtractionStrategy(['MyClass::Validate']);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php Myclass::validate("/test/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/test/', $result[0]->pattern);
        $this->assertSame('Myclass::validate()', $result[0]->source);

        unlink($tempFile);
    }

    public function test_handles_multiple_custom_functions(): void
    {
        $strategy = new TokenBasedExtractionStrategy(['customRegex1', 'customRegex2', 'MyClass::check']);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php
            customRegex1("/pattern1/", $text);
            customRegex2("/pattern2/", $text);
            MyClass::check("/pattern3/", $text);
        ');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(3, $result);
        $this->assertSame('/pattern1/', $result[0]->pattern);
        $this->assertSame('/pattern2/', $result[1]->pattern);
        $this->assertSame('/pattern3/', $result[2]->pattern);

        unlink($tempFile);
    }

    public function test_ignores_empty_custom_function_name(): void
    {
        $strategy = new TokenBasedExtractionStrategy(['', 'myCustomRegex']);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php myCustomRegex("/test/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/test/', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_ignores_non_string_custom_function_names(): void
    {
        $strategy = new TokenBasedExtractionStrategy([123, 'myCustomRegex']);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php myCustomRegex("/test/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertCount(1, $result);
        $this->assertSame('/test/', $result[0]->pattern);

        unlink($tempFile);
    }

    public function test_ignores_namespaced_function_call_not_in_custom_list(): void
    {
        $strategy = new TokenBasedExtractionStrategy(['myFunction']);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php \MyNamespace\myFunction("/test/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertEmpty($result);

        unlink($tempFile);
    }

    public function test_ignores_method_call_not_in_custom_list(): void
    {
        $strategy = new TokenBasedExtractionStrategy(['MyFunction']);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php $obj->MyFunction("/test/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertEmpty($result);

        unlink($tempFile);
    }

    public function test_ignores_nullsafe_method_call(): void
    {
        $strategy = new TokenBasedExtractionStrategy(['MyFunction']);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, '<?php $obj?->MyFunction("/test/", $text);');

        $result = $strategy->extract([$tempFile]);

        $this->assertEmpty($result);

        unlink($tempFile);
    }
}
