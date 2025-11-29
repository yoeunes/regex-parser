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

namespace RegexParser\Bridge\Symfony\Service;

use RegexParser\Bridge\Symfony\DataCollector\RegexCollector;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Decorates the Symfony Validator to trace Regex constraint usage.
 *
 * Implements ResetInterface for Swoole/FrankenPHP compatibility.
 */
final readonly class TraceableValidator implements ValidatorInterface, ResetInterface
{
    public function __construct(
        private ValidatorInterface $validator,
        private RegexCollector $collector,
    ) {}

    #[\Override]
    public function getMetadataFor(mixed $value): MetadataInterface
    {
        return $this->validator->getMetadataFor($value);
    }

    #[\Override]
    public function hasMetadataFor(mixed $value): bool
    {
        return $this->validator->hasMetadataFor($value);
    }

    /**
     * @param Constraint|array<Constraint>|null $constraints
     * @param mixed $groups
     */
    #[\Override]
    public function validate(mixed $value, Constraint|array|null $constraints = null, mixed $groups = null): ConstraintViolationListInterface
    {
        $this->collectConstraints(
            \is_array($constraints) ? $constraints : (null === $constraints ? [] : [$constraints]),
            $value,
        );

        return $this->validator->validate($value, $constraints, $groups);
    }

    /**
     * @param mixed $groups
     */
    #[\Override]
    public function validateProperty(object $object, string $propertyName, mixed $groups = null): ConstraintViolationListInterface
    {
        // Extract constraints from property metadata if possible
        try {
            $metadata = $this->validator->getMetadataFor($object);
            $className = $object::class;

            if (method_exists($metadata, 'getPropertyMetadata')) {
                /** @var iterable<object> $propertyMetadata */
                $propertyMetadata = $metadata->getPropertyMetadata($propertyName);
                foreach ($propertyMetadata as $propMeta) {
                    if (method_exists($propMeta, 'getConstraints')) {
                        /** @var array<Constraint> $constraints */
                        $constraints = $propMeta->getConstraints();
                        $this->collectConstraintsWithSource(
                            $constraints,
                            $object->{$propertyName} ?? null,
                            \sprintf('%s::$%s', $className, $propertyName),
                        );
                    }
                }
            }
        } catch (\Throwable) {
            // Never crash the application due to regex collection
        }

        return $this->validator->validateProperty($object, $propertyName, $groups);
    }

    /**
     * @param mixed $groups
     */
    #[\Override]
    public function validatePropertyValue(object|string $objectOrClass, string $propertyName, mixed $value, mixed $groups = null): ConstraintViolationListInterface
    {
        // Extract constraints from property metadata if possible
        try {
            $metadata = $this->validator->getMetadataFor($objectOrClass);
            $className = \is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass;

            if (method_exists($metadata, 'getPropertyMetadata')) {
                /** @var iterable<object> $propertyMetadata */
                $propertyMetadata = $metadata->getPropertyMetadata($propertyName);
                foreach ($propertyMetadata as $propMeta) {
                    if (method_exists($propMeta, 'getConstraints')) {
                        /** @var array<Constraint> $constraints */
                        $constraints = $propMeta->getConstraints();
                        $this->collectConstraintsWithSource(
                            $constraints,
                            $value,
                            \sprintf('%s::$%s', $className, $propertyName),
                        );
                    }
                }
            }
        } catch (\Throwable) {
            // Never crash the application due to regex collection
        }

        return $this->validator->validatePropertyValue($objectOrClass, $propertyName, $value, $groups);
    }

    #[\Override]
    public function startContext(): ContextualValidatorInterface
    {
        return $this->validator->startContext();
    }

    #[\Override]
    public function inContext(ExecutionContextInterface $context): ContextualValidatorInterface
    {
        return $this->validator->inContext($context);
    }

    /**
     * Resets the validator state for long-running processes (Swoole/FrankenPHP).
     */
    #[\Override]
    public function reset(): void
    {
        if ($this->validator instanceof ResetInterface) {
            $this->validator->reset();
        }
    }

    /**
     * @param array<Constraint> $constraints
     */
    private function collectConstraints(array $constraints, mixed $subject): void
    {
        $this->collectConstraintsWithSource($constraints, $subject, 'Validator');
    }

    /**
     * @param array<Constraint> $constraints
     */
    private function collectConstraintsWithSource(array $constraints, mixed $subject, string $source): void
    {
        try {
            $subjectStr = \is_scalar($subject) ? (string) $subject : null;

            foreach ($constraints as $constraint) {
                if ($constraint instanceof Regex && null !== $constraint->pattern) {
                    $this->collector->collectRegex(
                        $constraint->pattern,
                        \sprintf('%s (Regex constraint)', $source),
                        $subjectStr,
                        null, // We don't know the result at this stage
                    );
                }
            }
        } catch (\Throwable) {
            // Never crash the application due to regex collection
        }
    }
}
