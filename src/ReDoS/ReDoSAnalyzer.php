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

namespace RegexParser\ReDoS;

use RegexParser\Lexer;
use RegexParser\NodeVisitor\ReDoSProfileVisitor;
use RegexParser\Parser;
use RegexParser\Regex;
use RegexParser\TokenStream;

class ReDoSAnalyzer
{
    /**
     * Analyzes a regex pattern for ReDoS vulnerabilities and returns a detailed report.
     */
    public function analyze(string $regex): ReDoSAnalysis
    {
        try {
            $ast = $this->parseRegex($regex);
            $visitor = new ReDoSProfileVisitor();
            $ast->accept($visitor);

            $result = $visitor->getResult();

            return new ReDoSAnalysis(
                $result['severity'],
                $this->calculateScore($result['severity']),
                $result['vulnerablePattern'],
                $result['recommendations'],
            );
        } catch (\Throwable $e) {
            // Fallback for parsing errors, treat as unknown/safe or rethrow
            return new ReDoSAnalysis(ReDoSSeverity::SAFE, 0, null, ['Error parsing regex: '.$e->getMessage()]);
        }
    }

    private function calculateScore(ReDoSSeverity $severity): int
    {
        return match ($severity) {
            ReDoSSeverity::SAFE => 0,
            ReDoSSeverity::LOW => 2,
            ReDoSSeverity::MEDIUM => 5,
            ReDoSSeverity::HIGH => 8,
            ReDoSSeverity::CRITICAL => 10,
        };
    }

    /**
     * Parses a regex string using the decoupled Lexer and Parser.
     */
    private function parseRegex(string $regex): \RegexParser\Node\RegexNode
    {
        [$pattern, $flags, $delimiter] = $this->extractPatternAndFlags($regex);

        $lexer = new Lexer($pattern);
        $stream = new TokenStream($lexer->tokenize());
        $parser = new Parser();

        return $parser->parse($stream, $flags, $delimiter, \strlen($pattern));
    }

    /**
     * Extracts pattern, flags, and delimiter from a full regex string.
     *
     * @return array{0: string, 1: string, 2: string} [pattern, flags, delimiter]
     */
    private function extractPatternAndFlags(string $regex): array
    {
        // Use Regex class's static method for consistency
        return Regex::create()->extractPatternAndFlags($regex);
    }
}
