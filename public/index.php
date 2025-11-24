<?php

require __DIR__.'/../vendor/autoload.php';

use RegexParser\Exception\LexerException;
use RegexParser\Exception\ParserException;
use RegexParser\Regex;

header('Content-Type: text/html; charset=utf-8');

$regex = $_POST['regex'] ?? '';
$action = $_POST['action'] ?? 'parse';
$result = null;
$error = null;

if ($regex) {
    try {
        $regexParser = Regex::create();

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
    <title>RegexParser - Interactive Demo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 3em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            font-family: 'Courier New', monospace;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .button-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        
        button {
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        button.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        button.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        button.secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        button.secondary:hover {
            background: #e0e0e0;
        }
        
        .result {
            margin-top: 20px;
            padding: 20px;
            border-radius: 8px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
        }
        
        .error {
            background: #fee;
            border-left-color: #e74c3c;
            color: #c0392b;
        }
        
        .success {
            background: #efe;
            border-left-color: #27ae60;
            color: #1e8449;
        }
        
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-right: 8px;
        }
        
        .badge.safe { background: #d4edda; color: #155724; }
        .badge.low { background: #d1ecf1; color: #0c5460; }
        .badge.medium { background: #fff3cd; color: #856404; }
        .badge.high { background: #f8d7da; color: #721c24; }
        .badge.critical { background: #f5c6cb; color: #491217; }
        
        .examples {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .example {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .example:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .example-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .example-pattern {
            font-family: 'Courier New', monospace;
            color: #667eea;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 RegexParser</h1>
            <p>A powerful PCRE regex parser with lexer, AST, and validation</p>
        </div>
        
        <div class="card" style="background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 20px;">
            <h3 style="color: #856404; margin-bottom: 10px;">⚠️ Experimental Library Notice</h3>
            <p style="color: #856404; margin: 0;">
                This library is in <strong>experimental/alpha status</strong>. While it demonstrates functional parsing, AST generation, and analysis capabilities, 
                <strong>it has not been systematically validated against the official PCRE specification</strong>. 
                See <code>VALIDATION_REPORT.md</code> for detailed findings. Use for learning and experimentation, not production systems.
            </p>
        </div>
        
        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label for="regex">Enter your regex pattern:</label>
                    <input type="text" id="regex" name="regex" placeholder="/^[a-z0-9]+@[a-z]+\.[a-z]{2,}$/i" value="<?php echo htmlspecialchars($regex); ?>" autofocus>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="action" value="parse" class="primary">Parse AST</button>
                    <button type="submit" name="action" value="validate" class="primary">Validate</button>
                    <button type="submit" name="action" value="explain" class="primary">Explain</button>
                    <button type="submit" name="action" value="generate" class="primary">Generate Sample</button>
                    <button type="submit" name="action" value="redos" class="primary">ReDoS Analysis</button>
                    <button type="submit" name="action" value="literals" class="primary">Extract Literals</button>
                </div>
            </form>
            
            <?php if ($error) { ?>
                <div class="result error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php } ?>
            
            <?php if ($result) { ?>
                <div class="result">
                    <?php if ('parse' === $result['type']) { ?>
                        <h3>Abstract Syntax Tree (AST)</h3>
                        <p><strong>Flags:</strong> <?php echo htmlspecialchars($result['flags'] ?: 'none'); ?></p>
                        <pre><?php echo htmlspecialchars($result['ast']); ?></pre>
                        
                    <?php } elseif ('validate' === $result['type']) { ?>
                        <?php if ($result['isValid']) { ?>
                            <div class="success">
                                <strong>✓ Valid Regex</strong>
                                <p>This regex pattern is syntactically and semantically correct.</p>
                            </div>
                        <?php } else { ?>
                            <div class="error">
                                <strong>✗ Invalid Regex</strong>
                                <p><?php echo htmlspecialchars($result['error']); ?></p>
                            </div>
                        <?php } ?>
                        
                    <?php } elseif ('explain' === $result['type']) { ?>
                        <h3>Pattern Explanation</h3>
                        <pre><?php echo htmlspecialchars($result['explanation']); ?></pre>
                        
                    <?php } elseif ('generate' === $result['type']) { ?>
                        <h3>Generated Sample String</h3>
                        <p>A string that matches your pattern:</p>
                        <pre><?php echo htmlspecialchars($result['sample']); ?></pre>
                        
                    <?php } elseif ('redos' === $result['type']) { ?>
                        <h3>ReDoS Vulnerability Analysis</h3>
                        <p>
                            <span class="badge <?php echo $result['severity']; ?>"><?php echo strtoupper($result['severity']); ?></span>
                            <strong>Score:</strong> <?php echo $result['score']; ?>/10
                            <?php if ($result['isSafe']) { ?>
                                <span class="badge safe">SAFE</span>
                            <?php } else { ?>
                                <span class="badge high">VULNERABLE</span>
                            <?php } ?>
                        </p>
                        <?php if (!empty($result['recommendations'])) { ?>
                            <h4>Recommendations:</h4>
                            <ul>
                                <?php foreach ($result['recommendations'] as $rec) { ?>
                                    <li><?php echo htmlspecialchars($rec); ?></li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                        
                    <?php } elseif ('literals' === $result['type']) { ?>
                        <h3>Extracted Literals</h3>
                        <p><strong>Complete:</strong> <?php echo $result['complete'] ? 'Yes' : 'No'; ?></p>
                        <?php if ($result['longestPrefix']) { ?>
                            <p><strong>Longest Prefix:</strong> <code><?php echo htmlspecialchars($result['longestPrefix']); ?></code></p>
                        <?php } ?>
                        <?php if ($result['longestSuffix']) { ?>
                            <p><strong>Longest Suffix:</strong> <code><?php echo htmlspecialchars($result['longestSuffix']); ?></code></p>
                        <?php } ?>
                        <?php if (!empty($result['prefixes'])) { ?>
                            <p><strong>Prefixes:</strong></p>
                            <pre><?php echo htmlspecialchars(implode(', ', $result['prefixes'])); ?></pre>
                        <?php } ?>
                        <?php if (!empty($result['suffixes'])) { ?>
                            <p><strong>Suffixes:</strong></p>
                            <pre><?php echo htmlspecialchars(implode(', ', $result['suffixes'])); ?></pre>
                        <?php } ?>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
        
        <div class="card">
            <h2>Example Patterns</h2>
            <p>Click on any example to try it:</p>
            <div class="examples">
                <div class="example" onclick="setPattern('/^[a-z0-9]+@[a-z]+\\.[a-z]{2,}$/i')">
                    <div class="example-title">Email Validation</div>
                    <div class="example-pattern">/^[a-z0-9]+@[a-z]+\.[a-z]{2,}$/i</div>
                </div>
                <div class="example" onclick="setPattern('/^(?&lt;year&gt;\\d{4})-(?&lt;month&gt;\\d{2})-(?&lt;day&gt;\\d{2})$/')">
                    <div class="example-title">Date (YYYY-MM-DD)</div>
                    <div class="example-pattern">/^(?&lt;year&gt;\d{4})-(?&lt;month&gt;\d{2})-(?&lt;day&gt;\d{2})$/</div>
                </div>
                <div class="example" onclick="setPattern('/(a+)*b/')">
                    <div class="example-title">ReDoS Vulnerable</div>
                    <div class="example-pattern">/(a+)*b/</div>
                </div>
                <div class="example" onclick="setPattern('/^https?:\\/\\/[\\w.-]+\\.[a-z]{2,}(:\\d+)?(\\/.*)?$/i')">
                    <div class="example-title">URL Pattern</div>
                    <div class="example-pattern">/^https?:\/\/[\w.-]+\.[a-z]{2,}(:\d+)?(\/.*)?$/i</div>
                </div>
                <div class="example" onclick="setPattern('/user_(\\d+)@example\\.com/')">
                    <div class="example-title">Literal Extraction</div>
                    <div class="example-pattern">/user_(\d+)@example\.com/</div>
                </div>
                <div class="example" onclick="setPattern('/(?&lt;=price: )\\$\\d+\\.\\d{2}/')">
                    <div class="example-title">Lookbehind</div>
                    <div class="example-pattern">/(?&lt;=price: )\$\d+\.\d{2}/</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function setPattern(pattern) {
            document.getElementById('regex').value = pattern;
            document.getElementById('regex').focus();
        }
    </script>
</body>
</html>
