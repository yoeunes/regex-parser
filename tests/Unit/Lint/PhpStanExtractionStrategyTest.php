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

namespace RegexParser\Tests\Unit\Lint;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use RegexParser\Lint\PhpStanExtractionStrategy;

final class PhpStanExtractionStrategyTest extends TestCase
{
    private PhpStanExtractionStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new PhpStanExtractionStrategy();
    }

    #[DoesNotPerformAssertions]
    public function test_construct(): void
    {
        $strategy = new PhpStanExtractionStrategy();
    }

    public function test_extract_with_empty_array(): void
    {
        $result = $this->strategy->extract([]);
        $this->assertSame([], $result);
    }

    public function test_extract_with_nonexistent_file(): void
    {
        $result = $this->strategy->extract(['/nonexistent/file.php']);
        $this->assertSame([], $result);
    }

    public function test_extract_with_empty_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test');
        file_put_contents($tempFile, '');

        try {
            $result = $this->strategy->extract([$tempFile]);
            $this->assertSame([], $result);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_extract_with_preg_match(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test');
        $phpCode = '<?php preg_match(\'/test/\', $input);';
        file_put_contents($tempFile, $phpCode);

        try {
            $result = $this->strategy->extract([$tempFile]);

            if ($this->isPhpParserAvailable()) {
                $this->assertCount(1, $result);
                $this->assertSame('/test/', $result[0]->pattern);
                $this->assertSame($tempFile, $result[0]->file);
                $this->assertSame(1, $result[0]->line);
                $this->assertSame('php:preg_match()', $result[0]->source);
            } else {
                $this->assertSame([], $result);
            }
        } finally {
            unlink($tempFile);
        }
    }

    public function test_extract_with_multiple_preg_functions(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test');
        $phpCode = '<?php
            preg_match(\'/pattern1/\', $input);
            preg_replace(\'/pattern2/\', \'replacement\', $input);
            preg_split(\'/pattern3/\', $input);
        ';
        file_put_contents($tempFile, $phpCode);

        try {
            $result = $this->strategy->extract([$tempFile]);

            if ($this->isPhpParserAvailable()) {
                $this->assertCount(3, $result);
                $patterns = array_map(fn ($occurrence) => $occurrence->pattern, $result);
                $this->assertContains('/pattern1/', $patterns);
                $this->assertContains('/pattern2/', $patterns);
                $this->assertContains('/pattern3/', $patterns);
            } else {
                $this->assertSame([], $result);
            }
        } finally {
            unlink($tempFile);
        }
    }

    public function test_extract_with_concatenated_strings(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test');
        $phpCode = '<?php preg_match(\'/test\' . \'ing/\', $input);';
        file_put_contents($tempFile, $phpCode);

        try {
            $result = $this->strategy->extract([$tempFile]);

            if ($this->isPhpParserAvailable()) {
                $this->assertCount(1, $result);
                $this->assertSame('/testing/', $result[0]->pattern);
            } else {
                $this->assertSame([], $result);
            }
        } finally {
            unlink($tempFile);
        }
    }

    public function test_extract_ignores_empty_patterns(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test');
        $phpCode = '<?php preg_match(\'\', $input);';
        file_put_contents($tempFile, $phpCode);

        try {
            $result = $this->strategy->extract([$tempFile]);
            $this->assertSame([], $result);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_extract_ignores_null_patterns(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test');
        $phpCode = '<?php preg_match(null, $input);';
        file_put_contents($tempFile, $phpCode);

        try {
            $result = $this->strategy->extract([$tempFile]);
            $this->assertSame([], $result);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_extract_with_non_preg_function(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test');
        $phpCode = '<?php strpos(\'/test/\', $input);';
        file_put_contents($tempFile, $phpCode);

        try {
            $result = $this->strategy->extract([$tempFile]);
            $this->assertSame([], $result);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_extract_with_complex_concatenation(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test');
        $phpCode = '<?php preg_match(\'/\'. $prefix . \'test\' . $suffix . \'/\', $input);';
        file_put_contents($tempFile, $phpCode);

        try {
            $result = $this->strategy->extract([$tempFile]);
            // Complex concatenation with variables should not extract patterns
            $this->assertSame([], $result);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_extract_with_malformed_php(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test');
        $phpCode = '<?php preg_match(\'/test/\' $input);'; // Missing comma
        file_put_contents($tempFile, $phpCode);

        try {
            $result = $this->strategy->extract([$tempFile]);
            // Malformed PHP should not crash and should return empty
            $this->assertSame([], $result);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_extract_with_multiple_files(): void
    {
        $tempFile1 = tempnam(sys_get_temp_dir(), 'phpstan_test');
        $tempFile2 = tempnam(sys_get_temp_dir(), 'phpstan_test');

        $phpCode1 = '<?php preg_match(\'/test1/\', $input);';
        $phpCode2 = '<?php preg_match(\'/test2/\', $input);';

        file_put_contents($tempFile1, $phpCode1);
        file_put_contents($tempFile2, $phpCode2);

        try {
            $result = $this->strategy->extract([$tempFile1, $tempFile2]);

            if ($this->isPhpParserAvailable()) {
                $this->assertCount(2, $result);
                $patterns = array_map(fn ($occurrence) => $occurrence->pattern, $result);
                $this->assertContains('/test1/', $patterns);
                $this->assertContains('/test2/', $patterns);
            } else {
                $this->assertSame([], $result);
            }
        } finally {
            unlink($tempFile1);
            unlink($tempFile2);
        }
    }

    public function test_extract_with_preg_replace_callback(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test');
        $phpCode = '<?php preg_replace_callback(\'/test/\', $callback, $input);';
        file_put_contents($tempFile, $phpCode);

        try {
            $result = $this->strategy->extract([$tempFile]);

            if ($this->isPhpParserAvailable()) {
                $this->assertCount(1, $result);
                $this->assertSame('/test/', $result[0]->pattern);
                $this->assertSame('php:preg_replace_callback()', $result[0]->source);
            } else {
                $this->assertSame([], $result);
            }
        } finally {
            unlink($tempFile);
        }
    }

    public function test_extract_with_preg_replace_callback_array(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test');
        $phpCode = '<?php preg_replace_callback_array([\'/test/\' => $callback], $input);';
        file_put_contents($tempFile, $phpCode);

        try {
            $result = $this->strategy->extract([$tempFile]);
            // preg_replace_callback_array takes an array as first arg, so no pattern is extracted
            $this->assertSame([], $result);
        } finally {
            unlink($tempFile);
        }
    }

    private function isPhpParserAvailable(): bool
    {
        return class_exists(\PhpParser\ParserFactory::class);
    }
}
