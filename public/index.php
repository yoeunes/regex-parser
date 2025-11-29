<?php

// 1. Autoloading
require __DIR__.'/../vendor/autoload.php';

use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\NodeVisitor\ArrayExplorerVisitor;
use RegexParser\Regex;

// 2. Configuration
header('Content-Type: text/html; charset=utf-8');
$regex = $_POST['regex'] ?? '';
$action = $_POST['action'] ?? 'parse';
$result = null;
$error = null;
$duration = 0;

// 3. Processing Logic
if ($regex) {
    try {
        $regexParser = Regex::create();
        $start = microtime(true);

        switch ($action) {
            case 'parse':
                $ast = $regexParser->parse($regex);
                $explorer = new ArrayExplorerVisitor();
                $result = [
                    'type' => 'parse',
                    'tree' => $ast->accept($explorer),
                    'raw' => $regexParser->dump($regex),
                    'flags' => $ast->flags,
                ];

                break;

            case 'validate':
                $validation = $regexParser->validate($regex);
                $result = [
                    'type' => 'validate',
                    'isValid' => $validation->isValid,
                    'error' => $validation->error,
                    'score' => $validation->complexityScore,
                ];

                break;

            case 'explain':
                $result = [
                    'type' => 'explain',
                    'explanation' => $regexParser->explain($regex),
                ];

                break;

            case 'generate':
                $result = [
                    'type' => 'generate',
                    'sample' => $regexParser->generate($regex),
                ];

                break;

            case 'redos':
                $analysis = $regexParser->analyzeReDoS($regex);
                $result = [
                    'type' => 'redos',
                    'severity' => $analysis->severity->value,
                    'score' => $analysis->score,
                    'isSafe' => $analysis->isSafe(),
                    'recommendations' => $analysis->recommendations,
                ];

                break;

            case 'literals':
                $literals = $regexParser->extractLiterals($regex);
                $result = [
                    'type' => 'literals',
                    'prefixes' => $literals->prefixes,
                    'suffixes' => $literals->suffixes,
                    'longestPrefix' => $literals->getLongestPrefix(),
                    'longestSuffix' => $literals->getLongestSuffix(),
                ];

                break;
        }
        $duration = round((microtime(true) - $start) * 1000, 2);
    } catch (ParserException|LexerException|\Throwable $e) {
        $error = $e->getMessage();
    }
}

/**
 * Recursive View Helper for rendering the AST Tree (Compact Version)
 */
function renderTree(array $node, int $depth = 0): void
{
    $children = $node['children'] ?? [];
    $hasChildren = !empty($children);
    // Mapping colors to simpler UI classes
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

    echo '<div class="relative">';

    // Hierarchy line
    if ($depth > 0) {
        echo '<div class="absolute left-0 top-0 bottom-0 w-px bg-slate-200 -ml-3"></div>';
    }

    if ($hasChildren) {
        echo '<details open class="group">';
        echo '<summary class="list-none cursor-pointer flex items-center gap-2 py-1 px-1.5 rounded hover:bg-slate-50 transition-colors select-none -ml-1.5">';

        // Chevron
        echo '<span class="w-4 h-4 flex items-center justify-center text-slate-400 group-open:rotate-90 transition-transform duration-150">';
        echo '<i class="fa-solid fa-caret-right text-xs"></i>';
        echo '</span>';
    } else {
        echo '<div class="flex items-center gap-2 py-1 px-1.5 rounded hover:bg-slate-50 -ml-1.5">';
        // Spacer for leaf nodes alignment
        echo '<span class="w-4 h-4"></span>';
    }

    // Icon
    echo "<div class='w-5 h-5 rounded flex items-center justify-center shrink-0 {$styleClass} text-[10px] border border-black/5'>";
    echo "<i class='{$node['icon']}'></i>";
    echo '</div>';

    // Content
    echo '<div class="flex items-baseline gap-2 overflow-hidden">';
    echo "<span class='text-xs font-semibold text-slate-700 truncate'>{$node['label']}</span>";
    if (!empty($node['detail'])) {
        $detail = htmlspecialchars($node['detail']);
        echo "<code class='text-[10px] font-mono text-slate-500 bg-white px-1 border border-slate-200 rounded truncate max-w-[200px]'>{$detail}</code>";
    }
    echo '</div>';

    if ($hasChildren) {
        echo '</summary>';
        // Indented children container
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

?>
<!DOCTYPE html>
<html lang="en" class="h-full antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RegexParser // Documentation & Tools</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        slate: {850: '#1e293b', 900: '#0f172a'}
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom Scrollbar */
        .custom-scroll::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scroll::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 3px;
        }

        .custom-scroll::-webkit-scrollbar-thumb:hover {
            background: #cbd5e1;
        }

        summary::-webkit-details-marker {
            display: none;
        }

        .code-preview {
            background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
            background-size: 20px 20px;
        }
    </style>
</head>
<body class="h-full flex flex-col text-slate-800 bg-white">

<nav class="h-14 border-b border-slate-200 flex items-center justify-between px-4 bg-white z-20 sticky top-0">
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-slate-900 text-white rounded-lg flex items-center justify-center shadow-md">
            <i class="fa-solid fa-code-branch text-sm"></i>
        </div>
        <div class="flex flex-col">
            <h1 class="font-bold text-sm text-slate-900 leading-none">RegexParser</h1>
            <span
                class="text-[10px] font-medium text-slate-500 uppercase tracking-wider mt-0.5">Documentation & Tools</span>
        </div>
    </div>

    <div class="flex items-center gap-4">
        <?php
        if ($result) { ?>
            <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 border border-slate-200 text-[10px] font-mono font-medium text-slate-600">
                    <i class="fa-solid fa-bolt text-amber-500"></i> <?php
                echo $duration; ?>ms
                </span>
        <?php
        } ?>
        <a href="https://github.com/yoeunes/regex-parser" target="_blank"
           class="text-slate-400 hover:text-slate-900 transition-colors">
            <i class="fa-brands fa-github text-xl"></i>
        </a>
    </div>
</nav>

<div class="flex flex-1 overflow-hidden">

    <aside class="w-[320px] bg-slate-50 border-r border-slate-200 flex flex-col overflow-hidden">
        <form method="POST" id="regexForm" class="flex flex-col h-full">

            <div class="p-4 border-b border-slate-200 bg-white">
                <div class="flex justify-between items-center mb-2">
                    <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Current Pattern</label>
                    <span class="text-[10px] text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">PCRE2</span>
                </div>
                <div class="relative">
                    <input type="text" id="regex" name="regex"
                           class="block w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm font-mono text-slate-700 shadow-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all"
                           placeholder="/expression/i"
                           value="<?php
                           echo htmlspecialchars($regex); ?>"
                           autocomplete="off" spellcheck="false">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto custom-scroll p-4">

                <div class="mb-6">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-3 px-1">Actions</div>
                    <div class="grid grid-cols-2 gap-2">
                        <?php
                        $tools = [
                            'parse' => [
                                'icon' => 'fa-code',
                                'label' => 'Parse AST',
                                'bg' => 'hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-200'
                            ],
                            'validate' => [
                                'icon' => 'fa-check-double',
                                'label' => 'Validate',
                                'bg' => 'hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200'
                            ],
                            'explain' => [
                                'icon' => 'fa-list-ul',
                                'label' => 'Explain',
                                'bg' => 'hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200'
                            ],
                            'generate' => [
                                'icon' => 'fa-shuffle',
                                'label' => 'Generate',
                                'bg' => 'hover:bg-violet-50 hover:text-violet-700 hover:border-violet-200'
                            ],
                            'redos' => [
                                'icon' => 'fa-shield-virus',
                                'label' => 'Audit Security',
                                'bg' => 'hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200'
                            ],
                            'literals' => [
                                'icon' => 'fa-filter',
                                'label' => 'Optimize',
                                'bg' => 'hover:bg-amber-50 hover:text-amber-700 hover:border-amber-200'
                            ],
                        ];

foreach ($tools as $key => $tool) {
    $isActive = $action === $key;
    $base
        = 'flex flex-col items-center justify-center p-3 rounded-lg border text-center transition-all cursor-pointer gap-2';
    $style = $isActive
        ? 'bg-slate-800 text-white border-slate-800 shadow-md'
        : 'bg-white border-slate-200 text-slate-600 '.$tool['bg'];

    echo "<button type='submit' name='action' value='$key' class='$base $style'>";
    echo "<i class='fa-solid {$tool['icon']} text-sm'></i>";
    echo "<span class='text-[11px] font-medium leading-tight'>{$tool['label']}</span>";
    echo '</button>';
}
?>
                    </div>
                </div>

                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-3 px-1">Pattern
                        Library
                    </div>
                    <div class="space-y-2">
                        <?php
$presets = [
    [
        'name' => 'Email Validation',
        'regex' => '/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i',
        'icon' => 'fa-envelope',
        'action' => 'parse'
    ],
    [
        'name' => 'ISO Date (Named Groups)',
        'regex' => '/^(?<Y>\d{4})-(?<M>\d{2})-(?<D>\d{2})$/',
        'icon' => 'fa-calendar',
        'action' => 'parse'
    ],
    [
        'name' => 'ReDoS Exploit Check',
        'regex' => '/(a+)+b/',
        'icon' => 'fa-bomb',
        'action' => 'redos',
        'warning' => true
    ],
    [
        'name' => 'URL Matcher',
        'regex' => '/^https?:\/\/([\w.-]+)(:\d+)?(\/.*)?$/i',
        'icon' => 'fa-link',
        'action' => 'explain'
    ],
    [
        'name' => 'Lookbehind Price',
        'regex' => '/(?<=price: )\$\d+\.\d{2}/',
        'icon' => 'fa-tag',
        'action' => 'parse'
    ],
    [
        'name' => 'Recursive Pattern',
        'regex' => '/\((?>[^()]|(?R))*\)/',
        'icon' => 'fa-recycle',
        'action' => 'parse'
    ],
];

foreach ($presets as $p) {
    $patternDisplay = \strlen($p['regex']) > 35 ? substr($p['regex'], 0, 35).'...' : $p['regex'];
    $iconColor = isset($p['warning']) ? 'text-red-400' : 'text-slate-400';

    echo "
                                <div onclick=\"setPattern('".addslashes($p['regex'])."', '{$p['action']}')\" 
                                     class='group bg-white border border-slate-200 rounded-lg p-2.5 cursor-pointer hover:border-indigo-400 hover:shadow-sm transition-all'>
                                    <div class='flex items-center gap-2 mb-1.5'>
                                        <i class='fa-solid {$p['icon']} text-xs $iconColor'></i>
                                        <span class='text-xs font-semibold text-slate-700 group-hover:text-indigo-600 transition-colors'>{$p['name']}</span>
                                    </div>
                                    <div class='text-[10px] font-mono text-slate-500 bg-slate-50 px-1.5 py-1 rounded border border-slate-100 truncate'>
                                        {$patternDisplay}
                                    </div>
                                </div>";
}
?>
                    </div>
                </div>

            </div>
        </form>
    </aside>

    <main class="flex-1 bg-white overflow-hidden flex flex-col relative code-preview">

        <?php
        if ($error) { ?>
            <div class="flex-1 flex flex-col items-center justify-center p-10">
                <div class="bg-red-50 border border-red-100 rounded-2xl p-8 max-w-lg text-center shadow-sm">
                    <div
                        class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h3 class="text-lg font-bold text-red-900 mb-2">Syntax Error</h3>
                    <code
                        class="block bg-white border border-red-100 p-3 rounded text-xs font-mono text-red-600 text-left">
                        <?php
echo htmlspecialchars($error); ?>
                    </code>
                </div>
            </div>

        <?php
        } elseif (!$result) { ?>
            <div class="flex-1 overflow-y-auto custom-scroll">
                <div class="max-w-3xl mx-auto p-12">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl font-bold text-slate-900 mb-4 tracking-tight">Documentation &
                            Playground</h2>
                        <p class="text-lg text-slate-500">A strictly typed, zero-dependency PHP library for parsing,
                            analyzing, and optimizing PCRE regular expressions.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-6 mb-12">
                        <div
                            class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                            <div
                                class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center mb-4 text-xl">
                                <i class="fa-solid fa-tree"></i></div>
                            <h3 class="font-bold text-slate-900 mb-2">AST Parsing</h3>
                            <p class="text-sm text-slate-600">Converts complex regex strings into a structured,
                                traversable Object Tree for static analysis.</p>
                        </div>
                        <div
                            class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                            <div
                                class="w-10 h-10 bg-rose-50 text-rose-600 rounded-lg flex items-center justify-center mb-4 text-xl">
                                <i class="fa-solid fa-shield-halved"></i></div>
                            <h3 class="font-bold text-slate-900 mb-2">Security Audits</h3>
                            <p class="text-sm text-slate-600">Detects <strong>Catastrophic Backtracking (ReDoS)</strong>
                                vulnerabilities and structural flaws.</p>
                        </div>
                        <div
                            class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                            <div
                                class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center mb-4 text-xl">
                                <i class="fa-solid fa-wand-magic-sparkles"></i></div>
                            <h3 class="font-bold text-slate-900 mb-2">Generators</h3>
                            <p class="text-sm text-slate-600">Reverse-generates valid sample strings and human-readable
                                explanations from any pattern.</p>
                        </div>
                        <div
                            class="p-6 rounded-xl bg-white border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                            <div
                                class="w-10 h-10 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center mb-4 text-xl">
                                <i class="fa-solid fa-bolt"></i></div>
                            <h3 class="font-bold text-slate-900 mb-2">Optimization</h3>
                            <p class="text-sm text-slate-600">Extracts literals for pre-match optimization and
                                simplifies regex structures.</p>
                        </div>
                    </div>

                    <div class="bg-slate-900 rounded-xl p-6 text-slate-300 shadow-xl">
                        <div class="flex justify-between items-center mb-4 border-b border-slate-700 pb-4">
                            <span class="text-sm font-bold text-white">Installation</span>
                            <code class="text-xs bg-slate-800 px-2 py-1 rounded">composer require
                                yoeunes/regex-parser</code>
                        </div>
                        <pre class="font-mono text-xs leading-relaxed overflow-x-auto"><code>use RegexParser\Regex;

$parser = Regex::create();
$ast = $parser->parse('/(?<name>\w+)/');

echo $ast->flags; // ""
echo $parser->explain($pattern); // "Start Capturing Group..."</code></pre>
                    </div>
                </div>
            </div>

        <?php
        } else { ?>

            <div class="flex-1 overflow-hidden flex flex-col">

                <div class="h-12 border-b border-slate-200 bg-white px-6 flex items-center justify-between shrink-0">
                    <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wide flex items-center gap-2">
                        <?php
                        $icons = [
                            'parse' => 'fa-sitemap',
                            'validate' => 'fa-check-double',
                            'explain' => 'fa-list',
                            'redos' => 'fa-shield-virus'
                        ];
            $ico = $icons[$result['type']] ?? 'fa-terminal';
            ?>
                        <i class="fa-solid <?php
            echo $ico; ?> text-indigo-500"></i>
                        <?php
            echo ucfirst($result['type']); ?> Output
                    </h2>
                    <div class="flex gap-2">
                        <button
                            class="text-xs font-medium text-slate-500 hover:text-indigo-600 px-2 py-1 rounded hover:bg-slate-100 transition">
                            <i class="fa-solid fa-download mr-1"></i> Export JSON
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto custom-scroll bg-white relative">

                    <?php
                    if ('parse' === $result['type']) { ?>
                        <div class="flex h-full">
                            <div class="flex-1 p-6 overflow-y-auto custom-scroll">
                                <?php
                    renderTree($result['tree']); ?>
                            </div>
                            <div class="w-[350px] bg-slate-50 border-l border-slate-200 p-0 flex flex-col">
                                <div
                                    class="p-3 border-b border-slate-200 bg-slate-100 text-[10px] font-bold text-slate-500 uppercase">
                                    Structure Dump
                                </div>
                                <pre
                                    class="p-4 font-mono text-[10px] text-slate-600 overflow-auto custom-scroll flex-1 leading-relaxed"><?php
                        echo htmlspecialchars($result['raw']); ?></pre>
                            </div>
                        </div>

                    <?php
                    } elseif ('explain' === $result['type']) { ?>
                        <div class="max-w-3xl mx-auto p-10">
                            <div class="prose prose-sm max-w-none">
                                <div
                                    class="font-mono text-sm bg-slate-50 p-6 rounded-xl border border-slate-200 leading-7 text-slate-700 whitespace-pre-wrap">
                                    <?php
                                    // Highlight keywords manually for better look
                                    $exp = htmlspecialchars($result['explanation']);
                        $exp = preg_replace(
                            '/^(.*?)(:)/m',
                            '<strong class="text-indigo-700">$1</strong>$2',
                            $exp,
                        );
                        echo nl2br($exp);
                        ?>
                                </div>
                            </div>
                        </div>

                    <?php
                    } elseif ('redos' === $result['type']) { ?>
                        <div class="p-10 max-w-4xl mx-auto">
                            <div class="grid grid-cols-3 gap-6 mb-8">
                                <div class="bg-white border border-slate-200 rounded-xl p-6 text-center shadow-sm">
                                    <div class="text-xs font-bold text-slate-400 uppercase mb-2">Vulnerability Status
                                    </div>
                                    <?php
                                    if ($result['isSafe']) { ?>
                                        <div class="text-2xl font-bold text-emerald-600"><i
                                                class="fa-solid fa-check-circle mr-2"></i>SAFE
                                        </div>
                                    <?php
                                    } else { ?>
                                        <div class="text-2xl font-bold text-rose-600"><i
                                                class="fa-solid fa-triangle-exclamation mr-2"></i>VULNERABLE
                                        </div>
                                    <?php
                                    } ?>
                                </div>
                                <div class="bg-white border border-slate-200 rounded-xl p-6 text-center shadow-sm">
                                    <div class="text-xs font-bold text-slate-400 uppercase mb-2">Complexity Score</div>
                                    <div class="text-3xl font-mono font-bold text-slate-800"><?php
                                        echo $result['score']; ?></div>
                                </div>
                                <div class="bg-white border border-slate-200 rounded-xl p-6 text-center shadow-sm">
                                    <div class="text-xs font-bold text-slate-400 uppercase mb-2">Severity Level</div>
                                    <div class="inline-block px-3 py-1 rounded-full text-sm font-bold uppercase
                                            <?php
                                    echo match ($result['severity']) {
                                        'safe' => 'bg-emerald-100 text-emerald-700',
                                        'low' => 'bg-blue-100 text-blue-700',
                                        'medium' => 'bg-amber-100 text-amber-700',
                                        default => 'bg-rose-100 text-rose-700'
                                    }; ?>">
                                        <?php
                                        echo $result['severity']; ?>
                                    </div>
                                </div>
                            </div>

                            <?php
                            if (!empty($result['recommendations'])) { ?>
                                <div class="bg-rose-50 border border-rose-100 rounded-xl p-6">
                                    <h3 class="text-sm font-bold text-rose-800 uppercase mb-4 flex items-center gap-2">
                                        <i class="fa-solid fa-list-check"></i> Fix Recommendations
                                    </h3>
                                    <ul class="space-y-3">
                                        <?php
                                        foreach ($result['recommendations'] as $rec) { ?>
                                            <li class="flex gap-3 text-sm text-rose-700">
                                                <i class="fa-solid fa-arrow-right mt-1 opacity-50"></i>
                                                <?php
                                                echo htmlspecialchars($rec); ?>
                                            </li>
                                        <?php
                                        } ?>
                                    </ul>
                                </div>
                            <?php
                            } ?>
                        </div>

                    <?php
                    } elseif ('generate' === $result['type']) { ?>
                        <div class="flex flex-col items-center justify-center h-full pb-20">
                            <div class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Generated
                                Match
                            </div>
                            <div
                                class="text-3xl md:text-4xl font-mono text-indigo-600 bg-indigo-50/50 px-8 py-6 rounded-2xl border border-indigo-100 text-center break-all max-w-3xl">
                                <?php
                                echo htmlspecialchars($result['sample']); ?>
                            </div>
                            <button onclick="navigator.clipboard.writeText('<?php
                            echo addslashes($result['sample']); ?>')"
                                    class="mt-6 text-xs font-medium text-slate-400 hover:text-indigo-600 transition-colors flex items-center gap-2">
                                <i class="fa-regular fa-copy"></i> Copy to clipboard
                            </button>
                        </div>

                    <?php
                    } else { ?>
                        <div class="p-10">
                            <pre
                                class="bg-slate-50 p-6 rounded-xl border border-slate-200 font-mono text-sm text-slate-700 overflow-auto"><?php
                                print_r($result); ?></pre>
                        </div>
                    <?php
                    } ?>

                </div>
            </div>

        <?php
        } ?>
    </main>
</div>

<script>
    function setPattern(pattern, action) {
        document.getElementById('regex').value = pattern;
        const btn = document.querySelector(`button[value="${action}"]`);
        if (btn) btn.click();
    }
</script>
</body>
</html>
