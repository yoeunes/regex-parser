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
    /*
     * The automata classes used to live directly under RegexParser\Automata
     * before they were grouped by role. The old names still resolve, but only
     * for whoever asks for one: aliasing them up front loaded twenty-five
     * classes into every process that autoloads this package, including the
     * ones that only ever parse a pattern.
     */
    $aliases = [
        'RegexParser\\Automata\\CharSet' => 'RegexParser\\Automata\\Alphabet\\CharSet',
        'RegexParser\\Automata\\DfaBuilder' => 'RegexParser\\Automata\\Builder\\DfaBuilder',
        'RegexParser\\Automata\\NfaBuilder' => 'RegexParser\\Automata\\Builder\\NfaBuilder',
        'RegexParser\\Automata\\DfaMinimizer' => 'RegexParser\\Automata\\Minimization\\DfaMinimizer',
        'RegexParser\\Automata\\HopcroftWorklist' => 'RegexParser\\Automata\\Minimization\\HopcroftWorklist',
        'RegexParser\\Automata\\MinimizationAlgorithm' => 'RegexParser\\Automata\\Minimization\\MinimizationAlgorithm',
        'RegexParser\\Automata\\MinimizationAlgorithmFactory' => 'RegexParser\\Automata\\Minimization\\MinimizationAlgorithmFactory',
        'RegexParser\\Automata\\MinimizationAlgorithmInterface' => 'RegexParser\\Automata\\Minimization\\MinimizationAlgorithmInterface',
        'RegexParser\\Automata\\MoorePartitionRefinement' => 'RegexParser\\Automata\\Minimization\\MoorePartitionRefinement',
        'RegexParser\\Automata\\Dfa' => 'RegexParser\\Automata\\Model\\Dfa',
        'RegexParser\\Automata\\DfaState' => 'RegexParser\\Automata\\Model\\DfaState',
        'RegexParser\\Automata\\Nfa' => 'RegexParser\\Automata\\Model\\Nfa',
        'RegexParser\\Automata\\NfaFragment' => 'RegexParser\\Automata\\Model\\NfaFragment',
        'RegexParser\\Automata\\NfaState' => 'RegexParser\\Automata\\Model\\NfaState',
        'RegexParser\\Automata\\NfaTransition' => 'RegexParser\\Automata\\Model\\NfaTransition',
        'RegexParser\\Automata\\MatchMode' => 'RegexParser\\Automata\\Options\\MatchMode',
        'RegexParser\\Automata\\SolverOptions' => 'RegexParser\\Automata\\Options\\SolverOptions',
        'RegexParser\\Automata\\EquivalenceResult' => 'RegexParser\\Automata\\Solver\\EquivalenceResult',
        'RegexParser\\Automata\\IntersectionResult' => 'RegexParser\\Automata\\Solver\\IntersectionResult',
        'RegexParser\\Automata\\RegexSolver' => 'RegexParser\\Automata\\Solver\\RegexSolver',
        'RegexParser\\Automata\\RegexSolverInterface' => 'RegexParser\\Automata\\Solver\\RegexSolverInterface',
        'RegexParser\\Automata\\SubsetResult' => 'RegexParser\\Automata\\Solver\\SubsetResult',
        'RegexParser\\Automata\\AstToNfaTransformer' => 'RegexParser\\Automata\\Transform\\AstToNfaTransformer',
        'RegexParser\\Automata\\AstToNfaTransformerInterface' => 'RegexParser\\Automata\\Transform\\AstToNfaTransformerInterface',
        'RegexParser\\Automata\\RegularSubsetValidator' => 'RegexParser\\Automata\\Transform\\RegularSubsetValidator',
    ];

    \spl_autoload_register(static function (string $class) use ($aliases): void {
        if (!isset($aliases[$class])) {
            return;
        }

        \class_alias($aliases[$class], $class);
    });
}
