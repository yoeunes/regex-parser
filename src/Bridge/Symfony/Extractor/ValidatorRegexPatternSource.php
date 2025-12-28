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

namespace RegexParser\Bridge\Symfony\Extractor;

use RegexParser\Lint\RegexPatternOccurrence;
use RegexParser\Lint\RegexPatternSourceContext;
use RegexParser\Lint\RegexPatternSourceInterface;
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
final readonly class ValidatorRegexPatternSource implements RegexPatternSourceInterface
{
    public function __construct(
        private ?ValidatorInterface $validator = null,
        private ?LoaderInterface $validatorLoader = null,
    ) {}

    public function getName(): string
    {
        return 'validators';
    }

    public function isSupported(): bool
    {
        return null !== $this->validator && null !== $this->validatorLoader;
    }

    public function extract(RegexPatternSourceContext $context): array
    {
        if (!$this->isSupported()) {
            return [];
        }

        $patterns = [];
        $line = 1;

        foreach ($this->getMappedClasses() as $className) {
            // @codeCoverageIgnoreStart
            if (!\is_string($className) || '' === $className) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            try {
                // @codeCoverageIgnoreStart
                if (null === $this->validator) {
                    continue;
                }
                // @codeCoverageIgnoreEnd
                $metadata = $this->validator->getMetadataFor($className);
            } catch (\Throwable) {
                continue;
            }

            $file = $this->getClassFile($className) ?? 'Symfony Validator';
            $patterns = [...$patterns, ...$this->extractFromMetadata($metadata, $className, $file, $line)];
        }

        return $patterns;
    }

    /**
     * @return array<string>
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
     * @return array<RegexPatternOccurrence>
     */
    private function extractFromMetadata(MetadataInterface $metadata, string $className, string $file, int &$line): array
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

                    $propertyName = $propertyMetadata->getPropertyName();
                    $patterns = [...$patterns, ...$this->extractFromConstraints(
                        $propertyMetadata->getConstraints(),
                        \sprintf('%s::$%s', $className, $propertyName),
                        $file,
                        $line,
                    )];
                }
            }
        }

        return [...$patterns, ...$this->extractFromConstraints($constraints, $className, $file, $line)];
    }

    /**
     * @param array<Constraint> $constraints
     *
     * @return array<RegexPatternOccurrence>
     */
    private function extractFromConstraints(array $constraints, string $source, string $file, int &$line): array
    {
        $patterns = [];

        foreach ($constraints as $constraint) {
            if (!$constraint instanceof SymfonyRegex || null === $constraint->pattern || '' === $constraint->pattern) {
                continue;
            }

            $pattern = (string) $constraint->pattern;
            $patterns[] = new RegexPatternOccurrence(
                $pattern,
                $file,
                $line++,
                'validator:'.$source,
            );
        }

        return $patterns;
    }

    private function getClassFile(string $className): ?string
    {
        if (!class_exists($className)) {
            return null;
        }

        $reflection = new \ReflectionClass($className);
        $filename = $reflection->getFileName();

        return false === $filename ? null : $filename;
    }
}
