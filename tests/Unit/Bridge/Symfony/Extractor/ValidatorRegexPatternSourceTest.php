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

final class ValidatorRegexPatternSourceTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(\Symfony\Component\Validator\Validator\ValidatorInterface::class)) {
            $this->markTestSkipped('Symfony Validator component is not available');
        }
    }

    public function test_construct(): void
    {
        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource();
        $this->assertInstanceOf(\RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource::class, $source);
    }

    public function test_construct_with_validator(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $loader = $this->createLoaderStub();

        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator, $loader);
        $this->assertInstanceOf(\RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource::class, $source);
    }

    public function test_get_name(): void
    {
        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource();
        $this->assertSame('validators', $source->getName());
    }

    public function test_is_supported_returns_false_when_no_validator(): void
    {
        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource();
        $this->assertFalse($source->isSupported());
    }

    public function test_is_supported_returns_false_when_no_loader(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator);
        $this->assertFalse($source->isSupported());
    }

    public function test_is_supported_returns_true_when_both_present(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $loader = $this->createLoaderStub();

        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator, $loader);
        $this->assertTrue($source->isSupported());
    }

    public function test_extract_returns_empty_when_not_supported(): void
    {
        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource();
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertSame([], $result);
    }

    public function test_extract_with_empty_mapped_classes(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $loader = $this->createLoaderStub([]);

        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator, $loader);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertSame([], $result);
    }

    public function test_extract_with_regex_constraint(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $loader = $this->createLoaderStub([TestEntity::class]);

        // Mock metadata for a class
        $metadata = $this->createMock(\Symfony\Component\Validator\Mapping\ClassMetadataInterface::class);
        $metadata->method('getConstraints')->willReturn([]);
        $metadata->method('getConstrainedProperties')->willReturn(['email']);

        $propertyMetadata = $this->createMock(\Symfony\Component\Validator\Mapping\PropertyMetadataInterface::class);
        $propertyMetadata->method('getPropertyName')->willReturn('email');
        $propertyMetadata->method('getConstraints')->willReturn([
            new \Symfony\Component\Validator\Constraints\Regex(pattern: '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'),
        ]);

        $metadata->method('getPropertyMetadata')->willReturn([$propertyMetadata]);
        $validator->method('getMetadataFor')->willReturn($metadata);
        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator, $loader);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertCount(1, $result);
        $this->assertSame('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $result[0]->pattern);
        $this->assertSame(__FILE__, $result[0]->file);
        $this->assertSame('validator:'.TestEntity::class.'::$email', $result[0]->source);
    }

    public function test_extract_with_class_level_regex_constraint(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $loader = $this->createLoaderStub([TestEntity::class]);

        $regexConstraint = new \Symfony\Component\Validator\Constraints\Regex(pattern: '/^valid$/');

        $metadata = $this->createMock(\Symfony\Component\Validator\Mapping\ClassMetadataInterface::class);
        $metadata->method('getConstraints')->willReturn([$regexConstraint]);
        $metadata->method('getConstrainedProperties')->willReturn([]);

        $validator->method('getMetadataFor')->willReturn($metadata);
        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator, $loader);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertCount(1, $result);
        $this->assertSame('/^valid$/', $result[0]->pattern);
        $this->assertSame('validator:'.TestEntity::class, $result[0]->source);
    }

    public function test_extract_ignores_null_patterns(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $loader = $this->createLoaderStub([TestEntity::class]);

        $regexConstraint = new \Symfony\Component\Validator\Constraints\Regex(pattern: '/placeholder/');
        $regexConstraint->pattern = null;

        $metadata = $this->createMock(\Symfony\Component\Validator\Mapping\ClassMetadataInterface::class);
        $metadata->method('getConstraints')->willReturn([$regexConstraint]);
        $metadata->method('getConstrainedProperties')->willReturn([]);

        $validator->method('getMetadataFor')->willReturn($metadata);
        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator, $loader);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertSame([], $result);
    }

    public function test_extract_ignores_empty_patterns(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $loader = $this->createLoaderStub([TestEntity::class]);

        $regexConstraint = new \Symfony\Component\Validator\Constraints\Regex(pattern: '');

        $metadata = $this->createMock(\Symfony\Component\Validator\Mapping\ClassMetadataInterface::class);
        $metadata->method('getConstraints')->willReturn([$regexConstraint]);
        $metadata->method('getConstrainedProperties')->willReturn([]);

        $validator->method('getMetadataFor')->willReturn($metadata);
        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator, $loader);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertSame([], $result);
    }

    public function test_extract_ignores_non_regex_constraints(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $loader = $this->createLoaderStub([TestEntity::class]);

        $notConstraint = new \Symfony\Component\Validator\Constraints\NotBlank();

        $metadata = $this->createMock(\Symfony\Component\Validator\Mapping\ClassMetadataInterface::class);
        $metadata->method('getConstraints')->willReturn([$notConstraint]);
        $metadata->method('getConstrainedProperties')->willReturn([]);

        $validator->method('getMetadataFor')->willReturn($metadata);
        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator, $loader);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertSame([], $result);
    }

    public function test_extract_handles_metadata_exceptions(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $loader = $this->createLoaderStub([TestEntity::class]);

        $validator->method('getMetadataFor')->willThrowException(new \Exception('Metadata not found'));
        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator, $loader);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertSame([], $result);
    }

    public function test_extract_handles_invalid_mapped_classes(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $loader = $this->createLoaderStub([
            TestEntity::class, // Valid
            '', // Empty string
            null, // Null
            123, // Not a string
        ]);

        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator, $loader);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        // Should not crash and return empty since metadata retrieval will fail
        $this->assertSame([], $result);
    }

    public function test_extract_with_class_file(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $loader = $this->createLoaderStub([TestEntity::class]);

        $metadata = $this->createMock(\Symfony\Component\Validator\Mapping\ClassMetadataInterface::class);
        $metadata->method('getConstraints')->willReturn([
            new \Symfony\Component\Validator\Constraints\Regex(pattern: '/test/'),
        ]);
        $metadata->method('getConstrainedProperties')->willReturn([]);

        $validator->method('getMetadataFor')->willReturn($metadata);
        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator, $loader);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertCount(1, $result);
        $this->assertStringEndsWith('ValidatorRegexPatternSourceTest.php', $result[0]->file);
    }

    public function test_extract_handles_loader_exceptions(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $loader = $this->createLoaderStub([], new \Exception('Loader error'));

        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator, $loader);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertSame([], $result);
    }

    public function test_extract_handles_loader_without_method(): void
    {
        $validator = $this->createMock(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
        $loader = $this->createLoaderWithoutMappedClasses();

        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource($validator, $loader);
        $context = new \RegexParser\Lint\RegexPatternSourceContext(['.'], []);

        $result = $source->extract($context);

        $this->assertSame([], $result);
    }

    public function test_extract_skips_when_symfony_not_available(): void
    {
        if (interface_exists(\Symfony\Component\Validator\Validator\ValidatorInterface::class)) {
            $this->markTestSkipped('Symfony Validator is available, skipping test');
        }

        // This test will only run when Symfony is not available
        $source = new \RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource();
        $this->assertFalse($source->isSupported());
        $this->assertSame('validators', $source->getName());
    }

    private function createLoaderStub(array $mappedClasses = [], ?\Throwable $exception = null): \Symfony\Component\Validator\Mapping\Loader\LoaderInterface
    {
        return new class($mappedClasses, $exception) implements \Symfony\Component\Validator\Mapping\Loader\LoaderInterface {
            public function __construct(
                private readonly array $mappedClasses,
                private readonly ?\Throwable $exception,
            ) {}

            public function loadClassMetadata(\Symfony\Component\Validator\Mapping\ClassMetadata $metadata): bool
            {
                return true;
            }

            public function getMappedClasses(): array
            {
                if (null !== $this->exception) {
                    throw $this->exception;
                }

                return $this->mappedClasses;
            }
        };
    }

    private function createLoaderWithoutMappedClasses(): \Symfony\Component\Validator\Mapping\Loader\LoaderInterface
    {
        return new class implements \Symfony\Component\Validator\Mapping\Loader\LoaderInterface {
            public function loadClassMetadata(\Symfony\Component\Validator\Mapping\ClassMetadata $metadata): bool
            {
                return true;
            }
        };
    }
}

/**
 * Test entity class for testing validator extraction.
 */
class TestEntity
{
    public string $email;
}
