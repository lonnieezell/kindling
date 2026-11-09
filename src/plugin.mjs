import fs from 'fs';
import path from 'path';

export default function kindling(options = {}) {
    let sentinelPath;

    function deleteSentinel() {
        try {
            fs.rmSync(sentinelPath, { force: true });
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
        },
        configureServer(server) {
            try {
                fs.mkdirSync(path.dirname(sentinelPath), { recursive: true });
                fs.writeFileSync(sentinelPath, '');
            } catch (e) {
                console.warn(`myth-kindling: could not write sentinel file: ${e.message}`);
            }

            const cleanup = () => deleteSentinel();
            process.on('exit', cleanup);
            process.on('SIGINT', cleanup);
            process.on('SIGTERM', cleanup);
            server.httpServer?.on('close', cleanup);
        },
        closeBundle() {
            deleteSentinel();
        },
    };
}
