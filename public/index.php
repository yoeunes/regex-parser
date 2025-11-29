<?php

// 1. Autoloading and Imports
require __DIR__.'/../vendor/autoload.php';

use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Regex;
use RegexParser\NodeVisitor\ArrayExplorerVisitor;

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
                    'type'  => 'parse',
                    'tree'  => $ast->accept($explorer),
                    'raw'   => $regexParser->dump($regex),
                    'flags' => $ast->flags,
                ];
                break;

            case 'validate':
                $validation = $regexParser->validate($regex);
                $result = [
                    'type'    => 'validate',
                    'isValid' => $validation->isValid,
                    'error'   => $validation->error,
                    'score'   => $validation->complexityScore,
                ];
                break;

            case 'explain':
                $result = [
                    'type'        => 'explain',
                    'explanation' => $regexParser->explain($regex),
                ];
                break;

            case 'generate':
                $result = [
                    'type'   => 'generate',
                    'sample' => $regexParser->generate($regex),
                ];
                break;

            case 'redos':
                $analysis = $regexParser->analyzeReDoS($regex);
                $result = [
                    'type'            => 'redos',
                    'severity'        => $analysis->severity->value,
                    'score'           => $analysis->score,
                    'isSafe'          => $analysis->isSafe(),
                    'recommendations' => $analysis->recommendations,
                ];
                break;

            case 'literals':
                $literals = $regexParser->extractLiterals($regex);
                $result = [
                    'type'          => 'literals',
                    'prefixes'      => $literals->prefixes,
                    'suffixes'      => $literals->suffixes,
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
 * Recursive View Helper for rendering the AST Tree
 */
function renderTree(array $node): void
{
    $children = $node['children'] ?? [];
    $hasChildren = !empty($children);
    $bgColor = $node['bg'] ?? 'bg-white';
    $borderColor = $node['border'] ?? 'border-slate-100';
    $iconColor = $node['color'] ?? 'text-slate-500';
    $type = $node['type'] ?? 'Node';

    // Special handling for "Sequence" nodes to reduce visual clutter
    // If a sequence has only children and no specific detail, we might want to render it simply
    // But for structure clarity, we keep it collapsible.

    echo '<div class="ml-5 my-2">';

    if ($hasChildren) {
        echo '<details open class="group">';
        echo '<summary class="list-none cursor-pointer flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 transition-all border border-transparent hover:border-slate-200 select-none">';

        // Chevron
        echo '<span class="w-4 h-4 flex items-center justify-center text-slate-400 group-open:rotate-90 transition-transform duration-200">';
        echo '<i class="fa-solid fa-chevron-right text-[10px]"></i>';
        echo '</span>';
    } else {
        echo '<div class="flex items-center gap-3 p-2 rounded-lg border border-transparent bg-white shadow-sm ring-1 ring-slate-100">';
    }

    // Icon Box
    echo "<div class='w-8 h-8 rounded-md flex items-center justify-center shrink-0 {$bgColor} {$iconColor} ring-1 ring-inset ring-black/5'>";
    echo "<i class='{$node['icon']}'></i>";
    echo '</div>';

    // Content
    echo '<div class="flex flex-col leading-tight">';
    echo "<span class='text-sm font-bold text-slate-700'>{$node['label']}</span>";
    if (!empty($node['detail'])) {
        echo "<span class='text-xs font-mono text-slate-500 mt-0.5 bg-slate-50 px-1.5 py-0.5 rounded w-fit'>{$node['detail']}</span>";
    }
    echo '</div>';

    if ($hasChildren) {
        echo '</summary>';
        echo '<div class="pl-3 border-l-2 border-slate-100 ml-4 mt-1 space-y-1">';
        foreach ($children as $child) {
            if (is_array($child)) {
                renderTree($child);
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
<html lang="en" class="h-full antialiased bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RegexParser // Studio</title>

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
                        brand: {500: '#6366f1', 600: '#4f46e5'}
                    }
                }
            }
        }
    </script>
    <style>
        summary::-webkit-details-marker {
            display: none;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="h-full flex flex-col text-slate-800">

<nav
    class="bg-white/80 backdrop-blur-md border-b border-slate-200 h-16 flex items-center px-6 justify-between sticky top-0 z-50">
    <div class="flex items-center gap-4">
        <div
            class="w-9 h-9 bg-gradient-to-br from-indigo-600 to-violet-600 rounded-lg flex items-center justify-center text-white shadow-lg shadow-indigo-200">
            <i class="fa-solid fa-microchip"></i>
        </div>
        <div class="leading-none">
            <h1 class="font-bold text-lg tracking-tight text-slate-900">Regex<span class="text-indigo-600">Parser</span>
            </h1>
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Analyzer Studio</span>
        </div>
    </div>
    <div class="flex gap-3">
        <a href="https://github.com/yoeunes/regex-parser" target="_blank"
           class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors border border-transparent hover:border-slate-200">
            <i class="fa-brands fa-github text-lg"></i> Star on GitHub
        </a>
    </div>
</nav>

<div class="flex flex-1 overflow-hidden">

    <aside
        class="w-[380px] bg-white border-r border-slate-200 flex flex-col z-20 shadow-[4px_0_24px_rgba(0,0,0,0.02)] overflow-y-auto">
        <form method="POST" id="regexForm" class="p-6 space-y-8">

            <div class="space-y-3">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Regular
                    Expression</label>
                <div class="relative group">
                    <input type="text" id="regex" name="regex"
                           class="block w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono text-slate-700 shadow-sm
                                      focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all"
                           placeholder="/pattern/flags"
                           value="<?php
                           echo htmlspecialchars($regex); ?>"
                           autocomplete="off" spellcheck="false">
                    <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                        <kbd
                            class="hidden group-focus-within:inline-flex h-5 items-center gap-1 rounded border border-slate-200 bg-white px-1.5 font-mono text-[10px] font-medium text-slate-500">
                            <span class="text-xs">↵</span> Enter
                        </kbd>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Analysis Tools</label>
                <div class="grid grid-cols-1 gap-2">
                    <?php
                    $tools = [
                        'parse'    => [
                            'icon'  => 'fa-sitemap',
                            'label' => 'AST Visualizer',
                            'desc'  => 'Explore structure',
                            'color' => 'indigo'
                        ],
                        'validate' => ['icon'  => 'fa-shield-virus',
                                       'label' => 'Validator',
                                       'desc'  => 'Check syntax & limits',
                                       'color' => 'emerald'
                        ],
                        'explain'  => ['icon'  => 'fa-align-left',
                                       'label' => 'Explainer',
                                       'desc'  => 'Human description',
                                       'color' => 'blue'
                        ],
                        'generate' => ['icon'  => 'fa-dice',
                                       'label' => 'Generator',
                                       'desc'  => 'Create sample matches',
                                       'color' => 'violet'
                        ],
                        'redos'    => ['icon'  => 'fa-biohazard',
                                       'label' => 'ReDoS Audit',
                                       'desc'  => 'Security vulnerability',
                                       'color' => 'rose'
                        ],
                        'literals' => ['icon'  => 'fa-magnet',
                                       'label' => 'Optimizer',
                                       'desc'  => 'Extract literals',
                                       'color' => 'amber'
                        ],
                    ];

                    foreach ($tools as $key => $t) {
                        $isActive = $action === $key;
                        $color = $t['color'];
                        $activeStyle = $isActive
                            ? "bg-{$color}-50 border-{$color}-200 ring-1 ring-{$color}-200 shadow-sm z-10"
                            : 'bg-white border-slate-100 hover:bg-slate-50 hover:border-slate-300 text-slate-500';

                        $iconStyle = $isActive ? "text-{$color}-600" : 'text-slate-400';

                        echo "
                            <button type='submit' name='action' value='$key' class='relative flex items-center gap-4 p-3 rounded-xl border text-left transition-all duration-200 $activeStyle'>
                                <div class='w-10 h-10 rounded-lg flex items-center justify-center shrink-0 ".($isActive
                                ? 'bg-white' : 'bg-slate-100')." $iconStyle'>
                                    <i class='fa-solid {$t['icon']} text-lg'></i>
                                </div>
                                <div>
                                    <div class='font-bold text-sm ".($isActive ? "text-{$color}-900" : 'text-slate-700')
                            ."'>{$t['label']}</div>
                                    <div class='text-xs ".($isActive ? "text-{$color}-700" : 'text-slate-400')."'>{$t['desc']}</div>
                                </div>
                                ".($isActive
                                ? "<div class='absolute right-4 text-{$color}-500'><i class='fa-solid fa-circle-check'></i></div>"
                                : '').'
                            </button>';
                    }
                    ?>
                </div>
            </div>

            <div class="pt-6 border-t border-slate-100">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Quick Load</label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="loadPreset('email')"
                            class="px-3 py-1.5 bg-slate-50 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 border border-slate-200 rounded-md text-xs font-medium text-slate-600 transition-colors">
                        <i class="fa-regular fa-envelope mr-1"></i> Email
                    </button>
                    <button type="button" onclick="loadPreset('date')"
                            class="px-3 py-1.5 bg-slate-50 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 border border-slate-200 rounded-md text-xs font-medium text-slate-600 transition-colors">
                        <i class="fa-regular fa-calendar mr-1"></i> Date
                    </button>
                    <button type="button" onclick="loadPreset('redos')"
                            class="px-3 py-1.5 bg-slate-50 hover:bg-red-50 hover:text-red-600 hover:border-red-200 border border-slate-200 rounded-md text-xs font-medium text-slate-600 transition-colors">
                        <i class="fa-solid fa-bomb mr-1"></i> Exploit
                    </button>
                </div>
            </div>
        </form>
    </aside>

    <main class="flex-1 bg-slate-50/50 overflow-y-auto relative">
        <div class="absolute inset-0 pointer-events-none"
             style="background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 20px 20px; opacity: 0.4;"></div>

        <div class="max-w-5xl mx-auto p-8 relative z-10">

            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">
                        <?php
                        echo $result ? ucfirst($result['type']).' Results' : 'Ready'; ?>
                    </h2>
                    <p class="text-slate-500 text-sm">Parser Engine v1.0</p>
                </div>
                <?php
                if ($result): ?>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex items-center gap-2 px-3 py-1.5 bg-white rounded-full border border-slate-200 shadow-sm text-xs font-mono text-slate-500">
                            <i class="fa-solid fa-stopwatch"></i> <?php
                            echo $duration; ?>ms
                        </div>
                    </div>
                <?php
                endif; ?>
            </div>

            <div class="glass-card rounded-2xl min-h-[600px] p-1 shadow-sm">

                <?php
                if ($error): ?>
                    <div class="h-full flex flex-col items-center justify-center p-12 text-center">
                        <div
                            class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mb-6 text-3xl">
                            <i class="fa-solid fa-bug"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Parsing Failed</h3>
                        <p class="text-slate-500 max-w-md font-mono text-sm bg-red-50 p-4 rounded-lg border border-red-100 text-red-800"><?php
                            echo htmlspecialchars($error); ?></p>
                    </div>

                <?php
                elseif (!$result): ?>
                    <div class="h-full flex flex-col items-center justify-center p-20 text-center opacity-60">
                        <div class="w-20 h-20 bg-slate-100 rounded-2xl flex items-center justify-center mb-6">
                            <i class="fa-solid fa-wand-magic-sparkles text-3xl text-slate-300"></i>
                        </div>
                        <p class="text-lg font-medium text-slate-600">Enter a regex pattern to begin</p>
                    </div>

                <?php
                else: ?>

                    <?php
                    if ($result['type'] === 'parse'): ?>
                        <div
                            class="grid grid-cols-1 xl:grid-cols-3 h-full divide-y xl:divide-y-0 xl:divide-x divide-slate-100">
                            <div class="xl:col-span-2 p-6 bg-slate-50/30">
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">Tree
                                        Structure</h3>
                                    <?php
                                    if ($result['flags']): ?>
                                        <span
                                            class="px-2 py-1 bg-indigo-100 text-indigo-700 text-xs font-bold rounded uppercase">Flags: <?php
                                            echo htmlspecialchars($result['flags']); ?></span>
                                    <?php
                                    endif; ?>
                                </div>
                                <div class="font-sans">
                                    <?php
                                    renderTree($result['tree']); ?>
                                </div>
                            </div>
                            <div
                                class="xl:col-span-1 p-0 bg-slate-900 text-slate-300 overflow-hidden rounded-r-xl flex flex-col">
                                <div
                                    class="p-3 bg-slate-800 border-b border-slate-700 text-xs font-mono font-bold text-slate-400 uppercase tracking-wider">
                                    Raw Dump
                                </div>
                                <div
                                    class="p-6 font-mono text-xs overflow-auto flex-1 custom-scrollbar leading-relaxed">
                                    <?php
                                    echo htmlspecialchars($result['raw']); ?>
                                </div>
                            </div>
                        </div>

                    <?php
                    elseif ($result['type'] === 'validate'): ?>
                        <div class="p-10 flex flex-col items-center text-center">
                            <div class="w-24 h-24 rounded-full flex items-center justify-center mb-6 <?php
                            echo $result['isValid'] ? 'bg-green-50 text-green-500 ring-8 ring-green-50/50'
                                : 'bg-red-50 text-red-500 ring-8 ring-red-50/50'; ?>">
                                <i class="fa-solid <?php
                                echo $result['isValid'] ? 'fa-shield-check' : 'fa-shield-xmark'; ?> text-5xl"></i>
                            </div>
                            <h2 class="text-3xl font-bold text-slate-900 mb-2">
                                <?php
                                echo $result['isValid'] ? 'Valid Pattern' : 'Invalid Pattern'; ?>
                            </h2>
                            <p class="text-slate-500 mb-8">
                                <?php
                                echo $result['isValid'] ? 'The syntax is correct and compatible with PCRE2.'
                                    : 'The parser detected syntax errors.'; ?>
                            </p>

                            <?php
                            if (!$result['isValid']): ?>
                                <div
                                    class="max-w-2xl w-full bg-red-50 text-red-800 p-4 rounded-xl border border-red-100 flex items-start gap-3 text-left">
                                    <i class="fa-solid fa-circle-xmark mt-1 shrink-0"></i>
                                    <div class="font-mono text-sm"><?php
                                        echo htmlspecialchars($result['error']); ?></div>
                                </div>
                            <?php
                            else: ?>
                                <div class="grid grid-cols-2 gap-6 max-w-lg w-full">
                                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                                        <div class="text-xs text-slate-400 uppercase font-bold mb-1">Complexity</div>
                                        <div class="text-2xl font-mono font-bold text-indigo-600"><?php
                                            echo $result['score']; ?></div>
                                    </div>
                                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                                        <div class="text-xs text-slate-400 uppercase font-bold mb-1">Status</div>
                                        <div class="text-2xl font-mono font-bold text-green-600">PASS</div>
                                    </div>
                                </div>
                            <?php
                            endif; ?>
                        </div>

                    <?php
                    elseif ($result['type'] === 'redos'): ?>
                        <div class="p-8">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                                <?php
                                $colors = match ($result['severity']) {
                                    'safe' => ['green', 'fa-shield-check'],
                                    'low' => ['blue', 'fa-circle-info'],
                                    'medium' => ['yellow', 'fa-triangle-exclamation'],
                                    'high', 'critical' => ['red', 'fa-radiation'],
                                };
                                $c = $colors[0];
                                ?>
                                <div class="p-6 rounded-2xl bg-<?php
                                echo $c; ?>-50 border border-<?php
                                echo $c; ?>-100 text-center">
                                    <i class="fa-solid <?php
                                    echo $colors[1]; ?> text-3xl text-<?php
                                    echo $c; ?>-500 mb-3"></i>
                                    <div class="text-sm font-bold text-<?php
                                    echo $c; ?>-800 uppercase opacity-80">Severity
                                    </div>
                                    <div class="text-3xl font-bold text-<?php
                                    echo $c; ?>-900"><?php
                                        echo strtoupper($result['severity']); ?></div>
                                </div>
                                <div class="p-6 rounded-2xl bg-white border border-slate-200 text-center shadow-sm">
                                    <div class="text-slate-400 mb-2 text-xs font-bold uppercase">Risk Score</div>
                                    <div class="text-4xl font-bold text-slate-800"><?php
                                        echo $result['score']; ?><span class="text-lg text-slate-400">/10</span></div>
                                </div>
                                <div class="p-6 rounded-2xl bg-white border border-slate-200 text-center shadow-sm">
                                    <div class="text-slate-400 mb-2 text-xs font-bold uppercase">Status</div>
                                    <div class="text-3xl font-bold <?php
                                    echo $result['isSafe'] ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php
                                        echo $result['isSafe'] ? 'SAFE' : 'VULNERABLE'; ?>
                                    </div>
                                </div>
                            </div>

                            <?php
                            if (!empty($result['recommendations'])): ?>
                                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                                    <div
                                        class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 font-bold text-slate-700 flex items-center gap-2">
                                        <i class="fa-solid fa-clipboard-list text-indigo-500"></i> Recommendations
                                    </div>
                                    <div class="p-6">
                                        <ul class="space-y-3">
                                            <?php
                                            foreach ($result['recommendations'] as $rec): ?>
                                                <li class="flex gap-3 items-start text-slate-600 text-sm">
                                                    <i class="fa-solid fa-check text-green-500 mt-1"></i>
                                                    <span><?php
                                                        echo htmlspecialchars($rec); ?></span>
                                                </li>
                                            <?php
                                            endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php
                            endif; ?>
                        </div>

                    <?php
                    elseif ($result['type'] === 'generate'): ?>
                        <div class="h-full flex flex-col items-center justify-center p-10">
                            <div
                                class="w-full max-w-2xl bg-slate-900 rounded-2xl p-8 text-center shadow-2xl shadow-indigo-500/10 relative group cursor-pointer overflow-hidden"
                                onclick="navigator.clipboard.writeText(this.innerText)">
                                <div
                                    class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
                                <div class="text-xs font-bold text-slate-500 uppercase mb-4 tracking-widest">Generated
                                    Match
                                </div>
                                <div class="font-mono text-3xl text-white break-all">
                                    <?php
                                    echo htmlspecialchars($result['sample']); ?>
                                </div>
                                <div
                                    class="absolute bottom-3 right-4 text-xs text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <i class="fa-regular fa-copy mr-1"></i> Click to Copy
                                </div>
                            </div>
                        </div>

                    <?php
                    elseif ($result['type'] === 'literals'): ?>
                        <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="p-6 bg-slate-50 rounded-xl border border-slate-200">
                                <div class="flex items-center gap-2 mb-3 text-slate-500 font-bold text-xs uppercase">
                                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Longest Prefix
                                </div>
                                <code
                                    class="block p-3 bg-white rounded-lg border border-slate-200 text-indigo-600 font-mono break-all shadow-sm">
                                    "<?php
                                    echo htmlspecialchars($result['longestPrefix'] ?? ''); ?>"
                                </code>
                            </div>
                            <div class="p-6 bg-slate-50 rounded-xl border border-slate-200">
                                <div class="flex items-center gap-2 mb-3 text-slate-500 font-bold text-xs uppercase">
                                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Longest Suffix
                                </div>
                                <code
                                    class="block p-3 bg-white rounded-lg border border-slate-200 text-indigo-600 font-mono break-all shadow-sm">
                                    "<?php
                                    echo htmlspecialchars($result['longestSuffix'] ?? ''); ?>"
                                </code>
                            </div>
                            <?php
                            if (!empty($result['prefixes'])): ?>
                                <div class="md:col-span-2 p-6 bg-white rounded-xl border border-slate-200">
                                    <div class="text-slate-500 font-bold text-xs uppercase mb-3">Optimization
                                        Candidates
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <?php
                                        foreach ($result['prefixes'] as $p): ?>
                                            <span
                                                class="px-2 py-1 bg-slate-100 border border-slate-200 rounded font-mono text-xs text-slate-600">"<?php
                                                echo htmlspecialchars($p); ?>"</span>
                                        <?php
                                        endforeach; ?>
                                    </div>
                                </div>
                            <?php
                            endif; ?>
                        </div>

                    <?php
                    elseif ($result['type'] === 'explain'): ?>
                        <div class="p-0 h-full flex flex-col">
                            <div class="bg-slate-50 p-4 border-b border-slate-100 flex justify-between items-center">
                                <span class="text-xs font-bold text-slate-500 uppercase">Human Readable</span>
                            </div>
                            <div class="flex-1 p-6 overflow-auto font-mono text-sm leading-relaxed text-slate-700">
                                <?php
                                echo nl2br(htmlspecialchars($result['explanation'])); ?>
                            </div>
                        </div>

                    <?php
                    endif; ?>
                <?php
                endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
    function loadPreset(type) {
        const input = document.getElementById('regex');
        const form = document.getElementById('regexForm');

        if (type === 'email') input.value = '/^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,}$/i';
        if (type === 'date') input.value = '/^(?<year>\\d{4})-(?<month>\\d{2})-(?<day>\\d{2})$/';
        if (type === 'redos') input.value = '/(a+)+b/';

        // Submit parsing automatically
        // Create hidden input to simulate button click
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'action';
        hidden.value = (type === 'redos') ? 'redos' : 'parse';
        form.appendChild(hidden);
        form.submit();
    }
</script>
</body>
</html>
