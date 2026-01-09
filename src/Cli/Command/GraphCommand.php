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

namespace RegexParser\Cli\Command;

use RegexParser\Automata\Options\SolverOptions;
use RegexParser\Automata\Transform\AstToNfaTransformer;
use RegexParser\Cli\Graph\GraphGenerator;
use RegexParser\Cli\Input;
use RegexParser\Cli\Output;
use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;

final class GraphCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'graph';
    }

    public function getAliases(): array
    {
        return ['automata', 'nfa'];
    }

    public function getDescription(): string
    {
        return 'Generate a graph diagram (DOT/Mermaid) of the NFA';
    }

    public function run(Input $input, Output $output): int
    {
        $pattern = $input->args[0] ?? null;

        if (null === $pattern) {
            $output->write($output->error("Missing pattern argument.\n"));
            $output->write("Usage: bin/regex graph <pattern> [--format=dot|mermaid] [--output=file]\n");

            return 1;
        }

        $format = 'dot';
        $outputFile = null;

        foreach ($input->args as $arg) {
            if (str_starts_with($arg, '--format=')) {
                $format = substr($arg, 9);
            } elseif (str_starts_with($arg, '--output=')) {
                $outputFile = substr($arg, 9);
            }
        }

        $regex = $this->createRegex($output, $input->regexOptions);
        if (null === $regex) {
            return 1;
        }

        try {
            $ast = $regex->parse($pattern);

            // We need to transform AST to NFA manually here as it's not exposed via Facade directly for just dumping
            // Assuming AstToNfaTransformer is the way
            $transformer = new AstToNfaTransformer($pattern);
            $nfa = $transformer->transform($ast, new SolverOptions());

            $generator = new GraphGenerator();
            $content = $generator->generate($nfa, $format);

            if (null !== $outputFile) {
                file_put_contents($outputFile, $content);
                $output->write($output->success("Graph written to $outputFile")."\n");
            } else {
                $output->write($content);
            }

            return 0;
        } catch (LexerException|ParserException $e) {
            $output->write($output->error('Error: '.$e->getMessage())."\n");

            return 1;
        } catch (\Throwable $e) {
            $output->write($output->error('Graph generation failed: '.$e->getMessage())."\n");

            return 1;
        }
    }
}
