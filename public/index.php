<?php

require __DIR__.'/../vendor/autoload.php';

use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Regex;

// Configuration
header('Content-Type: text/html; charset=utf-8');
$regex = $_POST['regex'] ?? '';
$action = $_POST['action'] ?? 'parse';
$result = null;
$error = null;
$activeTab = $action;

if ($regex) {
    try {
        $regexParser = Regex::create();
        $start = microtime(true);

        switch ($action) {
            case 'parse':
                $ast = $regexParser->parse($regex);
                $result = [
                    'type'  => 'parse',
                    'ast'   => $regexParser->dump($regex),
                    'flags' => $ast->flags,
                ];
                break;

            case 'validate':
                $validation = $regexParser->validate($regex);
                $result = [
                    'type'    => 'validate',
                    'isValid' => $validation->isValid,
                    'error'   => $validation->error,
                ];
                break;

            case 'explain':
                $explanation = $regexParser->explain($regex);
                $result = [
                    'type'        => 'explain',
                    'explanation' => $explanation,
                ];
                break;

            case 'generate':
                $sample = $regexParser->generate($regex);
                $result = [
                    'type'   => 'generate',
                    'sample' => $sample,
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
                    'type' => 'literals',
                    'data' => $literals, // Pass object directly for flexibility
                ];
                break;
        }
        $duration = round((microtime(true) - $start) * 1000, 2);
    } catch (ParserException|LexerException $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RegexParser // Studio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@300;400;600&family=Inter:wght@400;600;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg-app: #09090b;
            --bg-panel: #18181b;
            --bg-input: #27272a;
            --border: #3f3f46;

            --primary: #8b5cf6;
            --primary-glow: rgba(139, 92, 246, 0.5);
            --accent: #06b6d4;

            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.2);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.2);

            --text-main: #f4f4f5;
            --text-muted: #a1a1aa;

            --font-mono: 'Fira Code', monospace;
            --font-ui: 'Inter', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            outline: none;
        }

        body {
            background-color: var(--bg-app);
            color: var(--text-main);
            font-family: var(--font-ui);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            background-image: radial-gradient(circle at 15% 50%, rgba(139, 92, 246, 0.08), transparent 25%),
            radial-gradient(circle at 85% 30%, rgba(6, 182, 212, 0.08), transparent 25%);
        }

        /* Layout */
        .app-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
            width: 100%;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        /* Header */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .brand {
            font-family: var(--font-mono);
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand span {
            color: var(--primary);
        }

        .brand .badge {
            font-size: 0.7rem;
            background: var(--bg-input);
            padding: 2px 8px;
            border-radius: 4px;
            color: var(--text-muted);
            border: 1px solid var(--border);
        }

        /* Main Grid */
        .studio-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        @media (min-width: 1024px) {
            .studio-grid {
                grid-template-columns: 350px 1fr;
                align-items: start;
            }
        }

        /* Input Section */
        .panel {
            background: var(--bg-panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .panel-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            background: rgba(0, 0, 0, 0.2);
        }

        .panel-body {
            padding: 20px;
        }

        /* Regex Input */
        .regex-input-wrapper {
            position: relative;
            margin-bottom: 20px;
        }

        .regex-label {
            position: absolute;
            top: -10px;
            left: 15px;
            background: var(--bg-panel);
            padding: 0 5px;
            font-size: 0.75rem;
            color: var(--primary);
            font-weight: 600;
        }

        input[type="text"] {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 18px 15px;
            border-radius: 8px;
            font-family: var(--font-mono);
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        input[type="text"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15), inset 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        /* Action Grid */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .action-btn {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-btn:hover {
            background: var(--bg-input);
            color: var(--text-main);
            border-color: var(--text-muted);
        }

        .action-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            box-shadow: 0 4px 12px var(--primary-glow);
        }

        /* Examples */
        .examples-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .example-item {
            padding: 10px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.03);
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s;
        }

        .example-item:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--border);
        }

        .example-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .example-code {
            font-family: var(--font-mono);
            font-size: 0.75rem;
            color: var(--accent);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Output Area */
        .output-area {
            min-height: 500px;
            display: flex;
            flex-direction: column;
        }

        .terminal-window {
            background: #0f0f11;
            border-radius: 8px;
            font-family: var(--font-mono);
            font-size: 0.9rem;
            line-height: 1.6;
            overflow: hidden;
            border: 1px solid var(--border);
            height: 100%;
        }

        .terminal-header {
            background: #1f1f22;
            padding: 8px 15px;
            display: flex;
            gap: 6px;
            border-bottom: 1px solid #2a2a2d;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .dot.red {
            background: #ff5f56;
        }

        .dot.yellow {
            background: #ffbd2e;
        }

        .dot.green {
            background: #27c93f;
        }

        .terminal-content {
            padding: 20px;
            color: #e4e4e7;
            overflow-x: auto;
        }

        /* AST & Code Styling */
        .tree-view {
            list-style: none;
            padding-left: 20px;
            border-left: 1px solid var(--border);
        }

        .tree-item {
            margin: 5px 0;
            position: relative;
        }

        .tree-item::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 10px;
            width: 15px;
            height: 1px;
            background: var(--border);
        }

        .keyword {
            color: var(--primary);
        }

        .string {
            color: #a5f3fc;
        }

        .number {
            color: #fca5a5;
        }

        .type {
            color: var(--accent);
            font-weight: bold;
        }

        /* Status States */
        .status-box {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .status-valid {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success);
        }

        .status-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--danger);
        }

        .stat-pill {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 100px;
            background: rgba(255, 255, 255, 0.1);
            margin-left: auto;
        }

    </style>
</head>
<body>

<div class="app-container">
    <nav class="navbar">
        <div class="brand">
            <span>Regex</span>Parser <span class="badge">v1.0-dev</span>
        </div>
        <div style="display: flex; gap: 15px;">
            <a href="https://github.com/yoeunes/regex-parser" target="_blank"
               style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">GitHub</a>
            <a href="#" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">Docs</a>
        </div>
    </nav>

    <div class="studio-grid">

        <aside>
            <form method="POST" id="regexForm">
                <div class="panel" style="margin-bottom: 30px; box-shadow: 0 0 40px rgba(139, 92, 246, 0.05);">
                    <div class="panel-header">Input Pattern</div>
                    <div class="panel-body">
                        <div class="regex-input-wrapper">
                            <label class="regex-label" for="regex">PATTERN</label>
                            <input type="text" id="regex" name="regex"
                                   placeholder="/expression/flags"
                                   value="<?php
                                   echo htmlspecialchars($regex); ?>"
                                   spellcheck="false"
                                   autocomplete="off"
                                   autofocus>
                        </div>
                        <div
                            style="font-size: 0.75rem; color: var(--text-muted); margin-top: -10px; text-align: right;">
                            Supported: PCRE2, Unicode
                        </div>
                    </div>
                </div>

                <div class="panel" style="margin-bottom: 30px;">
                    <div class="panel-header">Operation Mode</div>
                    <div class="panel-body">
                        <div class="actions-grid">
                            <?php
                            $buttons = [
                                'parse'    => '⚡ Parse AST',
                                'validate' => '🛡️ Validate',
                                'explain'  => '📖 Explain',
                                'generate' => '🎲 Generate',
                                'redos'    => '🔥 ReDoS Check',
                                'literals' => '🔍 Extract',
                            ];
                            foreach ($buttons as $val => $label) {
                                $isActive = $activeTab === $val ? 'active' : '';
                                echo "<button type='submit' name='action' value='$val' class='action-btn $isActive'>$label</button>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">Quick Load</div>
                    <div class="panel-body examples-list">
                        <div class="example-item" onclick="setPattern('/^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,}$/i')">
                            <div class="example-name">Email Validation</div>
                            <div class="example-code">/^[a-z0-9._%+-]+@...</div>
                        </div>
                        <div class="example-item"
                             onclick="setPattern('/^(?&lt;year&gt;\\d{4})-(?&lt;month&gt;\\d{2})-(?&lt;day&gt;\\d{2})$/')">
                            <div class="example-name">Date (Named Groups)</div>
                            <div class="example-code">/^(?&lt;year&gt;\d{4})...</div>
                        </div>
                        <div class="example-item" onclick="setPattern('/(a+)+b/')">
                            <div class="example-name">ReDoS Exploit</div>
                            <div class="example-code">/(a+)+b/</div>
                        </div>
                    </div>
                </div>
            </form>
        </aside>

        <main class="output-area">
            <div class="terminal-window">
                <div class="terminal-header">
                    <div class="dot red"></div>
                    <div class="dot yellow"></div>
                    <div class="dot green"></div>
                    <div style="margin-left: auto; color: #666; font-size: 0.75rem;">
                        <?php
                        echo isset($duration) ? "Completed in {$duration}ms" : 'Ready'; ?>
                    </div>
                </div>
                <div class="terminal-content">
                    <?php
                    if (!$regex): ?>
                        <div
                            style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 400px; color: var(--text-muted);">
                            <div style="font-size: 4rem; opacity: 0.2; margin-bottom: 20px;">⌘</div>
                            <p>Enter a regex pattern to begin analysis.</p>
                        </div>
                    <?php
                    elseif ($error): ?>
                        <div class="status-box status-error">
                            <div style="font-size: 1.5rem;">✕</div>
                            <div>
                                <h3 style="margin-bottom: 5px;">Parsing Failed</h3>
                                <div style="font-family: var(--font-mono); opacity: 0.9;"><?php
                                    echo htmlspecialchars($error); ?></div>
                            </div>
                        </div>
                    <?php
                    elseif ($result): ?>

                        <?php
                        if ($result['type'] === 'parse'): ?>
                            <div style="margin-bottom: 20px; color: var(--text-muted);">
                                Flags detected: <span class="type"><?php
                                    echo htmlspecialchars($result['flags'] ?: 'None'); ?></span>
                            </div>
                            <pre style="color: #d4d4d8;"><?php
                                echo htmlspecialchars($result['ast']); ?></pre>

                        <?php
                        elseif ($result['type'] === 'validate'): ?>
                            <div class="status-box <?php
                            echo $result['isValid'] ? 'status-valid' : 'status-error'; ?>">
                                <div style="font-size: 1.5rem;"><?php
                                    echo $result['isValid'] ? '✓' : '✕'; ?></div>
                                <div>
                                    <h3 style="margin-bottom: 5px;"><?php
                                        echo $result['isValid'] ? 'Pattern is Valid' : 'Pattern is Invalid'; ?></h3>
                                    <?php
                                    if (!$result['isValid']): ?>
                                        <div style="font-family: var(--font-mono);"><?php
                                            echo htmlspecialchars($result['error']); ?></div>
                                    <?php
                                    else: ?>
                                        <div style="opacity: 0.8;">AST constructed successfully. Semantics checked.
                                        </div>
                                    <?php
                                    endif; ?>
                                </div>
                            </div>

                        <?php
                        elseif ($result['type'] === 'explain'): ?>
                            <pre style="white-space: pre-wrap; font-family: var(--font-mono); line-height: 1.8;"><?php
                                echo htmlspecialchars($result['explanation']); ?></pre>

                        <?php
                        elseif ($result['type'] === 'generate'): ?>
                            <div style="text-align: center; padding: 40px;">
                                <div style="color: var(--text-muted); margin-bottom: 15px;">Generated Sample</div>
                                <div
                                    style="font-size: 2rem; font-family: var(--font-mono); color: var(--accent); word-break: break-all;">
                                    <?php
                                    echo htmlspecialchars($result['sample']); ?>
                                </div>
                            </div>

                        <?php
                        elseif ($result['type'] === 'redos'): ?>
                            <div
                                style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">
                                <div class="panel"
                                     style="text-align: center; padding: 15px; background: rgba(255,255,255,0.05);">
                                    <div style="color: var(--text-muted); font-size: 0.8rem;">Severity</div>
                                    <div style="font-size: 1.2rem; font-weight: bold; color: <?php
                                    echo $result['isSafe'] ? 'var(--success)' : 'var(--danger)'; ?>">
                                        <?php
                                        echo strtoupper($result['severity']); ?>
                                    </div>
                                </div>
                                <div class="panel"
                                     style="text-align: center; padding: 15px; background: rgba(255,255,255,0.05);">
                                    <div style="color: var(--text-muted); font-size: 0.8rem;">Safety Score</div>
                                    <div style="font-size: 1.2rem; font-weight: bold;"><?php
                                        echo $result['score']; ?>/10
                                    </div>
                                </div>
                                <div class="panel"
                                     style="text-align: center; padding: 15px; background: rgba(255,255,255,0.05);">
                                    <div style="color: var(--text-muted); font-size: 0.8rem;">Status</div>
                                    <div style="font-size: 1.2rem; font-weight: bold;"><?php
                                        echo $result['isSafe'] ? 'SAFE' : 'VULNERABLE'; ?></div>
                                </div>
                            </div>

                            <?php
                            if (!empty($result['recommendations'])): ?>
                                <h4 style="color: var(--danger); margin-bottom: 10px;">Recommendations:</h4>
                                <ul style="padding-left: 20px; color: #fda4af;">
                                    <?php
                                    foreach ($result['recommendations'] as $rec): ?>
                                        <li style="margin-bottom: 5px;"><?php
                                            echo htmlspecialchars($rec); ?></li>
                                    <?php
                                    endforeach; ?>
                                </ul>
                            <?php
                            endif; ?>

                        <?php
                        elseif ($result['type'] === 'literals'): ?>
                            <?php
                            $d = $result;
                            ?>
                            <div style="display: grid; gap: 20px;">
                                <div>
                                    <span class="keyword">Longest Prefix:</span>
                                    <span class="string">"<?php
                                        echo htmlspecialchars($d['longestPrefix'] ?? '<none>'); ?>"</span>
                                </div>
                                <div>
                                    <span class="keyword">Longest Suffix:</span>
                                    <span class="string">"<?php
                                        echo htmlspecialchars($d['longestSuffix'] ?? '<none>'); ?>"</span>
                                </div>
                                <div style="border-top: 1px solid #333; padding-top: 10px; margin-top: 10px;">
                                    <div style="color: var(--text-muted); margin-bottom: 5px;">All Prefixes:</div>
                                    <div class="type">[<?php
                                        echo htmlspecialchars(implode(', ', $d['prefixes'])); ?>]
                                    </div>
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
</div>

<script>
    function setPattern(pattern) {
        const input = document.getElementById('regex');
        input.value = pattern;
        input.focus();
        // Optional: Auto submit or just highlight
        input.style.borderColor = 'var(--accent)';
        setTimeout(() => input.style.borderColor = '', 300);
    }
</script>
</body>
</html>
