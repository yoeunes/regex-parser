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

namespace RegexParser\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassConst;
use Rector\Rector\AbstractRector;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\Parser;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RegexOptimizationRector extends AbstractRector
{
    /**
     * @var array<string, int>
     */
    private const PREG_FUNCTION_MAP = [
        'preg_match' => 0, 'preg_match_all' => 0, 'preg_replace' => 0,
        'preg_replace_callback' => 0, 'preg_split' => 0, 'preg_grep' => 0,
    ];

    /**
     * @var array<string> constants we know contain regexes
     */
    private const REGEX_CONSTANT_NAMES = [
        'REGEX_OUTSIDE',
        'REGEX_INSIDE',
    ];

    private ?Parser $parser = null;
    private ?CompilerNodeVisitor $compiler = null;

    /**
     * We inject the main OptimizerNodeVisitor service from the container.
     * This ensures optimization logic is defined in one single place.
     */
    public function __construct(
        private readonly OptimizerNodeVisitor $optimizerVisitor,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Optimizes regex patterns using RegexParser\'s OptimizerNodeVisitor.',
            [
                new CodeSample(
                    "preg_match('/[a-zA-Z0-9_]+/', \$str);",
                    "preg_match('/\\w+/', \$str);",
                ),
                new CodeSample(
                    "preg_match('/(a|b|c)/', \$str);",
                    "preg_match('/[abc]/', \$str);",
                ),
                new CodeSample(
                    "preg_match('/a.b.c.d/', \$str);", // No change
                    "preg_match('/a.b.c.d/', \$str);",
                ),
            ],
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [FuncCall::class, ClassConst::class];
    }

    /**
     * @param FuncCall|ClassConst $node
     */
    public function refactor(Node $node): ?Node
    {
        $stringNode = $this->getRegexStringNode($node);

        if (!$stringNode instanceof String_) {
            return null;
        }

        $originalRegexString = $stringNode->value;

        try {
            $ast = $this->getParser()->parse($originalRegexString);

            // 1. Optimize the AST (AST -> AST)
            // We clone the visitor to ensure its state (like $flags) is fresh for this run.
            $optimizer = clone $this->optimizerVisitor;
            $optimizedAst = $ast->accept($optimizer);

            // 2. Re-compile the optimized AST to a string (AST -> string)
            $compiler = $this->getCompiler();
            $newRegexString = $optimizedAst->accept($compiler);

            if ($newRegexString !== $originalRegexString) {
                $stringNode->value = $newRegexString;

                return $node;
            }
        } catch (\Throwable) {
            // If parsing or optimizing fails, do nothing.
            // This protects against invalid regexes in the codebase.
            return null;
        }

        return null;
    }

    private function getParser(): Parser
    {
        return $this->parser ??= new Parser([]);
    }

    private function getCompiler(): CompilerNodeVisitor
    {
        return $this->compiler ??= new CompilerNodeVisitor();
    }

    private function getRegexStringNode(Node $node): ?String_
    {
        if ($node instanceof FuncCall) {
            $functionName = $this->getName($node->name);
            if (null === $functionName || !isset(self::PREG_FUNCTION_MAP[$functionName])) {
                return null;
            }
            $patternArgPosition = self::PREG_FUNCTION_MAP[$functionName];
            $args = $node->getArgs();
            if (!isset($args[$patternArgPosition]) || !$args[$patternArgPosition]->value instanceof String_) {
                return null;
            }

            return $args[$patternArgPosition]->value;
        }

        if ($node instanceof ClassConst) {
            if (!$this->isNames($node, self::REGEX_CONSTANT_NAMES)) {
                return null;
            }
            $const = $node->consts[0];
            if (!$const->value instanceof String_) {
                return null;
            }

            return $const->value;
        }

        return null;
    }
}
