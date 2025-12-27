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

use RegexParser\Cli\Input;
use RegexParser\Cli\Output;

final class HelpCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'help';
    }

    public function getAliases(): array
    {
        return ['--help', '-h'];
    }

    public function getDescription(): string
    {
        return 'Display this help message';
    }

    public function run(Input $input, Output $output): int
    {
        $output->write($output->color('Regex Parser', Output::CYAN)."\n");
        $output->write($output->color(str_repeat('=', 12), Output::CYAN)."\n\n");

        $output->write($output->color('Description:', Output::MAGENTA)."\n");
        $output->write('  '.$output->bold('CLI for regex parsing, validation, analysis, and linting')."\n\n");

        $output->write($output->color('Usage:', Output::MAGENTA)."\n");
        $output->write('  regex '.$output->color('<command>', Output::YELLOW).' '.$output->color('[options]', Output::CYAN).' '.$output->color('<pattern>', Output::GREEN)."\n\n");

        $output->write($output->color('Commands:', Output::MAGENTA)."\n");
        $output->write('  '.$output->info('parse')."       Parse and recompile a regex pattern\n");
        $output->write('  '.$output->info('analyze')."     Parse, validate, and analyze ReDoS risk\n");
        $output->write('  '.$output->info('debug')."       Deep ReDoS analysis with heatmap output\n");
        $output->write('  '.$output->info('diagram')."     Render an ASCII diagram of the AST\n");
        $output->write('  '.$output->info('highlight')."   Highlight a regex for display\n");
        $output->write('  '.$output->info('validate')."    Validate a regex pattern\n");
        $output->write('  '.$output->info('lint')."        Lint regex patterns in PHP source code\n");
        $output->write('  '.$output->info('self-update')." Update the CLI phar to the latest release\n");
        $output->write('  '.$output->info('help')."        Display this help message\n\n");

        $output->write($output->color('Global Options:', Output::MAGENTA)."\n");
        $output->write('  '.$output->color('--ansi', Output::CYAN)."            Force ANSI output\n");
        $output->write('  '.$output->color('--no-ansi', Output::CYAN)."         Disable ANSI output\n");
        $output->write('  '.$output->color('-q, --quiet', Output::CYAN)."       Suppress output\n");
        $output->write('  '.$output->color('--silent', Output::CYAN)."          Same as --quiet\n");
        $output->write('  '.$output->color('--php-version', Output::CYAN)." <ver>  Target PHP version for validation\n");
        $output->write('  '.$output->color('--help', Output::CYAN)."            Display this help message\n\n");

        $output->write($output->color('Lint Options:', Output::MAGENTA)."\n");
        $output->write('  '.$output->color('--exclude', Output::CYAN)." <path>       Paths to exclude (repeatable)\n");
        $output->write('  '.$output->color('--min-savings', Output::CYAN)." <n>     Minimum optimization savings\n");
        $output->write('  '.$output->color('--jobs', Output::CYAN)." <n>           Parallel workers for analysis\n");
        $output->write('  '.$output->color('--format', Output::CYAN)." <format>     Output format (console, json, github, checkstyle, junit)\n");
        $output->write('  '.$output->color('--no-redos', Output::CYAN)."           Skip ReDoS risk analysis\n");
        $output->write('  '.$output->color('--no-validate', Output::CYAN)."        Skip validation errors (structural lint only)\n");
        $output->write('  '.$output->color('--no-optimize', Output::CYAN)."        Disable optimization suggestions\n");
        $output->write('  '.$output->color('-v, --verbose', Output::CYAN)."         Show detailed output\n");
        $output->write('  '.$output->color('--debug', Output::CYAN)."              Show debug information\n\n");
        $output->write($output->dim('  Config: regex.json or regex.dist.json in the working directory sets lint defaults.')."\n");
        $output->write($output->dim('  Inline ignore: // @regex-ignore-next-line or // @regex-ignore')."\n\n");

        $output->write($output->color('Diagram Options:', Output::MAGENTA)."\n");
        $output->write('  '.$output->color('--format', Output::CYAN)." <format>     Output format (ascii)\n\n");

        $output->write($output->color('Debug Options:', Output::MAGENTA)."\n");
        $output->write('  '.$output->color('--input', Output::CYAN)." <string>     Input string to test against the pattern\n\n");

        $output->write($output->color('Examples:', Output::MAGENTA)."\n");
        $output->write('  '.$output->color('regex', Output::BLUE).' '.$output->color("'/a+'", Output::GREEN)."                           # Quick highlight\n");
        $output->write('  '.$output->color('regex', Output::BLUE).' '.$output->color('parse', Output::YELLOW).' '.$output->color("'/a+'", Output::GREEN).' '.$output->color('--validate', Output::CYAN)."          # Parse with validation\n");
        $output->write('  '.$output->color('regex', Output::BLUE).' '.$output->color('analyze', Output::YELLOW).' '.$output->color("'/a+'", Output::GREEN)."                   # Full analysis\n");
        $output->write('  '.$output->color('regex', Output::BLUE).' '.$output->color('diagram', Output::YELLOW).' '.$output->color("'/^a+$/'", Output::GREEN)."                # ASCII diagram\n");
        $output->write('  '.$output->color('regex', Output::BLUE).' '.$output->color('debug', Output::YELLOW).' '.$output->color("'/(a+)+$/'", Output::GREEN)."              # Heatmap + ReDoS details\n");
        $output->write('  '.$output->color('regex', Output::BLUE).' '.$output->color('highlight', Output::YELLOW).' '.$output->color("'/a+'", Output::GREEN).' '.$output->color('--format=html', Output::CYAN)."   # HTML highlight\n");
        $output->write('  '.$output->color('regex', Output::BLUE).' '.$output->color('lint', Output::YELLOW).' '.$output->color('src/', Output::GREEN).' '.$output->color('--exclude=vendor', Output::CYAN)."       # Lint a codebase\n");
        $output->write('  '.$output->color('regex', Output::BLUE).' '.$output->color('lint', Output::YELLOW).' '.$output->color('--format=json', Output::CYAN).' '.$output->color('src/', Output::GREEN)."           # JSON output\n");
        $output->write('  '.$output->color('regex', Output::BLUE).' '.$output->color('lint', Output::YELLOW).' '.$output->color('--verbose', Output::CYAN).' '.$output->color('src/', Output::GREEN)."              # Verbose output\n");
        $output->write('  '.$output->color('regex', Output::BLUE).' '.$output->color('self-update', Output::YELLOW)."                  # Update the installed phar\n");

        return 0;
    }
}
