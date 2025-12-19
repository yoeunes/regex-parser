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

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\ParserFactory;
use PhpParser\Node\Scalar\String_;

/**
 * PHPStan-based regex pattern extraction strategy.
 *
 * This strategy uses PHPStan's powerful AST analysis when available
 * to extract regex patterns with better accuracy than token-based approach.
 *
 * @internal
 */
final readonly class PhpStanExtractionStrategy implements ExtractorInterface
{
    public function __construct() {}

    public function extract(array $files): array
    {
        if (empty($files)) {
            return [];
        }

        return $this->analyzeFilesWithPhpStan($files);
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

            $parserFactoryClass = 'PhpParser\\ParserFactory';
            if (!class_exists($parserFactoryClass)) {
                return [];
            }
            $parserFactory = new ParserFactory();
            $parser = $parserFactory->createForHostVersion();

            $ast = $parser->parse($content);
            if (!\is_array($ast)) {
                return [];
            }

            return $this->extractFromTokens($ast, $file);
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
    private function extractFromNode(Node $node, string $file): array
    {
        $occurrences = [];

        if ($node instanceof FuncCall) {
            $occurrences = [...$occurrences, ...$this->extractFromFuncCall($node, $file)];
        }

        // Recursively check child nodes
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};
            if ($subNode instanceof Node) {
                $occurrences = [...$occurrences, ...$this->extractFromNode($subNode, $file)];
            } elseif (\is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof Node) {
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
    private function extractFromFuncCall(FuncCall $funcCall, string $file): array
    {
        if (!$funcCall->name instanceof Name) {
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
    private function extractPatternFromArg(Arg $arg, string $file, string $functionName): array
    {
        $value = $arg->value;

        if ($value instanceof ConstFetch && 'null' === $value->name->toString()) {
            return [];
        }

        if ($value instanceof String_) {
            $pattern = $value->value;
            if ('' === $pattern) {
                return [];
            }

            return [new RegexPatternOccurrence(
                $pattern,
                $file,
                $value->getStartLine(),
                'php:'.$functionName.'()',
            )];
        }

        // Handle concatenation of strings
        if ($value instanceof Concat) {
            $result = $this->extractFromConcat($value, $file, $functionName);

            return $result ? [$result] : [];
        }

        return [];
    }

    private function extractFromConcat(Concat $concat, string $file, string $functionName): ?RegexPatternOccurrence
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
            'php:'.$functionName.'()',
        );
    }

    private function extractStringValue(Expr $expr): ?string
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }

        if ($expr instanceof Concat) {
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
