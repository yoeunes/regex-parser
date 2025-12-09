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

namespace RegexParser\Tests\Bridge\Symfony\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use RegexParser\Bridge\Symfony\Analyzer\ValidatorRegexAnalyzer;
use RegexParser\Bridge\Symfony\CacheWarmer\RegexParserCacheWarmer;
use RegexParser\Regex;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Regex as SymfonyRegex;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegexParserCacheWarmerTest extends TestCase
{
    public function test_warm_up_logs_issues(): void
    {
        $loggedRecords = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('log')->willReturnCallback(function ($level, $message, $context = []) use (&$loggedRecords): void {
            $loggedRecords[] = ['level' => (string) $level, 'message' => (string) $message];
        });
        $analyzer = new RouteRequirementAnalyzer(Regex::create(), 0, 0);
        $warmup = new RegexParserCacheWarmer($analyzer, new RouteCollectionRouterWithIssue(), $logger);

        $warmup->warmUp(sys_get_temp_dir());

        $this->assertNotEmpty($loggedRecords);
        $this->assertSame('error', $loggedRecords[0]['level']);
    }

    public function test_warm_up_is_optional(): void
    {
        $analyzer = new RouteRequirementAnalyzer(Regex::create(), 0, 0);
        $warmup = new RegexParserCacheWarmer($analyzer, null, null);

        $this->assertTrue($warmup->isOptional());
        $this->assertSame([], $warmup->warmUp(sys_get_temp_dir()));
    }

    public function test_warm_up_logs_validator_issues(): void
    {
        $loggedRecords = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('log')->willReturnCallback(function ($level, $message, $context = []) use (&$loggedRecords): void {
            $loggedRecords[] = ['level' => (string) $level, 'message' => (string) $message];
        });
        $validatorAnalyzer = new ValidatorRegexAnalyzer(Regex::create(), 0, 1000);
        $validator = new FakeValidator([new SymfonyRegex(pattern: '(')]);
        $loader = new FakeLoader([FakeValidator::class]);

        $warmup = new RegexParserCacheWarmer(
            new RouteRequirementAnalyzer(Regex::create(), 0, 0),
            null,
            $logger,
            $validatorAnalyzer,
            $validator,
            $loader,
        );

        $warmup->warmUp(sys_get_temp_dir());

        $this->assertNotEmpty($loggedRecords);
        $this->assertSame('error', $loggedRecords[0]['level']);
    }
}

final class RouteCollectionRouterWithIssue implements RouterInterface
{
    public function setContext(RequestContext $context): void {}

    public function getContext(): RequestContext
    {
        return new RequestContext();
    }

    public function getRouteCollection(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo', [], ['slug' => '(']));

        return $collection;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function match(string $pathinfo): array
    {
        return [];
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
