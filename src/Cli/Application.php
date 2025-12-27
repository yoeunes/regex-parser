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

namespace RegexParser\Cli;

use RegexParser\Cli\Command\CommandInterface;

final class Application
{
    /**
     * @var array<string, CommandInterface>
     */
    private array $commands = [];

    public function __construct(
        private readonly GlobalOptionsParser $globalOptionsParser,
        private readonly Output $output,
        private readonly CommandInterface $helpCommand,
    ) {}

    public function register(CommandInterface $command): void
    {
        $this->commands[$command->getName()] = $command;
        foreach ($command->getAliases() as $alias) {
            $this->commands[$alias] = $command;
        }
    }

    /**
     * @param array<int, string> $argv
     */
    public function run(array $argv): int
    {
        $args = $argv;
        array_shift($args);

        $parsed = $this->globalOptionsParser->parse($args);
        $options = $parsed->options;
        $args = $parsed->args;

        $this->output->setAnsi($this->resolveAnsi($options->ansi));
        $this->output->setQuiet($options->quiet);

        if (null !== $options->error) {
            $this->output->write($this->output->error('Error: '.$options->error."\n"));

            return 1;
        }

        if ($options->help) {
            return $this->helpCommand->run(new Input('help', [], $options, []), $this->output);
        }

        if ([] === $args) {
            $this->helpCommand->run(new Input('help', [], $options, []), $this->output);

            return 1;
        }

        $commandName = $args[0] ?? '';
        if ('' === $commandName) {
            $this->helpCommand->run(new Input('help', [], $options, []), $this->output);

            return 1;
        }

        $commandArgs = \array_slice($args, 1);
        if (str_starts_with($commandName, '/')) {
            $commandArgs = $args;
            $commandName = 'highlight';
        }

        $command = $this->commands[$commandName] ?? null;
        if (null === $command) {
            $this->output->write($this->output->error("Unknown command: {$commandName}\n\n"));
            $this->helpCommand->run(new Input('help', [], $options, []), $this->output);

            return 1;
        }

        $regexOptions = null !== $options->phpVersion
            ? ['php_version' => $options->phpVersion]
            : [];

        $input = new Input($commandName, $commandArgs, $options, $regexOptions);

        return $command->run($input, $this->output);
    }

    private function resolveAnsi(?bool $forced): bool
    {
        if (null !== $forced) {
            return $forced;
        }

        return \function_exists('posix_isatty') && posix_isatty(\STDOUT);
    }
}
