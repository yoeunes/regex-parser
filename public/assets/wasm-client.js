/**
 * RegexParser Studio (WASM) client.
 *
 * Loads php-wasm, mounts the RegexParser sources into the in-memory filesystem,
 * then executes `public/worker.php` with JSON input provided via stdin.
 */

import { PhpWeb } from 'https://unpkg.com/php-wasm@0.0.8/PhpWeb.mjs';

export class RegexParserWasmClient {
    /**
     * @param {{ onStatus?: (status: string) => void }=} options
     */
    constructor(options = {}) {
        this._onStatus = options.onStatus ?? null;
        this._php = null;
        this._initialized = false;
        this._inflight = Promise.resolve();
    }

    /**
     * @returns {Promise<void>}
     */
    async init() {
        if (this._initialized) {
            return;
        }

        this._status('Booting PHP (WASM)…');
        this._php = new PhpWeb();

        // Warm up the engine.
        await this._php.run('<?php echo "ready";');

        this._status('Loading RegexParser bundle…');
        const bundleUrl = new URL('../library-bundle.json', import.meta.url);
        const bundleRes = await fetch(bundleUrl);
        if (!bundleRes.ok) {
            throw new Error(`Failed to fetch library bundle (${bundleRes.status})`);
        }

        /** @type {Record<string, string>} */
        const bundle = await bundleRes.json();

        this._status('Mounting sources into the virtual FS…');
        const mod = await this._php.binary;
        mod.FS.mkdirTree('/var/www/src');

        for (const [relativePath, content] of Object.entries(bundle)) {
            const targetPath =
                relativePath === 'autoload.php'
                    ? '/var/www/autoload.php'
                    : `/var/www/src/${relativePath}`;

            const dir = targetPath.slice(0, targetPath.lastIndexOf('/')) || '/';
            mod.FS.mkdirTree(dir);
            mod.FS.writeFile(targetPath, content);
        }

        this._status('Mounting worker…');
        const workerUrl = new URL('../worker.php', import.meta.url);
        const workerRes = await fetch(workerUrl);
        if (!workerRes.ok) {
            throw new Error(`Failed to fetch worker.php (${workerRes.status})`);
        }
        const workerCode = await workerRes.text();
        mod.FS.mkdirTree('/var/www');
        mod.FS.writeFile('/var/www/worker.php', workerCode);

        this._initialized = true;
        this._status('Ready');
    }

    /**
     * @param {Record<string, unknown>} payload
     * @returns {Promise<{ok: boolean, result: unknown, error: unknown, meta?: unknown}>}
     */
    async call(payload) {
        // Serialize calls to avoid interleaved stdout/stderr.
        this._inflight = this._inflight.then(async () => {
            await this.init();

            const php = this._php;
            if (!php) {
                throw new Error('PHP engine not initialized.');
            }

            let stdout = '';
            let stderr = '';

            /** @param {CustomEvent} event */
            const onOut = (event) => {
                stdout += (event.detail && event.detail[0]) ? event.detail[0] : '';
            };

            /** @param {CustomEvent} event */
            const onErr = (event) => {
                stderr += (event.detail && event.detail[0]) ? event.detail[0] : '';
            };

            php.addEventListener('output', onOut);
            php.addEventListener('error', onErr);

            try {
                php.inputString(JSON.stringify(payload));
                const exitCode = await php.run('<?php require "/var/www/worker.php";');
                const text = stdout.trim();
                if (!text) {
                    throw new Error(`Empty response (exitCode=${exitCode}). stderr: ${stderr.trim()}`);
                }

                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(`Invalid JSON response. stdout: ${text.slice(0, 2000)} stderr: ${stderr.trim()}`);
                }
            } finally {
                php.removeEventListener('output', onOut);
                php.removeEventListener('error', onErr);
            }
        });

        return this._inflight;
    }

    _status(status) {
        if (this._onStatus) {
            this._onStatus(status);
        }
    }
}
