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

/**
 * PHPStan-based regex pattern extraction strategy.
 *
 * This strategy uses PHPStan's powerful AST analysis when available
 * to extract regex patterns with better accuracy than token-based approach.
 *
 * @internal
 */
final readonly class PhpStanExtractionStrategy implements ExtractionStrategyInterface
{
    /**
     * @param list<string> $excludePaths
     */
    public function __construct(
        private array $excludePaths = ['vendor'],
    ) {}

    public function extract(array $paths): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        return $this->extractWithPhpStan($paths);
    }

    public function isAvailable(): bool
    {
        return class_exists(\PHPStan\Analyser\Analyser::class)
            && class_exists(\PHPStan\Parser\Parser::class)
            && class_exists(\PHPStan\PhpDoc\TypeNodeResolver::class);
    }

    public function getPriority(): int
    {
        return 10; // High priority, preferred when available
    }

    /**
     * @param list<string> $paths
     *
     * @return list<RegexPatternOccurrence>
     */
    private function extractWithPhpStan(array $paths): array
    {
        try {
            $phpFiles = $this->collectPhpFiles($paths);
            if (empty($phpFiles)) {
                return [];
            }

            return $this->analyzeFilesWithPhpStan($phpFiles);
        } catch (\Throwable) {
            // If PHPStan analysis fails, return empty array to fallback gracefully
            return [];
        }
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function collectPhpFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if ('' === $path) {
                continue;
            }

            if (is_file($path) && str_ends_with($path, '.php')) {
                $files[] = $path;

                continue;
            }

            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || 'php' !== $file->getExtension()) {
                    continue;
                }

                $filePath = $file->getPathname();

                // Skip excluded directories
                $excluded = false;
                foreach ($this->excludePaths as $excludePath) {
                    $excludePath = trim($excludePath, '/\\');
                    if ('' === $excludePath) {
                        continue;
                    }
                    if (str_contains($filePath, \DIRECTORY_SEPARATOR.$excludePath.\DIRECTORY_SEPARATOR) || str_starts_with($filePath, $excludePath.\DIRECTORY_SEPARATOR)) {
                        $excluded = true;

                        break;
                    }
                }

                if (!$excluded) {
                    $files[] = $filePath;
                }
            }
        }

        return $files;
    }

    /**
     * @param list<string> $files
     *
     * @return list<RegexPatternOccurrence>
     */
    private function analyzeFilesWithPhpStan(array $files): array
    {
        $occurrences = [];

        foreach ($files as $file) {
            $fileOccurrences = $this->analyzeFileWithPhpStan($file);
            $occurrences = [...$occurrences, ...$fileOccurrences];
        }

        return $occurrences;
    }

    /**
     * @return list<RegexPatternOccurrence>
     */
    private function analyzeFileWithPhpStan(string $file): array
    {
        try {
            $content = file_get_contents($file);
            if (false === $content || '' === $content) {
                return [];
            }

            // Use PHPStan's lexer to get tokens (much more reliable than token_get_all)
            $lexerClass = 'PHPStan\\Parser\\Lexer';
            if (!class_exists($lexerClass)) {
                return [];
            }
            $lexer = new $lexerClass();
            $tokens = $lexer->tokenize($content);

            return $this->extractFromTokens($tokens, $file);
        } catch (\Throwable) {
            // If analysis fails for this file, return empty results
            return [];
        }
    }

    /**
     * @param array<\PhpParser\Node> $tokens
     *
     * @return list<RegexPatternOccurrence>
     */
    private function extractFromTokens(array $tokens, string $file): array
    {
        $occurrences = [];

        foreach ($tokens as $node) {
            $nodeOccurrences = $this->extractFromNode($node, $file);
            $occurrences = [...$occurrences, ...$nodeOccurrences];
        }

        return $occurrences;
    }

    /**
     * @return list<RegexPatternOccurrence>
     */
    private function extractFromNode(\PhpParser\Node $node, string $file): array
    {
        $occurrences = [];

        if ($node instanceof \PhpParser\Node\Expr\FuncCall) {
            $occurrences = [...$occurrences, ...$this->extractFromFuncCall($node, $file)];
        }

        // Recursively check child nodes
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};
            if ($subNode instanceof \PhpParser\Node) {
                $occurrences = [...$occurrences, ...$this->extractFromNode($subNode, $file)];
            } elseif (\is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof \PhpParser\Node) {
                        $occurrences = [...$occurrences, ...$this->extractFromNode($item, $file)];
                    }
                }
            }
        }

        return $occurrences;
    }

    /**
     * @return list<RegexPatternOccurrence>
     */
    private function extractFromFuncCall(\PhpParser\Node\Expr\FuncCall $funcCall, string $file): array
    {
        if (!$funcCall->name instanceof \PhpParser\Node\Name) {
            return [];
        }

        $functionName = strtolower($funcCall->name->toString());
        if (!$this->isPregFunction($functionName)) {
            return [];
        }

        $args = $funcCall->getArgs();
        if (empty($args)) {
            return [];
        }

        $firstArg = $args[0];

        return $this->extractPatternFromArg($firstArg, $file, $functionName);
    }

    private function isPregFunction(string $functionName): bool
    {
        return \in_array($functionName, [
            'preg_match',
            'preg_match_all',
            'preg_replace',
            'preg_replace_callback',
            'preg_split',
            'preg_grep',
            'preg_filter',
            'preg_replace_callback_array',
        ], true);
    }

    /**
     * @return list<RegexPatternOccurrence>
     */
    private function extractPatternFromArg(\PhpParser\Node\Arg $arg, string $file, string $functionName): array
    {
        $value = $arg->value;

        if ($value instanceof \PhpParser\Node\Expr\ConstFetch && 'null' === $value->name->toString()) {
            return [];
        }

        if ($value instanceof \PhpParser\Node\Scalar\String_) {
            $pattern = $value->value;
            if ('' === $pattern) {
                return [];
            }

            return [new RegexPatternOccurrence(
                $pattern,
                $file,
                $value->getStartLine(),
                $functionName.'()',
            )];
        }

        // Handle concatenation of strings
        if ($value instanceof \PhpParser\Node\Expr\BinaryOp\Concat) {
            $result = $this->extractFromConcat($value, $file, $functionName);

            return $result ? [$result] : [];
        }

        return [];
    }

    private function extractFromConcat(\PhpParser\Node\Expr\BinaryOp\Concat $concat, string $file, string $functionName): ?RegexPatternOccurrence
    {
        $left = $this->extractStringValue($concat->left);
        $right = $this->extractStringValue($concat->right);

        if (null === $left || null === $right) {
            return null;
        }

        $pattern = $left.$right;
        if ('' === $pattern) {
            return null;
        }

        return new RegexPatternOccurrence(
            $pattern,
            $file,
            $concat->getStartLine(),
            $functionName.'()',
        );
    }

    private function extractStringValue(\PhpParser\Node\Expr $expr): ?string
    {
        if ($expr instanceof \PhpParser\Node\Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof \PhpParser\Node\Expr\BinaryOp\Concat) {
            $left = $this->extractStringValue($expr->left);
            $right = $this->extractStringValue($expr->right);

            if (null === $left || null === $right) {
                return null;
            }

            return $left.$right;
        }

        return null;
    }
}
