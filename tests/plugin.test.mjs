import fs from 'fs';
import os from 'os';
import path from 'path';
import { afterEach, beforeEach, describe, it, expect } from 'vitest';
import { build, createServer, resolveConfig } from 'vite';
import kindling from '../src/plugin.mjs';

describe('kindling plugin', () => {
    it('has the correct plugin name', () => {
        const plugin = kindling();
        expect(plugin.name).toBe('myth-kindling');
    });

    it('sets base to / in dev mode', () => {
        const plugin = kindling();
        const config = plugin.config({}, { command: 'serve' });
        expect(config.base).toBe('/');
    });

    it('sets base to /build/ in production', () => {
        const plugin = kindling();
        const config = plugin.config({}, { command: 'build' });
        expect(config.base).toBe('/build/');
    });

    it('sets build outDir, manifest, and rollupOptions.input', () => {
        const input = ['resources/js/app.js', 'resources/js/admin.js'];
        const plugin = kindling({ input });
        const config = plugin.config({}, { command: 'build' });
        expect(config.build.outDir).toBe('public/build');
        expect(config.build.manifest).toBe(true);
        expect(config.build.rollupOptions.input).toEqual(input);
    });

    it('defaults input to resources/js/app.js when no options given', () => {
        const plugin = kindling();
        const config = plugin.config({}, { command: 'build' });
        expect(config.build.rollupOptions.input).toEqual(['resources/js/app.js']);
    });
});

describe('kindling plugin inside Vite', () => {
    let root;

    beforeEach(() => {
        root = fs.mkdtempSync(path.join(os.tmpdir(), 'kindling-plugin-'));
    });

    afterEach(() => {
        fs.rmSync(root, { recursive: true, force: true });
    });

    const inlineConfig = (userConfig = {}, pluginOptions) => ({
        configFile: false,
        root,
        logLevel: 'silent',
        plugins: [kindling(pluginOptions)],
        ...userConfig,
    });

    const resolved = (command, userConfig, pluginOptions) =>
        resolveConfig(inlineConfig(userConfig, pluginOptions), command);

    it('keeps a user-configured build.outDir', async () => {
        const config = await resolved('build', { build: { outDir: 'dist' } });
        expect(config.build.outDir).toBe('dist');
    });

    it('keeps a user-configured base when building', async () => {
        const config = await resolved('build', { base: '/assets/' });
        expect(config.base).toBe('/assets/');
    });

    it('forces base to / when serving, even with a user-configured base', async () => {
        const config = await resolved('serve', { base: '/assets/' });
        expect(config.base).toBe('/');
    });

    it('keeps a user-configured build.manifest', async () => {
        const config = await resolved('build', { build: { manifest: 'assets.json' } });
        expect(config.build.manifest).toBe('assets.json');
    });

    it('uses input passed to kindling() instead of a user rollupOptions.input', async () => {
        const config = await resolved(
            'build',
            { build: { rollupOptions: { input: ['resources/js/user.js'] } } },
            { input: ['resources/js/plugin.js'] },
        );
        expect(config.build.rollupOptions.input).toEqual(['resources/js/plugin.js']);
    });

    it('keeps a user rollupOptions.input when kindling() has no input', async () => {
        const config = await resolved('build', { build: { rollupOptions: { input: ['resources/js/user.js'] } } });
        expect(config.build.rollupOptions.input).toEqual(['resources/js/user.js']);
    });

    it('keeps a user rollupOptions.input when the same config object is resolved again', async () => {
        const config = inlineConfig({ build: { rollupOptions: { input: ['resources/js/user.js'] } } });

        await resolveConfig(config, 'build');
        const second = await resolveConfig(config, 'build');

        expect(second.build.rollupOptions.input).toEqual(['resources/js/user.js']);
    });

    it('leaves other user rollupOptions keys untouched', async () => {
        const config = await resolved('build', { build: { rollupOptions: { external: ['jquery'] } } });
        expect(config.build.rollupOptions.external).toEqual(['jquery']);
    });

    it('applies the documented defaults with no user config', async () => {
        const config = await resolved('build');
        expect(config.base).toBe('/build/');
        expect(config.build.outDir).toBe('public/build');
        expect(config.build.manifest).toBe(true);
        expect(config.build.rollupOptions.input).toEqual(['resources/js/app.js']);
    });

    const sentinelIn = (...dirs) => path.join(root, ...dirs, '.vite-dev-running');

    const startServer = async (userConfig) => {
        const server = await createServer(inlineConfig({ ...userConfig, server: { port: 0 } }));
        await server.listen();
        return server;
    };

    it('creates the sentinel in public/build by default and removes it on shutdown', async () => {
        const server = await startServer();
        expect(fs.existsSync(sentinelIn('public', 'build'))).toBe(true);

        await server.close();
        expect(fs.existsSync(sentinelIn('public', 'build'))).toBe(false);
    });

    it('creates the sentinel in a custom outDir relative to root and removes it on shutdown', async () => {
        const server = await startServer({ build: { outDir: 'dist' } });
        expect(fs.existsSync(sentinelIn('dist'))).toBe(true);

        await server.close();
        expect(fs.existsSync(sentinelIn('dist'))).toBe(false);
    });

    it('removes the sentinel from a custom outDir when a build finishes', async () => {
        const entry = path.join(root, 'entry.js');
        fs.writeFileSync(entry, 'export default 1;');
        fs.mkdirSync(path.join(root, 'dist'));
        fs.writeFileSync(sentinelIn('dist'), '');

        await build(inlineConfig({ build: { outDir: 'dist', emptyOutDir: false } }, { input: [entry] }));

        expect(fs.existsSync(sentinelIn('dist'))).toBe(false);
    });

    it('serves the dev client and entries at root URLs when the user configured a base', async () => {
        fs.mkdirSync(path.join(root, 'resources/js'), { recursive: true });
        fs.writeFileSync(path.join(root, 'resources/js/app.js'), 'export default 1;');
        const server = await startServer({ base: '/assets/' });
        const url = new URL(server.resolvedUrls.local[0]).origin;

        try {
            expect((await fetch(`${url}/@vite/client`)).status).toBe(200);
            expect((await fetch(`${url}/resources/js/app.js`)).status).toBe(200);
        } finally {
            await server.close();
        }
    });
});
