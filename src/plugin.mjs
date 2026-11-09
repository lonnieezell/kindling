import { randomUUID } from 'crypto';
import fs from 'fs';
import path from 'path';

export default function kindling(options = {}) {
    let sentinelPath;
    let isBuild;

    // When given an owner, only deletes a sentinel that owner wrote.
    function deleteSentinel(owner) {
        try {
            if (owner === undefined || fs.readFileSync(sentinelPath, 'utf8') === owner) {
                fs.rmSync(sentinelPath, { force: true });
            }
        } catch {
            // best-effort cleanup
        }
    }

    return {
        name: 'myth-kindling',
        config(config, { command }) {
            const userRollupOptions = config.build?.rollupOptions;
            const input = options.input ?? userRollupOptions?.input ?? ['resources/js/app.js'];

            // Vite concatenates arrays when merging a hook's result, so a user-set input is replaced in place.
            if (userRollupOptions?.input) {
                userRollupOptions.input = input;
            }

            return {
                base: command === 'serve' ? '/' : (config.base ?? '/build/'),
                build: {
                    outDir: config.build?.outDir ?? 'public/build',
                    manifest: config.build?.manifest ?? true,
                    rollupOptions: userRollupOptions?.input ? {} : { input },
                },
            };
        },
        configResolved(config) {
            sentinelPath = path.resolve(config.root, config.build.outDir, '.vite-dev-running');
            isBuild = config.command === 'build';
        },
        configureServer(server) {
            // On restart Vite starts the new server before closing the old one, so each server only removes its own sentinel.
            const owner = randomUUID();

            try {
                fs.mkdirSync(path.dirname(sentinelPath), { recursive: true });
                fs.writeFileSync(sentinelPath, owner);
            } catch (e) {
                console.warn(`myth-kindling: could not write sentinel file: ${e.message}`);
            }

            const cleanup = () => {
                process.off('exit', cleanup);
                process.off('SIGINT', stopOnSigint);
                process.off('SIGTERM', cleanup);
                deleteSentinel(owner);
            };

            // A SIGINT listener disables Node's default exit, and Vite has no handler of its own, so re-raise it.
            const stopOnSigint = () => {
                cleanup();
                process.kill(process.pid, 'SIGINT');
            };

            process.on('exit', cleanup);
            process.on('SIGINT', stopOnSigint);
            process.on('SIGTERM', cleanup);
            server.httpServer?.on('close', cleanup);
        },
        closeBundle() {
            if (isBuild) {
                deleteSentinel();
            }
        },
    };
}
