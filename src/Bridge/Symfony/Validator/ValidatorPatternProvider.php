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

namespace RegexParser\Bridge\Symfony\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Regex as SymfonyRegex;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Extracts regex patterns from Symfony Validator metadata.
 *
 * @internal
 */
final readonly class ValidatorPatternProvider
{
    public function __construct(
        private ?ValidatorInterface $validator = null,
        private ?LoaderInterface $validatorLoader = null,
    ) {}

    public function isSupported(): bool
    {
        return null !== $this->validator && null !== $this->validatorLoader;
    }

    /**
     * @return list<ValidatorPattern>
     */
    public function collect(): array
    {
        if (!$this->isSupported()) {
            return [];
        }

        $classes = $this->getMappedClasses();
        $patterns = [];

        foreach ($classes as $className) {
            if (!\is_string($className) || '' === $className) {
                continue;
            }

            try {
                if (null === $this->validator) {
                    continue;
                }
                $metadata = $this->validator->getMetadataFor($className);
            } catch (\Throwable) {
                continue;
            }

            $file = $this->resolveClassFile($className);
            $patterns = [...$patterns, ...$this->extractFromMetadata($metadata, $className, $file)];
        }

        return $patterns;
    }

    /**
     * @return list<string>
     */
    private function getMappedClasses(): array
    {
        if (null === $this->validatorLoader) {
            return [];
        }

        try {
            if (!method_exists($this->validatorLoader, 'getMappedClasses')) {
                return [];
            }

            $mappedClasses = $this->validatorLoader->getMappedClasses();

            if (!\is_array($mappedClasses)) {
                return [];
            }

            return array_values(array_filter(
                $mappedClasses,
                static fn (mixed $className): bool => \is_string($className) && '' !== $className,
            ));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<ValidatorPattern>
     */
    private function extractFromMetadata(MetadataInterface $metadata, string $className, ?string $file): array
    {
        $patterns = [];
        $constraints = [];

        if ($metadata instanceof ClassMetadataInterface) {
            $constraints = $metadata->getConstraints();

            foreach ($metadata->getConstrainedProperties() as $propertyName) {
                foreach ($metadata->getPropertyMetadata($propertyName) as $propertyMetadata) {
                    if (!$propertyMetadata instanceof PropertyMetadataInterface) {
                        continue;
                    }

                    $property = $propertyMetadata->getPropertyName();
                    $patterns = [...$patterns, ...$this->extractFromConstraints(
                        $propertyMetadata->getConstraints(),
                        \sprintf('%s::$%s', $className, $property),
                        $file,
                    )];
                }
            }
        }

        return [...$patterns, ...$this->extractFromConstraints($constraints, $className, $file)];
    }

    /**
     * @param array<Constraint> $constraints
     *
     * @return list<ValidatorPattern>
     */
    private function extractFromConstraints(array $constraints, string $source, ?string $file): array
    {
        $patterns = [];

        foreach ($constraints as $constraint) {
            if (!$constraint instanceof SymfonyRegex || null === $constraint->pattern || '' === $constraint->pattern) {
                continue;
            }

            $patterns[] = new ValidatorPattern(
                (string) $constraint->pattern,
                $source,
                $file,
            );
        }

        return $patterns;
    }

    private function resolveClassFile(string $className): ?string
    {
        if (!class_exists($className)) {
            return null;
        }

        $reflection = new \ReflectionClass($className);
        $filename = $reflection->getFileName();

        return false === $filename ? null : $filename;
    }
}
