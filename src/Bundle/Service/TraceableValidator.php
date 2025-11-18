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

namespace RegexParser\Bundle\Service;

use RegexParser\Bundle\DataCollector\RegexCollector;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Decorates the Symfony Validator to trace Regex constraint usage.
 */
class TraceableValidator implements ValidatorInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly RegexCollector $collector,
    ) {}

    public function getMetadataFor(mixed $value): \Symfony\Component\Validator\Mapping\MetadataInterface
    {
        return $this->validator->getMetadataFor($value);
    }

    public function hasMetadataFor(mixed $value): bool
    {
        return $this->validator->hasMetadataFor($value);
    }

    public function validate(mixed $value, Constraint|array|null $constraints = null, $groups = null): ConstraintViolationListInterface
    {
        $this->collectConstraints(
            \is_array($constraints) ? $constraints : (null === $constraints ? [] : [$constraints]),
            $value,
        );

        return $this->validator->validate($value, $constraints, $groups);
    }

    public function validateProperty(object $object, string $propertyName, $groups = null): ConstraintViolationListInterface
    {
        // We cannot easily get the constraints here, so we rely on validate()
        return $this->validator->validateProperty($object, $propertyName, $groups);
    }

    public function validatePropertyValue(object|string $objectOrClass, string $propertyName, mixed $value, $groups = null): ConstraintViolationListInterface
    {
        // We cannot easily get the constraints here, so we rely on validate()
        return $this->validator->validatePropertyValue($objectOrClass, $propertyName, $value, $groups);
    }

    public function startContext(): ContextualValidatorInterface
    {
        return $this->validator->startContext();
    }

    public function inContext(ExecutionContextInterface $context): ContextualValidatorInterface
    {
        return $this->validator->inContext($context);
    }

    /**
     * @param array<Constraint> $constraints
     */
    private function collectConstraints(array $constraints, mixed $subject): void
    {
        $subject = \is_scalar($subject) ? (string) $subject : null;

        foreach ($constraints as $constraint) {
            if ($constraint instanceof Regex) {
                if (null !== $constraint->pattern) {
                    $this->collector->collectRegex(
                        $constraint->pattern,
                        'Validator (Regex constraint)',
                        $subject,
                        null, // We don't know the result at this stage
                    );
                }
            }
        }
    }
}
