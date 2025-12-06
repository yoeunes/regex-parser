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
 * Optimizes PCRE regex patterns found in function calls and class constants.
 *
 * This rule parses the regex string, applies AST optimizations (like flattening groups
 * or merging character classes), and recompiles it back to a cleaner string.
 */
final class RegexOptimizationRector extends AbstractRector implements ConfigurableRectorInterface
{
    public const string EXTRA_FUNCTIONS = 'extra_functions';

    public const string EXTRA_CONSTANTS = 'extra_constants';

    private const array DEFAULT_FUNCTIONS = [
        'preg_match',
        'preg_match_all',
        'preg_replace',
        'preg_replace_callback',
        'preg_split',
        'preg_grep',
        'preg_filter',
    ];

    private const array DEFAULT_CONSTANTS = [
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
        // Initialize defaults
        foreach (self::DEFAULT_FUNCTIONS as $func) {
            $this->targetFunctions[$func] = true;
        }
        foreach (self::DEFAULT_CONSTANTS as $const) {
            $this->targetConstants[$const] = true;
        }
    }

    /**
     * Provides the definition of the rule for documentation generation.
     *
     * Purpose: This method is used by Rector's documentation generator to create a
     * clear explanation of what the rule does, including before-and-after code samples.
     * As a contributor, if you change the rule's behavior or add new configuration,
     * you must update this definition to reflect those changes.
     *
     * @return RuleDefinition the definition of the rule
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
     * Configures the rector with user-defined settings.
     *
     * Purpose: This method allows users of the Rector rule to extend its functionality.
     * By default, the rule targets standard `preg_*` functions and common constant names.
     * This method merges user-provided lists of additional function and constant names
     * into the rector's target list, making the rule adaptable to different codebases.
     *
     * @param array<string, mixed> $configuration The configuration array from the user's `rector.php` file.
     *                                            Expected keys are `EXTRA_FUNCTIONS` and `EXTRA_CONSTANTS`.
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
     * Specifies which types of PHP-Parser nodes this rector should visit.
     *
     * Purpose: This is a performance optimization for Rector. By telling Rector that
     * this rule is only interested in `FuncCall` and `ClassConst` nodes, Rector can
     * skip calling the `refactor()` method for all other node types, speeding up the
     * overall analysis process.
     *
     * @return array<class-string<Node>> the list of node types to process
     */
    public function getNodeTypes(): array
    {
        return [FuncCall::class, ClassConst::class];
    }

    /**
     * The core transformation logic of the rector.
     *
     * Purpose: This method is called by Rector for each node matching the types defined
     * in `getNodeTypes()`. It identifies the string containing the regex pattern,
     * parses it, runs the `OptimizerNodeVisitor` to simplify the AST, and then uses the
     * `CompilerNodeVisitor` to generate the new, optimized regex string. If the string
     * has changed, it modifies the node in place.
     *
     * @param Node $node the AST node being visited (either a `FuncCall` or `ClassConst`)
     *
     * @return Node|null the modified node if a change was made, or null if no change was needed
     */
    public function refactor(Node $node): ?Node
    {
        $stringNode = $this->resolveRegexStringNode($node);

        if (!$stringNode instanceof String_) {
            return null;
        }

        $originalPattern = $stringNode->value;

        // Quick check: if it doesn't look like a regex, skip to save performance
        if (\strlen($originalPattern) < 2) {
            return null;
        }

        try {
            $parser = $this->getParser();
            $ast = $parser->parse($originalPattern);

            // 1. Optimization Phase (AST -> AST)
            // We clone the visitor to ensure a fresh state for each run
            $optimizer = clone $this->optimizerVisitor;
            $optimizedAst = $ast->accept($optimizer);

            // 2. Compilation Phase (AST -> String)
            $compiler = $this->getCompiler();
            $newPattern = $optimizedAst->accept($compiler);

            // Only modify the AST if the optimization resulted in a change
            if ($newPattern !== $originalPattern) {
                $stringNode->value = $newPattern;

                return $node;
            }
        } catch (\Throwable) {
            // Silently ignore invalid regexes or parsing errors.
            // Rector's job is to refactor valid code, not to act as a linter.
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

        // In standard preg_ functions, the pattern is always the first argument (index 0)
        // If custom functions use a different index, we might need more advanced config later.
        $args = $node->getArgs();
        if (!isset($args[0])) {
            return null;
        }

        $patternArg = $args[0]->value;

        return $patternArg instanceof String_ ? $patternArg : null;
    }

    private function resolveClassConstantValue(ClassConst $node): ?String_
    {
        // Check if any of the constant names match our target list
        foreach ($node->consts as $const) {
            if (isset($this->targetConstants[$const->name->toString()])) {
                return $const->value instanceof String_ ? $const->value : null;
            }
        }

        return null;
    }

    private function getParser(): Parser
    {
        // Lazy initialization
        return $this->parser ??= new Parser();
    }

    private function getCompiler(): CompilerNodeVisitor
    {
        // Lazy initialization
        return $this->compiler ??= new CompilerNodeVisitor();
    }
}
