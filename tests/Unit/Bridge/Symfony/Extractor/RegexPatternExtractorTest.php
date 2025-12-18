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

namespace RegexParser\Tests\Unit\Bridge\Symfony\Extractor;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Extractor\ExtractorInterface;
use RegexParser\Bridge\Symfony\Extractor\PhpStanExtractionStrategy;
use RegexParser\Bridge\Symfony\Extractor\RegexPatternExtractor;
use RegexParser\Bridge\Symfony\Extractor\TokenBasedExtractionStrategy;

final class RegexPatternExtractorTest extends TestCase
{
    public function test_delegates_to_injected_extractor(): void
    {
        $mockExtractor = $this->createMock(ExtractorInterface::class);
        $mockExtractor->method('extract')->willReturn(['pattern1', 'pattern2']);

        $extractor = new RegexPatternExtractor($mockExtractor, ['vendor']);

        $result = $extractor->extract(['test.php']);

        $this->assertSame(['pattern1', 'pattern2'], $result);
    }

    public function test_works_with_phpstan_extractor(): void
    {
        $phpstanExtractor = new PhpStanExtractionStrategy();

        $extractor = new RegexPatternExtractor($phpstanExtractor, ['vendor']);

        $result = $extractor->extract(['nonexistent']);

        $this->assertIsArray($result);
    }

    public function test_works_with_token_based_extractor(): void
    {
        $tokenExtractor = new TokenBasedExtractionStrategy();

        $extractor = new RegexPatternExtractor($tokenExtractor, ['vendor']);

        $result = $extractor->extract(['nonexistent']);

        $this->assertIsArray($result);
    }

    public function test_collects_and_filters_php_files(): void
    {
        $mockExtractor = $this->createMock(ExtractorInterface::class);
        $mockExtractor->expects($this->once())
            ->method('extract')
            ->with($this->callback(function ($files) {
                return is_array($files) && count($files) === 1 && str_ends_with($files[0], 'test.php');
            }))
            ->willReturn([]);

        $extractor = new RegexPatternExtractor($mockExtractor, ['excluded']);

        // Create a temporary structure to test file discovery
        $tempDir = sys_get_temp_dir().'/regex_test_'.uniqid();
        mkdir($tempDir, 0777, true);
        mkdir($tempDir.'/excluded', 0777, true);
        
        // Create test files
        file_put_contents($tempDir.'/test.php', '<?php preg_match("/test/", $str);');
        file_put_contents($tempDir.'/excluded/ignored.php', '<?php preg_match("/test/", $str);');
        file_put_contents($tempDir.'/notphp.txt', 'not a php file');

        $result = $extractor->extract([$tempDir]);

        $this->assertIsArray($result);
        
        // Cleanup
        $this->removeDirectory($tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}