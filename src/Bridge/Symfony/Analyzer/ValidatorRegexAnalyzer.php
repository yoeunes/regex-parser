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
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Regex as SymfonyRegex;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Analyses Symfony Validator metadata for Regex constraints.
 *
 * @internal
 */
final readonly class ValidatorRegexAnalyzer
{
    /** @var list<string> */
    private array $ignoredPatterns;
    /**
     * @param list<string> $ignoredPatterns
     */
    public function __construct(
        private Regex $regex,
        private int $warningThreshold,
        private int $redosThreshold,
        array $ignoredPatterns = [],
    ) {
        $this->ignoredPatterns = $this->buildIgnoredPatterns($ignoredPatterns);
    }

    /**
     * @return list<AnalysisIssue>
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

            $issues = \array_merge(
                $issues,
                $this->analyzeMetadata($metadata, $className),
            );
        }

        return $issues;
    }

    /**
     * @return list<AnalysisIssue>
     */
    private function analyzeMetadata(MetadataInterface $metadata, string $className): array
    {
        $issues = [];
        $constraints = [];

        if ($metadata instanceof ClassMetadataInterface) {
            $constraints = $metadata->getConstraints();

            foreach ($metadata->getConstrainedProperties() as $propertyName) {
                foreach ($metadata->getPropertyMetadata($propertyName) as $propertyMetadata) {
                    if (!$propertyMetadata instanceof PropertyMetadataInterface) {
                        continue;
                    }

                    $issues = \array_merge(
                        $issues,
                        $this->analyzeConstraints(
                            $propertyMetadata->getConstraints(),
                            \sprintf('%s::$%s', $className, $propertyMetadata->getName()),
                        ),
                    );
                }
            }
        }

        return \array_merge(
            $issues,
            $this->analyzeConstraints($constraints, $className),
        );
    }

    /**
     * @param array<Constraint> $constraints
     *
     * @return list<AnalysisIssue>
     */
    private function analyzeConstraints(array $constraints, string $source): array
    {
        $issues = [];

        foreach ($constraints as $constraint) {
            if (!$constraint instanceof SymfonyRegex || null === $constraint->pattern || '' === $constraint->pattern) {
                continue;
            }

            $pattern = (string) $constraint->pattern;
            $fragment = $this->extractFragment($pattern);
            $body = $this->trimPatternBody($pattern);

            if ($this->isIgnored($fragment) || $this->isIgnored($body)) {
                continue;
            }

            $isTrivial = $this->isTriviallySafe($fragment) || $this->isTriviallySafe($body);

            $result = $this->regex->validate($pattern);

            if ($isTrivial) {
                if (!$result->isValid) {
                    $issues[] = new AnalysisIssue(\sprintf('Validator "%s" pattern is invalid: %s (pattern: %s)', $source, $result->error ?? 'unknown error', $this->formatPattern($pattern)), true);
                }

                continue;
            }

            if (!$result->isValid) {
                $issues[] = new AnalysisIssue(
                    \sprintf('Validator "%s" pattern is invalid: %s (pattern: %s)', $source, $result->error ?? 'unknown error', $this->formatPattern($pattern)),
                    true,
                );

                continue;
            }

            if ($result->complexityScore >= $this->redosThreshold) {
                $issues[] = new AnalysisIssue(
                    \sprintf('Validator "%s" pattern may be vulnerable to ReDoS (score: %d, pattern: %s).', $source, $result->complexityScore, $this->formatPattern($pattern)),
                    true,
                );

                continue;
            }

            if ($result->complexityScore >= $this->warningThreshold) {
                $issues[] = new AnalysisIssue(
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

    private function trimPatternBody(string $pattern): string
    {
        if ('' === $pattern) {
            return '';
        }

        $first = $pattern[0];
        $last = $pattern[-1];

        if ($first === $last) {
            $pattern = substr($pattern, 1, -1);
        }

        if (str_starts_with($pattern, '^')) {
            $pattern = substr($pattern, 1);
        }

        if (str_ends_with($pattern, '$')) {
            $pattern = substr($pattern, 0, -1);
        }

        return $pattern;
    }

    private function extractFragment(string $pattern): string
    {
        if ('' === $pattern) {
            return '';
        }

        $first = $pattern[0];
        $last = $pattern[-1];

        if ($first === $last && \in_array($first, ['/', '#', '~', '%'], true)) {
            $pattern = substr($pattern, 1, -1);
        }

        if (str_starts_with($pattern, '^')) {
            $pattern = substr($pattern, 1);
        }

        if (str_ends_with($pattern, '$')) {
            $pattern = substr($pattern, 0, -1);
        }

        return $pattern;
    }

    private function isIgnored(string $body): bool
    {
        if ('' === $body) {
            return false;
        }

        return \in_array($body, $this->ignoredPatterns, true);
    }

    private function isTriviallySafe(string $body): bool
    {
        if ('' === $body) {
            return false;
        }

        return 1 === preg_match('#^[A-Za-z0-9._-]+(?:\|[A-Za-z0-9._-]+)+$#', $body);
    }

    /**
     * @param list<string> $userIgnored
     *
     * @return list<string>
     */
    private function buildIgnoredPatterns(array $userIgnored): array
    {
        $defaults = [
            Requirement::ASCII_SLUG,
            Requirement::CATCH_ALL,
            Requirement::DATE_YMD,
            Requirement::DIGITS,
            Requirement::MONGODB_ID,
            Requirement::POSITIVE_INT,
            Requirement::UID_BASE32,
            Requirement::UID_BASE58,
            Requirement::UID_RFC4122,
            Requirement::UID_RFC9562,
            Requirement::ULID,
            Requirement::UUID,
            Requirement::UUID_V1,
            Requirement::UUID_V3,
            Requirement::UUID_V4,
            Requirement::UUID_V5,
            Requirement::UUID_V6,
            Requirement::UUID_V7,
            Requirement::UUID_V8,
            '^'.Requirement::ASCII_SLUG.'$',
        ];

        return array_values(array_unique([...$defaults, ...$userIgnored]));
    }
}
