import { describe, it, expect } from 'vitest';
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
