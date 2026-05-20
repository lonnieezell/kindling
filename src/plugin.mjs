import fs from 'fs';
import path from 'path';

const sentinelPath = path.resolve(process.cwd(), 'public/build/.vite-dev-running');

function deleteSentinel() {
    try {
        fs.rmSync(sentinelPath, { force: true });
    } catch {
        // best-effort cleanup
    }
}

export default function kindling(options = { input: ['resources/js/app.js'] }) {
    return {
        name: 'myth-kindling',
        config(config, { command }) {
            return {
                base: command === 'serve' ? '/' : '/build/',
                build: {
                    outDir: 'public/build',
                    manifest: true,
                    rollupOptions: {
                        input: options.input,
                    },
                },
            };
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
