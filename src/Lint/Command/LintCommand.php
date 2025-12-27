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

namespace RegexParser\Lint\Command;

use RegexParser\Cli\Command\AbstractCommand;
use RegexParser\Cli\Command\CommandInterface;
use RegexParser\Cli\Command\HelpCommand;
use RegexParser\Cli\Input;
use RegexParser\Cli\Output;
use RegexParser\Lint\Formatter\ConsoleFormatter;
use RegexParser\Lint\Formatter\FormatterRegistry;
use RegexParser\Lint\Formatter\OutputConfiguration;
use RegexParser\Lint\PhpRegexPatternSource;
use RegexParser\Lint\RegexAnalysisService;
use RegexParser\Lint\RegexLintReport;
use RegexParser\Lint\RegexLintRequest;
use RegexParser\Lint\RegexLintService;
use RegexParser\Lint\RegexPatternSourceCollection;

final class LintCommand extends AbstractCommand implements CommandInterface
{
    public function __construct(
        private readonly HelpCommand $helpCommand,
        private readonly LintConfigLoader $configLoader,
        private readonly LintDefaultsBuilder $defaultsBuilder,
        private readonly LintArgumentParser $argumentParser,
        private readonly LintExtractorFactory $extractorFactory,
        private readonly LintOutputRenderer $outputRenderer,
    ) {}

    public function getName(): string
    {
        return 'lint';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getDescription(): string
    {
        return 'Lint regex patterns in PHP source code';
    }

    public function run(Input $input, Output $output): int
    {
        $lintConfigResult = $this->configLoader->load();
        if (null !== $lintConfigResult->error) {
            $output->write($output->error('Error: '.$lintConfigResult->error."\n"));

            return 1;
        }

        $lintDefaults = $this->defaultsBuilder->build($lintConfigResult->config ?? []);
        $lintConfigFiles = $lintConfigResult->files ?? [];

        $parsed = $this->argumentParser->parse($input->args, $lintDefaults);
        if ($parsed->help) {
            return $this->helpCommand->run(new Input('help', [], $input->globalOptions, []), $output);
        }
        if (null !== $parsed->error) {
            $output->write($output->error('Error: '.$parsed->error."\n"));
            $output->write("Usage: regex lint [paths...] [--exclude <path>] [--min-savings <n>] [--jobs <n>] [--format console|json|github|checkstyle|junit] [--no-redos] [--no-validate] [--no-optimize] [--verbose|--debug|--quiet]\n");

            return 1;
        }

        $arguments = $parsed->arguments;
        if (null === $arguments) {
            $output->write($output->error("Error: Invalid lint arguments\n"));

            return 1;
        }

        $paths = $arguments->paths;
        $exclude = $arguments->exclude;
        $minSavings = (int) $arguments->minSavings;
        $verbosity = $arguments->verbosity;
        $format = $arguments->format;
        $quiet = $arguments->quiet;
        $checkRedos = $arguments->checkRedos;
        $checkValidation = $arguments->checkValidation;
        $checkOptimizations = $arguments->checkOptimizations;
        $jobs = (int) $arguments->jobs;

        if ([] === $paths) {
            $paths = ['.'];
        }
        if ([] === $exclude && !\array_key_exists('exclude', $lintDefaults)) {
            $exclude = ['vendor'];
        }

        $regex = $this->createRegex($output, $input->regexOptions);
        if (null === $regex) {
            return 1;
        }

        $config = match ($verbosity) {
            OutputConfiguration::VERBOSITY_QUIET => OutputConfiguration::quiet(),
            OutputConfiguration::VERBOSITY_VERBOSE => OutputConfiguration::verbose(),
            OutputConfiguration::VERBOSITY_DEBUG => OutputConfiguration::debug(),
            default => new OutputConfiguration(ansi: $output->isAnsi()),
        };

        $formatterRegistry = new FormatterRegistry();
        if (!$formatterRegistry->has($format)) {
            $output->write($output->error(\sprintf('Unknown format: %s. Available formats: %s', $format, implode(', ', $formatterRegistry->getNames()))."\n"));

            return 1;
        }
        $formatter = $formatterRegistry->get($format);

        if ('console' === $format) {
            $analysis = new RegexAnalysisService($regex);
            $formatter = new ConsoleFormatter($analysis, $config);
        }

        $analysis = new RegexAnalysisService($regex);
        $extractor = $this->extractorFactory->create();
        $sources = new RegexPatternSourceCollection([
            new PhpRegexPatternSource($extractor),
        ]);
        $lint = new RegexLintService($analysis, $sources);

        if ('console' === $format && OutputConfiguration::VERBOSITY_QUIET !== $verbosity) {
            echo $this->outputRenderer->renderBanner($output, $jobs, $lintConfigFiles);
        }

        $collectionProgress = null;
        $startTime = microtime(true);
        $collectionStartTime = $startTime;

        if ('console' === $format && $config->shouldShowProgress()) {
            $output->write('  '.$output->dim('[1/2] Collecting patterns')."\n");
            $collectionStarted = false;
            $lastCount = 0;
            $collectionProgress = static function (int $current, int $total) use (&$collectionStarted, &$lastCount, $output): void {
                if ($total <= 0) {
                    return;
                }

                if (!$collectionStarted) {
                    $output->progressStart($total);
                    $collectionStarted = true;
                }

                $advance = $current - $lastCount;
                if ($advance > 0) {
                    $output->progressAdvance($advance);
                    $lastCount = $current;
                }

                if ($collectionStarted && $current >= $total) {
                    $output->progressFinish();
                }
            };
        }

        try {
            $request = new RegexLintRequest(
                paths: $paths,
                excludePaths: $exclude,
                minSavings: $minSavings,
                checkValidation: $checkValidation,
                checkRedos: $checkRedos,
                checkOptimizations: $checkOptimizations,
                analysisWorkers: $jobs,
            );
            $patterns = $lint->collectPatterns($request, $collectionProgress);
        } catch (\Throwable $e) {
            $output->write($output->error("Failed to collect patterns: {$e->getMessage()}\n"));

            return 1;
        }

        $collectionTime = microtime(true) - $collectionStartTime;

        if ([] === $patterns) {
            if ('console' === $format) {
                $this->outputRenderer->renderSummary($output, ['errors' => 0, 'warnings' => 0, 'optimizations' => 0], true);

                return 0;
            }

            $emptyReport = new RegexLintReport([], ['errors' => 0, 'warnings' => 0, 'optimizations' => 0]);
            echo $formatter->format($emptyReport);

            return 0;
        }

        if ('console' === $format && $config->shouldShowProgress() && $collectionTime > 1) {
            $collectionInfo = 'Collection: '.round($collectionTime, 2).'s';
            if ($jobs > 1) {
                $collectionInfo .= ' (parallel: '.$jobs.' workers)';
            }
            $output->write('  '.$output->dim($collectionInfo)."\n\n");
        }

        $progressCallback = null;
        $analysisStartTime = microtime(true);
        if ('console' === $format && $config->shouldShowProgress()) {
            $output->write('  '.$output->dim('[2/2] Analyzing patterns')."\n");
            $output->progressStart(\count($patterns));
            $progressCallback = static fn () => $output->progressAdvance();
        }

        $report = $lint->analyze($patterns, $request, $progressCallback);

        if (null !== $progressCallback) {
            $output->progressFinish();
        }

        $analysisTime = microtime(true) - $analysisStartTime;

        if ('console' === $format && $config->shouldShowProgress() && $analysisTime > 0.1) {
            $analysisInfo = 'Analysis: '.round($analysisTime, 2).'s';
            if ($jobs > 1) {
                $analysisInfo .= ' (parallel: '.$jobs.' workers)';
            }
            $output->write('  '.$output->dim($analysisInfo)."\n");
        }

        echo $formatter->format($report);
        echo $formatter->getSummary($report->stats);
        $elapsed = microtime(true) - $startTime;
        $peakMemory = memory_get_peak_usage(true);
        $output->write('  '.$output->bold('Time: '.round($elapsed, 2).'s | Memory: '.round($peakMemory / 1024 / 1024, 2).' MB')."\n");
        $output->write("\n");
        echo $formatter->formatFooter();

        return $report->stats['errors'] > 0 ? 1 : 0;
    }
}
