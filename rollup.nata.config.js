import terser from '@rollup/plugin-terser';
import resolve from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';

export default [
    {
        input: 'frontend/src/js/nata.js',
        output: {
            file: 'frontend/dist/nata.min.js',
            format: 'iife',
            name: 'nata',
            sourcemap: true,
            plugins: [terser()]
        }
    },
    {
        input: 'frontend/src/js/form/form.js',
        context: 'window',
        plugins: [resolve(), commonjs()],
        output: {
            file: 'frontend/dist/nata.form.min.js',
            format: 'iife',
            name: 'nataForm',
            sourcemap: true,
            plugins: [terser()]
        }
    },
    {
        // Standalone global bundle: exposes window.Upload
        input: 'frontend/src/js/upload/upload.global.js',
        context: 'window',
        plugins: [resolve(), commonjs()],
        output: {
            file: 'frontend/dist/nata.upload.global.min.js',
            format: 'iife',
            name: 'nataUpload',
            sourcemap: true,
            plugins: [terser()]
        }
    }
];
