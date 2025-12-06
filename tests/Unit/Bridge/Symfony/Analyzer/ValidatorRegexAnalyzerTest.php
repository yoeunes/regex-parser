<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Bridge\Symfony\Analyzer;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Analyzer\AnalysisIssue;
use RegexParser\Bridge\Symfony\Analyzer\ValidatorRegexAnalyzer;
use RegexParser\Tests\Unit\Support\StubRegex;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Constraints\Regex as SymfonyRegex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidatorRegexAnalyzerTest extends TestCase
{
    public function testAnalyzeDetectsInvalidAndComplexConstraints(): void
    {
        $regex = \RegexParser\Regex::create();

        $metadata = new ClassMetadata(DummyValidated::class);
        $metadata->addPropertyConstraint('value', new SymfonyRegex('/(/')); // invalid
        $metadata->addPropertyConstraint('value', new SymfonyRegex('/bar/')); // valid
        $metadata->addPropertyConstraint('other', new SymfonyRegex('/baz/')); // valid

        $validator = new StubValidator(['class' => $metadata]);
        $loader = new class implements LoaderInterface {
            public function loadClassMetadata(ClassMetadata $metadata): bool
            {
                return true;
            }

            public function getMappedClasses(): array
            {
                return [DummyValidated::class];
            }
        };

        $analyzer = new ValidatorRegexAnalyzer($regex, warningThreshold: 0, redosThreshold: 100000);
        $issues = $analyzer->analyze($validator, $loader);

        self::assertCount(3, $issues);
        self::assertTrue($issues[0] instanceof AnalysisIssue && $issues[0]->isError);
        self::assertFalse($issues[1]->isError);
        self::assertFalse($issues[2]->isError);
    }

    public function testAnalyzeReturnsEmptyWhenValidatorMissing(): void
    {
        $analyzer = new ValidatorRegexAnalyzer(\RegexParser\Regex::create(), 10, 20);

        self::assertSame([], $analyzer->analyze(null, null));
    }
}

final class DummyValidated
{
    public string $value = '';
    public string $other = '';
}

final class StubValidator implements ValidatorInterface
{
    /**
     * @param array<string, ClassMetadata> $metadata
     */
    public function __construct(private array $metadata) {}

    public function getMetadataFor(mixed $value): ClassMetadata
    {
        return $this->metadata['class'];
    }

    public function hasMetadataFor(mixed $value): bool
    {
        return true;
    }

    public function validateProperty(object $object, string $property, mixed $groups = null): ConstraintViolationListInterface
    {
        throw new \LogicException('Not needed for tests');
    }

    public function validatePropertyValue(object|string $objectOrClass, string $property, mixed $value, mixed $groups = null): ConstraintViolationListInterface
    {
        throw new \LogicException('Not needed for tests');
    }

    public function validate(mixed $value, mixed $constraints = null, mixed $groups = null): ConstraintViolationListInterface
    {
        throw new \LogicException('Not needed for tests');
    }

    public function startContext(): ContextualValidatorInterface
    {
        throw new \LogicException('Not needed for tests');
    }

    public function inContext(ExecutionContextInterface $context): ContextualValidatorInterface
    {
        throw new \LogicException('Not needed for tests');
    }
}
