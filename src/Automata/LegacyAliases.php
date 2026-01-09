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

    $aliases = [
        CharSet::class => \RegexParser\Automata\CharSet::class,
        DfaBuilder::class => \RegexParser\Automata\DfaBuilder::class,
        NfaBuilder::class => \RegexParser\Automata\NfaBuilder::class,
        DfaMinimizer::class => \RegexParser\Automata\DfaMinimizer::class,
        HopcroftWorklist::class => \RegexParser\Automata\HopcroftWorklist::class,
        MinimizationAlgorithm::class => \RegexParser\Automata\MinimizationAlgorithm::class,
        MinimizationAlgorithmFactory::class => \RegexParser\Automata\MinimizationAlgorithmFactory::class,
        MinimizationAlgorithmInterface::class => \RegexParser\Automata\MinimizationAlgorithmInterface::class,
        MoorePartitionRefinement::class => \RegexParser\Automata\MoorePartitionRefinement::class,
        Dfa::class => \RegexParser\Automata\Dfa::class,
        DfaState::class => \RegexParser\Automata\DfaState::class,
        Nfa::class => \RegexParser\Automata\Nfa::class,
        NfaFragment::class => \RegexParser\Automata\NfaFragment::class,
        NfaState::class => \RegexParser\Automata\NfaState::class,
        NfaTransition::class => \RegexParser\Automata\NfaTransition::class,
        MatchMode::class => \RegexParser\Automata\MatchMode::class,
        SolverOptions::class => \RegexParser\Automata\SolverOptions::class,
        EquivalenceResult::class => \RegexParser\Automata\EquivalenceResult::class,
        IntersectionResult::class => \RegexParser\Automata\IntersectionResult::class,
        RegexSolver::class => \RegexParser\Automata\RegexSolver::class,
        RegexSolverInterface::class => \RegexParser\Automata\RegexSolverInterface::class,
        SubsetResult::class => \RegexParser\Automata\SubsetResult::class,
        AstToNfaTransformer::class => \RegexParser\Automata\AstToNfaTransformer::class,
        AstToNfaTransformerInterface::class => \RegexParser\Automata\AstToNfaTransformerInterface::class,
        RegularSubsetValidator::class => \RegexParser\Automata\RegularSubsetValidator::class,
    ];

    foreach ($aliases as $new => $old) {
        $legacyExists = \class_exists($old, false)
            || \interface_exists($old, false)
            || (\function_exists('enum_exists') && \enum_exists($old, false));

        if ($legacyExists) {
            $legacyReflection = new \ReflectionClass($old);
            $currentReflection = new \ReflectionClass($new);
            if ($legacyReflection->getName() !== $currentReflection->getName()) {
                \trigger_error(
                    \sprintf('Legacy alias "%s" is already defined and does not match "%s".', $old, $new),
                    \E_USER_WARNING,
                );
            }

            continue;
        }

        \class_alias($new, $old);
    }
}
