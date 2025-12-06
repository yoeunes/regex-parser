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

namespace RegexParser\Tests\Unit\Bridge\Symfony\Analyzer;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Analyzer\AnalysisIssue;
use RegexParser\Bridge\Symfony\Analyzer\ValidatorRegexAnalyzer;
use Symfony\Component\Validator\Constraints\Regex as SymfonyRegex;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidatorRegexAnalyzerTest extends TestCase
{
    public function test_analyze_detects_invalid_and_complex_constraints(): void
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

        $this->assertCount(3, $issues);
        $this->assertTrue($issues[0] instanceof AnalysisIssue && $issues[0]->isError);
        $this->assertFalse($issues[1]->isError);
        $this->assertFalse($issues[2]->isError);
    }

    public function test_analyze_returns_empty_when_validator_missing(): void
    {
        $analyzer = new ValidatorRegexAnalyzer(\RegexParser\Regex::create(), 10, 20);

        $this->assertSame([], $analyzer->analyze(null, null));
    }

    public function test_analyze_skips_ignored_and_trivial_patterns(): void
    {
        $regex = \RegexParser\Regex::create();
        $analyzer = new ValidatorRegexAnalyzer($regex, warningThreshold: 10, redosThreshold: 20, ignoredPatterns: ['safe']);

        $metadata = new ClassMetadata(DummyValidated::class);
        $metadata->addPropertyConstraint('value', new SymfonyRegex('/safe/')); // ignored via config
        $metadata->addPropertyConstraint('value', new SymfonyRegex('/foo|bar/')); // trivial safe

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

        $this->assertSame([], $analyzer->analyze($validator, $loader));
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
