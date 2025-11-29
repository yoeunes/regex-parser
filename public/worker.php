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

// Ensure we are in the WASM environment
if (!\defined('STDERR')) {
    \define('STDERR', fopen('php://stderr', 'w'));
}

// 1. Bootstrap the environment
// The autoloader is injected into /var/www/autoload.php by the JS frontend
$autoloaderPath = '/var/www/autoload.php';

if (!file_exists($autoloaderPath)) {
    echo json_encode([
        'error' => 'Library not loaded. The autoloader was not found in the WASM filesystem.',
    ]);
    exit(1);
}

require_once $autoloaderPath;

use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\NodeVisitor\ArrayExplorerVisitor;
use RegexParser\Regex;

// 2. Parse Input
// In the PHP-WASM environment, we inject the input as a variable $jsonInput
// to avoid issues with php://stdin stream blocking in some runtimes.
$rawInput = $jsonInput ?? '{}';

try {
    $input = json_decode($rawInput, true, 512, \JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    echo json_encode(['error' => 'Invalid JSON input: '.$e->getMessage()]);
    exit(1);
}

$regex = $input['regex'] ?? '';
$action = $input['action'] ?? 'parse';
$response = ['error' => null, 'result' => null];

// 3. Process Request
if ('' !== $regex) {
    try {
        $parser = Regex::create();

        switch ($action) {
            case 'parse':
                $ast = $parser->parse($regex);
                $explorer = new ArrayExplorerVisitor();
                $treeData = $ast->accept($explorer);

                // Server-Side Rendering (SSR) of the AST Tree
                // We render HTML here to keep the frontend JS lightweight and logic-free.
                ob_start();
                renderTree($treeData);
                $htmlTree = ob_get_clean();

                $response['result'] = [
                    'type' => 'parse',
                    'html_tree' => $htmlTree,
                    'raw' => $parser->dump($regex),
                    'flags' => $ast->flags,
                ];

                break;

            case 'validate':
                $res = $parser->validate($regex);
                $response['result'] = [
                    'type' => 'validate',
                    'isValid' => $res->isValid,
                    'error' => $res->error,
                    'score' => $res->complexityScore,
                ];

                break;

            case 'explain':
                $response['result'] = [
                    'type' => 'explain',
                    'explanation' => $parser->explain($regex),
                ];

                break;

            case 'generate':
                $response['result'] = [
                    'type' => 'generate',
                    'sample' => $parser->generate($regex),
                ];

                break;

            case 'redos':
                $analysis = $parser->analyzeReDoS($regex);
                $response['result'] = [
                    'type' => 'redos',
                    'severity' => $analysis->severity->value,
                    'score' => $analysis->score,
                    'isSafe' => $analysis->isSafe(),
                    'recommendations' => $analysis->recommendations,
                ];

                break;

            case 'literals':
                $literals = $parser->extractLiterals($regex);
                $response['result'] = [
                    'type' => 'literals',
                    'prefixes' => $literals->prefixes,
                    'suffixes' => $literals->suffixes,
                    'longestPrefix' => $literals->getLongestPrefix(),
                    'longestSuffix' => $literals->getLongestSuffix(),
                ];

                break;

            default:
                throw new \InvalidArgumentException(\sprintf('Unknown action "%s".', $action));
        }
    } catch (ParserException|LexerException|\Throwable $e) {
        $response['error'] = $e->getMessage();
    }
}

// 4. Output JSON Response
echo json_encode($response);

/**
 * Recursive View Helper to render the AST Tree as HTML.
 *
 * @param array<string, mixed> $node
 */
function renderTree(array $node, int $depth = 0): void
{
    /** @var array<array<string, mixed>> $children */
    $children = $node['children'] ?? [];
    $hasChildren = !empty($children);

    // Style mapping for node types
    $colors = [
        'text-indigo-600' => 'text-indigo-600 bg-indigo-50',
        'text-green-600' => 'text-emerald-600 bg-emerald-50',
        'text-emerald-600' => 'text-teal-600 bg-teal-50',
        'text-blue-500' => 'text-blue-600 bg-blue-50',
        'text-blue-600' => 'text-blue-600 bg-blue-50',
        'text-orange-600' => 'text-amber-600 bg-amber-50',
        'text-purple-600' => 'text-purple-600 bg-purple-50',
        'text-red-600' => 'text-rose-600 bg-rose-50',
        'text-slate-700' => 'text-slate-600 bg-slate-100',
    ];

    $styleClass = $colors[$node['color'] ?? ''] ?? 'text-slate-500 bg-slate-50';
    $icon = $node['icon'] ?? 'fa-solid fa-circle';
    $label = htmlspecialchars((string) ($node['label'] ?? 'Node'));

    echo '<div class="relative">';

    // Hierarchy line indentation
    if ($depth > 0) {
        echo '<div class="absolute left-0 top-0 bottom-0 w-px bg-slate-200 -ml-3"></div>';
    }

    if ($hasChildren) {
        echo '<details open class="group">';
        echo '<summary class="list-none cursor-pointer flex items-center gap-2 py-1 px-1.5 rounded hover:bg-slate-50 transition-colors select-none -ml-1.5">';
        echo '<span class="w-4 h-4 flex items-center justify-center text-slate-400 group-open:rotate-90 transition-transform duration-150"><i class="fa-solid fa-caret-right text-xs"></i></span>';
    } else {
        echo '<div class="flex items-center gap-2 py-1 px-1.5 rounded hover:bg-slate-50 -ml-1.5">';
        echo '<span class="w-4 h-4"></span>';
    }

    // Node Icon
    echo \sprintf('<div class="w-5 h-5 rounded flex items-center justify-center shrink-0 %s text-[10px] border border-black/5"><i class="%s"></i></div>', $styleClass, $icon);

    // Node Label & Detail
    echo '<div class="flex items-baseline gap-2 overflow-hidden">';
    echo \sprintf('<span class="text-xs font-semibold text-slate-700 truncate">%s</span>', $label);

    if (!empty($node['detail'])) {
        $detail = htmlspecialchars((string) $node['detail']);
        echo \sprintf('<code class="text-[10px] font-mono text-slate-500 bg-white px-1 border border-slate-200 rounded truncate max-w-[200px]">%s</code>', $detail);
    }
    echo '</div>';

    if ($hasChildren) {
        echo '</summary>';
        echo '<div class="pl-4 ml-1.5 border-l border-slate-200/50">';
        foreach ($children as $child) {
            if (\is_array($child)) {
                renderTree($child, $depth + 1);
            }
        }
        echo '</div>';
        echo '</details>';
    } else {
        echo '</div>';
    }
    echo '</div>';
}
