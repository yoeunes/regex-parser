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

namespace RegexParser\Lint;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * PhpParser-based regex pattern extraction strategy.
 *
 * This strategy uses nikic/php-parser to build an AST and extract regex
 * patterns with better accuracy than the token-based approach.
 *
 * @internal
 */
final readonly class PhpStanExtractionStrategy implements ExtractorInterface
{
    private ?Parser $parser;

    public function __construct()
    {
        $parser = null;
        if (class_exists(ParserFactory::class)) {
            $parserFactory = new ParserFactory();
            $parser = $parserFactory->createForHostVersion();
        }

        $this->parser = $parser;
    }

    public function extract(array $files): array
    {
        if (empty($files)) {
            return [];
        }

        return $this->analyzeFilesWithPhpStan($files);
    }

    /**
     * @param array<string> $files
     *
     * @return array<RegexPatternOccurrence>
     */
    private function analyzeFilesWithPhpStan(array $files): array
    {
        $occurrences = [];

        foreach ($files as $file) {
            $fileOccurrences = $this->analyzeFileWithPhpStan($file);
            $this->appendOccurrences($occurrences, $fileOccurrences);
        }

        return $occurrences;
    }

    /**
     * @return array<RegexPatternOccurrence>
     */
    private function analyzeFileWithPhpStan(string $file): array
    {
        try {
            if (null === $this->parser) {
                return [];
            }

            if (!is_file($file) || !is_readable($file)) {
                return [];
            }

            $content = file_get_contents($file);
            if (false === $content || '' === $content) {
                return [];
            }

            if (false === stripos($content, 'preg_')) {
                return [];
            }

            $ast = $this->parser->parse($content);
            if (!\is_array($ast)) {
                return [];
            }

            return $this->extractFromTokens($ast, $file, $content);
        } catch (\Throwable) {
            // If analysis fails for this file, return empty results
            return [];
        }
    }

    /**
     * @param array<Node> $tokens
     *
     * @return array<RegexPatternOccurrence>
     */
    private function extractFromTokens(array $tokens, string $file, string $content): array
    {
        $occurrences = [];

        foreach ($tokens as $node) {
            $nodeOccurrences = $this->extractFromNode($node, $file, $content);
            $this->appendOccurrences($occurrences, $nodeOccurrences);
        }

        return $occurrences;
    }

    /**
     * @return array<RegexPatternOccurrence>
     */
    private function extractFromNode(Node $node, string $file, string $content): array
    {
        $occurrences = [];

        if ($node instanceof FuncCall) {
            $this->appendOccurrences($occurrences, $this->extractFromFuncCall($node, $file, $content));
        }

        // Recursively check child nodes
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};
            if ($subNode instanceof Node) {
                $this->appendOccurrences($occurrences, $this->extractFromNode($subNode, $file, $content));
            } elseif (\is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof Node) {
                        $this->appendOccurrences($occurrences, $this->extractFromNode($item, $file, $content));
                    }
                }
            }
        }

        return $occurrences;
    }

    /**
     * @return array<RegexPatternOccurrence>
     */
    private function extractFromFuncCall(FuncCall $funcCall, string $file, string $content): array
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

        return $this->extractPatternFromArg($firstArg, $file, $content, $functionName);
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
     * @return array<RegexPatternOccurrence>
     */
    private function extractPatternFromArg(Arg $arg, string $file, string $content, string $functionName): array
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

            $offset = $this->normalizeOffset($value->getStartFilePos());
            $column = null !== $offset ? $this->columnFromOffset($content, $offset) : null;

            return [new RegexPatternOccurrence(
                $pattern,
                $file,
                $value->getStartLine(),
                'php:'.$functionName.'()',
                column: $column,
                fileOffset: $offset,
            )];
        }

        // Handle concatenation of strings
        if ($value instanceof Concat) {
            $result = $this->extractFromConcat($value, $file, $content, $functionName);

            return $result ? [$result] : [];
        }

        return [];
    }

    private function extractFromConcat(Concat $concat, string $file, string $content, string $functionName): ?RegexPatternOccurrence
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
            column: $this->columnFromOffset($content, $this->normalizeOffset($concat->getStartFilePos())),
            fileOffset: $this->normalizeOffset($concat->getStartFilePos()),
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

    private function normalizeOffset(?int $offset): ?int
    {
        if (null === $offset || $offset < 0) {
            return null;
        }

        return $offset;
    }

    private function columnFromOffset(string $content, ?int $offset): ?int
    {
        if (null === $offset || $offset < 0) {
            return null;
        }

        $prefix = substr($content, 0, $offset);
        $lastNewline = strrpos($prefix, "\n");
        if (false === $lastNewline) {
            return $offset + 1;
        }

        return $offset - $lastNewline;
    }

    /**
     * @param array<RegexPatternOccurrence> $occurrences
     * @param array<RegexPatternOccurrence> $items
     */
    private function appendOccurrences(array &$occurrences, array $items): void
    {
        foreach ($items as $item) {
            $occurrences[] = $item;
        }
    }
}
