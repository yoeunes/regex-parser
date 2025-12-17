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

use RegexParser\Exception\ParserException;
use RegexParser\Exception\PcreRuntimeException;
use RegexParser\Exception\SemanticErrorException;
use RegexParser\NodeVisitor\ArrayExplorerVisitor;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\Regex;

error_reporting(\E_ALL);
ini_set('display_errors', '0');
ini_set('html_errors', '0');

// Ensure STDERR exists (php-wasm may not define it).
if (!\defined('STDERR')) {
    \define('STDERR', fopen('php://stderr', 'w'));
}

ob_start();
$startedAt = microtime(true);

try {
    $autoloaderPath = '/var/www/autoload.php';
    if (!file_exists($autoloaderPath)) {
        throw new \RuntimeException('Library not loaded: /var/www/autoload.php not found.');
    }

    require_once $autoloaderPath;

    $rawInput = (string) @file_get_contents('php://stdin');
    if ('' === trim($rawInput)) {
        $rawInput = isset($jsonInput) && \is_string($jsonInput) ? $jsonInput : '{}';
    }

    /** @var array<string, mixed> $input */
    $input = json_decode($rawInput, true, 512, \JSON_THROW_ON_ERROR);

    $action = (string) ($input['action'] ?? 'analyze');
    $regex = (string) ($input['regex'] ?? '');

    $parser = Regex::create();

    $result = match ($action) {
        'meta' => [
            'phpVersion' => \PHP_VERSION,
            'pcreVersion' => \defined('PCRE_VERSION') ? (string) \PCRE_VERSION : null,
            'engine' => 'php-wasm',
        ],
        'analyze' => analyzeAction(
            $parser,
            $regex,
            (string) ($input['subject'] ?? ''),
        ),
        'validate' => validateAction($parser, $regex),
        'lint' => lintAction($parser, $regex),
        'parse' => parseAction($parser, $regex),
        'dump' => dumpAction($parser, $regex),
        'explain' => explainAction($parser, $regex),
        'visualize' => visualizeAction($parser, $regex),
        'optimize' => optimizeAction($parser, $regex),
        'modernize' => modernizeAction($parser, $regex),
        'redos' => redosAction($parser, $regex),
        'literals' => literalsAction($parser, $regex),
        'generate' => generateAction($parser, $regex),
        'testcases' => testCasesAction($parser, $regex),
        'match' => matchAction($regex, (string) ($input['subject'] ?? ''), (int) ($input['matchLimit'] ?? 1000)),
        default => throw new \InvalidArgumentException(\sprintf('Unknown action "%s".', $action)),
    };

    $response = [
        'ok' => true,
        'result' => $result,
        'error' => null,
        'meta' => [
            'action' => $action,
            'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
        ],
    ];
} catch (\Throwable $e) {
    $response = [
        'ok' => false,
        'result' => null,
        'error' => errorToArray($e),
        'meta' => [
            'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
        ],
    ];
} finally {
    $strayOutput = trim(ob_get_clean() ?: '');
    if ('' !== $strayOutput) {
        $response['meta']['strayOutput'] = $strayOutput;
    }
}

echo json_encode($response, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

function validateAction(Regex $parser, string $regex): array
{
    $validation = $parser->validate($regex);

    return [
        'regex' => $regex,
        'isValid' => $validation->isValid,
        'complexityScore' => $validation->complexityScore,
        'error' => $validation->error,
        'category' => $validation->getErrorCategory()?->value,
        'offset' => $validation->getErrorOffset(),
        'caret' => $validation->getCaretSnippet(),
        'hint' => $validation->getHint(),
        'code' => $validation->getErrorCode(),
    ];
}

function lintAction(Regex $parser, string $regex): array
{
    $ast = $parser->parse($regex);
    $linter = new LinterNodeVisitor();
    $ast->accept($linter);

    return [
        'regex' => $regex,
        'issues' => array_map(
            static fn (\RegexParser\LintIssue $issue): array => [
                'id' => $issue->id,
                'message' => $issue->message,
                'offset' => $issue->offset,
                'hint' => $issue->hint,
            ],
            $linter->getIssues(),
        ),
    ];
}

function parseAction(Regex $parser, string $regex): array
{
    $ast = $parser->parse($regex);
    $explorer = new ArrayExplorerVisitor();

    return [
        'regex' => $regex,
        'flags' => $ast->flags,
        'tree' => $ast->accept($explorer),
    ];
}

function dumpAction(Regex $parser, string $regex): array
{
    return [
        'regex' => $regex,
        'dump' => $parser->dump($regex),
    ];
}

function explainAction(Regex $parser, string $regex): array
{
    return [
        'regex' => $regex,
        'explanation' => $parser->explain($regex),
    ];
}

function visualizeAction(Regex $parser, string $regex): array
{
    $visualization = $parser->visualize($regex);

    return [
        'regex' => $regex,
        'mermaid' => $visualization->mermaid,
    ];
}

function optimizeAction(Regex $parser, string $regex): array
{
    $optimization = $parser->optimize($regex);

    return [
        'regex' => $regex,
        'optimized' => $optimization->optimized,
        'changes' => $optimization->changes,
        'isChanged' => $optimization->isChanged(),
    ];
}

function modernizeAction(Regex $parser, string $regex): array
{
    return [
        'regex' => $regex,
        'modernized' => $parser->modernize($regex),
    ];
}

function redosAction(Regex $parser, string $regex): array
{
    $analysis = $parser->analyzeReDoS($regex);

    return [
        'regex' => $regex,
        'severity' => $analysis->severity->value,
        'score' => $analysis->score,
        'isSafe' => $analysis->isSafe(),
        'vulnerableSubpattern' => $analysis->getVulnerableSubpattern(),
        'trigger' => $analysis->trigger,
        'confidence' => $analysis->confidence?->value,
        'falsePositiveRisk' => $analysis->falsePositiveRisk,
        'recommendations' => $analysis->recommendations,
        'findings' => array_map(
            static fn (\RegexParser\ReDoS\ReDoSFinding $finding): array => [
                'severity' => $finding->severity->value,
                'message' => $finding->message,
                'pattern' => $finding->pattern,
                'trigger' => $finding->trigger,
                'suggestedRewrite' => $finding->suggestedRewrite,
                'confidence' => $finding->confidence->value,
                'falsePositiveRisk' => $finding->falsePositiveRisk,
            ],
            $analysis->findings,
        ),
    ];
}

function literalsAction(Regex $parser, string $regex): array
{
    $literals = $parser->extractLiterals($regex);
    $literalSet = $literals->literalSet;

    return [
        'regex' => $regex,
        'confidence' => $literals->confidence,
        'literals' => $literals->literals,
        'patterns' => $literals->patterns,
        'prefixes' => $literalSet->prefixes,
        'suffixes' => $literalSet->suffixes,
        'longestPrefix' => $literalSet->getLongestPrefix(),
        'longestSuffix' => $literalSet->getLongestSuffix(),
    ];
}

function generateAction(Regex $parser, string $regex): array
{
    return [
        'regex' => $regex,
        'sample' => $parser->generate($regex),
    ];
}

function testCasesAction(Regex $parser, string $regex): array
{
    $cases = $parser->generateTestCases($regex);

    return [
        'regex' => $regex,
        'matching' => $cases->matching,
        'nonMatching' => $cases->nonMatching,
        'notes' => $cases->notes,
    ];
}

function matchAction(string $regex, string $subject, int $matchLimit): array
{
    if ($matchLimit < 1) {
        $matchLimit = 1;
    }

    if (\strlen($subject) > 250_000) {
        throw new \RuntimeException('Subject is too large (max 250k).');
    }

    $matches = [];
    $ok = @preg_match_all($regex, $subject, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
    if (false === $ok) {
        $msg = preg_last_error_msg();
        throw new \RuntimeException('PCRE error: '.($msg !== '' ? $msg : 'Unknown error.'));
    }

    $normalized = [];
    foreach ($matches as $matchIndex => $match) {
        if ($matchIndex >= $matchLimit) {
            break;
        }

        $groups = [];
        foreach ($match as $key => $value) {
            if (!\is_array($value) || 2 !== \count($value)) {
                continue;
            }

            [$text, $offset] = $value;
            $groups[] = [
                'key' => $key,
                'text' => $text,
                'offset' => $offset,
                'length' => \is_string($text) ? \strlen($text) : null,
            ];
        }

        $normalized[] = [
            'index' => $matchIndex,
            'groups' => $groups,
        ];
    }

    return [
        'regex' => $regex,
        'subject' => $subject,
        'matchCount' => \count($matches),
        'matches' => $normalized,
    ];
}

function analyzeAction(Regex $parser, string $regex, string $subject): array
{
    return [
        'regex' => $regex,
        'subject' => $subject,
        'match' => safeAction(static fn (): array => matchAction($regex, $subject, 1000)),
        'validate' => safeAction(static fn (): array => validateAction($parser, $regex)),
        'lint' => safeAction(static fn (): array => lintAction($parser, $regex)),
        'redos' => safeAction(static fn (): array => redosAction($parser, $regex)),
        'optimize' => safeAction(static fn (): array => optimizeAction($parser, $regex)),
        'literals' => safeAction(static fn (): array => literalsAction($parser, $regex)),
        'visualize' => safeAction(static fn (): array => visualizeAction($parser, $regex)),
    ];
}

/**
 * @param callable(): array<string, mixed> $callable
 *
 * @return array{ok: bool, result: array<string, mixed>|null, error: array<string, mixed>|null}
 */
function safeAction(callable $callable): array
{
    try {
        return [
            'ok' => true,
            'result' => $callable(),
            'error' => null,
        ];
    } catch (\Throwable $e) {
        return [
            'ok' => false,
            'result' => null,
            'error' => errorToArray($e),
        ];
    }
}

/**
 * @return array{message: string, type: string, category: string, offset: int|null, caret: string|null, hint: string|null, code: string|null}
 */
function errorToArray(\Throwable $e): array
{
    $category = 'runtime';
    $hint = null;
    $code = null;
    $offset = null;
    $caret = null;

    if ($e instanceof ParserException) {
        $offset = $e->getPosition();
        $caret = $e->getVisualSnippet() !== '' ? $e->getVisualSnippet() : null;
        $category = 'syntax';
    }

    if ($e instanceof SemanticErrorException) {
        $category = 'semantic';
        $hint = $e->getHint();
        $code = $e->getErrorCode();
    }

    if ($e instanceof PcreRuntimeException) {
        $category = 'pcre-runtime';
        $code = $e->getErrorCode();
    }

    return [
        'message' => $e->getMessage(),
        'type' => $e::class,
        'category' => $category,
        'offset' => $offset,
        'caret' => $caret,
        'hint' => $hint,
        'code' => $code,
    ];
}
