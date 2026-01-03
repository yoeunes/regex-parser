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

use RegexParser\Cli\ConsoleStyle;
use RegexParser\Cli\Input;
use RegexParser\Cli\Output;
use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\NodeVisitor\ConsoleHighlighterVisitor;
use RegexParser\ReDoS\ReDoSHeatmap;
use RegexParser\ReDoS\ReDoSHotspot;
use RegexParser\ReDoS\ReDoSInputGenerator;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\RegexOptions;
use RegexParser\RegexPattern;

final class DebugCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'debug';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getDescription(): string
    {
        return 'Deep ReDoS analysis with heatmap output';
    }

    public function run(Input $input, Output $output): int
    {
        $pattern = $input->args[0] ?? '';
        if ('' === $pattern) {
            $output->write($output->error("Error: Missing pattern\n"));
            $output->write("Usage: regex debug <pattern> [--input <string>]\n");

            return 1;
        }

        $inputValue = null;
        for ($i = 0; $i < \count($input->args); $i++) {
            $arg = $input->args[$i];
            if (str_starts_with($arg, '--input=')) {
                $inputValue = substr($arg, \strlen('--input='));

                continue;
            }
            if ('--input' === $arg) {
                $value = $input->args[$i + 1] ?? '';
                if ('' === $value || str_starts_with($value, '-')) {
                    $output->write($output->error("Error: Missing value for --input\n"));

                    return 1;
                }
                $inputValue = $value;
                $i++;
            }
        }

        $regex = $this->createRegex($output, $input->regexOptions);
        if (null === $regex) {
            return 1;
        }

        $style = new ConsoleStyle($output, $input->globalOptions->visuals);
        $meta = [];
        if (null !== $input->globalOptions->phpVersion) {
            $meta['Target PHP'] = $output->warning('PHP '.$input->globalOptions->phpVersion);
        }

        $style->renderBanner('Debug', $meta);

        $phpVersionId = null;
        if ([] !== $input->regexOptions) {
            $phpVersionId = RegexOptions::fromArray($input->regexOptions)->phpVersionId;
        }

        try {
            $patternInfo = RegexPattern::fromDelimited($pattern, $phpVersionId);
            $analysis = $regex->redos($pattern);
            $steps = [] !== $analysis->findings ? 2 : 1;
            $heatmap = new ReDoSHeatmap();
            $heatmapBody = $heatmap->highlight($patternInfo->pattern, $analysis->hotspots, $output->isAnsi());
            $heatmapPattern = $patternInfo->delimiter.$heatmapBody.$patternInfo->delimiter.$patternInfo->flags;
            $highlightedPattern = $pattern;
            $showSyntaxPattern = $output->isAnsi() && $style->visualsEnabled();

            if ($showSyntaxPattern) {
                try {
                    $ast = $regex->parse($pattern);
                    $highlightedBody = $ast->accept(new ConsoleHighlighterVisitor());
                    $highlightedPattern = $patternInfo->delimiter.$highlightedBody.$patternInfo->delimiter.$patternInfo->flags;
                } catch (LexerException|ParserException) {
                    $highlightedPattern = $pattern;
                }
            }

            $style->renderSection('Heatmap', 1, $steps);
            if ($showSyntaxPattern) {
                $style->renderPattern($highlightedPattern);
            }

            $showHeatmapLine = [] !== $analysis->hotspots || !$showSyntaxPattern;
            $heatmapPrefix = '';
            if ($showHeatmapLine) {
                $label = $showSyntaxPattern ? 'Heatmap' : 'Pattern';
                $heatmapPrefix = '  '.$label.':    ';
                $output->write($heatmapPrefix.$heatmapPattern."\n");
            }

            if (null !== $analysis->error) {
                $output->write('  Error:      '.$output->error($analysis->error)."\n");
            }

            $severityLabel = strtoupper($analysis->severity->value);
            $severityOutput = match ($analysis->severity) {
                ReDoSSeverity::SAFE, ReDoSSeverity::LOW => $output->success($severityLabel),
                ReDoSSeverity::MEDIUM => $output->warning($severityLabel),
                ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL => $output->error($severityLabel),
                ReDoSSeverity::UNKNOWN => $output->info($severityLabel),
            };

            $output->write('  ReDoS:      '.$severityOutput.' (score '.$analysis->score.")\n");

            if (null !== $analysis->getVulnerableSubpattern()) {
                $output->write('  Culprit:    '.$analysis->getVulnerableSubpattern()."\n");
            }

            if (null !== $analysis->trigger && '' !== $analysis->trigger) {
                $output->write('  Trigger:    '.$analysis->trigger."\n");
            }

            if ([] !== $analysis->hotspots) {
                $output->write('  Hotspots:   '.\count($analysis->hotspots)."\n");
            }

            $inputSource = '';
            if (null === $inputValue && null !== $analysis->getCulpritNode()) {
                $inputValue = (new ReDoSInputGenerator())->generate(
                    $analysis->getCulpritNode(),
                    $patternInfo->flags,
                    $analysis->severity,
                );
                $inputSource = ' (auto)';
            }

            if (null !== $inputValue) {
                $escaped = addcslashes($inputValue, "\0..\37\177..\377");
                $output->write('  Input:      "'.$escaped.'"'.$inputSource."\n");
            }

            $hotspot = null;
            $hotspotRank = -1;
            foreach ($analysis->hotspots as $candidate) {
                // @codeCoverageIgnoreStart
                if (!$candidate instanceof ReDoSHotspot) {
                    continue;
                }
                // @codeCoverageIgnoreEnd
                $rank = match ($candidate->severity) {
                    ReDoSSeverity::SAFE => 0,
                    ReDoSSeverity::LOW => 1,
                    ReDoSSeverity::MEDIUM => 2,
                    ReDoSSeverity::HIGH => 3,
                    ReDoSSeverity::CRITICAL => 4,
                    ReDoSSeverity::UNKNOWN => 1, // @codeCoverageIgnore
                };
                if ($rank > $hotspotRank) {
                    $hotspotRank = $rank;
                    $hotspot = $candidate;
                }
            }

            if (null !== $hotspot && '' !== $heatmapPrefix) {
                $prefix = $heatmapPrefix;
                $start = max(0, $hotspot->start);
                $length = max(1, $hotspot->end - $hotspot->start);
                $caret = str_repeat(' ', \strlen($prefix) + 1 + $start).str_repeat('^', $length);
                $caretColor = match ($hotspot->severity) {
                    ReDoSSeverity::SAFE, ReDoSSeverity::LOW => Output::GREEN,
                    ReDoSSeverity::MEDIUM => Output::YELLOW,
                    ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL => Output::RED,
                    ReDoSSeverity::UNKNOWN => Output::GRAY, // @codeCoverageIgnore
                };
                $output->write($output->color($caret, $caretColor)."\n");
            }

            if ([] !== $analysis->findings) {
                $output->write("\n");
                $style->renderSection('Findings', $steps, $steps);
                foreach ($analysis->findings as $finding) {
                    $label = strtoupper($finding->severity->value);
                    $findingSeverity = match ($finding->severity) {
                        ReDoSSeverity::SAFE, ReDoSSeverity::LOW => $output->success($label),
                        ReDoSSeverity::MEDIUM => $output->warning($label),
                        ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL => $output->error($label),
                        ReDoSSeverity::UNKNOWN => $output->info($label), // @codeCoverageIgnore
                    };
                    $output->write('  - ['.$findingSeverity.'] '.$finding->message."\n");
                    if (null !== $finding->suggestedRewrite && '' !== $finding->suggestedRewrite) {
                        $output->write('      Suggested: '.$finding->suggestedRewrite."\n");
                    }
                }
            }
        } catch (\Throwable $e) {
            $output->write('  '.$output->badge('FAIL', Output::WHITE, Output::BG_RED).' '.$output->error('Debug failed: '.$e->getMessage())."\n");

            return 1;
        }

        return 0;
    }
}
