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
$duration = 0;

if ($regex) {
    try {
        $regexParser = Regex::create();
        $start = microtime(true);

        switch ($action) {
            case 'parse':
                $ast = $regexParser->parse($regex);
                $result = [
                    'type' => 'parse',
                    'ast' => $regexParser->dump($regex),
                    'flags' => $ast->flags,
                ];
                break;

            case 'validate':
                $validation = $regexParser->validate($regex);
                $result = [
                    'type' => 'validate',
                    'isValid' => $validation->isValid,
                    'error' => $validation->error,
                ];
                break;

            case 'explain':
                $explanation = $regexParser->explain($regex);
                $result = [
                    'type' => 'explain',
                    'explanation' => $explanation,
                ];
                break;

            case 'generate':
                $sample = $regexParser->generate($regex);
                $result = [
                    'type' => 'generate',
                    'sample' => $sample,
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
                // FIX: Conversion explicite de l'objet en tableau pour la vue
                $result = [
                    'type' => 'literals',
                    'prefixes' => $literals->prefixes,
                    'suffixes' => $literals->suffixes,
                    'complete' => $literals->complete,
                    'longestPrefix' => $literals->getLongestPrefix(),
                    'longestSuffix' => $literals->getLongestSuffix(),
                ];
                break;
        }
        $duration = round((microtime(true) - $start) * 1000, 2);
    } catch (ParserException|LexerException|Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RegexParser // Laboratory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Palette Clean / Scientific */
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --bg-input: #ffffff;
            --bg-panel: #f1f5f9;
            
            --border-color: #e2e8f0;
            --border-focus: #3b82f6;
            
            --primary: #0f172a; /* Slate 900 */
            --primary-hover: #1e293b;
            --accent: #2563eb; /* Blue 600 */
            
            --success-bg: #dcfce7;
            --success-text: #15803d;
            --danger-bg: #fee2e2;
            --danger-text: #b91c1c;
            --warning-bg: #fef3c7;
            --warning-text: #b45309;
            
            --text-main: #334155;
            --text-title: #0f172a;
            --text-muted: #64748b;
            
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            
            --font-mono: 'JetBrains Mono', monospace;
            --font-sans: 'Plus Jakarta Sans', sans-serif;
            
            --radius: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; outline: none; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-sans);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Layout */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 20px;
            width: 100%;
        }

        /* Header */
        .header {
            margin-bottom: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-title);
        }

        .logo-box {
            width: 40px;
            height: 40px;
            background: var(--text-title);
            border-radius: 8px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-mono);
            font-weight: 700;
            font-size: 1.2rem;
        }

        .brand-text h1 {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .brand-text p {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Main Card */
        .lab-interface {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        /* Controls Section */
        .controls {
            padding: 30px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(to bottom, #fff, #f8fafc);
        }

        .input-group {
            position: relative;
            margin-bottom: 25px;
        }

        .input-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 8px;
            display: block;
        }

        input[type="text"] {
            width: 100%;
            padding: 16px 20px;
            font-family: var(--font-mono);
            font-size: 1.1rem;
            color: var(--text-title);
            background: var(--bg-input);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        input[type="text"]:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        /* Tabs/Actions */
        .tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 10px 16px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--text-main);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            border-color: var(--text-muted);
            background: var(--bg-body);
        }

        .tab-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        /* Output Section */
        .output-stage {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 500px;
        }

        /* Sidebar (Examples) */
        .sidebar {
            background: var(--bg-body);
            border-right: 1px solid var(--border-color);
            padding: 20px;
        }

        .sidebar-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 15px;
            letter-spacing: 0.05em;
        }

        .example-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .example-chip {
            padding: 10px 12px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .example-chip:hover {
            border-color: var(--accent);
            transform: translateX(2px);
        }

        .example-chip h4 {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-title);
            margin-bottom: 2px;
        }

        .example-chip code {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-family: var(--font-mono);
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Result Area */
        .result-viewer {
            padding: 30px;
            background: white;
            overflow-x: auto;
        }

        /* States */
        .empty-state {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            opacity: 0.6;
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--border-color);
        }

        /* Alert Boxes */
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .alert-error { background: var(--danger-bg); color: var(--danger-text); }
        .alert-success { background: var(--success-bg); color: var(--success-text); }
        
        .alert-icon { font-size: 1.2rem; margin-top: -2px; }
        .alert-content h3 { font-size: 0.95rem; font-weight: 700; margin-bottom: 4px; }
        .alert-content p { font-size: 0.9rem; opacity: 0.9; }

        /* Syntax / Data Display */
        .data-block {
            font-family: var(--font-mono);
            font-size: 0.9rem;
            background: #1e293b; /* Dark background for code contrast */
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .meta-info {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
        }

        .meta-item strong { color: var(--text-title); }

        /* ReDoS Grid */
        .redos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .metric-card {
            background: var(--bg-body);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .metric-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; }
        .metric-value { font-size: 1.5rem; font-weight: 700; color: var(--text-title); margin-top: 5px; }
        .metric-value.safe { color: var(--success-text); }
        .metric-value.danger { color: var(--danger-text); }

        /* Responsive */
        @media (max-width: 768px) {
            .output-stage { grid-template-columns: 1fr; }
            .sidebar { border-right: none; border-bottom: 1px solid var(--border-color); }
            .example-list { flex-direction: row; overflow-x: auto; padding-bottom: 10px; }
            .example-chip { min-width: 200px; }
        }
    </style>
</head>
<body>

<div class="container">
    <header class="header">
        <a href="#" class="brand">
            <div class="logo-box">RP</div>
            <div class="brand-text">
                <h1>RegexParser</h1>
                <p>Static Analysis Laboratory</p>
            </div>
        </a>
        <div style="font-size: 0.85rem; color: var(--text-muted);">
            PHP 8.4+ | PCRE2
        </div>
    </header>

    <div class="lab-interface">
        <form method="POST" id="regexForm">
            <div class="controls">
                <div class="input-group">
                    <label class="input-label" for="regex">Regular Expression</label>
                    <input type="text" id="regex" name="regex" 
                           placeholder="/pattern/flags" 
                           value="<?php echo htmlspecialchars($regex); ?>" 
                           spellcheck="false" autocomplete="off">
                </div>

                <div class="tabs">
                    <?php
                    $tabs = [
                        'parse' => '⚡ Parse AST',
                        'validate' => '🛡️ Validate',
                        'explain' => '📖 Explain',
                        'generate' => '🎲 Generate',
                        'redos' => '🔥 ReDoS Analysis',
                        'literals' => '🔍 Extract Literals'
                    ];
                    
                    foreach ($tabs as $value => $label) {
                        $activeClass = ($action === $value) ? 'active' : '';
                        echo "<button type='submit' name='action' value='$value' class='tab-btn $activeClass'>$label</button>";
                    }
                    ?>
                </div>
            </div>
        </form>

        <div class="output-stage">
            <aside class="sidebar">
                <div class="sidebar-title">Load Preset</div>
                <div class="example-list">
                    <div class="example-chip" onclick="setPattern('/^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,}$/i')">
                        <h4>Email Address</h4>
                        <code>/^[a-z0-9...]/i</code>
                    </div>
                    <div class="example-chip" onclick="setPattern('/^(?<year>\\d{4})-(?<month>\\d{2})-(?<day>\\d{2})$/')">
                        <h4>ISO Date</h4>
                        <code>/^(?&lt;year&gt;\d{4})...</code>
                    </div>
                    <div class="example-chip" onclick="setPattern('/(a+)+b/')">
                        <h4>ReDoS Vulnerability</h4>
                        <code>/(a+)+b/</code>
                    </div>
                    <div class="example-chip" onclick="setPattern('/^https?:\\/\\/[\\w.-]+/i')">
                        <h4>URL Match</h4>
                        <code>/^https?:\/\/...</code>
                    </div>
                    <div class="example-chip" onclick="setPattern('/(?<=price: )\\$\\d+\\.\\d{2}/')">
                        <h4>Lookbehind</h4>
                        <code>/(?&lt;=price: )...</code>
                    </div>
                </div>
            </aside>

            <main class="result-viewer">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <div class="alert-icon">✕</div>
                        <div class="alert-content">
                            <h3>Parsing Error</h3>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                
                <?php elseif ($result): ?>
                    <div class="meta-info">
                        <div class="meta-item">Process Time: <strong><?php echo $duration; ?>ms</strong></div>
                        <?php if (isset($result['flags']) && $result['flags']): ?>
                            <div class="meta-item">Flags: <strong><?php echo htmlspecialchars($result['flags']); ?></strong></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($result['type'] === 'parse'): ?>
                        <div class="data-block"><?php echo htmlspecialchars($result['ast']); ?></div>

                    <?php elseif ($result['type'] === 'validate'): ?>
                        <?php if ($result['isValid']): ?>
                            <div class="alert alert-success">
                                <div class="alert-icon">✓</div>
                                <div class="alert-content">
                                    <h3>Valid Regex</h3>
                                    <p>This pattern is syntactically correct and safe.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-error">
                                <div class="alert-icon">✕</div>
                                <div class="alert-content">
                                    <h3>Invalid Regex</h3>
                                    <p><?php echo htmlspecialchars($result['error']); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($result['type'] === 'explain'): ?>
                        <div class="data-block" style="background: #f8fafc; color: #334155; border: 1px solid #e2e8f0;"><?php echo htmlspecialchars($result['explanation']); ?></div>

                    <?php elseif ($result['type'] === 'generate'): ?>
                        <div style="padding: 40px; text-align: center; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px;">Generated Sample</div>
                            <div style="font-family: var(--font-mono); font-size: 1.5rem; color: var(--accent); word-break: break-all;">
                                "<?php echo htmlspecialchars($result['sample']); ?>"
                            </div>
                        </div>

                    <?php elseif ($result['type'] === 'redos'): ?>
                        <div class="redos-grid">
                            <div class="metric-card">
                                <div class="metric-label">Severity</div>
                                <div class="metric-value <?php echo $result['isSafe'] ? 'safe' : 'danger'; ?>">
                                    <?php echo strtoupper($result['severity']); ?>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-label">Score</div>
                                <div class="metric-value"><?php echo $result['score']; ?>/10</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-label">Status</div>
                                <div class="metric-value"><?php echo $result['isSafe'] ? 'SAFE' : 'VULNERABLE'; ?></div>
                            </div>
                        </div>
                        <?php if (!empty($result['recommendations'])): ?>
                            <div class="alert alert-error">
                                <div class="alert-content">
                                    <h3>Recommendations</h3>
                                    <ul style="padding-left: 20px; margin-top: 10px;">
                                        <?php foreach ($result['recommendations'] as $rec): ?>
                                            <li><?php echo htmlspecialchars($rec); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($result['type'] === 'literals'): ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="metric-card" style="text-align: left;">
                                <div class="metric-label">Longest Prefix</div>
                                <div class="metric-value" style="font-size: 1rem; color: var(--accent);">
                                    "<?php echo htmlspecialchars($result['longestPrefix'] ?? ''); ?>"
                                </div>
                            </div>
                            <div class="metric-card" style="text-align: left;">
                                <div class="metric-label">Longest Suffix</div>
                                <div class="metric-value" style="font-size: 1rem; color: var(--accent);">
                                    "<?php echo htmlspecialchars($result['longestSuffix'] ?? ''); ?>"
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($result['prefixes'])): ?>
                            <div style="margin-bottom: 15px;">
                                <div class="input-label">All Possible Prefixes</div>
                                <div class="data-block" style="padding: 10px;"><?php echo htmlspecialchars(implode(', ', $result['prefixes'])); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($result['suffixes'])): ?>
                            <div>
                                <div class="input-label">All Possible Suffixes</div>
                                <div class="data-block" style="padding: 10px;"><?php echo htmlspecialchars(implode(', ', $result['suffixes'])); ?></div>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">⚡</div>
                        <p>Ready to analyze. Select an example or type a pattern.</p>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<script>
    function setPattern(pattern) {
        const input = document.getElementById('regex');
        input.value = pattern;
        input.focus();
        // Petit effet visuel pour confirmer l'action
        input.style.borderColor = 'var(--accent)';
        setTimeout(() => input.style.borderColor = '', 300);
    }
</script>

</body>
</html>
