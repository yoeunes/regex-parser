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

namespace RegexParser\Bridge\Symfony\Analyzer;

use RegexParser\Regex;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Regex as SymfonyRegex;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Analyses Symfony Validator metadata for Regex constraints.
 *
 * @internal
 */
final readonly class ValidatorRegexAnalyzer
{
    public function __construct(
        private Regex $regex,
        private int $warningThreshold,
        private int $redosThreshold,
    ) {}

    /**
     * @return list<RegexAnalysisIssue>
     */
    public function analyze(?ValidatorInterface $validator, ?LoaderInterface $loader): array
    {
        if (null === $validator) {
            return [];
        }

        $classes = [];
        if (null !== $loader && method_exists($loader, 'getMappedClasses')) {
            $classes = $loader->getMappedClasses();
        }

        $issues = [];
        foreach ($classes as $className) {
            if (!\is_string($className) || '' === $className) {
                continue;
            }

            try {
                $metadata = $validator->getMetadataFor($className);
            } catch (\Throwable) {
                continue;
            }

            $issues = array_merge(
                $issues,
                $this->analyzeMetadata($metadata, $className),
            );
        }

        return $issues;
    }

    /**
     * @return list<RegexAnalysisIssue>
     */
    private function analyzeMetadata(MetadataInterface $metadata, string $className): array
    {
        $issues = [];
        $constraints = [];

        if ($metadata instanceof ClassMetadataInterface) {
            $constraints = $metadata->getConstraints();

            foreach ($metadata->getConstrainedProperties() as $propertyName) {
                foreach ($metadata->getPropertyMetadata($propertyName) as $propertyMetadata) {
                    $issues = array_merge(
                        $issues,
                        $this->analyzeConstraints(
                            $propertyMetadata->getConstraints(),
                            \sprintf('%s::$%s', $className, $propertyMetadata->getName()),
                        ),
                    );
                }
            }
        }

        return array_merge(
            $issues,
            $this->analyzeConstraints($constraints, $className),
        );
    }

    /**
     * @param array<Constraint> $constraints
     *
     * @return list<RegexAnalysisIssue>
     */
    private function analyzeConstraints(array $constraints, string $source): array
    {
        $issues = [];

        foreach ($constraints as $constraint) {
            if (!$constraint instanceof SymfonyRegex || null === $constraint->pattern || '' === $constraint->pattern) {
                continue;
            }

            $pattern = (string) $constraint->pattern;
            $result = $this->regex->validate($pattern);

            if (!$result->isValid) {
                $issues[] = new RegexAnalysisIssue(
                    \sprintf('Validator "%s" pattern is invalid: %s (pattern: %s)', $source, $result->error ?? 'unknown error', $this->formatPattern($pattern)),
                    true,
                );

                continue;
            }

            if ($result->complexityScore >= $this->redosThreshold) {
                $issues[] = new RegexAnalysisIssue(
                    \sprintf('Validator "%s" pattern may be vulnerable to ReDoS (score: %d, pattern: %s).', $source, $result->complexityScore, $this->formatPattern($pattern)),
                    true,
                );

                continue;
            }

            if ($result->complexityScore >= $this->warningThreshold) {
                $issues[] = new RegexAnalysisIssue(
                    \sprintf('Validator "%s" pattern is complex (score: %d, pattern: %s).', $source, $result->complexityScore, $this->formatPattern($pattern)),
                    false,
                );
            }
        }

        return $issues;
    }

    private function formatPattern(string $pattern): string
    {
        if (\strlen($pattern) <= 80) {
            return $pattern;
        }

        return substr($pattern, 0, 77).'...';
    }
}
