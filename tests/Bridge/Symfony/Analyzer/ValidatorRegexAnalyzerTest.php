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

namespace RegexParser\Tests\Bridge\Symfony\Analyzer;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Analyzer\ValidatorRegexAnalyzer;
use RegexParser\Regex;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Regex as SymfonyRegex;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidatorRegexAnalyzerTest extends TestCase
{
    public function test_analyzer_reports_invalid_and_warning_patterns(): void
    {
        $validator = new FakeValidator([
            new SymfonyRegex(pattern: '('), // invalid
            new SymfonyRegex(pattern: '/[a-z]+/'), // warning (score >= 0)
        ]);

        $loader = new FakeLoader([FakeEntity::class]);

        $analyzer = new ValidatorRegexAnalyzer(Regex::create(), 0, 'high');

        $issues = $analyzer->analyze($validator, $loader);

        $this->assertCount(2, $issues);
        $this->assertTrue($issues[0]->isError);
        $this->assertFalse($issues[1]->isError);
    }

    public function test_literal_alternation_is_ignored_for_warnings(): void
    {
        $validator = new FakeValidator([
            new SymfonyRegex(pattern: '#^en|fr|de$#'),
        ]);

        $loader = new FakeLoader([FakeEntity::class]);

        $analyzer = new ValidatorRegexAnalyzer(Regex::create(), 0, 'high');

        $issues = $analyzer->analyze($validator, $loader);

        $this->assertSame([], $issues);
    }
}

final class FakeEntity
{
    public string $name = '';
}

final readonly class FakeLoader implements LoaderInterface
{
    /**
     * @param list<string> $classes
     */
    public function __construct(private array $classes) {}

    public function loadClassMetadata(\Symfony\Component\Validator\Mapping\ClassMetadata $metadata): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    public function getMappedClasses(): array
    {
        return $this->classes;
    }
}

final readonly class FakeValidator implements ValidatorInterface
{
    private ClassMetadata $metadata;

    /**
     * @param list<SymfonyRegex> $constraints
     */
    public function __construct(array $constraints)
    {
        $this->metadata = new ClassMetadata(FakeEntity::class);
        foreach ($constraints as $constraint) {
            $this->metadata->addPropertyConstraint('name', $constraint);
        }
    }

    public function getMetadataFor(mixed $value): ClassMetadata
    {
        return $this->metadata;
    }

    public function hasMetadataFor(mixed $value): bool
    {
        return true;
    }

    public function validate(mixed $value, Constraint|array|null $constraints = null, GroupSequence|array|string|null $groups = null): ConstraintViolationList
    {
        return new ConstraintViolationList();
    }

    public function validateProperty(object $object, string $propertyName, GroupSequence|array|string|null $groups = null): ConstraintViolationList
    {
        return new ConstraintViolationList();
    }

    public function validatePropertyValue(object|string $objectOrClass, string $propertyName, mixed $value, GroupSequence|array|string|null $groups = null): ConstraintViolationList
    {
        return new ConstraintViolationList();
    }

    public function startContext(): ContextualValidatorInterface
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function inContext(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): ContextualValidatorInterface
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}
