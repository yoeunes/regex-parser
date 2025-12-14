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

namespace RegexParser\Bridge\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassConst;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\Parser;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Optimizes PCRE regex patterns in function calls and class constants.
 */
final class RegexOptimizationRector extends AbstractRector implements ConfigurableRectorInterface
{
    public const EXTRA_FUNCTIONS = 'extra_functions';

    public const EXTRA_CONSTANTS = 'extra_constants';

    private const DEFAULT_FUNCTIONS = [
        'preg_match',
        'preg_match_all',
        'preg_replace',
        'preg_replace_callback',
        'preg_split',
        'preg_grep',
        'preg_filter',
    ];

    private const DEFAULT_CONSTANTS = [
        'REGEX',
        'PATTERN',
    ];

    /**
     * @var array<string, bool>
     */
    private array $targetFunctions = [];

    /**
     * @var array<string, bool>
     */
    private array $targetConstants = [];

    private ?Parser $parser = null;

    private ?CompilerNodeVisitor $compiler = null;

    public function __construct(
        private readonly OptimizerNodeVisitor $optimizerVisitor,
    ) {
        foreach (self::DEFAULT_FUNCTIONS as $func) {
            $this->targetFunctions[$func] = true;
        }
        foreach (self::DEFAULT_CONSTANTS as $const) {
            $this->targetConstants[$const] = true;
        }
    }

    /**
     * @return RuleDefinition
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Optimizes regex patterns using RegexParser\'s AST transformation.',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
                        class SomeClass
                        {
                            public function run($str)
                            {
                                preg_match('/[a-zA-Z0-9_]+/', $str);
                            }
                        }
                        CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
                        class SomeClass
                        {
                            public function run($str)
                            {
                                preg_match('/\\w+/', $str);
                            }
                        }
                        CODE_SAMPLE,
                    [
                        self::EXTRA_FUNCTIONS => ['my_custom_preg_wrapper'],
                    ],
                ),
            ],
        );
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function configure(array $configuration): void
    {
        if (isset($configuration[self::EXTRA_FUNCTIONS])) {
            foreach ((array) $configuration[self::EXTRA_FUNCTIONS] as $func) {
                $this->targetFunctions[(string) $func] = true;
            }
        }

        if (isset($configuration[self::EXTRA_CONSTANTS])) {
            foreach ((array) $configuration[self::EXTRA_CONSTANTS] as $const) {
                $this->targetConstants[(string) $const] = true;
            }
        }
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [FuncCall::class, ClassConst::class];
    }

    /**
     * @param Node $node
     * @return Node|null
     */
    public function refactor(Node $node): ?Node
    {
        $stringNode = $this->resolveRegexStringNode($node);

        if (!$stringNode instanceof String_) {
            return null;
        }

        $originalPattern = $stringNode->value;

        if (\strlen($originalPattern) < 2) {
            return null;
        }

        try {
            $parser = $this->getParser();
            $ast = $parser->parse($originalPattern);

            $optimizer = clone $this->optimizerVisitor;
            $optimizedAst = $ast->accept($optimizer);

            $compiler = $this->getCompiler();
            $newPattern = $optimizedAst->accept($compiler);

            if ($newPattern !== $originalPattern) {
                $stringNode->value = $newPattern;

                return $node;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function resolveRegexStringNode(Node $node): ?String_
    {
        if ($node instanceof FuncCall) {
            return $this->resolveFuncCallArgument($node);
        }

        if ($node instanceof ClassConst) {
            return $this->resolveClassConstantValue($node);
        }

        return null;
    }

    private function resolveFuncCallArgument(FuncCall $node): ?String_
    {
        if (!$node->name instanceof Node\Name) {
            return null;
        }

        $name = $this->getName($node->name);
        if (null === $name || !isset($this->targetFunctions[$name])) {
            return null;
        }

        $args = $node->getArgs();
        if (!isset($args[0])) {
            return null;
        }

        $patternArg = $args[0]->value;

        return $patternArg instanceof String_ ? $patternArg : null;
    }

    private function resolveClassConstantValue(ClassConst $node): ?String_
    {
        foreach ($node->consts as $const) {
            if (isset($this->targetConstants[$const->name->toString()])) {
                return $const->value instanceof String_ ? $const->value : null;
            }
        }

        return null;
    }

    private function getParser(): Parser
    {
        return $this->parser ??= new Parser();
    }

    private function getCompiler(): CompilerNodeVisitor
    {
        return $this->compiler ??= new CompilerNodeVisitor();
    }
}
