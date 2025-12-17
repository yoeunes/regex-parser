import { RegexParserWasmClient } from './wasm-client.js';

const elements = {
    themeToggle: document.getElementById('themeToggle'),
    themeIcon: document.getElementById('themeIcon'),
    engineStatus: document.getElementById('engineStatus'),
    perfBadge: document.getElementById('perfBadge'),
    inputRegex: document.getElementById('inputRegex'),
    inputSubject: document.getElementById('inputSubject'),
    runButton: document.getElementById('runButton'),
    shareButton: document.getElementById('shareButton'),
    autoRunToggle: document.getElementById('autoRunToggle'),
    resultError: document.getElementById('resultError'),
    panes: {
        tabMatch: document.getElementById('tabMatch'),
        tabValidate: document.getElementById('tabValidate'),
        tabLint: document.getElementById('tabLint'),
        tabReDos: document.getElementById('tabReDos'),
        tabOptimize: document.getElementById('tabOptimize'),
        tabLiterals: document.getElementById('tabLiterals'),
        tabMermaid: document.getElementById('tabMermaid'),
        tabExplain: document.getElementById('tabExplain'),
        tabAst: document.getElementById('tabAst'),
        tabDump: document.getElementById('tabDump'),
    },
};

const state = {
    activeTab: 'tabMatch',
    lastRun: {
        regex: null,
        subject: null,
        analyze: null,
        explain: null,
        ast: null,
        dump: null,
    },
    debounceTimer: null,
    running: false,
};

const client = new RegexParserWasmClient({
    onStatus: (status) => setEngineStatus(status),
});

initTheme();
initTabs();
initPresets();
initActions();
restoreFromUrl();

// Warm up lazily (downloads in background).
if ('requestIdleCallback' in window) {
    window.requestIdleCallback(() => client.init().catch(() => {}));
} else {
    window.setTimeout(() => client.init().catch(() => {}), 250);
}

// Initial run if values exist.
if (elements.inputRegex.value.trim() !== '') {
    runAnalyze().catch(() => {});
}

function initTheme() {
    const renderIcon = () => {
        const isDark = document.documentElement.classList.contains('dark');
        elements.themeIcon.innerHTML = isDark
            ? '<path fill="currentColor" d="M12 18a6 6 0 1 1 0-12 6 6 0 0 1 0 12Zm0-14a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V5a1 1 0 0 1 1-1Zm0 15a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1Zm8-7a1 1 0 0 1-1 1h-1a1 1 0 1 1 0-2h1a1 1 0 0 1 1 1ZM7 12a1 1 0 0 1-1 1H5a1 1 0 1 1 0-2h1a1 1 0 0 1 1 1Zm10.95 6.36a1 1 0 0 1 0 1.41l-.7.7a1 1 0 1 1-1.41-1.41l.7-.7a1 1 0 0 1 1.41 0ZM8.16 7.57a1 1 0 0 1 0 1.41l-.7.7A1 1 0 1 1 6.05 8.3l.7-.7a1 1 0 0 1 1.41 0Zm11.1-1.52a1 1 0 0 1 0 1.41l-.7.7a1 1 0 1 1-1.41-1.41l.7-.7a1 1 0 0 1 1.41 0ZM8.16 16.43a1 1 0 0 1 0 1.41l-.7.7a1 1 0 1 1-1.41-1.41l.7-.7a1 1 0 0 1 1.41 0Z"/>'
            : '<path fill="currentColor" d="M21 14.5A7.5 7.5 0 0 1 9.5 3a.8.8 0 0 1 1 .93A6 6 0 0 0 18.07 11.5a.8.8 0 0 1 .93 1A7.47 7.47 0 0 1 21 14.5Z"/>';
    };

    renderIcon();

    elements.themeToggle.addEventListener('click', () => {
        document.documentElement.classList.toggle('dark');
        localStorage.setItem('rps-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
        renderIcon();
        if (state.lastRun.analyze?.visualize?.ok) {
            renderMermaidFromAnalyze(state.lastRun.analyze).catch(() => {});
        }
    });
}

function initTabs() {
    const buttons = Array.from(document.querySelectorAll('.tabBtn'));

    const setActive = (id) => {
        state.activeTab = id;
        for (const btn of buttons) {
            const active = btn.dataset.tab === id;
            btn.classList.toggle('tabBtnActive', active);
        }
        for (const [paneId, el] of Object.entries(elements.panes)) {
            if (!el) continue;
            el.classList.toggle('hidden', paneId !== id);
        }

        if (id === 'tabExplain') {
            runExplainIfNeeded().catch(() => {});
        } else if (id === 'tabAst') {
            runAstIfNeeded().catch(() => {});
        } else if (id === 'tabDump') {
            runDumpIfNeeded().catch(() => {});
        } else if (id === 'tabMermaid') {
            renderMermaidFromAnalyze(state.lastRun.analyze).catch(() => {});
        }
    };

    for (const btn of buttons) {
        btn.addEventListener('click', () => setActive(btn.dataset.tab));
    }

    setActive(state.activeTab);
}

function initPresets() {
    for (const button of document.querySelectorAll('.presetBtn')) {
        button.addEventListener('click', () => {
            const regex = button.dataset.regex ?? '';
            const subject = button.dataset.subject ?? '';
            elements.inputRegex.value = regex;
            elements.inputSubject.value = subject;
            scheduleRun();
        });
    }
}

function initActions() {
    elements.runButton.addEventListener('click', () => runAnalyze());
    elements.shareButton.addEventListener('click', () => shareState());
    elements.inputRegex.addEventListener('input', () => scheduleRun());
    elements.inputSubject.addEventListener('input', () => scheduleRun());
    elements.autoRunToggle.addEventListener('change', () => scheduleRun());
}

function scheduleRun() {
    if (!elements.autoRunToggle.checked) {
        return;
    }
    window.clearTimeout(state.debounceTimer);
    state.debounceTimer = window.setTimeout(() => runAnalyze().catch(() => {}), 350);
}

function restoreFromUrl() {
    const hash = window.location.hash || '';
    if (!hash.startsWith('#state=')) {
        if (elements.inputRegex.value.trim() === '') {
            elements.inputRegex.value = '/(?|(a)|(b))\\1/';
            elements.inputSubject.value = 'a';
        }
        return;
    }

    const encoded = hash.slice('#state='.length);
    const decoded = safeDecodeState(encoded);
    if (!decoded) {
        return;
    }

    if (typeof decoded.regex === 'string') {
        elements.inputRegex.value = decoded.regex;
    }
    if (typeof decoded.subject === 'string') {
        elements.inputSubject.value = decoded.subject;
    }
}

async function runAnalyze() {
    if (state.running) return;
    state.running = true;
    setError(null);

    const regex = elements.inputRegex.value.trim();
    const subject = elements.inputSubject.value;

    if (!regex) {
        state.running = false;
        return;
    }

    const startedAt = performance.now();
    setEngineStatus('Running…');
    setPerf(null);

    try {
        const response = await client.call({ action: 'analyze', regex, subject });

        if (!response || response.ok !== true) {
            const err = response && response.error ? response.error : { message: 'Unknown error.' };
            setError(formatError(err));
            clearAllPanes();
            state.lastRun = { regex, subject, analyze: null, explain: null, ast: null, dump: null };
            return;
        }

        const durationMs = response.meta && typeof response.meta.durationMs === 'number'
            ? response.meta.durationMs
            : Math.round(performance.now() - startedAt);

        setPerf(`PHP: ${durationMs}ms`);
        setEngineStatus('Ready');

        state.lastRun.regex = regex;
        state.lastRun.subject = subject;
        state.lastRun.analyze = response.result;
        state.lastRun.explain = null;
        state.lastRun.ast = null;
        state.lastRun.dump = null;

        renderAnalyze(response.result);
    } catch (e) {
        setError(e instanceof Error ? e.message : String(e));
        clearAllPanes();
    } finally {
        state.running = false;
    }
}

function renderAnalyze(analyze) {
    renderMatchPane(analyze);
    renderValidatePane(analyze);
    renderLintPane(analyze);
    renderRedosPane(analyze);
    renderOptimizePane(analyze);
    renderLiteralsPane(analyze);
    renderMermaidPane(analyze);
}

function renderMatchPane(analyze) {
    const pane = elements.panes.tabMatch;
    if (!pane) return;

    if (!analyze.match || !analyze.match.ok) {
        pane.innerHTML = renderInlineError(analyze.match?.error ?? { message: 'Match failed.' });
        return;
    }

    const result = analyze.match.result;
    const matchCount = result.matchCount ?? 0;
    const matches = Array.isArray(result.matches) ? result.matches : [];

    if (matchCount === 0) {
        pane.innerHTML = '<div class="text-sm text-slate-600 dark:text-slate-300">No matches.</div>';
        return;
    }

    const rows = [];
    for (const match of matches.slice(0, 30)) {
        const groups = Array.isArray(match.groups) ? match.groups : [];
        for (const group of groups) {
            rows.push({
                match: match.index,
                group: group.key,
                text: typeof group.text === 'string' ? group.text : '',
                offset: group.offset,
            });
        }
    }

    pane.innerHTML = `
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm font-semibold">Matches: <span class="font-mono">${escapeHtml(String(matchCount))}</span></div>
            <div class="text-xs text-slate-500 dark:text-slate-400">Showing up to 30 matches</div>
        </div>
        <div class="mt-3 overflow-auto rounded-xl border border-slate-200 dark:border-white/10">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-600 dark:bg-white/5 dark:text-slate-300">
                    <tr>
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">Group</th>
                        <th class="px-3 py-2 text-left">Text</th>
                        <th class="px-3 py-2 text-left">Offset</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                    ${rows.map((r) => `
                        <tr class="bg-white dark:bg-transparent">
                            <td class="px-3 py-2 font-mono text-xs text-slate-500 dark:text-slate-400">${escapeHtml(String(r.match))}</td>
                            <td class="px-3 py-2 font-mono text-xs">${escapeHtml(String(r.group))}</td>
                            <td class="px-3 py-2 font-mono text-xs break-all">${escapeHtml(r.text)}</td>
                            <td class="px-3 py-2 font-mono text-xs text-slate-500 dark:text-slate-400">${escapeHtml(String(r.offset))}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function renderValidatePane(analyze) {
    const pane = elements.panes.tabValidate;
    if (!pane) return;

    if (!analyze.validate || !analyze.validate.ok) {
        pane.innerHTML = renderInlineError(analyze.validate?.error ?? { message: 'Validation failed.' });
        return;
    }

    const v = analyze.validate.result;
    const isValid = Boolean(v.isValid);

    const badgeClass = isValid
        ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100'
        : 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100';

    pane.innerHTML = `
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold ${badgeClass}">
                <span class="inline-flex h-2 w-2 rounded-full ${isValid ? 'bg-emerald-500' : 'bg-rose-500'}"></span>
                ${isValid ? 'Valid' : 'Invalid'}
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">Complexity score: <span class="font-mono">${escapeHtml(String(v.complexityScore ?? 0))}</span></div>
        </div>

        ${!isValid ? `
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-800 dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                <div class="font-semibold">Error</div>
                <div class="mt-2 font-mono text-xs break-words">${escapeHtml(String(v.error ?? ''))}</div>
                ${v.hint ? `<div class="mt-3 text-xs text-slate-600 dark:text-slate-300"><span class="font-semibold">Hint:</span> ${escapeHtml(String(v.hint))}</div>` : ''}
                ${v.caret ? `<pre class="mt-3 overflow-auto rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-800 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">${escapeHtml(String(v.caret))}</pre>` : ''}
                <div class="mt-3 grid grid-cols-2 gap-3 text-xs text-slate-600 dark:text-slate-300">
                    <div><span class="font-semibold">Category:</span> ${escapeHtml(String(v.category ?? 'unknown'))}</div>
                    <div><span class="font-semibold">Code:</span> ${escapeHtml(String(v.code ?? ''))}</div>
                </div>
            </div>
        ` : `
            <div class="mt-4 text-sm text-slate-600 dark:text-slate-300">No validation errors detected.</div>
        `}
    `;
}

function renderLintPane(analyze) {
    const pane = elements.panes.tabLint;
    if (!pane) return;

    if (!analyze.lint || !analyze.lint.ok) {
        pane.innerHTML = renderInlineError(analyze.lint?.error ?? { message: 'Lint failed.' });
        return;
    }

    const issues = analyze.lint.result.issues ?? [];
    if (!Array.isArray(issues) || issues.length === 0) {
        pane.innerHTML = '<div class="text-sm text-slate-600 dark:text-slate-300">No linter issues.</div>';
        return;
    }

    pane.innerHTML = `
        <div class="space-y-3">
            ${issues.map((issue) => `
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-transparent">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-sm font-semibold">${escapeHtml(String(issue.message ?? ''))}</div>
                        <div class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 font-mono text-[11px] text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300">${escapeHtml(String(issue.id ?? ''))}</div>
                    </div>
                    ${issue.hint ? `<div class="mt-2 text-xs text-slate-600 dark:text-slate-300">${escapeHtml(String(issue.hint))}</div>` : ''}
                </div>
            `).join('')}
        </div>
    `;
}

function renderRedosPane(analyze) {
    const pane = elements.panes.tabReDos;
    if (!pane) return;

    if (!analyze.redos || !analyze.redos.ok) {
        pane.innerHTML = renderInlineError(analyze.redos?.error ?? { message: 'ReDoS analysis failed.' });
        return;
    }

    const r = analyze.redos.result;
    const severity = String(r.severity ?? 'unknown');
    const severityColor = {
        safe: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100',
        low: 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100',
        medium: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100',
        high: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100',
        critical: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100',
        unknown: 'border-slate-200 bg-slate-50 text-slate-900 dark:border-white/10 dark:bg-white/5 dark:text-slate-100',
    }[severity] ?? 'border-slate-200 bg-slate-50 text-slate-900 dark:border-white/10 dark:bg-white/5 dark:text-slate-100';

    const recommendations = Array.isArray(r.recommendations) ? r.recommendations : [];
    const findings = Array.isArray(r.findings) ? r.findings : [];

    pane.innerHTML = `
        <div class="grid gap-3 md:grid-cols-3">
            <div class="rounded-xl border px-4 py-3 ${severityColor}">
                <div class="text-xs font-semibold opacity-80">Severity</div>
                <div class="mt-1 text-lg font-bold tracking-tight">${escapeHtml(severity.toUpperCase())}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Score</div>
                <div class="mt-1 text-lg font-bold tracking-tight">${escapeHtml(String(r.score ?? 0))}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Confidence</div>
                <div class="mt-1 text-lg font-bold tracking-tight">${escapeHtml(String(r.confidence ?? 'n/a'))}</div>
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-sm font-semibold">Trigger</div>
                <div class="mt-2 font-mono text-xs text-slate-700 dark:text-slate-200">${escapeHtml(String(r.trigger ?? ''))}</div>
                ${r.vulnerableSubpattern ? `<div class="mt-3 text-xs text-slate-600 dark:text-slate-300"><span class="font-semibold">Vulnerable:</span> <span class="font-mono">${escapeHtml(String(r.vulnerableSubpattern))}</span></div>` : ''}
                ${r.falsePositiveRisk ? `<div class="mt-2 text-xs text-slate-600 dark:text-slate-300"><span class="font-semibold">False-positive risk:</span> ${escapeHtml(String(r.falsePositiveRisk))}</div>` : ''}
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-sm font-semibold">Recommendations</div>
                ${recommendations.length === 0
                    ? '<div class="mt-2 text-sm text-slate-600 dark:text-slate-300">None.</div>'
                    : `<ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-300">${recommendations.map((x) => `<li>${escapeHtml(String(x))}</li>`).join('')}</ul>`
                }
            </div>
        </div>

        ${findings.length > 0 ? `
            <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-sm font-semibold">Findings</div>
                <div class="mt-3 space-y-3">
                    ${findings.slice(0, 8).map((f) => `
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="text-xs font-semibold text-slate-600 dark:text-slate-300">${escapeHtml(String(f.severity ?? ''))}</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">confidence: ${escapeHtml(String(f.confidence ?? ''))}</div>
                            </div>
                            <div class="mt-1 text-sm font-semibold">${escapeHtml(String(f.message ?? ''))}</div>
                            <div class="mt-2 font-mono text-xs text-slate-700 dark:text-slate-200">${escapeHtml(String(f.pattern ?? ''))}</div>
                            ${f.suggestedRewrite ? `<div class="mt-2 text-xs text-slate-600 dark:text-slate-300"><span class="font-semibold">Suggested:</span> <span class="font-mono">${escapeHtml(String(f.suggestedRewrite))}</span></div>` : ''}
                        </div>
                    `).join('')}
                </div>
            </div>
        ` : ''}
    `;
}

function renderOptimizePane(analyze) {
    const pane = elements.panes.tabOptimize;
    if (!pane) return;

    if (!analyze.optimize || !analyze.optimize.ok) {
        pane.innerHTML = renderInlineError(analyze.optimize?.error ?? { message: 'Optimization failed.' });
        return;
    }

    const o = analyze.optimize.result;
    const optimized = String(o.optimized ?? '');
    const changed = Boolean(o.isChanged);
    const changes = Array.isArray(o.changes) ? o.changes : [];

    pane.innerHTML = `
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm font-semibold">${changed ? 'Optimized pattern' : 'No optimization applied'}</div>
            <button class="copyBtn rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10" type="button" data-copy="${escapeHtmlAttr(optimized)}">Copy</button>
        </div>
        <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 font-mono text-xs text-slate-900 dark:border-white/10 dark:bg-slate-950 dark:text-white break-all">${escapeHtml(optimized)}</div>
        ${changes.length > 0 ? `<ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-300">${changes.map((c) => `<li>${escapeHtml(String(c))}</li>`).join('')}</ul>` : ''}
    `;

    wireCopyButtons(pane);
}

function renderLiteralsPane(analyze) {
    const pane = elements.panes.tabLiterals;
    if (!pane) return;

    if (!analyze.literals || !analyze.literals.ok) {
        pane.innerHTML = renderInlineError(analyze.literals?.error ?? { message: 'Literal extraction failed.' });
        return;
    }

    const l = analyze.literals.result;
    const literals = Array.isArray(l.literals) ? l.literals : [];
    const patterns = Array.isArray(l.patterns) ? l.patterns : [];

    pane.innerHTML = `
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm font-semibold">Confidence: <span class="font-mono">${escapeHtml(String(l.confidence ?? 'n/a'))}</span></div>
        </div>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-sm font-semibold">Literals</div>
                ${literals.length === 0
                    ? '<div class="mt-2 text-sm text-slate-600 dark:text-slate-300">None.</div>'
                    : `<ul class="mt-2 space-y-1 font-mono text-xs text-slate-800 dark:text-slate-100">${literals.slice(0, 12).map((x) => `<li class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 dark:border-white/10 dark:bg-slate-950 break-all">${escapeHtml(String(x))}</li>`).join('')}</ul>`
                }
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-sm font-semibold">Prefilter patterns</div>
                ${patterns.length === 0
                    ? '<div class="mt-2 text-sm text-slate-600 dark:text-slate-300">None.</div>'
                    : `<ul class="mt-2 space-y-1 font-mono text-xs text-slate-800 dark:text-slate-100">${patterns.slice(0, 12).map((x) => `<li class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 dark:border-white/10 dark:bg-slate-950 break-all">${escapeHtml(String(x))}</li>`).join('')}</ul>`
                }
            </div>
        </div>
    `;
}

function renderMermaidPane(analyze) {
    const pane = elements.panes.tabMermaid;
    if (!pane) return;

    if (!analyze.visualize || !analyze.visualize.ok) {
        pane.innerHTML = renderInlineError(analyze.visualize?.error ?? { message: 'Mermaid generation failed.' });
        return;
    }

    const mermaid = String(analyze.visualize.result.mermaid ?? '');

    pane.innerHTML = `
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm font-semibold">Mermaid graph</div>
            <button class="copyBtn rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10" type="button" data-copy="${escapeHtmlAttr(mermaid)}">Copy</button>
        </div>
        <pre class="mt-3 overflow-auto rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-800 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">${escapeHtml(mermaid)}</pre>
        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Rendered</div>
            <div id="mermaidRender" class="mt-3 overflow-auto"></div>
        </div>
    `;

    wireCopyButtons(pane);
    renderMermaidFromAnalyze(analyze).catch(() => {});
}

async function renderMermaidFromAnalyze(analyze) {
    const pane = elements.panes.tabMermaid;
    if (!pane || state.activeTab !== 'tabMermaid') return;
    if (!analyze?.visualize?.ok) return;

    const mermaidCode = String(analyze.visualize.result.mermaid ?? '');
    const target = pane.querySelector('#mermaidRender');
    if (!target || !mermaidCode) return;

    const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'default';
    const { default: mermaid } = await import('https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs');
    mermaid.initialize({ startOnLoad: false, theme });

    const id = `m_${Math.random().toString(16).slice(2)}`;
    const { svg } = await mermaid.render(id, mermaidCode);
    target.innerHTML = svg;
}

async function runExplainIfNeeded() {
    const regex = elements.inputRegex.value.trim();
    if (!regex || state.lastRun.regex !== regex) return;
    if (state.lastRun.explain) {
        renderExplainPane(state.lastRun.explain);
        return;
    }

    try {
        const response = await client.call({ action: 'explain', regex });
        if (!response.ok) {
            elements.panes.tabExplain.innerHTML = renderInlineError(response.error ?? { message: 'Explain failed.' });
            return;
        }
        state.lastRun.explain = response.result;
        renderExplainPane(response.result);
    } catch (e) {
        elements.panes.tabExplain.innerHTML = renderInlineError({ message: e instanceof Error ? e.message : String(e) });
    }
}

function renderExplainPane(result) {
    const pane = elements.panes.tabExplain;
    if (!pane) return;
    pane.innerHTML = `
        <div class="text-sm font-semibold">Explanation</div>
        <pre class="mt-3 overflow-auto rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-800 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">${escapeHtml(String(result.explanation ?? ''))}</pre>
    `;
}

async function runAstIfNeeded() {
    const regex = elements.inputRegex.value.trim();
    if (!regex || state.lastRun.regex !== regex) return;
    if (state.lastRun.ast) {
        renderAstPane(state.lastRun.ast);
        return;
    }

    try {
        const response = await client.call({ action: 'parse', regex });
        if (!response.ok) {
            elements.panes.tabAst.innerHTML = renderInlineError(response.error ?? { message: 'AST parse failed.' });
            return;
        }
        state.lastRun.ast = response.result;
        renderAstPane(response.result);
    } catch (e) {
        elements.panes.tabAst.innerHTML = renderInlineError({ message: e instanceof Error ? e.message : String(e) });
    }
}

function renderAstPane(result) {
    const pane = elements.panes.tabAst;
    if (!pane) return;

    const tree = result.tree ?? null;
    if (!tree || typeof tree !== 'object') {
        pane.innerHTML = '<div class="text-sm text-slate-600 dark:text-slate-300">No AST available.</div>';
        return;
    }

    pane.innerHTML = `
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm font-semibold">AST</div>
        </div>
        <div class="mt-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
            ${renderTree(tree)}
        </div>
    `;
}

function renderTree(node) {
    const label = escapeHtml(String(node.label ?? 'Node'));
    const detail = node.detail ? escapeHtml(String(node.detail)) : '';
    const children = Array.isArray(node.children) ? node.children : [];

    const header = `
        <div class="flex flex-wrap items-center gap-2">
            <div class="font-semibold">${label}</div>
            ${detail ? `<div class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 font-mono text-[11px] text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300">${detail}</div>` : ''}
        </div>
    `;

    if (children.length === 0) {
        return `<div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-slate-950">${header}</div>`;
    }

    return `
        <details open class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-slate-950">
            <summary class="cursor-pointer select-none">${header}</summary>
            <div class="mt-3 space-y-2 border-l border-slate-200 pl-3 dark:border-white/10">
                ${children.filter((x) => x && typeof x === 'object').slice(0, 80).map((child) => renderTree(child)).join('')}
            </div>
        </details>
    `;
}

async function runDumpIfNeeded() {
    const regex = elements.inputRegex.value.trim();
    if (!regex || state.lastRun.regex !== regex) return;
    if (state.lastRun.dump) {
        renderDumpPane(state.lastRun.dump);
        return;
    }

    try {
        const response = await client.call({ action: 'dump', regex });
        if (!response.ok) {
            elements.panes.tabDump.innerHTML = renderInlineError(response.error ?? { message: 'Dump failed.' });
            return;
        }
        state.lastRun.dump = response.result;
        renderDumpPane(response.result);
    } catch (e) {
        elements.panes.tabDump.innerHTML = renderInlineError({ message: e instanceof Error ? e.message : String(e) });
    }
}

function renderDumpPane(result) {
    const pane = elements.panes.tabDump;
    if (!pane) return;
    pane.innerHTML = `
        <div class="text-sm font-semibold">Dump</div>
        <pre class="mt-3 overflow-auto rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-800 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100">${escapeHtml(String(result.dump ?? ''))}</pre>
    `;
}

function clearAllPanes() {
    for (const pane of Object.values(elements.panes)) {
        if (pane) pane.innerHTML = '';
    }
}

function setEngineStatus(text) {
    if (!elements.engineStatus) return;
    const dot = elements.engineStatus.querySelector('span');
    const label = elements.engineStatus.childNodes[elements.engineStatus.childNodes.length - 1];

    if (label && label.nodeType === Node.TEXT_NODE) {
        label.textContent = ` ${text}`;
    } else {
        elements.engineStatus.innerHTML = `<span class="inline-flex h-2 w-2 rounded-full bg-slate-300 dark:bg-white/20"></span> ${escapeHtml(text)}`;
    }

    if (dot) {
        dot.className = 'inline-flex h-2 w-2 rounded-full ' + (text === 'Ready' ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-white/20');
    }
}

function setPerf(text) {
    if (!elements.perfBadge) return;
    if (!text) {
        elements.perfBadge.classList.add('hidden');
        elements.perfBadge.textContent = '';
        return;
    }
    elements.perfBadge.textContent = text;
    elements.perfBadge.classList.remove('hidden');
}

function setError(message) {
    if (!elements.resultError) return;
    if (!message) {
        elements.resultError.classList.add('hidden');
        elements.resultError.textContent = '';
        return;
    }
    elements.resultError.textContent = message;
    elements.resultError.classList.remove('hidden');
}

function renderInlineError(error) {
    return `<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100">${escapeHtml(formatError(error))}</div>`;
}

function formatError(error) {
    if (!error) return 'Unknown error.';
    if (typeof error === 'string') return error;
    if (typeof error.message === 'string') {
        const extras = [];
        if (error.hint) extras.push(`Hint: ${String(error.hint)}`);
        if (error.caret) extras.push(String(error.caret));
        return [String(error.message), ...extras].join('\n');
    }
    return JSON.stringify(error);
}

function shareState() {
    const regex = elements.inputRegex.value.trim();
    const subject = elements.inputSubject.value;
    if (!regex) return;

    const payload = JSON.stringify({ regex, subject });
    const encoded = base64UrlEncode(payload);
    const url = new URL(window.location.href);
    url.hash = `state=${encoded}`;

    navigator.clipboard?.writeText(url.toString()).catch(() => {});
    window.history.replaceState(null, '', url.toString());

    setPerf('Link copied');
    window.setTimeout(() => setPerf(null), 1200);
}

function safeDecodeState(encoded) {
    try {
        const json = base64UrlDecode(encoded);
        return JSON.parse(json);
    } catch {
        return null;
    }
}

function base64UrlEncode(text) {
    const bytes = new TextEncoder().encode(text);
    let binary = '';
    for (const b of bytes) binary += String.fromCharCode(b);
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

function base64UrlDecode(b64url) {
    const padded = b64url.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((b64url.length + 3) % 4);
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return new TextDecoder().decode(bytes);
}

function escapeHtml(input) {
    return String(input)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeHtmlAttr(input) {
    return escapeHtml(input).replaceAll('\n', '\\n');
}

function wireCopyButtons(root) {
    for (const btn of root.querySelectorAll('.copyBtn')) {
        btn.addEventListener('click', async () => {
            const text = btn.dataset.copy ?? '';
            try {
                await navigator.clipboard.writeText(text);
                btn.textContent = 'Copied';
                window.setTimeout(() => (btn.textContent = 'Copy'), 900);
            } catch {
            }
        });
    }
}

