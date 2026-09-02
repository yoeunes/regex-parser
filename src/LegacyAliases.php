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

namespace {
    use RegexParser\Automata\Alphabet\CharSet;
    use RegexParser\Automata\Builder\DfaBuilder;
    use RegexParser\Automata\Builder\NfaBuilder;
    use RegexParser\Automata\Minimization\DfaMinimizer;
    use RegexParser\Automata\Minimization\HopcroftWorklist;
    use RegexParser\Automata\Minimization\MinimizationAlgorithm;
    use RegexParser\Automata\Minimization\MinimizationAlgorithmFactory;
    use RegexParser\Automata\Minimization\MinimizationAlgorithmInterface;
    use RegexParser\Automata\Minimization\MoorePartitionRefinement;
    use RegexParser\Automata\Model\Dfa;
    use RegexParser\Automata\Model\DfaState;
    use RegexParser\Automata\Model\Nfa;
    use RegexParser\Automata\Model\NfaFragment;
    use RegexParser\Automata\Model\NfaState;
    use RegexParser\Automata\Model\NfaTransition;
    use RegexParser\Automata\Options\MatchMode;
    use RegexParser\Automata\Options\SolverOptions;
    use RegexParser\Automata\Solver\EquivalenceResult;
    use RegexParser\Automata\Solver\IntersectionResult;
    use RegexParser\Automata\Solver\RegexSolver;
    use RegexParser\Automata\Solver\RegexSolverInterface;
    use RegexParser\Automata\Solver\SubsetResult;
    use RegexParser\Automata\Transform\AstToNfaTransformer;
    use RegexParser\Automata\Transform\AstToNfaTransformerInterface;
    use RegexParser\Automata\Transform\RegularSubsetValidator;
    use RegexParser\Cli\Command\LintCommand;
    use RegexParser\Cli\Command\LintOutputRenderer;
    use RegexParser\Lint\Extraction\ExtractorInterface;
    use RegexParser\Lint\Extraction\PhpParserExtractionStrategy;
    use RegexParser\Lint\Extraction\TokenBasedExtractionStrategy;

    /*
     * Classes that moved keep answering to the name they had. The old names
     * resolve only for whoever asks for one: aliasing them up front loaded
     * twenty-five classes into every process that autoloads this package,
     * including the ones that only ever parse a pattern.
     */
    $aliases = [
        'RegexParser\\Automata\\CharSet' => CharSet::class,
        'RegexParser\\Automata\\DfaBuilder' => DfaBuilder::class,
        'RegexParser\\Automata\\NfaBuilder' => NfaBuilder::class,
        'RegexParser\\Automata\\DfaMinimizer' => DfaMinimizer::class,
        'RegexParser\\Automata\\HopcroftWorklist' => HopcroftWorklist::class,
        'RegexParser\\Automata\\MinimizationAlgorithm' => MinimizationAlgorithm::class,
        'RegexParser\\Automata\\MinimizationAlgorithmFactory' => MinimizationAlgorithmFactory::class,
        'RegexParser\\Automata\\MinimizationAlgorithmInterface' => MinimizationAlgorithmInterface::class,
        'RegexParser\\Automata\\MoorePartitionRefinement' => MoorePartitionRefinement::class,
        'RegexParser\\Automata\\Dfa' => Dfa::class,
        'RegexParser\\Automata\\DfaState' => DfaState::class,
        'RegexParser\\Automata\\Nfa' => Nfa::class,
        'RegexParser\\Automata\\NfaFragment' => NfaFragment::class,
        'RegexParser\\Automata\\NfaState' => NfaState::class,
        'RegexParser\\Automata\\NfaTransition' => NfaTransition::class,
        'RegexParser\\Automata\\MatchMode' => MatchMode::class,
        'RegexParser\\Automata\\SolverOptions' => SolverOptions::class,
        'RegexParser\\Automata\\EquivalenceResult' => EquivalenceResult::class,
        'RegexParser\\Automata\\IntersectionResult' => IntersectionResult::class,
        'RegexParser\\Automata\\RegexSolver' => RegexSolver::class,
        'RegexParser\\Automata\\RegexSolverInterface' => RegexSolverInterface::class,
        'RegexParser\\Automata\\SubsetResult' => SubsetResult::class,
        'RegexParser\\Automata\\AstToNfaTransformer' => AstToNfaTransformer::class,
        'RegexParser\\Automata\\AstToNfaTransformerInterface' => AstToNfaTransformerInterface::class,
        'RegexParser\\Automata\\RegularSubsetValidator' => RegularSubsetValidator::class,
        'RegexParser\\Lint\\Command\\LintCommand' => LintCommand::class,
        'RegexParser\\Lint\\Command\\LintOutputRenderer' => LintOutputRenderer::class,
        'RegexParser\\Lint\\ExtractorInterface' => ExtractorInterface::class,
        'RegexParser\\Lint\\TokenBasedExtractionStrategy' => TokenBasedExtractionStrategy::class,
        'RegexParser\\Lint\\PhpStanExtractionStrategy' => PhpParserExtractionStrategy::class,
    ];

    \spl_autoload_register(static function (string $class) use ($aliases): void {
        if (!isset($aliases[$class])) {
            return;
        }

        \class_alias($aliases[$class], $class);
    });
}
