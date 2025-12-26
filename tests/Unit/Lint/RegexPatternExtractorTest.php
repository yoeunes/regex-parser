<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Lint;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\RegexPatternExtractor;
use RegexParser\Lint\ExtractorInterface;
use RegexParser\Lint\RegexPatternOccurrence;

final class RegexPatternExtractorTest extends TestCase
{
    private ExtractorInterface $extractor;
    private RegexPatternExtractor $patternExtractor;

    protected function setUp(): void
    {
        $this->extractor = $this->createMock(ExtractorInterface::class);
        $this->patternExtractor = new RegexPatternExtractor($this->extractor);
    }

    public function test_supports_parallel(): void
    {
        $actual = RegexPatternExtractor::supportsParallel();

        $this->assertIsBool($actual);
    }

    public function test_extract_with_empty_paths(): void
    {
        $this->extractor->expects($this->any())->method('extract')->willReturn([]);

        $result = $this->patternExtractor->extract([]);

        $this->assertSame([], $result);
    }

    public function test_extract_with_default_exclude_paths(): void
    {
        $this->extractor->expects($this->any())->method('extract')->willReturn([]);

        $result = $this->patternExtractor->extract(['src/']);

        $this->assertSame([], $result);
    }

    public function test_extract_with_custom_exclude_paths(): void
    {
        $this->extractor->expects($this->any())->method('extract')->willReturn([]);

        $result = $this->patternExtractor->extract(['src/'], ['tests', 'vendor']);

        $this->assertSame([], $result);
    }

    public function test_extract_with_progress_callback(): void
    {
        $occurrences = [
            new RegexPatternOccurrence('/test1/', 'file1.php', 1, 'source1'),
            new RegexPatternOccurrence('/test2/', 'file2.php', 2, 'source2'),
        ];

        $this->extractor->expects($this->any())->method('extract')->willReturn($occurrences);

        $progressCalls = [];
        $result = $this->patternExtractor->extract(
            ['file1.php', 'file2.php'],
            null,
            function (int $current, int $total) use (&$progressCalls): void {
                $progressCalls[] = ['current' => $current, 'total' => $total];
            }
        );

        $this->assertCount(2, $result);
        $this->assertCount(3, $progressCalls);
    }

    public function test_extract_with_empty_result_reports_progress(): void
    {
        $this->extractor->expects($this->any())->method('extract')->willReturn([]);

        $progressCalls = [];
        $result = $this->patternExtractor->extract(
            ['src/'],
            null,
            function (int $current, int $total) use (&$progressCalls): void {
                $progressCalls[] = ['current' => $current, 'total' => $total];
            }
        );

        $this->assertSame([], $result);
        $this->assertNotEmpty($progressCalls);
    }

    public function test_extract_with_workers_greater_than_one(): void
    {
        $occurrence = new RegexPatternOccurrence('/test/', 'file.php', 1, 'source');
        $this->extractor->expects($this->any())->method('extract')->willReturn([$occurrence]);

        $result = $this->patternExtractor->extract(['file.php'], null, null, 2);

        $this->assertCount(1, $result);
    }

    public function test_writeWorkerPayload_creates_serialized_file(): void
    {
        $reflection = new \ReflectionClass($this->patternExtractor);
        $method = $reflection->getMethod('writeWorkerPayload');
        $method->setAccessible(true);

        $tmpFile = sys_get_temp_dir() . '/test_payload_' . uniqid();
        $payload = ['ok' => true, 'result' => ['test']];

        $method->invoke($this->patternExtractor, $tmpFile, $payload);

        $this->assertFileExists($tmpFile);
        $content = file_get_contents($tmpFile);
        $unserialized = unserialize($content);
        $this->assertSame($payload['ok'], $unserialized['ok']);

        @unlink($tmpFile);
    }

    public function test_readWorkerPayload_reads_valid_payload(): void
    {
        $reflection = new \ReflectionClass($this->patternExtractor);
        $method = $reflection->getMethod('readWorkerPayload');
        $method->setAccessible(true);

        $tmpFile = sys_get_temp_dir() . '/test_read_payload_' . uniqid();
        $payload = ['ok' => true, 'result' => ['test']];
        file_put_contents($tmpFile, serialize($payload));

        $result = $method->invoke($this->patternExtractor, $tmpFile);

        $this->assertTrue($result['ok']);
        $this->assertSame(['test'], $result['result']);

        @unlink($tmpFile);
    }

    public function test_readWorkerPayload_handles_missing_file(): void
    {
        $reflection = new \ReflectionClass($this->patternExtractor);
        $method = $reflection->getMethod('readWorkerPayload');
        $method->setAccessible(true);

        $nonExistentFile = sys_get_temp_dir() . '/non_existent_' . uniqid();

        $result = $method->invoke($this->patternExtractor, $nonExistentFile);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_readWorkerPayload_handles_invalid_serialization(): void
    {
        $reflection = new \ReflectionClass($this->patternExtractor);
        $method = $reflection->getMethod('readWorkerPayload');
        $method->setAccessible(true);

        $tmpFile = sys_get_temp_dir() . '/test_invalid_payload_' . uniqid();
        file_put_contents($tmpFile, 'not valid serialized data');

        $result = $method->invoke($this->patternExtractor, $tmpFile);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('error', $result);

        @unlink($tmpFile);
    }

    public function test_readWorkerPayload_handles_ok_false(): void
    {
        $reflection = new \ReflectionClass($this->patternExtractor);
        $method = $reflection->getMethod('readWorkerPayload');
        $method->setAccessible(true);

        $tmpFile = sys_get_temp_dir() . '/test_error_payload_' . uniqid();
        $payload = ['ok' => false, 'error' => ['message' => 'Test error', 'class' => 'Exception']];
        file_put_contents($tmpFile, serialize($payload));

        $result = $method->invoke($this->patternExtractor, $tmpFile);

        $this->assertFalse($result['ok']);
        $this->assertSame('Test error', $result['error']['message']);
        $this->assertSame('Exception', $result['error']['class']);

        @unlink($tmpFile);
    }

    public function test_readWorkerPayload_handles_invalid_error_payload(): void
    {
        $reflection = new \ReflectionClass($this->patternExtractor);
        $method = $reflection->getMethod('readWorkerPayload');
        $method->setAccessible(true);

        $tmpFile = sys_get_temp_dir() . '/test_bad_error_payload_' . uniqid();
        $payload = ['ok' => false, 'error' => 'invalid'];
        file_put_contents($tmpFile, serialize($payload));

        $result = $method->invoke($this->patternExtractor, $tmpFile);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('error', $result);

        @unlink($tmpFile);
    }

    public function test_isTemplateFile_identifies_blade(): void
    {
        $reflection = new \ReflectionClass($this->patternExtractor);
        $method = $reflection->getMethod('isTemplateFile');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->patternExtractor, 'view.blade.php'));
    }

    public function test_isTemplateFile_identifies_twig(): void
    {
        $reflection = new \ReflectionClass($this->patternExtractor);
        $method = $reflection->getMethod('isTemplateFile');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->patternExtractor, 'template.twig.php'));
    }

    public function test_isTemplateFile_identifies_tpl(): void
    {
        $reflection = new \ReflectionClass($this->patternExtractor);
        $method = $reflection->getMethod('isTemplateFile');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->patternExtractor, 'file.tpl.php'));
    }

    public function test_isTemplateFile_identifies_regular_php(): void
    {
        $reflection = new \ReflectionClass($this->patternExtractor);
        $method = $reflection->getMethod('isTemplateFile');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($this->patternExtractor, 'regular.php'));
    }
}
